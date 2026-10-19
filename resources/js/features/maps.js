import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';
import 'leaflet.markercluster';

const createMarkerIcon = (color = '#0d6efd') => L.divIcon({
    className: 'spacematch-map-pin',
    html: `<span style="display:block;width:18px;height:18px;border-radius:999px 999px 999px 0;background:${color};border:2px solid #fff;box-shadow:0 6px 16px rgba(15,23,42,0.35);transform:rotate(-45deg);position:relative;"></span>`,
    iconSize: [18, 18],
    iconAnchor: [9, 18],
    popupAnchor: [0, -14],
});

const buildPopupContent = (item) => {
    const priceLine = item.price == null ? '' : ` · From PHP ${Number(item.price).toLocaleString('en-PH')}/month`;
    const content = document.createElement('div');
    content.className = 'map-popup';
    const name = document.createElement(item.url ? 'a' : 'span');
    name.textContent = `${item.name || 'Property'}${priceLine}`;
    if (item.url) name.href = item.url;
    content.append(name, document.createElement('br'), document.createTextNode(item.location?.precision || 'Property location'));
    const reviewCount = Number(item.review_count);
    const averageRating = Number(item.rating);
    if (reviewCount > 0 && Number.isFinite(averageRating)) {
        const rating = document.createElement('span');
        rating.textContent = `★ ${averageRating.toFixed(1)} (${reviewCount} ${reviewCount === 1 ? 'review' : 'reviews'})`;
        content.append(document.createElement('br'), rating);
    }
    return content;
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-rental-map]').forEach(async section => {
        const status = section.querySelector('[data-map-status]');
        const results = section.querySelector('[data-map-results]');
        const canvas = section.querySelector('[data-map-canvas]');
        let points = JSON.parse(section.dataset.points || '[]');
        let map = null;
        let clusterLayer = null;
        let selectedMarker = null;
        let loadError = null;

        const summary = () => {
            results.replaceChildren();
            if (!points.length) {
                results.textContent = section.dataset.editor === 'true' ? 'No location pin selected.' : 'No published rentals with owner-confirmed public map locations are available in the Philippines yet.';
                return;
            }

            points.forEach(item => {
                const line = document.createElement('p');
                const link = document.createElement('a');
                link.textContent = `${item.name}${item.price == null ? '' : ` · From PHP ${Number(item.price).toLocaleString('en-PH')}/month`}`;
                if (item.url) link.href = item.url;
                line.append(link, document.createTextNode(` — ${item.location?.precision || 'Property location'}`));
                results.append(line);
            });
        };

        try {
            if (section.dataset.propertyMap !== 'true') {
                const response = await fetch(section.dataset.mapEndpoint, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Map listing data could not be loaded. Use the rental search.');
                const payload = await response.json();
                points = payload.data || [];
            }
            summary();
        } catch (error) {
            loadError = error.message;
        }

        const initializeMap = () => {
            const tileUrl = section.dataset.mapTileUrl || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
            const tileAttribution = section.dataset.mapTileAttribution || '&copy; OpenStreetMap contributors';
            const defaultLat = Number(section.dataset.defaultLat || 12.8797);
            const defaultLng = Number(section.dataset.defaultLng || 121.7740);
            const defaultZoom = Number(section.dataset.defaultZoom || 5);

            try {
                if (!canvas || !tileUrl) {
                    throw new Error('Map display is unavailable until a valid OpenStreetMap tile layer is configured.');
                }

                if (!map) {
                    map = L.map(canvas, {
                        scrollWheelZoom: true,
                        zoomControl: true,
                        maxBounds: [[4, 116], [22, 127]],
                        maxBoundsViscosity: 0.85,
                    });
                    L.tileLayer(tileUrl, {
                        attribution: tileAttribution,
                        maxZoom: 19,
                    }).addTo(map);
                }

                map.setView([defaultLat, defaultLng], defaultZoom);
                map.invalidateSize();

                if (clusterLayer) {
                    map.removeLayer(clusterLayer);
                    clusterLayer = null;
                }

                if (section.dataset.editor === 'true') {
                    let pinLat = null;
                    let pinLng = null;

                    if (points.length && points[0]?.location) {
                        pinLat = Number(points[0].location.lat);
                        pinLng = Number(points[0].location.lng);
                    }

                    if (pinLat !== null && pinLng !== null) {
                        selectedMarker = L.marker([pinLat, pinLng], { icon: createMarkerIcon('#198754') });
                        selectedMarker.addTo(map);
                        selectedMarker.bindPopup('Selected location');
                    }

                    map.on('click', event => {
                        const form = section.closest('form');
                        const lat = Number(event.latlng.lat.toFixed(7));
                        const lng = Number(event.latlng.lng.toFixed(7));

                        if (form) {
                            const latitudeField = form.querySelector('[name=latitude]');
                            const longitudeField = form.querySelector('[name=longitude]');
                            const confirmField = form.querySelector('[name=location_confirmed]');
                            if (latitudeField) latitudeField.value = lat;
                            if (longitudeField) longitudeField.value = lng;
                            if (confirmField) confirmField.checked = false;
                        }

                        if (selectedMarker) {
                            map.removeLayer(selectedMarker);
                        }

                        selectedMarker = L.marker([lat, lng], { icon: createMarkerIcon('#198754') });
                        selectedMarker.addTo(map);
                        selectedMarker.bindPopup('Selected pin — confirm before saving');
                        selectedMarker.openPopup();
                    });

                    status.textContent = points.length ? 'Owner location selected. Review the pin and confirm it before publishing.' : 'Click the map to choose a public listing pin.';
                    return;
                }

                clusterLayer = L.markerClusterGroup({
                    showCoverageOnHover: false,
                    spiderfyOnMaxZoom: true,
                    maxClusterRadius: 45,
                });

                points.forEach(item => {
                    const marker = L.marker([item.location.lat, item.location.lng], { icon: createMarkerIcon('#0d6efd') });
                    marker.bindPopup(buildPopupContent(item));
                    clusterLayer.addLayer(marker);
                });

                map.addLayer(clusterLayer);
                if (points.length) {
                    const bounds = L.latLngBounds(points.map(item => [item.location.lat, item.location.lng]));
                    map.fitBounds(bounds, { padding: [24, 24], maxZoom: 11 });
                }

                results.replaceChildren();
                summary();

                status.textContent = loadError || (points.length
                    ? 'Showing published rentals across the Philippines. Select a marker to view its space; approximate pins do not show exact addresses.'
                    : 'No published rentals with owner-confirmed public map locations are available in the Philippines yet.');
            } catch (error) {
                status.textContent = error.message || 'Map display is unavailable. Rental search remains available.';
            }
        };

        initializeMap();
    });
});
