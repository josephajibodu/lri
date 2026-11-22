/*
 * The check-in scanner (resources/views/check-in/show.blade.php).
 *
 * It reads QR codes with the phone's camera. Android phones have a QR reader built into the browser; iPhones don't, so
 * there the barcode-detector package reads them, with its reading engine (a .wasm file) served from this site.
 *
 * A QR code that isn't one of our tickets is turned away on the spot, without asking the server. Anything that looks
 * like a ticket is checked with the server, which also records the person as let in. The verdict fills the screen
 * until staff tap "Scan next", so a ticket held up for a while isn't read twice.
 */
import wasmUrl from 'zxing-wasm/reader/zxing_reader.wasm?url';

/** Ticket codes are 8 characters from an alphabet without 0, O, 1 and I (app/Support/ReadableCode.php). */
const ticketCode = /^[2-9A-HJ-NP-Z]{8}$/;

/** After "Scan next", the ticket just checked is ignored for a moment, in case it's still in front of the camera. */
const sameTicketPause = 3000;

/** How long to wait for the server before saying there's no connection. */
const serverWait = 10000;

/**
 * The ticket code in what was scanned or typed: the code itself, or the headset desk link a ticket's QR code holds.
 * Null when it isn't one of our tickets.
 */
function ticketCodeIn(text) {
    let typed = text.trim();

    if (/^https?:\/\//i.test(typed)) {
        try {
            const link = new URL(typed);
            typed = link.pathname.replace(/\/+$/, '').endsWith('/admin/headset-desk') ? (link.searchParams.get('search') ?? '') : '';
        } catch {
            typed = '';
        }
    }

    const code = typed.toUpperCase().replace(/[^A-Z0-9]/g, '');

    return ticketCode.test(code) ? code : null;
}

/** A QR reader: the browser's own where it has one that reads QR codes, otherwise barcode-detector's. */
async function makeDetector() {
    if ('BarcodeDetector' in window) {
        try {
            if ((await window.BarcodeDetector.getSupportedFormats()).includes('qr_code')) {
                return new window.BarcodeDetector({ formats: ['qr_code'] });
            }
        } catch {
            // Fall through to the package.
        }
    }

    const { BarcodeDetector, prepareZXingModule } = await import('barcode-detector/ponyfill');

    await prepareZXingModule({
        overrides: { locateFile: (path, prefix) => (path.endsWith('.wasm') ? wasmUrl : prefix + path) },
        fireImmediately: true,
    });

    return new BarcodeDetector({ formats: ['qr_code'] });
}

function startScanner(page) {
    const video = page.querySelector('[data-video]');
    const startPanel = page.querySelector('[data-start]');
    const startButton = page.querySelector('[data-start-button]');
    const cameraNote = page.querySelector('[data-camera-note]');
    const typeForm = page.querySelector('[data-type-form]');
    const typeInput = typeForm.querySelector('input');
    const installButton = page.querySelector('[data-install]');
    const iosTip = page.querySelector('[data-ios-tip]');
    const letInCount = page.querySelector('[data-let-in]');
    const ticketsCount = page.querySelector('[data-tickets]');

    const result = document.querySelector('[data-result]');
    const resultHeading = result.querySelector('[data-result-heading]');
    const resultName = result.querySelector('[data-result-name]');
    const resultCode = result.querySelector('[data-result-code]');
    const resultDetail = result.querySelector('[data-result-detail]');
    const resultButton = result.querySelector('[data-result-button]');

    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    let detectorReady = null;
    let detector = null;
    let stream = null;
    let wantsCamera = false;
    let showingResult = false;
    let scanning = false;
    let afterResult = null;
    let paused = { code: null, until: 0 };
    let sound = null;

    /* Camera */

    async function openCamera() {
        if (!navigator.mediaDevices?.getUserMedia) {
            cameraNote.textContent = 'This browser can’t use the camera here. Type the code below instead.';

            return;
        }

        wantsCamera = true;
        startButton.disabled = true;
        cameraNote.textContent = 'Opening the camera…';

        try {
            const asked = navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false,
            });

            try {
                detector = await loadDetector();
            } catch {
                asked.then((camera) => camera.getTracks().forEach((track) => track.stop()), () => {});
                throw Object.assign(new Error('The QR reader didn’t load'), { name: 'ReaderError' });
            }

            const camera = await asked;

            stream = camera;
            video.srcObject = camera;
            await video.play();
            camera.getVideoTracks()[0]?.addEventListener('ended', () => closeCamera());

            startPanel.hidden = true;
            keepScreenOn();
            scan();
        } catch (error) {
            closeCamera();
            wantsCamera = false;
            cameraNote.textContent = cameraProblem(error);
        } finally {
            startButton.disabled = false;
        }
    }

    /** The QR reader, loaded once. If it fails, say with no connection, the next try loads it again. */
    function loadDetector() {
        detectorReady ??= makeDetector().catch((error) => {
            detectorReady = null;
            throw error;
        });

        return detectorReady;
    }

    function closeCamera() {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
        startPanel.hidden = false;
        cameraNote.textContent = 'Point the camera at the QR code on their ticket.';
    }

    function cameraProblem(error) {
        switch (error?.name) {
            case 'ReaderError':
                return 'The QR reader didn’t load. Check your connection and tap Start scanning again, or type the code below.';
            case 'NotAllowedError':
                return 'The camera is blocked. Allow it for this site in your phone’s settings, then tap Start scanning.';
            case 'NotFoundError':
            case 'OverconstrainedError':
                return 'No camera found. Type the code below instead.';
            case 'NotReadableError':
                return 'Another app is using the camera. Close it, then tap Start scanning.';
            default:
                return 'The camera couldn’t start. Try again, or type the code below.';
        }
    }

    async function keepScreenOn() {
        try {
            await navigator.wakeLock?.request('screen');
        } catch {
            // The screen may dim on its own; scanning still works.
        }
    }

    /** Read the camera frame by frame until something is found or a verdict is on screen. */
    function scan() {
        if (scanning) {
            return;
        }

        scanning = true;
        requestAnimationFrame(readFrame);
    }

    async function readFrame() {
        if (!stream || showingResult) {
            scanning = false;

            return;
        }

        if (video.readyState >= video.HAVE_ENOUGH_DATA) {
            try {
                const [found] = await detector.detect(video);

                if (found?.rawValue && stream && !showingResult) {
                    scanned(found.rawValue);
                }
            } catch {
                // A frame that couldn't be read. The next one will do.
            }
        }

        requestAnimationFrame(readFrame);
    }

    /* Checking a ticket */

    function scanned(text) {
        const code = ticketCodeIn(text);

        if ((code ?? text) === paused.code && Date.now() < paused.until) {
            return;
        }

        if (!code) {
            show('invalid', {
                heading: 'Not our ticket',
                detail: 'This QR code isn’t one of our tickets. Ask for the QR code on their ticket email or ticket page.',
                pauseOn: text,
            });

            return;
        }

        check(code);
    }

    async function check(code) {
        show('checking', { heading: 'Checking…', code: formatted(code), pauseOn: code, button: false });

        const timeout = new AbortController();
        const timer = setTimeout(() => timeout.abort(), serverWait);

        try {
            const response = await fetch(page.dataset.scanUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ code }),
                credentials: 'same-origin',
                signal: timeout.signal,
            });

            if ([401, 403, 419].includes(response.status)) {
                show('problem', {
                    heading: 'Signed out',
                    detail: 'You’re signed out, or no longer allowed to check tickets in. This ticket wasn’t checked.',
                    button: 'Sign in again',
                    then: () => window.location.assign(page.dataset.startUrl),
                });

                return;
            }

            if (response.status === 429) {
                show('problem', { heading: 'Slow down', detail: 'Too many scans in a minute. Wait a moment, then scan this ticket again.', pauseOn: code });

                return;
            }

            if (!response.ok) {
                throw new Error(`The server said ${response.status}`);
            }

            const verdict = await response.json();

            letInCount.textContent = verdict.counts.let_in.toLocaleString('en-NG');
            ticketsCount.textContent = verdict.counts.tickets.toLocaleString('en-NG');
            typeInput.value = '';

            show(verdict.valid ? 'valid' : 'invalid', { ...verdict, pauseOn: code });
        } catch {
            show('problem', {
                heading: 'No connection',
                detail: 'This ticket wasn’t checked. Find a signal, then try again.',
                code: formatted(code),
                button: 'Try again',
                then: () => check(code),
            });
        } finally {
            clearTimeout(timer);
        }
    }

    function formatted(code) {
        return `${code.slice(0, 4)}-${code.slice(4)}`;
    }

    /* The verdict on screen */

    function show(state, { heading, name = null, code = null, detail = '', button = 'Scan next', then = null, pauseOn = null }) {
        showingResult = true;
        result.dataset.state = state;
        resultHeading.textContent = heading;
        resultName.textContent = name ?? '';
        resultName.hidden = !name;
        resultCode.textContent = code ?? '';
        resultCode.hidden = !code;
        resultDetail.textContent = detail;
        resultDetail.hidden = !detail;
        resultButton.hidden = !button;
        resultButton.textContent = button || '';
        afterResult = then;
        paused = { code: pauseOn, until: Infinity };
        result.hidden = false;
        document.activeElement?.blur();

        if (state === 'valid' || state === 'invalid') {
            signal(state === 'valid');
        }
    }

    function next() {
        if (result.dataset.state === 'checking') {
            return;
        }

        const then = afterResult;

        result.hidden = true;
        showingResult = false;
        afterResult = null;
        paused = { ...paused, until: Date.now() + sameTicketPause };

        if (then) {
            then();
        } else if (wantsCamera && !stream) {
            openCamera();
        } else {
            scan();
        }
    }

    /** A rising beep for a good ticket and a low buzz for a bad one, plus a buzz on phones that can. */
    function signal(valid) {
        navigator.vibrate?.(valid ? 90 : [140, 80, 140]);

        if (!sound) {
            return;
        }

        const tones = valid ? [[880, 0, 0.09], [1320, 0.1, 0.14]] : [[196, 0, 0.4]];
        const now = sound.currentTime;

        for (const [frequency, start, length] of tones) {
            const tone = sound.createOscillator();
            const volume = sound.createGain();

            tone.type = valid ? 'sine' : 'square';
            tone.frequency.value = frequency;
            volume.gain.setValueAtTime(0.0001, now + start);
            volume.gain.exponentialRampToValueAtTime(valid ? 0.35 : 0.2, now + start + 0.01);
            volume.gain.exponentialRampToValueAtTime(0.0001, now + start + length);
            tone.connect(volume).connect(sound.destination);
            tone.start(now + start);
            tone.stop(now + start + length + 0.02);
        }
    }

    /** Phones only play sound after a tap, so the first tap on the page turns it on. */
    function allowSound() {
        const AudioContext = window.AudioContext ?? window.webkitAudioContext;

        if (!sound && AudioContext) {
            sound = new AudioContext();
        }

        sound?.resume?.();
    }

    /* Wiring */

    startButton.addEventListener('click', () => {
        allowSound();
        openCamera();
    });

    typeForm.addEventListener('submit', (event) => {
        event.preventDefault();
        allowSound();

        const code = ticketCodeIn(typeInput.value);

        if (!code) {
            show('invalid', {
                heading: 'Not a ticket code',
                detail: 'Ticket codes are 8 letters and numbers, like 7K3Q-9PXM. Check it and try again.',
                button: 'Try again',
                then: () => typeInput.focus(),
            });

            return;
        }

        check(code);
    });

    resultButton.addEventListener('click', next);

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            const wanted = wantsCamera;
            closeCamera();
            wantsCamera = wanted;
        } else if (wantsCamera && !stream && !showingResult) {
            openCamera();
        }
    });

    // Start loading the reader now, so it's ready by the time staff tap "Start scanning".
    loadDetector().catch(() => {});

    /* Adding the scanner to the home screen */

    navigator.serviceWorker?.register(page.dataset.serviceWorkerUrl, { scope: page.dataset.scope }).catch(() => {});

    let installPrompt = null;

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event;
        installButton.hidden = false;
    });

    installButton.addEventListener('click', async () => {
        installPrompt?.prompt();
        await installPrompt?.userChoice;
        installPrompt = null;
        installButton.hidden = true;
    });

    const installed = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const iPhone = /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    iosTip.hidden = installed || !iPhone;
}

const page = document.querySelector('[data-check-in]');

if (page) {
    startScanner(page);
}
