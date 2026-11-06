/* Let Vite fingerprint the fonts so the layout can preload them with Vite::asset() */
import.meta.glob("../fonts/*.woff2");

(function () {
    var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    function $(id) { return document.getElementById(id); }
    function pad(n) { return (n < 10 ? "0" : "") + n; }

    /* Run fn once el is close to the screen, so sections further down cost nothing while the page loads */
    function whenNear(el, fn) {
        if (!("IntersectionObserver" in window)) { fn(); return; }
        var observer = new IntersectionObserver(function (entries) {
            if (entries.some(function (e) { return e.isIntersecting; })) {
                observer.disconnect();
                fn();
            }
        }, { rootMargin: "600px 0px" });
        observer.observe(el);
    }

    /* Race clock to the next run, read from the countdown's data-starts-at */
    var countdown = $("countdown");
    var target = countdown ? new Date(countdown.dataset.startsAt).getTime() : NaN;
    var clockTimer;
    function tick() {
        var ms = target - Date.now();
        if (ms <= 0) {
            ["c-d", "c-h", "c-m", "c-s"].forEach(function (id) { $(id).textContent = "00"; });
            $("next-status").textContent = "Last community run";
            $("clock-note").textContent = "That one’s done. The next date goes out on @letsrunibadan.";
            clearInterval(clockTimer);
            return;
        }
        var s = Math.floor(ms / 1000);
        var d = Math.floor(s / 86400); s -= d * 86400;
        var h = Math.floor(s / 3600); s -= h * 3600;
        var m = Math.floor(s / 60); s -= m * 60;
        $("c-d").textContent = pad(d);
        $("c-h").textContent = pad(h);
        $("c-m").textContent = pad(m);
        $("c-s").textContent = pad(s);
    }
    if (!isNaN(target)) {
        tick();
        clockTimer = setInterval(tick, 1000);
    }

    var DAY = 86400000;
    function ibadanDay(t) { return Math.floor((t + 3600000) / DAY); }
    document.querySelectorAll("[data-until]").forEach(function (el) {
        if (ibadanDay(new Date(el.getAttribute("data-until")).getTime()) < ibadanDay(Date.now())) el.hidden = true;
    });

    /* Home hero: the running crowd lives in its own file, fetched only on the page that has it */
    var heroScene = document.querySelector("[data-hero-scene]");
    if (heroScene) {
        import("./hero-runners.js").then(function (scene) { scene.startHeroRunners(heroScene, reduce); });
    }

    /* Pace playground */
    function initPace() {
        var pace = $("pace-input");
        if (!pace) return;
        function fmt(sec) {
            sec = Math.round(sec);
            var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
            return h ? h + ":" + pad(m) + ":" + pad(s) : m + ":" + pad(s);
        }
        var bands = [
            [270, "Flying", "Our quickest runners will happily chase you."],
            [360, "Tempo", "Plenty of runners hold this pace on a good morning."],
            [450, "Easy run", "The sweet spot for most Saturday groups."],
            [540, "Chatty jog", "Perfect for talking the whole way round."],
            [Infinity, "Brisk walk", "Walking is allowed, and a leader stays at the back with you."]
        ];
        function updatePace() {
            var p = +pace.value;
            var band = bands.filter(function (b) { return p <= b[0]; })[0];
            $("pace-out").textContent = fmt(p);
            $("pace-label").textContent = band[1];
            $("pace-msg").textContent = band[2];
            $("t5").textContent = fmt(p * 5);
            $("t10").textContent = fmt(p * 10);
            var fill = ((p - pace.min) / (pace.max - pace.min)) * 100;
            pace.style.setProperty("--fill", fill + "%");
            pace.setAttribute("aria-valuetext", fmt(p) + " per kilometre, " + band[1]);
            var you = $("you-runner");
            you.style.setProperty("--dur", (p / 60 * 1.2).toFixed(2) + "s");
            you.style.setProperty("--rest", Math.max(6, 100 - fill) * .9 + "%");
        }
        pace.addEventListener("input", updatePace);
        updatePace();
    }
    initPace();

    /* Tribe list lights up its compass pin */
    var compass = $("compass");
    document.querySelectorAll(".tribe").forEach(function (t) {
        var pin = $(t.dataset.pin);
        function on() { compass.classList.add("focus"); pin.classList.add("is-active"); t.classList.add("is-active"); }
        function off() { compass.classList.remove("focus"); pin.classList.remove("is-active"); t.classList.remove("is-active"); }
        t.addEventListener("mouseenter", on);
        t.addEventListener("mouseleave", off);
        t.addEventListener("focus", on);
        t.addEventListener("blur", off);
    });

    /* Route map on a running track */
    function initRoutes() {
        var routes = {
            ring: { name: "Ring Road loop", meta: "All tribes · 1 Aug 2026", chip: "Long run",
                stops: ["The Law Hub", "Mobil", "Ring Road", "Challenge", "Molete", "Dugbe", "Onireke", "Eleyele", "Iyaganku"] },
            b10: { name: "Bodija 10K", meta: "Community hangout · 5 Sep 2026", chip: "10 km",
                stops: ["Pasta Express, Osuntokun", "Bodija Market", "Trans Road", "Sango / Mokola Road", "Premier Junction", "Premier Hotel climb", "Secretariat Road", "Secretariat Roundabout"] },
            b5: { name: "Bodija 5K", meta: "Community hangout · 5 Sep 2026", chip: "5 km",
                stops: ["Pasta Express, Osuntokun", "Bodija Market", "Trans Road", "Sango / Elewure Junction"] }
        };
        var NS = "http://www.w3.org/2000/svg";
        var base = $("route-base"), draw = $("route-draw"), stopsG = $("route-stops"), runner = $("route-runner");
        var L = base.getTotalLength();
        draw.style.strokeDasharray = L;
        draw.style.strokeDashoffset = reduce ? 0 : L;

        function svgEl(tag, attrs, text) {
            var n = document.createElementNS(NS, tag);
            for (var k in attrs) n.setAttribute(k, attrs[k]);
            if (text) n.textContent = text;
            return n;
        }
        function renderRoute(key) {
            var r = routes[key];
            $("route-name").textContent = r.name;
            $("route-meta").textContent = r.meta;
            $("route-chip").textContent = r.chip;
            var list = $("route-list");
            list.textContent = "";
            r.stops.concat([r.stops[0]]).forEach(function (s, i, all) {
                var li = document.createElement("li");
                li.textContent = s;
                if (i === 0 || i === all.length - 1) {
                    li.className = "end";
                    var f = document.createElement("span");
                    f.className = "flag";
                    f.textContent = i === 0 ? "Start" : "Finish";
                    li.appendChild(document.createTextNode(" "));
                    li.appendChild(f);
                }
                list.appendChild(li);
            });
            stopsG.textContent = "";
            var n = r.stops.length;
            r.stops.forEach(function (s, i) {
                var p = base.getPointAtLength((L * i) / n);
                var nx, ny;
                if (p.x >= 300 && p.x <= 600) { nx = 0; ny = p.y < 230 ? -1 : 1; }
                else {
                    var cx = p.x < 300 ? 300 : 600, dx = p.x - cx, dy = p.y - 230, len = Math.hypot(dx, dy) || 1;
                    nx = dx / len; ny = dy / len;
                }
                var g = svgEl("g", { "class": "stop" + (i === 0 ? " stop-start" : "") });
                g.appendChild(svgEl("circle", { cx: p.x, cy: p.y, r: i === 0 ? 14 : 10 }));
                var off = 36;
                var anchor = Math.abs(nx) < 0.4 ? "middle" : nx > 0 ? "start" : "end";
                var lx = p.x + nx * off, ly = p.y + ny * off + (ny > 0.4 ? 6 : ny < -0.4 ? 0 : 5);
                if (i === 0) g.appendChild(svgEl("text", { "class": "stop-flag", x: lx, y: ly - 20, "text-anchor": anchor }, "START + FINISH"));
                g.appendChild(svgEl("text", { "class": "stop-label", x: lx, y: ly, "text-anchor": anchor }, s));
                stopsG.appendChild(g);
            });
        }
        function drawIn() {
            if (reduce) return;
            draw.style.transition = "none";
            draw.style.strokeDashoffset = L;
            draw.getBoundingClientRect();
            draw.style.transition = "stroke-dashoffset 1.8s cubic-bezier(.6, .1, .2, 1)";
            draw.style.strokeDashoffset = 0;
        }
        renderRoute("ring");

        var tabs = [].slice.call(document.querySelectorAll(".route-tab"));
        function selectTab(tab) {
            tabs.forEach(function (t) {
                var on = t === tab;
                t.setAttribute("aria-selected", on ? "true" : "false");
                t.tabIndex = on ? 0 : -1;
            });
            $("route-panel").setAttribute("aria-labelledby", tab.id);
            renderRoute(tab.dataset.route);
            drawIn();
        }
        tabs.forEach(function (t, i) {
            t.addEventListener("click", function () { selectTab(t); });
            t.addEventListener("keydown", function (e) {
                var j = e.key === "ArrowRight" ? i + 1 : e.key === "ArrowLeft" ? i - 1 : null;
                if (j === null) return;
                var next = tabs[(j + tabs.length) % tabs.length];
                next.focus();
                selectTab(next);
            });
        });

        var mapVisible = false, drawn = false, last = 0, dist = 0;
        function loop(ts) {
            if (!mapVisible) return;
            if (last) dist = (dist + ((ts - last) / 14000) * L) % L;
            last = ts;
            var p = base.getPointAtLength(dist);
            runner.setAttribute("cx", p.x);
            runner.setAttribute("cy", p.y);
            requestAnimationFrame(loop);
        }
        if ("IntersectionObserver" in window) {
            new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    mapVisible = e.isIntersecting;
                    if (mapVisible && !drawn) { drawn = true; drawIn(); }
                    if (mapVisible && !reduce) { last = 0; requestAnimationFrame(loop); }
                });
            }, { threshold: 0.25 }).observe($("route-panel"));
        } else {
            draw.style.strokeDashoffset = 0;
        }
    }
    if ($("route-panel")) whenNear($("route-panel"), initRoutes);

    /* Saturday kit check, remembered in this browser only */
    function initKit() {
        var KEY = "lri-kit-v1";
        if (!$("kit")) return;
        var boxes = [].slice.call(document.querySelectorAll("#kit input"));
        var saved = {};
        try { saved = JSON.parse(localStorage.getItem(KEY) || "{}") || {}; } catch (e) { saved = {}; }
        function updateKit() {
            var done = boxes.filter(function (b) { return b.checked; }).length;
            var total = boxes.length;
            $("kit-count").textContent = done + " / " + total;
            $("kit-bar").style.width = (done / total) * 100 + "%";
            $("kit-msg").textContent = done === total ? "You’re ready. See you at 6:30." : (total - done) + " to go. Tick them off as you pack.";
            $("kit").classList.toggle("done", done === total);
            var state = {};
            boxes.forEach(function (b) { state[b.id] = b.checked; });
            try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {}
        }
        boxes.forEach(function (b) {
            b.checked = !!saved[b.id];
            b.addEventListener("change", updateKit);
        });
        updateKit();
    }
    initKit();

    /* Instagram rail: arrow buttons, and Instagram's embed script loaded only once the rail is near the screen */
    function initInstagram() {
        var rail = $("ig-rail");
        if (!rail) return;
        var prev = document.querySelector("[data-rail-prev]");
        var next = document.querySelector("[data-rail-next]");
        function step() {
            var slide = rail.querySelector(".ig-slide");
            var gap = parseFloat(getComputedStyle(rail).columnGap) || 0;
            return slide ? slide.getBoundingClientRect().width + gap : rail.clientWidth * 0.8;
        }
        function updateArrows() {
            prev.disabled = rail.scrollLeft <= 2;
            next.disabled = rail.scrollLeft >= rail.scrollWidth - rail.clientWidth - 2;
        }
        prev.addEventListener("click", function () { rail.scrollBy({ left: -step(), behavior: reduce ? "auto" : "smooth" }); });
        next.addEventListener("click", function () { rail.scrollBy({ left: step(), behavior: reduce ? "auto" : "smooth" }); });
        rail.addEventListener("scroll", updateArrows, { passive: true });
        window.addEventListener("resize", updateArrows);

        whenNear(rail, function () {
            updateArrows();
            var script = document.createElement("script");
            script.src = "https://www.instagram.com/embed.js";
            script.async = true;
            script.onload = function () {
                if (window.instgrm) window.instgrm.Embeds.process();
                setTimeout(updateArrows, 1500);
            };
            document.body.appendChild(script);
        });
    }
    initInstagram();

    /* FAQ: one answer open at a time. Browsers that support <details name> do this already */
    function initFaq() {
        var items = [].slice.call(document.querySelectorAll(".faq details"));
        items.forEach(function (item) {
            item.addEventListener("toggle", function () {
                if (!item.open) return;
                items.forEach(function (other) {
                    if (other !== item) other.open = false;
                });
            });
        });
    }
    initFaq();

    /* Founders start list: the founder's photo follows the pointer over their row, leaning into the movement */
    function initFounderPeek() {
        var roster = $("fd-roster");
        var peek = $("fd-peek");
        if (!roster || !peek || !window.matchMedia("(hover: hover)").matches) return;
        var cards = [].slice.call(peek.querySelectorAll(".fd-peek-card"));
        var x = 0, y = 0, lastX = 0, frame = 0, settle;
        function place() {
            frame = 0;
            var tilt = reduce ? 0 : Math.max(-12, Math.min(12, (x - lastX) * .6));
            lastX = x;
            peek.style.setProperty("--px", x + "px");
            peek.style.setProperty("--py", y + "px");
            peek.style.setProperty("--tilt", tilt + "deg");
            clearTimeout(settle);
            settle = setTimeout(function () { peek.style.setProperty("--tilt", "0deg"); }, 120);
        }
        function follow(e) {
            x = e.clientX; y = e.clientY;
            if (!frame) frame = requestAnimationFrame(place);
        }
        roster.addEventListener("pointermove", follow);
        roster.querySelectorAll(".fd-row").forEach(function (row) {
            row.addEventListener("pointerenter", function (e) {
                if (!peek.classList.contains("is-on")) { lastX = e.clientX; follow(e); }
                cards.forEach(function (card) { card.classList.toggle("is-active", card.dataset.bib === row.dataset.bib); });
                peek.classList.add("is-on");
            });
        });
        roster.addEventListener("pointerleave", function () { peek.classList.remove("is-on"); });
    }
    initFounderPeek();

    /* Founder profiles and story splits rise in on scroll, and the splits' track fills in as you read */
    function initFounderReveal() {
        var profiles = $("fd-profiles");
        var splits = $("fd-splits");
        if (!profiles || reduce || !("IntersectionObserver" in window)) return;
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (!e.isIntersecting) return;
                e.target.classList.add("is-in");
                observer.unobserve(e.target);
            });
        }, { rootMargin: "0px 0px -12% 0px" });
        [profiles, splits].forEach(function (list) {
            if (!list) return;
            list.classList.add("fd-reveal");
            [].forEach.call(list.children, function (item) { observer.observe(item); });
        });
        if (!splits) return;
        var ticking = false;
        function fill() {
            ticking = false;
            var rect = splits.getBoundingClientRect();
            var progress = (window.innerHeight * .6 - rect.top) / rect.height;
            splits.style.setProperty("--progress", Math.max(0, Math.min(1, progress)).toFixed(3));
        }
        window.addEventListener("scroll", function () {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(fill);
        }, { passive: true });
        fill();
    }
    initFounderReveal();

    /* Pace setters: lane chips narrow the page to one group, and cards rise in a row at a time */
    function initPaceSetters() {
        var filter = $("ps-filter");
        var list = $("ps-groups");
        if (!filter || !list) return;
        var chips = [].slice.call(filter.querySelectorAll(".ps-chip"));
        var groups = [].slice.call(list.querySelectorAll(".ps-group"));
        var cards = [].slice.call(list.querySelectorAll(".ps-card"));
        var animate = !reduce && "IntersectionObserver" in window;
        var observer;

        function reveal(card) {
            card.classList.add("is-in");
            setTimeout(function () { card.style.removeProperty("--stagger"); }, 900);
        }
        if (animate) {
            observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (!e.isIntersecting) return;
                    reveal(e.target);
                    observer.unobserve(e.target);
                });
            }, { rootMargin: "0px 0px -8% 0px" });
            list.classList.add("ps-reveal");
            groups.forEach(function (group) {
                [].forEach.call(group.querySelectorAll(".ps-card"), function (card, i) {
                    card.style.setProperty("--stagger", (i % 4) * .07 + "s");
                    observer.observe(card);
                });
            });
        }

        function show(lane) {
            chips.forEach(function (chip) { chip.setAttribute("aria-pressed", chip.dataset.lane === lane ? "true" : "false"); });
            groups.forEach(function (group) { group.hidden = lane !== "all" && group.dataset.lane !== lane; });
            if (!animate) return;
            cards.forEach(function (card, i) {
                if (card.closest(".ps-group").hidden) return;
                card.classList.remove("is-in");
                card.style.setProperty("--stagger", (i % 4) * .05 + "s");
            });
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    cards.forEach(function (card) { if (!card.closest(".ps-group").hidden) reveal(card); });
                });
            });
        }

        filter.hidden = false;
        chips.forEach(function (chip) {
            chip.addEventListener("click", function () {
                show(chip.dataset.lane);
                filter.scrollTo({ left: chip.offsetLeft - 24, behavior: reduce ? "auto" : "smooth" });
                var top = list.getBoundingClientRect().top;
                if (top < 0) window.scrollTo({ top: window.scrollY + top - 170, behavior: reduce ? "auto" : "smooth" });
            });
        });

        /* A lane picked from the hero board should never land on a hidden group */
        function followHash() {
            var target = location.hash ? document.getElementById(location.hash.slice(1)) : null;
            var group = target && target.closest ? target.closest(".ps-group") : null;
            if (group && group.hidden) {
                show("all");
                target.scrollIntoView();
            }
        }
        window.addEventListener("hashchange", followHash);
    }
    initPaceSetters();

    /* Footer wordmark stretches toward the pointer */
    function initFooterWord() {
        var word = $("foot-word");
        if (!word) return;
        var text = word.textContent;
        word.textContent = "";
        var letters = [];
        text.split(" ").forEach(function (part, index) {
            if (index > 0) word.appendChild(document.createTextNode(" "));
            var group = document.createElement("span");
            group.className = "foot-word-group";
            group.setAttribute("aria-hidden", "true");
            part.split("").forEach(function (ch) {
                var s = document.createElement("span");
                s.textContent = ch;
                group.appendChild(s);
                letters.push(s);
            });
            word.appendChild(group);
        });
        if (!reduce && window.matchMedia("(hover: hover)").matches) {
            word.addEventListener("pointermove", function (e) {
                var rect = word.getBoundingClientRect();
                var px = (e.clientX - rect.left) / rect.width;
                letters.forEach(function (s, i) {
                    var f = Math.max(0, 1 - Math.abs(px - (i + 0.5) / letters.length) / 0.16);
                    s.style.fontStretch = (100 + f * 50) + "%";
                    s.style.fontWeight = Math.round(800 + f * 100);
                    s.classList.toggle("hot", f > 0.55);
                });
            });
            word.addEventListener("pointerleave", function () {
                letters.forEach(function (s) { s.style.fontStretch = ""; s.style.fontWeight = ""; s.classList.remove("hot"); });
            });
        }
    }
    initFooterWord();

    /* Birthday form: preview the chosen photos and stop more than the allowed number */
    function initBirthdayPhotos() {
        var input = $("photos");
        var previews = $("bday-previews");
        if (!input || !previews) return;
        var max = Number(input.dataset.maxPhotos);
        input.addEventListener("change", function () {
            var files = [].slice.call(input.files);
            input.setCustomValidity(files.length > max ? "Please choose no more than " + max + " photos." : "");
            input.reportValidity();
            previews.replaceChildren.apply(previews, files.slice(0, max).map(function (file) {
                var img = document.createElement("img");
                img.src = URL.createObjectURL(file);
                img.alt = file.name;
                return img;
            }));
        });
    }
    initBirthdayPhotos();

    /* Barracks registration: upload the passport photograph as soon as it is chosen, with progress, and hold the form until it finishes */
    function initPassportUpload() {
        var input = $("passport");
        var uploaded = $("passport_upload");
        var status = $("passport-status");
        if (!input || !uploaded || !status) return;
        var form = input.form;
        var submit = form.querySelector('[type="submit"]');
        var submitLabel = submit.textContent;
        var text = $("passport-status-text");
        var bar = $("passport-progress");
        var preview = $("passport-preview");
        var request = null;

        function show(state, message) {
            status.hidden = false;
            status.dataset.state = state;
            text.textContent = message;
            bar.hidden = state !== "uploading";
        }
        function setUploading(isUploading) {
            submit.disabled = isUploading;
            submit.textContent = isUploading ? "Uploading photo…" : submitLabel;
            form.setAttribute("aria-busy", isUploading ? "true" : "false");
        }
        function fail(message) {
            uploaded.value = "";
            input.value = "";
            input.required = true;
            preview.hidden = true;
            show("error", message);
        }

        input.addEventListener("change", function () {
            var file = input.files[0];
            if (request) request.abort();
            if (!file) return;

            /* The photo goes up now, so the form itself doesn't send it again */
            input.removeAttribute("name");
            uploaded.value = "";
            preview.src = URL.createObjectURL(file);
            preview.hidden = false;
            bar.value = 0;
            show("uploading", "Uploading… 0%");
            setUploading(true);

            var data = new FormData();
            data.append("passport", file);
            data.append("_token", form.querySelector('[name="_token"]').value);

            var xhr = request = new XMLHttpRequest();
            xhr.open("POST", status.dataset.uploadUrl);
            xhr.setRequestHeader("Accept", "application/json");
            xhr.upload.addEventListener("progress", function (e) {
                if (!e.lengthComputable) return;
                var percent = Math.round(e.loaded / e.total * 100);
                bar.value = percent;
                bar.textContent = percent + "%";
                text.textContent = percent < 100 ? "Uploading… " + percent + "%" : "Finishing up…";
            });
            xhr.addEventListener("load", function () {
                if (request !== xhr) return;
                request = null;
                setUploading(false);
                var body = null;
                try { body = JSON.parse(xhr.responseText); } catch (e) {}
                if (xhr.status >= 200 && xhr.status < 300 && body && body.passport_upload) {
                    uploaded.value = body.passport_upload;
                    input.required = false;
                    show("done", "Photo uploaded. Choose another to replace it.");
                } else if (body && body.errors && body.errors.passport) {
                    fail(body.errors.passport[0]);
                } else if (xhr.status === 419) {
                    fail("This page has expired. Refresh the page and try again.");
                } else if (xhr.status === 429) {
                    fail("Too many uploads. Wait a minute, then choose the photo again.");
                } else {
                    fail("The upload didn’t work. Choose the photo again.");
                }
            });
            xhr.addEventListener("error", function () {
                if (request !== xhr) return;
                request = null;
                setUploading(false);
                fail("The upload didn’t work. Check your connection and choose the photo again.");
            });
            xhr.send(data);
        });

        form.addEventListener("submit", function (e) {
            if (request) e.preventDefault();
        });
    }
    initPassportUpload();

    /* Silent Disco Run: tune the page to a channel, light follows the pointer, blocks fade in, the ticket tilts */
    function initDiscoRun() {
        var disco = $("disco");
        if (!disco) return;

        var buttons = [].slice.call(disco.querySelectorAll("[data-channel-button]"));
        var headsets = [].slice.call(disco.querySelectorAll(".dr-headset"));
        var nowPlaying = disco.querySelector("[data-channel-name]");
        function tune(button) {
            buttons.forEach(function (b) {
                disco.classList.remove("dr-tone-" + b.dataset.tone);
                b.setAttribute("aria-pressed", b === button ? "true" : "false");
            });
            disco.classList.add("dr-tone-" + button.dataset.tone);
            nowPlaying.textContent = button.querySelector(".dr-tune-name").textContent;
            headsets.forEach(function (h) {
                h.classList.toggle("is-tuned", h.dataset.on === button.dataset.channelButton);
            });
        }
        buttons.forEach(function (b) {
            b.addEventListener("click", function () { tune(b); });
        });
        if (buttons.length) tune(buttons[0]);

        var reveals = [].slice.call(disco.querySelectorAll("[data-reveal]"));
        if (!reduce && "IntersectionObserver" in window) {
            disco.classList.add("is-live");
            var revealer = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (!e.isIntersecting) return;
                    e.target.classList.add("is-in");
                    revealer.unobserve(e.target);
                });
            }, { rootMargin: "0px 0px -10% 0px" });
            reveals.forEach(function (el) { revealer.observe(el); });
        }

        if (reduce || !window.matchMedia("(pointer: fine)").matches) return;

        var hero = disco.querySelector(".dr-hero");
        var frame;
        hero.addEventListener("pointermove", function (e) {
            var box = hero.getBoundingClientRect();
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(function () {
                disco.style.setProperty("--mx", ((e.clientX - box.left) / box.width * 100) + "%");
                disco.style.setProperty("--my", ((e.clientY - box.top) / box.height * 100) + "%");
            });
        });

        var ticket = disco.querySelector("[data-tilt]");
        if (!ticket) return;
        ticket.addEventListener("pointermove", function (e) {
            var box = ticket.getBoundingClientRect();
            var x = (e.clientX - box.left) / box.width;
            var y = (e.clientY - box.top) / box.height;
            ticket.style.setProperty("--ty", ((x - .5) * 8) + "deg");
            ticket.style.setProperty("--tx", ((.5 - y) * 8) + "deg");
        });
        ticket.addEventListener("pointerleave", function () {
            ticket.style.setProperty("--tx", "0deg");
            ticket.style.setProperty("--ty", "0deg");
        });
    }
    initDiscoRun();

    /*
     * Checkout. Without JavaScript the form posts and the server sends the buyer to the payment page.
     * With it, the checkout opens over this page when the provider has a pop-up (an overlay), and falls
     * back to the payment page when it doesn't or can't load. Paid or not, the buyer ends on their order page.
     */
    var overlayScripts = { bachs: "https://checkout.bachs.io/bachs.js" };
    /* The addresses each provider's pop-up may open checkouts on. Bachs serves live and sandbox checkouts from different ones. */
    var overlayOrigins = { bachs: ["https://checkout.bachs.io", "https://sandbox-checkout.bachs.io"] };
    var eventsUrl = document.querySelector("meta[name=checkout-events]");

    /* Note a step only the browser sees. sendBeacon survives the page navigating away straight after. */
    function track(form, step, details) {
        if (!eventsUrl || !navigator.sendBeacon) return;
        var data = new FormData();
        data.set("_token", form.querySelector("input[name=_token]").value);
        data.set("step", step);
        Object.keys(details || {}).forEach(function (key) {
            if (details[key]) data.set(key, String(details[key]).toLowerCase().replace(/[^a-z0-9_.-]+/g, "_").slice(0, 50));
        });
        navigator.sendBeacon(eventsUrl.content, data);
    }
    var overlayLoads = {};

    function loadOverlay(name) {
        if (!overlayLoads[name]) {
            overlayLoads[name] = new Promise(function (resolve, reject) {
                var script = document.createElement("script");
                script.src = overlayScripts[name];
                script.async = true;
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }
        return overlayLoads[name];
    }

    function openOverlay(form, checkout) {
        if (checkout.overlay !== "bachs" || !overlayScripts[checkout.overlay]) return Promise.reject();

        var origin = new URL(checkout.checkout_url, location.href).origin;
        if (overlayOrigins[checkout.overlay].indexOf(origin) === -1) return Promise.reject(new Error("Unexpected checkout address"));

        return loadOverlay(checkout.overlay).then(function () {
            if (!window.Bachs || !window.Bachs.Checkout) throw new Error("Bachs checkout did not load");
            /* Bachs's script only opens checkouts on the address it is set up with, which is the live one unless told otherwise */
            window.Bachs.Initialize({ baseUrl: origin });
            var finished = false;
            return window.Bachs.Checkout.open({
                checkoutUrl: checkout.checkout_url,
                onEvent: function (event) {
                    if (event.type === "checkout.completed") {
                        finished = true;
                        window.location.assign(checkout.status_url);
                    } else if (event.type === "checkout.expired") {
                        finished = true;
                        window.location.assign(checkout.cancelled_url);
                    } else if (event.type === "checkout.closed" && !finished) {
                        track(form, "checkout_closed", { order: checkout.order, reason: event.data && event.data.reason });
                        window.location.assign(checkout.cancelled_url);
                    } else if (event.type === "checkout.error") {
                        window.location.assign(checkout.checkout_url);
                    }
                }
            });
        });
    }

    document.querySelectorAll("form[data-checkout]").forEach(function (form) {
        var status = form.querySelector("[data-checkout-status]");
        var buttons = form.querySelectorAll("button[name=channel]");
        var quantity = form.querySelector("[data-checkout-quantity]");
        var total = form.querySelector("[data-checkout-total]");

        if (form.hasAttribute("data-checkout-form-start")) {
            form.addEventListener("focusin", function started() {
                form.removeEventListener("focusin", started);
                track(form, "form_started");
            });
        }

        if (quantity && total) {
            var format = new Intl.NumberFormat("en-NG", { style: "currency", currency: total.dataset.currency, minimumFractionDigits: 0 });
            var minQuantity = Number(quantity.min);
            var maxQuantity = Number(quantity.max);
            var stepButtons = form.querySelectorAll("[data-checkout-step]");
            var couponBox = form.querySelector("[data-coupon]");
            var appliedCoupon = null;

            /* Keep the quantity a whole number the ticket allows, with the total and the − and + buttons to match. */
            var setQuantity = function (value) {
                var count = Math.min(Math.max(Math.round(value) || minQuantity, minQuantity), maxQuantity);
                quantity.value = count;
                var discount = appliedCoupon ? appliedCoupon.discounts[count] || 0 : 0;
                var toPay = Number(total.dataset.unitPrice) * count - discount;
                total.textContent = format.format(toPay / 100);
                if (appliedCoupon) {
                    couponBox.querySelector("[data-coupon-applied]").textContent = appliedCoupon.description + ": −" + format.format(discount / 100) + ". You pay " + format.format(toPay / 100) + ".";
                }
                stepButtons.forEach(function (button) {
                    var isAtLimit = Number(button.dataset.checkoutStep) < 0 ? count <= minQuantity : count >= maxQuantity;
                    button.setAttribute("aria-disabled", String(isAtLimit));
                });
            };

            quantity.addEventListener("change", function () { setQuantity(Number(quantity.value)); });
            /* The buttons start hidden, so without JavaScript the form only shows a number box that still works. */
            stepButtons.forEach(function (button) {
                button.hidden = false;
                button.addEventListener("click", function () {
                    setQuantity(Number(quantity.value) + Number(button.dataset.checkoutStep));
                });
            });
            setQuantity(Number(quantity.value));

            /*
             * "Have a coupon?": ask the site what the code takes off at each quantity, so the total stays right as the
             * number of tickets changes. The code also goes with the form and is checked again when the order is
             * placed, which is the only check without JavaScript.
             */
            if (couponBox) {
                var couponInput = couponBox.querySelector("[data-coupon-input]");
                var couponApply = couponBox.querySelector("[data-coupon-apply]");
                var couponRemove = couponBox.querySelector("[data-coupon-remove]");
                var couponApplied = couponBox.querySelector("[data-coupon-applied]");
                var couponError = couponBox.querySelector('[data-error-for="coupon"]');

                var useCoupon = function (coupon) {
                    appliedCoupon = coupon;
                    if (coupon) couponInput.value = coupon.code;
                    couponInput.readOnly = Boolean(coupon);
                    couponInput.removeAttribute("aria-invalid");
                    couponError.textContent = "";
                    couponApply.hidden = Boolean(coupon);
                    couponRemove.hidden = !coupon;
                    couponApplied.hidden = !coupon;
                    setQuantity(Number(quantity.value));
                };

                var couponProblem = function (message) {
                    couponError.textContent = message;
                    couponInput.setAttribute("aria-invalid", "true");
                    couponInput.focus();
                };

                var applyCoupon = function () {
                    var code = couponInput.value.trim();
                    if (!code) {
                        couponProblem("Type your coupon code first.");
                        return;
                    }

                    var data = new FormData();
                    data.set("_token", form.querySelector("input[name=_token]").value);
                    data.set("ticket_type", form.querySelector("input[name=ticket_type]").value);
                    data.set("coupon", code);
                    couponApply.disabled = true;

                    fetch(couponBox.dataset.couponUrl, { method: "POST", body: data, headers: { Accept: "application/json" }, credentials: "same-origin" })
                        .then(function (response) {
                            return response.json().then(function (json) { return { status: response.status, json: json }; });
                        })
                        .then(function (result) {
                            if (result.status === 200) {
                                useCoupon(result.json);
                                return;
                            }
                            couponProblem((result.json.errors && result.json.errors.coupon && result.json.errors.coupon[0])
                                || (result.status === 429 ? "Too many tries. Wait a minute and try again." : "We couldn’t check that coupon. Refresh the page and try again."));
                        })
                        .catch(function () { couponProblem("We couldn’t check that coupon. Try again."); })
                        .then(function () { couponApply.disabled = false; });
                };

                /* The Apply button starts hidden, so without JavaScript the box just goes with the form. */
                couponBox.querySelector("[data-coupon-hint]").hidden = true;
                couponApply.hidden = false;
                couponApply.addEventListener("click", applyCoupon);
                /* Enter here applies the coupon. Left alone, it would submit the form with the first way to pay. */
                couponInput.addEventListener("keydown", function (event) {
                    if (event.key !== "Enter") return;
                    event.preventDefault();
                    if (!appliedCoupon) applyCoupon();
                });
                couponRemove.addEventListener("click", function () {
                    useCoupon(null);
                    couponInput.value = "";
                    couponInput.focus();
                });
                if (couponInput.value) applyCoupon();
            }
        }

        function busy(isBusy, message) {
            buttons.forEach(function (button) { button.disabled = isBusy; });
            if (status) {
                status.hidden = !message;
                status.textContent = message || "";
            }
        }

        function showErrors(errors) {
            form.querySelectorAll("[data-error-for]").forEach(function (el) { el.textContent = ""; });
            form.querySelectorAll("[aria-invalid]").forEach(function (el) { el.removeAttribute("aria-invalid"); });
            var first;
            Object.keys(errors).forEach(function (field) {
                var slot = form.querySelector('[data-error-for="' + field + '"]');
                var input = form.querySelector('[name="' + field + '"]:not(button)');
                if (slot) slot.textContent = errors[field][0];
                if (input) {
                    input.setAttribute("aria-invalid", "true");
                    first = first || input;
                    /* A field folded away, like the coupon, has to open for its error to be seen. */
                    var folded = input.closest("details");
                    if (folded) folded.open = true;
                }
            });
            if (first) first.focus();
        }

        var botCheck = form.querySelector(".cf-turnstile");

        /* The bot check's token takes a moment to arrive, so wait for it instead of sending the form without one. */
        function whenBotCheckIsReady() {
            return new Promise(function (resolve) {
                var waited = 0;
                (function check() {
                    var token = form.querySelector("[name=cf-turnstile-response]");
                    if (!botCheck || (token && token.value) || waited >= 15000) return resolve();
                    waited += 200;
                    setTimeout(check, 200);
                })();
            });
        }

        /* A token only works once, so anything that keeps the buyer on this page needs a new one for their next try. */
        function renewBotCheck() {
            if (botCheck && window.turnstile) window.turnstile.reset();
        }

        form.addEventListener("submit", function (event) {
            var button = event.submitter;
            if (!button || button.name !== "channel" || !window.fetch) return;
            event.preventDefault();

            busy(true, "Opening the payment page…");

            whenBotCheckIsReady()
                .then(function () {
                    var data = new FormData(form);
                    data.set("channel", button.value);

                    return fetch(form.action, { method: "POST", body: data, headers: { Accept: "application/json" }, credentials: "same-origin" });
                })
                .then(function (response) {
                    return response.json().then(function (json) { return { status: response.status, json: json }; });
                })
                .then(function (result) {
                    if (result.status === 422) {
                        busy(false);
                        renewBotCheck();
                        /* A coupon that stopped working since it was applied no longer counts in the total shown. */
                        if (useCoupon && result.json.errors && result.json.errors.coupon) useCoupon(null);
                        showErrors(result.json.errors || {});
                        return;
                    }
                    if (result.json.status_url && !result.json.checkout_url) {
                        window.location.assign(result.json.status_url);
                        return;
                    }
                    if (!result.json.checkout_url) throw new Error("No checkout");

                    openOverlay(form, result.json)
                        .then(function () { busy(false); renewBotCheck(); })
                        .catch(function () { window.location.assign(result.json.checkout_url); });
                })
                .catch(function () {
                    var channel = document.createElement("input");
                    channel.type = "hidden";
                    channel.name = "channel";
                    channel.value = button.value;
                    form.appendChild(channel);
                    form.submit();
                });
        });
    });

    /* Order page: while a payment is being confirmed, check every few seconds and reload when it settles */
    var poll = document.querySelector("[data-order-poll]");
    if (poll) {
        var started = Date.now();
        (function check() {
            var waited = Date.now() - started;
            if (waited > 15 * 60000) return;
            setTimeout(function () {
                fetch(poll.dataset.orderPoll, { headers: { Accept: "application/json" }, credentials: "same-origin" })
                    .then(function (response) { return response.json(); })
                    .then(function (json) {
                        if (json.state && json.state !== poll.dataset.state) {
                            window.location.reload();
                        } else {
                            check();
                        }
                    })
                    .catch(check);
            }, waited < 120000 ? 4000 : 10000);
        })();
    }

    /* Share or copy a ticket's own link: the phone's share sheet where there is one, otherwise the clipboard */
    function shareLink(url) {
        if (navigator.share && window.matchMedia("(pointer: coarse)").matches) {
            return navigator.share({ title: "Your ticket", url: url }).then(function () { return true; }, function () { return false; });
        }
        if (navigator.clipboard) {
            return navigator.clipboard.writeText(url).then(function () { return true; }, function () { window.prompt("Copy this link:", url); return true; });
        }
        window.prompt("Copy this link:", url);
        return Promise.resolve(true);
    }

    document.querySelectorAll("[data-copy]").forEach(function (button) {
        button.addEventListener("click", function () {
            var label = button.textContent;
            shareLink(button.dataset.copy).then(function (shared) {
                if (!shared) { return; }
                button.textContent = button.dataset.copied;
                setTimeout(function () { button.textContent = label; }, 2500);
            });
        });
    });

    /* "Send link": share the link first, then tell the site it went out. Without JavaScript the form just submits,
       and the receipt offers the link to copy */
    document.querySelectorAll("form[data-share]").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            event.preventDefault();
            shareLink(form.dataset.share).then(function (shared) {
                if (shared) { form.submit(); }
            });
        });
    });

    /* Gallery: photos open in a full-screen viewer with arrows, keys and swipes. Without JavaScript each link opens the photo itself */
    function initGalleryViewer() {
        var viewer = $("gallery-viewer");
        if (!viewer || typeof viewer.showModal !== "function") return;
        var links = Array.prototype.slice.call(document.querySelectorAll("[data-gallery-photo]"));
        var image = viewer.querySelector("[data-viewer-image]");
        var current = 0;
        var swipeStart = null;
        function show(index) {
            current = (index + links.length) % links.length;
            var link = links[current];
            image.src = link.href;
            image.alt = link.querySelector("img").alt;
            [current + 1, current - 1].forEach(function (neighbour) {
                new Image().src = links[(neighbour + links.length) % links.length].href;
            });
        }
        links.forEach(function (link, index) {
            link.addEventListener("click", function (event) {
                event.preventDefault();
                show(index);
                viewer.showModal();
            });
        });
        viewer.querySelector("[data-viewer-close]").addEventListener("click", function () { viewer.close(); });
        viewer.querySelector("[data-viewer-prev]").addEventListener("click", function () { show(current - 1); });
        viewer.querySelector("[data-viewer-next]").addEventListener("click", function () { show(current + 1); });
        viewer.addEventListener("keydown", function (event) {
            if (event.key === "ArrowLeft") { show(current - 1); }
            if (event.key === "ArrowRight") { show(current + 1); }
        });
        viewer.addEventListener("close", function () { links[current].focus(); });
        image.addEventListener("pointerdown", function (event) { swipeStart = event.clientX; });
        image.addEventListener("pointerup", function (event) {
            if (swipeStart === null) return;
            var distance = event.clientX - swipeStart;
            swipeStart = null;
            if (Math.abs(distance) > 40) { show(current + (distance < 0 ? 1 : -1)); }
        });
    }
    initGalleryViewer();

    /* Ask before an undo that can't be taken back */
    document.querySelectorAll("form[data-confirm]").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            if (!window.confirm(form.dataset.confirm)) { event.preventDefault(); }
        });
    });

})();
