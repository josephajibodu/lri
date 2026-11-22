/*
 * The check-in scanner's service worker, served at /check-in/service-worker.js. Phones look for one before they offer
 * to add the scanner to the home screen. It keeps no copies of anything: every ticket has to be checked with the
 * server, so with no connection the scanner says so instead of opening an old copy that can't check anything.
 */
const offlinePage = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1C1917">
<title>No connection · Check-in</title>
<style>
body { margin: 0; min-height: 100svh; display: grid; place-items: center; padding: 24px; background: #1C1917; color: #F7F6F3; font: 1.05rem/1.5 system-ui, sans-serif; text-align: center; }
h1 { margin: 0 0 8px; font-size: 1.6rem; }
p { margin: 0 0 24px; color: #CFC9C1; }
button { padding: 14px 24px; border: 0; border-radius: 999px; background: #FFB000; color: #1C1917; font: 600 1rem system-ui, sans-serif; }
</style>
</head>
<body>
<main>
<h1>No connection</h1>
<p>The scanner needs the internet to check tickets. Find a signal, then try again.</p>
<button type="button" onclick="location.reload()">Try again</button>
</main>
</body>
</html>`;

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() => new Response(offlinePage, {
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        })),
    );
});
