/* Cloyne: the Dashboard lead map. Draws <div data-lead-map data-lead-map-points="[...]"> on a real
   map (Leaflet + free Esri gray tiles, OpenStreetMap as backup), zoomed to where the leads are. Colors come from the CSS variables. */
(function () {
    'use strict';

    const box = document.querySelector('[data-lead-map]');
    if (!box) return;

    const fallback = box.querySelector('[data-lead-map-fallback]');
    if (typeof window.L === 'undefined') {
        if (fallback) fallback.textContent = "The map couldn't load. Check your internet connection and refresh.";
        return;
    }

    let points = [];
    try { points = JSON.parse(box.dataset.leadMapPoints || '[]'); } catch (e) { points = []; }
    if (!points.length) return;
    if (fallback) fallback.remove();

    const css = getComputedStyle(document.documentElement);
    const color = (name) => css.getPropertyValue(name).trim();
    const light = document.documentElement.dataset.theme === 'light';

    const map = L.map(box, {
        preferCanvas: true,
        zoomControl: true,
        scrollWheelZoom: false, // Don't hijack page scrolling; click the map first to zoom with the wheel.
        worldCopyJump: true,
        attributionControl: true,
    });
    box.addEventListener('click', () => map.scrollWheelZoom.enable(), { once: true });

    // Map background: Esri's free gray canvas (dark or light) with a separate layer for place names.
    // If those tiles fail, fall back to OpenStreetMap, darkened with a CSS filter in dark mode.
    const esri = (name) => 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/' + name + '/MapServer/tile/{z}/{y}/{x}';
    const shade = light ? 'Light' : 'Dark';
    const base = L.tileLayer(esri('World_' + shade + '_Gray_Base'), {
        maxZoom: 16,
        attribution: 'Map &copy; <a href="https://www.esri.com">Esri</a>, HERE, Garmin, &copy; OpenStreetMap contributors',
    }).addTo(map);
    const names = L.tileLayer(esri('World_' + shade + '_Gray_Reference'), { maxZoom: 16, pane: 'overlayPane' }).addTo(map);

    let failures = 0;
    base.on('tileerror', () => {
        if (++failures !== 3) return;
        map.removeLayer(base);
        map.removeLayer(names);
        box.classList.toggle('is-osm-dark', !light);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);
    });

    function popup(p) {
        const el = document.createElement('div');
        const name = document.createElement('div');
        name.className = 'map-pop__name';
        name.textContent = p.name;
        el.appendChild(name);
        if (p.about) {
            const about = document.createElement('div');
            about.className = 'map-pop__about';
            about.textContent = p.about;
            el.appendChild(about);
        }
        const row = document.createElement('div');
        row.className = 'map-pop__row';
        const score = document.createElement('span');
        score.className = 'map-pop__score' + (p.hot ? ' is-hot' : '');
        score.textContent = p.score === null ? 'Not audited' : 'Score ' + p.score;
        const open = document.createElement('a');
        open.className = 'map-pop__open';
        open.href = p.url;
        open.textContent = 'Open lead';
        row.appendChild(score);
        row.appendChild(open);
        el.appendChild(row);
        return el;
    }

    const groups = { hot: L.featureGroup(), other: L.featureGroup() };
    // Draw other leads first so hot ones sit on top.
    points.slice().sort((a, b) => (a.hot === b.hot ? 0 : a.hot ? 1 : -1)).forEach((p) => {
        const fill = color(p.hot ? '--brand' : '--signal');
        L.circleMarker([p.lat, p.lng], {
            radius: p.hot ? 7 : 5,
            color: color('--bg'),
            weight: 1.5,
            fillColor: fill,
            fillOpacity: p.hot ? 0.95 : 0.8,
        })
            .bindTooltip(p.name, { direction: 'top', offset: [0, -6] })
            .bindPopup(() => popup(p), { closeButton: true })
            .addTo(groups[p.hot ? 'hot' : 'other']);
    });
    groups.other.addTo(map);
    groups.hot.addTo(map);

    // Hide hover labels while a lead's card is open, so they don't sit behind it.
    map.on('popupopen', () => box.classList.add('has-popup'));
    map.on('popupclose', () => box.classList.remove('has-popup'));

    const all = L.featureGroup([groups.hot, groups.other]);
    map.fitBounds(all.getBounds(), { padding: [30, 30], maxZoom: 13 });

    // Legend buttons show or hide each group.
    document.querySelectorAll('[data-lead-map-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const group = groups[button.dataset.leadMapToggle];
            if (!group) return;
            const on = map.hasLayer(group);
            if (on) map.removeLayer(group); else group.addTo(map);
            button.setAttribute('aria-pressed', on ? 'false' : 'true');
        });
    });
})();
