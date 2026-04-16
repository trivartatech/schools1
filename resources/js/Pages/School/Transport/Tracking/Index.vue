<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const { can } = usePermissions();

const mapContainer  = ref(null);
const vehicles      = ref([]);
const error         = ref(null);
const lastUpdated   = ref(null);
const loading       = ref(true);
const selectedId    = ref(null);   // selected vehicle_id
const mapReady      = ref(false);

let map            = null;
let markers        = {};           // vehicle_id → L.marker
let stopMarkers    = [];           // L.marker[] for selected route stops
let routePolyline  = null;         // L.polyline for selected route
let timer          = null;
let busIcon        = null;

const DEFAULT_LAT  = 28.5921;     // Delhi area default
const DEFAULT_LNG  = 77.0460;
const DEFAULT_ZOOM = 12;

// ── Computed ──
const selected = computed(() =>
    vehicles.value.find(v => v.vehicle_id === selectedId.value) || null
);
const tracking = computed(() => selected.value?.tracking || null);
const routeStops = computed(() => tracking.value?.stops || []);
const busInfo = computed(() => {
    const v = selected.value?.vehicle;
    const t = tracking.value;
    if (!v) return null;
    return {
        vehicleNumber: v.vehicle_number || 'N/A',
        vehicleName:   v.vehicle_name || '',
        routeName:     v.route?.route_name || 'Unassigned',
        routeCode:     v.route?.route_code || '',
        capacity:      v.capacity || '—',
        driverName:    t?.driver_name || 'N/A',
        driverPhone:   t?.driver_phone || null,
        conductorName: t?.conductor_name || 'N/A',
        totalStudents: t?.total_students || 0,
    };
});
const etaInfo = computed(() => {
    const t = tracking.value;
    const s = selected.value;
    if (!t || !s) return null;
    return {
        eta:          t.eta_minutes != null ? `${t.eta_minutes} min` : '—',
        speed:        `${s.speed || 0} km/h`,
        distance:     t.next_stop_dist != null ? `${t.next_stop_dist} km` : '—',
        distToSchool: t.dist_to_school != null ? `${t.dist_to_school} km` : '—',
        nextStop:     t.next_stop?.name || 'Arriving',
        currentStop:  t.nearest_stop?.name || '—',
    };
});

// ── Map init ──
function initMap() {
    busIcon = L.divIcon({
        className: 'bus-marker-icon',
        html: `<div class="bus-marker"><span class="bus-emoji">🚌</span></div>`,
        iconSize: [44, 44],
        iconAnchor: [22, 44],
        popupAnchor: [0, -44],
    });

    map = L.map(mapContainer.value, {
        zoomControl: false,
    }).setView([DEFAULT_LAT, DEFAULT_LNG], DEFAULT_ZOOM);

    L.control.zoom({ position: 'topright' }).addTo(map);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 19,
    }).addTo(map);

    mapReady.value = true;
}

// ── Data fetch ──
async function fetchLiveLocations() {
    try {
        const res = await fetch('/school/transport/live-data', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error('Failed to fetch live data.');
        const data = await res.json();
        vehicles.value = data;
        lastUpdated.value = school.fmtTime(new Date().toISOString());
        updateBusMarkers(data);

        // Auto-select first vehicle if nothing selected
        if (!selectedId.value && data.length) {
            selectedId.value = data[0].vehicle_id;
        }
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

// ── Bus markers on map ──
function updateBusMarkers(locations) {
    if (!map) return;
    const currentIds = new Set(locations.map(l => l.vehicle_id));

    Object.keys(markers).forEach(id => {
        if (!currentIds.has(Number(id))) {
            map.removeLayer(markers[id]);
            delete markers[id];
        }
    });

    locations.forEach(loc => {
        const lat   = parseFloat(loc.latitude);
        const lng   = parseFloat(loc.longitude);
        const vid   = loc.vehicle_id;
        const label = loc.vehicle?.vehicle_number || `Vehicle #${vid}`;

        if (markers[vid]) {
            markers[vid].setLatLng([lat, lng]);
        } else {
            markers[vid] = L.marker([lat, lng], { icon: busIcon })
                .addTo(map)
                .bindTooltip(label, { direction: 'top', offset: [0, -44] });
        }

        // Highlight selected bus
        const el = markers[vid].getElement();
        if (el) {
            el.classList.toggle('bus-selected', vid === selectedId.value);
        }
    });
}

// ── Draw route stops + polyline when selection changes ──
watch(selectedId, () => {
    drawSelectedRoute();
    // Highlight markers
    Object.entries(markers).forEach(([id, m]) => {
        const el = m.getElement();
        if (el) el.classList.toggle('bus-selected', Number(id) === selectedId.value);
    });
});

function drawSelectedRoute() {
    if (!map) return;

    // Clear previous
    stopMarkers.forEach(m => map.removeLayer(m));
    stopMarkers = [];
    if (routePolyline) { map.removeLayer(routePolyline); routePolyline = null; }

    const stops = routeStops.value;
    if (!stops.length) {
        const busLoc = selected.value;
        if (busLoc && map) {
            map.flyTo([parseFloat(busLoc.latitude), parseFloat(busLoc.longitude)], 15);
        }
        return;
    }

    const coords = [];

    stops.forEach((stop, idx) => {
        if (!stop.latitude || !stop.longitude) return;
        const lat = parseFloat(stop.latitude);
        const lng = parseFloat(stop.longitude);
        coords.push([lat, lng]);

        const isFirst  = idx === 0;
        const isLast   = idx === stops.length - 1;
        const isCurrent = stop.status === 'current';
        const isPassed  = stop.status === 'passed';

        let color = '#94a3b8'; // upcoming: slate
        let size  = 12;
        let border = '#fff';
        if (isPassed)  { color = '#22c55e'; }       // green
        if (isCurrent) { color = '#3b82f6'; size = 16; } // blue
        if (isLast)    { color = '#ef4444'; size = 14; } // red = school
        if (isFirst)   { color = '#f59e0b'; size = 14; } // amber = start

        const icon = L.divIcon({
            className: 'stop-marker-icon',
            html: `<div style="
                width:${size}px;height:${size}px;border-radius:50%;
                background:${color};border:2.5px solid ${border};
                box-shadow:0 0 0 3px ${color}33;
                ${isCurrent ? 'animation:pulse-stop 1.5s infinite;' : ''}
            "></div>`,
            iconSize: [size, size],
            iconAnchor: [size / 2, size / 2],
        });

        const marker = L.marker([lat, lng], { icon, interactive: true })
            .addTo(map)
            .bindTooltip(`${stop.name}${stop.student_count ? ' (' + stop.student_count + ' students)' : ''}`, {
                direction: 'top', offset: [0, -8],
            });
        stopMarkers.push(marker);
    });

    // Draw polyline
    if (coords.length > 1) {
        routePolyline = L.polyline(coords, {
            color: '#3b82f6',
            weight: 3,
            opacity: 0.6,
            dashArray: '8 6',
        }).addTo(map);
    }

    // Fit bounds to include bus + stops
    const busLoc = selected.value;
    if (busLoc) coords.push([parseFloat(busLoc.latitude), parseFloat(busLoc.longitude)]);
    if (coords.length) {
        map.fitBounds(L.latLngBounds(coords).pad(0.15));
    }
}

// ── Actions ──
function selectVehicle(vid) {
    selectedId.value = vid;
    nextTick(centerOnBus);
}

function callDriver() {
    const phone = tracking.value?.driver_phone;
    if (phone) window.open(`tel:${phone}`);
}

function centerOnBus() {
    const loc = selected.value;
    if (loc && map) {
        map.flyTo([parseFloat(loc.latitude), parseFloat(loc.longitude)], 15);
    }
}

// ── Lifecycle ──
onMounted(async () => {
    await loadLeaflet();
    initMap();
    await fetchLiveLocations();
    timer = setInterval(fetchLiveLocations, 10000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
    if (map) map.remove();
});

function loadLeaflet() {
    return new Promise((resolve) => {
        if (typeof L !== 'undefined') { resolve(); return; }
        if (!document.querySelector('link[href*="leaflet"]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(link);
        }
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = () => resolve();
        document.body.appendChild(script);
    });
}
</script>

<template>
    <SchoolLayout title="Live Bus Tracking">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Live Bus Tracking</h1>
                <p class="page-header-sub">
                    Real-time vehicle tracking dashboard
                    <span v-if="lastUpdated" class="last-updated">— Updated {{ lastUpdated }}</span>
                </p>
            </div>
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <span class="live-indicator">
                    <span class="live-dot"></span>
                    LIVE
                </span>
                <span v-if="vehicles.length" class="badge badge-blue">{{ vehicles.length }} Online</span>
            </div>
        </div>

        <!-- No Permission -->
        <div v-if="!can('view_transport_tracking')" class="card" style="padding:5rem;text-align:center;color:var(--text-muted);">
            <p>You do not have permission to view live tracking data.</p>
        </div>

        <div v-else class="tracking-wrapper">

            <!-- ═══ LEFT: Vehicle List ═══ -->
            <div class="vehicle-list-panel card">
                <div class="card-header" style="border-bottom:1px solid var(--border);">
                    <span class="card-title">Vehicles</span>
                </div>

                <div v-if="loading" class="panel-msg">Loading…</div>
                <div v-else-if="error" class="panel-msg" style="color:var(--danger);">{{ error }}</div>
                <div v-else-if="!vehicles.length" class="panel-msg">No buses online</div>

                <div v-else class="vehicle-scroll">
                    <button
                        v-for="loc in vehicles" :key="loc.vehicle_id"
                        @click="selectVehicle(loc.vehicle_id)"
                        :class="['vehicle-item', { active: loc.vehicle_id === selectedId }]"
                    >
                        <div class="vehicle-item-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M3 12h18"/><circle cx="7" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/><path d="M5.5 19h13"/></svg>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="vehicle-item-num">{{ loc.vehicle?.vehicle_number }}</div>
                            <div class="vehicle-item-route">{{ loc.vehicle?.route?.route_name || 'No Route' }}</div>
                        </div>
                        <div class="vehicle-item-speed">
                            <span class="speed-val">{{ loc.speed || 0 }}</span>
                            <span class="speed-unit">km/h</span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- ═══ CENTER: Map ═══ -->
            <div class="map-panel">
                <div class="map-wrapper">
                    <div ref="mapContainer" style="width:100%;height:100%;"></div>

                    <!-- Map Overlay: LIVE badge + Legend -->
                    <div v-if="mapReady" class="map-overlay-top">
                        <div class="map-legend">
                            <span class="legend-item"><span class="legend-dot" style="background:#22c55e;"></span> Passed</span>
                            <span class="legend-item"><span class="legend-dot" style="background:#3b82f6;"></span> Current</span>
                            <span class="legend-item"><span class="legend-dot" style="background:#94a3b8;"></span> Upcoming</span>
                            <span class="legend-item"><span class="legend-dot" style="background:#ef4444;"></span> School</span>
                        </div>
                    </div>

                    <!-- Center on bus button -->
                    <button v-if="selected" @click="centerOnBus" class="center-bus-btn" title="Center on bus">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v4m0 12v4M2 12h4m12 0h4"/></svg>
                    </button>

                    <!-- Loading overlay -->
                    <div v-if="loading" class="map-loading">
                        <p style="font-size:0.875rem;color:var(--text-muted);">Loading map…</p>
                    </div>
                </div>

                <!-- ═══ ETA Bar ═══ -->
                <div v-if="etaInfo" class="eta-bar">
                    <div class="eta-card eta-main">
                        <div class="eta-value">{{ etaInfo.eta }}</div>
                        <div class="eta-label">ETA to next</div>
                    </div>
                    <div class="eta-card">
                        <div class="eta-value">{{ etaInfo.speed }}</div>
                        <div class="eta-label">Speed</div>
                    </div>
                    <div class="eta-card">
                        <div class="eta-value">{{ etaInfo.distance }}</div>
                        <div class="eta-label">Next stop</div>
                    </div>
                    <div class="eta-card">
                        <div class="eta-value">{{ etaInfo.distToSchool }}</div>
                        <div class="eta-label">To school</div>
                    </div>
                    <div class="eta-card eta-next">
                        <div class="eta-label">Next Stop</div>
                        <div class="eta-next-name">{{ etaInfo.nextStop }}</div>
                    </div>
                </div>
            </div>

            <!-- ═══ RIGHT: Info Panel ═══ -->
            <div class="info-panel" v-if="selected">

                <!-- Bus Info Card -->
                <div class="card info-card" v-if="busInfo">
                    <div class="info-card-header">
                        <div class="info-card-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M3 12h18"/><circle cx="7" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/><path d="M5.5 19h13"/></svg>
                        </div>
                        <div>
                            <div class="info-card-title">{{ busInfo.routeName }}</div>
                            <div class="info-card-sub">{{ busInfo.routeCode }}</div>
                        </div>
                    </div>
                    <div class="info-grid">
                        <div class="info-row">
                            <span class="info-label">Vehicle</span>
                            <span class="info-val">{{ busInfo.vehicleNumber }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Capacity</span>
                            <span class="info-val">{{ busInfo.capacity }} seats</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Students</span>
                            <span class="info-val">{{ busInfo.totalStudents }} assigned</span>
                        </div>
                    </div>

                    <!-- Driver Section -->
                    <div class="driver-section">
                        <div class="driver-avatar">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="driver-name">{{ busInfo.driverName }}</div>
                            <div class="driver-role">Driver</div>
                        </div>
                        <button
                            v-if="busInfo.driverPhone"
                            @click="callDriver"
                            class="call-btn"
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                            Call
                        </button>
                    </div>
                    <div v-if="busInfo.conductorName !== 'N/A'" class="conductor-row">
                        <span class="info-label">Conductor</span>
                        <span class="info-val">{{ busInfo.conductorName }}</span>
                    </div>
                </div>

                <!-- Route Timeline -->
                <div class="card timeline-card">
                    <div class="card-header" style="border-bottom:1px solid var(--border);">
                        <span class="card-title">Route Timeline</span>
                        <span class="badge badge-blue" style="font-size:0.7rem;">{{ routeStops.length }} stops</span>
                    </div>
                    <div class="timeline-scroll">
                        <div class="timeline">
                            <div
                                v-for="(stop, idx) in routeStops" :key="stop.id"
                                :class="['timeline-item', stop.status]"
                            >
                                <!-- Timeline line -->
                                <div class="timeline-line-wrapper">
                                    <div v-if="idx > 0" :class="['timeline-line-top', routeStops[idx-1]?.status === 'passed' || stop.status === 'passed' || stop.status === 'current' ? 'line-passed' : '']"></div>
                                    <div :class="['timeline-dot', `dot-${stop.status}`]">
                                        <!-- Passed = checkmark -->
                                        <svg v-if="stop.status === 'passed'" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>
                                        <!-- Current = bus icon -->
                                        <span v-else-if="stop.status === 'current'" class="dot-bus">🚌</span>
                                        <!-- Upcoming = number -->
                                        <span v-else class="dot-num">{{ idx + 1 }}</span>
                                    </div>
                                    <div v-if="idx < routeStops.length - 1" :class="['timeline-line-bottom', stop.status === 'passed' ? 'line-passed' : '']"></div>
                                </div>

                                <!-- Stop info -->
                                <div class="timeline-content">
                                    <div class="timeline-stop-name">
                                        {{ stop.name }}
                                        <span v-if="idx === routeStops.length - 1" class="timeline-badge school-badge">SCHOOL</span>
                                    </div>
                                    <div class="timeline-stop-meta">
                                        <span v-if="stop.pickup_time" class="meta-time">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                            {{ stop.pickup_time }}
                                        </span>
                                        <span v-if="stop.student_count" class="meta-students">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                                            {{ stop.student_count }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- No selection placeholder -->
            <div v-else class="info-panel">
                <div class="card" style="padding:3rem;text-align:center;color:var(--text-muted);">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin:0 auto 1rem;opacity:0.3;"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M3 12h18"/><circle cx="7" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/></svg>
                    <p style="font-size:0.875rem;">Select a vehicle to view tracking details</p>
                </div>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* ── Global Animation Keyframes ── */
@keyframes pulse-live {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}
@keyframes pulse-stop {
    0% { box-shadow: 0 0 0 0 rgba(59,130,246,0.5); }
    70% { box-shadow: 0 0 0 8px rgba(59,130,246,0); }
    100% { box-shadow: 0 0 0 0 rgba(59,130,246,0); }
}
@keyframes pulse-bus {
    0% { box-shadow: 0 0 0 0 rgba(29,78,216,0.4); }
    70% { box-shadow: 0 0 0 12px rgba(29,78,216,0); }
    100% { box-shadow: 0 0 0 0 rgba(29,78,216,0); }
}

/* ── Live Indicator ── */
.live-indicator {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: #dc2626; color: #fff; padding: 0.3rem 0.75rem;
    border-radius: 99px; font-size: 0.75rem; font-weight: 700;
    letter-spacing: 0.05em;
}
.live-dot {
    width: 8px; height: 8px; border-radius: 50%; background: #fff;
    animation: pulse-live 1.5s infinite;
}
.last-updated { margin-left: 0.5rem; color: var(--text-muted); }

/* ── Main Wrapper ── */
.tracking-wrapper {
    display: grid;
    grid-template-columns: 15rem 1fr 22rem;
    gap: 1rem;
    height: calc(100vh - 200px);
    min-height: 550px;
}
@media (max-width: 1200px) {
    .tracking-wrapper { grid-template-columns: 14rem 1fr 20rem; }
}
@media (max-width: 960px) {
    .tracking-wrapper {
        grid-template-columns: 1fr;
        grid-template-rows: auto 400px auto;
        height: auto;
    }
}

/* ── Vehicle List Panel ── */
.vehicle-list-panel {
    display: flex; flex-direction: column; overflow: hidden;
}
.panel-msg {
    flex: 1; display: flex; align-items: center; justify-content: center;
    padding: 2rem; text-align: center; font-size: 0.85rem; color: var(--text-muted);
}
.vehicle-scroll {
    flex: 1; overflow-y: auto;
}
.vehicle-item {
    width: 100%; text-align: left; padding: 0.65rem 0.75rem;
    display: flex; align-items: center; gap: 0.6rem;
    border: none; border-bottom: 1px solid var(--border);
    background: none; cursor: pointer; transition: all 0.15s;
}
.vehicle-item:hover { background: #f1f5f9; }
.vehicle-item.active {
    background: #eff6ff; border-left: 3px solid #3b82f6;
}
.vehicle-item-icon {
    width: 32px; height: 32px; border-radius: 8px;
    background: #e0e7ff; color: #4f46e5;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.vehicle-item.active .vehicle-item-icon { background: #3b82f6; color: #fff; }
.vehicle-item-num { font-weight: 600; font-size: 0.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vehicle-item-route { font-size: 0.7rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vehicle-item-speed { text-align: right; flex-shrink: 0; }
.speed-val { font-size: 0.85rem; font-weight: 700; color: var(--accent); }
.speed-unit { font-size: 0.6rem; color: var(--text-muted); display: block; }

/* ── Map Panel ── */
.map-panel {
    display: flex; flex-direction: column; gap: 0.75rem; min-height: 0;
}
.map-wrapper {
    flex: 1; border-radius: var(--radius); overflow: hidden;
    border: 1px solid var(--border); position: relative; min-height: 300px;
}
.map-loading {
    position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,0.85); border-radius: var(--radius); z-index: 500;
}
.map-overlay-top {
    position: absolute; top: 0.75rem; left: 0.75rem; z-index: 500;
    display: flex; gap: 0.5rem; align-items: center;
}
.map-legend {
    background: rgba(255,255,255,0.95); backdrop-filter: blur(4px);
    border-radius: 8px; padding: 0.4rem 0.75rem;
    display: flex; gap: 0.75rem; font-size: 0.7rem; color: #475569;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}
.legend-item { display: flex; align-items: center; gap: 0.3rem; }
.legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

.center-bus-btn {
    position: absolute; bottom: 1rem; right: 1rem; z-index: 500;
    width: 36px; height: 36px; border-radius: 8px;
    background: #fff; border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    color: #475569; transition: all 0.15s;
}
.center-bus-btn:hover { background: #f1f5f9; color: #1d4ed8; }

/* ── ETA Bar ── */
.eta-bar {
    display: flex; gap: 0.5rem; flex-wrap: wrap;
}
.eta-card {
    flex: 1; min-width: 90px;
    background: var(--surface, #fff); border: 1px solid var(--border);
    border-radius: var(--radius, 8px); padding: 0.6rem 0.75rem;
    text-align: center;
}
.eta-main {
    background: #1d4ed8; border-color: #1d4ed8; color: #fff;
}
.eta-main .eta-label { color: #bfdbfe; }
.eta-value { font-size: 1.1rem; font-weight: 700; line-height: 1.2; }
.eta-label { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; margin-top: 0.15rem; }
.eta-next { flex: 1.5; text-align: left; }
.eta-next-name { font-size: 0.85rem; font-weight: 600; margin-top: 0.1rem; color: var(--text-primary); }

/* ── Info Panel (Right) ── */
.info-panel {
    display: flex; flex-direction: column; gap: 0.75rem; overflow-y: auto;
}

/* ── Bus Info Card ── */
.info-card { padding: 0; overflow: hidden; }
.info-card-header {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.85rem 1rem; background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: #fff;
}
.info-card-icon {
    width: 40px; height: 40px; border-radius: 10px;
    background: rgba(255,255,255,0.2);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.info-card-title { font-weight: 700; font-size: 0.95rem; }
.info-card-sub { font-size: 0.72rem; opacity: 0.8; }

.info-grid { padding: 0.5rem 1rem; }
.info-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.4rem 0; font-size: 0.8rem;
    border-bottom: 1px solid var(--border);
}
.info-row:last-child { border-bottom: none; }
.info-label { color: var(--text-muted); }
.info-val { font-weight: 600; }

.driver-section {
    display: flex; align-items: center; gap: 0.65rem;
    padding: 0.75rem 1rem; background: #f8fafc;
    border-top: 1px solid var(--border);
}
.driver-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: #e0e7ff; color: #4f46e5;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.driver-name { font-weight: 600; font-size: 0.85rem; }
.driver-role { font-size: 0.7rem; color: var(--text-muted); }

.call-btn {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.4rem 0.85rem; border-radius: 99px;
    background: #22c55e; color: #fff; border: none;
    font-size: 0.75rem; font-weight: 600; cursor: pointer;
    transition: background 0.15s;
}
.call-btn:hover { background: #16a34a; }

.conductor-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.5rem 1rem; font-size: 0.8rem;
    border-top: 1px solid var(--border);
}

/* ── Route Timeline ── */
.timeline-card {
    flex: 1; display: flex; flex-direction: column; overflow: hidden; padding: 0;
}
.timeline-scroll { flex: 1; overflow-y: auto; padding: 0.75rem 0; }

.timeline { padding: 0 0.75rem; }
.timeline-item {
    display: flex; gap: 0.75rem; min-height: 56px;
}

.timeline-line-wrapper {
    display: flex; flex-direction: column; align-items: center;
    width: 26px; flex-shrink: 0;
}
.timeline-line-top, .timeline-line-bottom {
    flex: 1; width: 2px; background: #e2e8f0;
}
.line-passed { background: #22c55e; }

.timeline-dot {
    width: 26px; height: 26px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 0.65rem; font-weight: 700;
    border: 2px solid #e2e8f0; background: #fff; color: #94a3b8;
    transition: all 0.3s;
}
.dot-passed {
    background: #22c55e; border-color: #22c55e; color: #fff;
}
.dot-current {
    background: #3b82f6; border-color: #3b82f6; color: #fff;
    animation: pulse-bus 1.5s infinite;
}
.dot-upcoming {
    background: #f8fafc; border-color: #e2e8f0; color: #94a3b8;
}
.dot-bus { font-size: 12px; line-height: 1; }
.dot-num { font-size: 0.6rem; }

.timeline-content {
    flex: 1; padding: 0.35rem 0; min-width: 0;
}
.timeline-stop-name {
    font-size: 0.82rem; font-weight: 600; color: var(--text-primary);
    display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;
}
.timeline-item.passed .timeline-stop-name { color: var(--text-muted); }
.timeline-item.current .timeline-stop-name { color: #1d4ed8; }

.timeline-badge {
    display: inline-flex; padding: 0.1rem 0.4rem;
    border-radius: 4px; font-size: 0.58rem; font-weight: 700;
    letter-spacing: 0.04em; text-transform: uppercase;
}
.school-badge { background: #fef2f2; color: #dc2626; }

.timeline-stop-meta {
    display: flex; gap: 0.65rem; margin-top: 0.2rem;
    font-size: 0.7rem; color: var(--text-muted);
}
.meta-time, .meta-students {
    display: inline-flex; align-items: center; gap: 0.2rem;
}

/* ── Bus Marker Global Styles ── */
:deep(.bus-marker) {
    width: 40px; height: 40px; border-radius: 50%;
    background: #1d4ed8; display: flex; align-items: center; justify-content: center;
    box-shadow: 0 0 0 4px rgba(29,78,216,0.25), 0 2px 8px rgba(0,0,0,0.2);
    border: 3px solid #fff;
    animation: pulse-bus 2s infinite;
    transition: all 0.3s;
}
:deep(.bus-emoji) { font-size: 18px; }
:deep(.bus-selected .bus-marker) {
    background: #dc2626;
    box-shadow: 0 0 0 4px rgba(220,38,38,0.3), 0 2px 12px rgba(220,38,38,0.3);
}
:deep(.stop-marker-icon) { transition: transform 0.3s; }
:deep(.stop-marker-icon:hover) { transform: scale(1.3); }
</style>
