// Leaflet map with OSM tiles
const mapEl = document.getElementById('map');
if (mapEl) {
  // Load Leaflet from CDN if not present
  await new Promise((resolve, reject) => {
    if (window.L) return resolve();
    const s = document.createElement('script');
    s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    s.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
    s.crossOrigin = '';
    s.onload = resolve;
    s.onerror = reject;
    document.head.appendChild(s);
  });

  const L = window.L;
  const lat = 48.58128010400103, lon = 17.815753332069;
  const map = L.map(mapEl, { scrollWheelZoom: false }).setView([lat, lon], 16);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);

  const icon = L.divIcon({
    html: '<div style="width:28px;height:28px;border-radius:50% 50% 50% 0;background:#D88BBE;transform:rotate(-45deg);box-shadow:0 4px 10px rgba(0,0,0,0.3);border:2px solid #fff"></div>',
    className: '',
    iconSize: [28, 28],
    iconAnchor: [14, 28],
  });
  L.marker([lat, lon], { icon })
    .addTo(map)
    .bindPopup('<strong>KUKO detský svet</strong><br>Bratislavská 141<br>921 01 Piešťany');
}
