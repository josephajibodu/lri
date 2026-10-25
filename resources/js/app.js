(function () {
    var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    function $(id) { return document.getElementById(id); }
    function pad(n) { return (n < 10 ? "0" : "") + n; }

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

    /* Draggable stickers */
    var z = 10;
    var hint = document.querySelector(".drag-hint");
    document.querySelectorAll(".sticker").forEach(function (st) {
        var sx = 0, sy = 0, ox = 0, oy = 0, dragging = false;
        st.addEventListener("pointerdown", function (e) {
            dragging = true;
            st.setPointerCapture(e.pointerId);
            sx = e.clientX; sy = e.clientY;
            ox = parseFloat(st.dataset.dx || 0); oy = parseFloat(st.dataset.dy || 0);
            st.classList.add("grabbing");
            st.style.zIndex = ++z;
            if (hint) hint.classList.add("used");
        });
        st.addEventListener("pointermove", function (e) {
            if (!dragging) return;
            var dx = ox + e.clientX - sx, dy = oy + e.clientY - sy;
            st.dataset.dx = dx; st.dataset.dy = dy;
            st.style.setProperty("--dx", dx + "px");
            st.style.setProperty("--dy", dy + "px");
        });
        function end() { dragging = false; st.classList.remove("grabbing"); }
        st.addEventListener("pointerup", end);
        st.addEventListener("pointercancel", end);
    });

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
        if (!base) return;
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
    initRoutes();

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
        updateArrows();

        var requested = false;
        function loadEmbeds() {
            if (requested) return;
            requested = true;
            var script = document.createElement("script");
            script.src = "https://www.instagram.com/embed.js";
            script.async = true;
            script.onload = function () {
                if (window.instgrm) window.instgrm.Embeds.process();
                setTimeout(updateArrows, 1500);
            };
            document.body.appendChild(script);
        }
        if ("IntersectionObserver" in window) {
            var observer = new IntersectionObserver(function (entries) {
                if (entries.some(function (e) { return e.isIntersecting; })) {
                    observer.disconnect();
                    loadEmbeds();
                }
            }, { rootMargin: "600px 0px" });
            observer.observe(rail);
        } else {
            loadEmbeds();
        }
    }
    initInstagram();

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

})();
