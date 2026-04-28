<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';

const toast = useToast();
const confirm = useConfirm();

const SEND_INTERVAL = 10; // seconds

// ── State ──
const vehicles      = ref([]);
const loading       = ref(true);
const error         = ref(null);
const selectedId    = ref(null);
const tracking      = ref(false);
const lastCoords    = ref(null);
const sendCount     = ref(0);
const elapsed       = ref(0);
const lastError     = ref(null);
const search        = ref('');

let sendTimer = null;
let tickTimer = null;

// Return correct CSRF header for Laravel
function getCsrfHeaders() {
    const meta = document.querySelector('meta[name="csrf-token"]')?.content;
    if (meta) return { 'X-CSRF-TOKEN': meta };
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) } : {};
}

// ── Computed ──
const selected = computed(() => vehicles.value.find(v => v.id === selectedId.value) || null);

const filteredVehicles = computed(() => {
    const q = search.value.toLowerCase().trim();
    if (!q) return vehicles.value;
    return vehicles.value.filter(v =>
        (v.vehicle_number || '').toLowerCase().includes(q) ||
        (v.vehicle_name || '').toLowerCase().includes(q) ||
        (v.route_name || '').toLowerCase().includes(q) ||
        (v.driver_name || '').toLowerCase().includes(q)
    );
});

const elapsedDisplay = computed(() => {
    const s = elapsed.value;
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    const pad = n => String(n).padStart(2, '0');
    if (h > 0) return `${h}:${pad(m)}:${pad(sec)}`;
    return `${pad(m)}:${pad(sec)}`;
});

const speedDisplay = computed(() => {
    if (!lastCoords.value) return '--';
    return `${Math.round(lastCoords.value.speed)} km/h`;
});

const activeCount = computed(() => vehicles.value.filter(v => v.is_live).length);

// ── Fetch vehicles ──
async function fetchVehicles() {
    try {
        loading.value = true;
        error.value = null;
        const res = await fetch('/school/transport/driver-tracking/status', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error('Failed to load vehicles');
        const data = await res.json();
        vehicles.value = data.vehicles || [];
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

// ── Geolocation ──
function getPosition() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Geolocation not supported by this browser'));
            return;
        }
        navigator.geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 5000,
        });
    });
}

async function sendLocation() {
    if (!selectedId.value) return;
    try {
        const pos = await getPosition();
        const { latitude, longitude, speed, heading } = pos.coords;

        const coords = {
            vehicle_id: selectedId.value,
            latitude,
            longitude,
            speed: speed != null && speed >= 0 ? Math.round(speed * 3.6) : 0,
            heading: heading != null && heading >= 0 ? Math.round(heading) : null,
        };
        lastCoords.value = coords;

        const res = await fetch('/school/transport/driver-tracking/update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...getCsrfHeaders(), Accept: 'application/json' },
            body: JSON.stringify(coords),
        });

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            throw new Error(data.error || 'Failed to send location');
        }
        sendCount.value++;
        lastError.value = null;
    } catch (e) {
        lastError.value = e.message;
    }
}

// ── Start / Stop ──
function selectVehicle(id) {
    if (tracking.value) return;
    selectedId.value = selectedId.value === id ? null : id;
}

async function startTracking() {
    if (!selectedId.value) return;

    try {
        await getPosition();
    } catch (e) {
        if (e.code === 1) {
            toast.error('Location permission is required. Please allow location access in your browser settings.');
        } else {
            toast.error('Could not get your location: ' + e.message);
        }
        return;
    }

    tracking.value = true;
    sendCount.value = 0;
    elapsed.value = 0;
    lastError.value = null;

    await sendLocation();
    sendTimer = setInterval(sendLocation, SEND_INTERVAL * 1000);
    tickTimer = setInterval(() => { elapsed.value++; }, 1000);
}

async function stopTracking() {
    if (sendTimer) clearInterval(sendTimer);
    if (tickTimer) clearInterval(tickTimer);
    sendTimer = null;
    tickTimer = null;
    tracking.value = false;

    try {
        await fetch('/school/transport/driver-tracking/stop', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...getCsrfHeaders(), Accept: 'application/json' },
            body: JSON.stringify({ vehicle_id: selectedId.value }),
        });
    } catch (e) { /* best effort */ }

    fetchVehicles();
}

async function toggleTracking() {
    if (tracking.value) {
        const ok = await confirm({
            title: 'Stop tracking?',
            message: 'Stop sharing your location?',
            confirmLabel: 'Stop',
            danger: true,
        });
        if (!ok) return;
        stopTracking();
    } else {
        startTracking();
    }
}

// ── Lifecycle ──
onMounted(fetchVehicles);
onUnmounted(() => {
    if (sendTimer) clearInterval(sendTimer);
    if (tickTimer) clearInterval(tickTimer);
});
</script>

<template>
    <SchoolLayout title="Driver Tracking">

        <!-- Header -->
        <PageHeader title="Driver Tracking">
            <template #subtitle>
                <p class="page-header-sub">Select a vehicle and start live GPS tracking
                    <span v-if="activeCount" class="ml-2 text-green">{{ activeCount }} active</span></p>
            </template>
            <template #actions>
                <span v-if="tracking" class="live-indicator">
                    <span class="live-dot"></span>
                    LIVE
                </span>
                <span v-if="vehicles.length" class="badge badge-muted">{{ vehicles.length }} Vehicles</span>

            </template>
        </PageHeader>

        <!-- Loading -->
        <div v-if="loading" class="card" style="padding:5rem;text-align:center;">
            <div class="spinner"></div>
            <p style="margin-top:1rem;color:var(--text-muted);font-size:0.875rem;">Loading vehicles…</p>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="card" style="padding:4rem;text-align:center;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="1.5" style="margin:0 auto 1rem;">
                <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
            </svg>
            <p style="color:var(--text-muted);margin-bottom:1rem;">{{ error }}</p>
            <Button variant="secondary" size="sm" @click="fetchVehicles">Retry</Button>
        </div>

        <!-- No vehicles -->
        <div v-else-if="!vehicles.length" class="card" style="padding:5rem;text-align:center;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5" style="margin:0 auto 1rem;">
                <rect x="3" y="4" width="18" height="12" rx="2"/><path d="M3 12h18"/><circle cx="7" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/>
            </svg>
            <p style="font-size:1rem;font-weight:600;color:var(--text-primary);">No Vehicles Found</p>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-top:0.25rem;">Add vehicles in the Transport &rarr; Vehicles section first.</p>
        </div>

        <!-- Main -->
        <div v-else class="dt-layout">

            <!-- LEFT: Vehicle List -->
            <div class="card vehicle-list-panel">
                <div class="card-header" style="border-bottom:1px solid var(--border);">
                    <span class="card-title">Select Vehicle</span>
                </div>

                <div class="search-box">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input v-model="search" type="text" placeholder="Search vehicles…" class="search-input" />
                </div>

                <div class="vehicle-scroll">
                    <button
                        v-for="v in filteredVehicles"
                        :key="v.id"
                        @click="selectVehicle(v.id)"
                        :class="['vehicle-item', { active: v.id === selectedId, disabled: tracking && v.id !== selectedId }]"
                    >
                        <div class="vehicle-item-icon" :class="{ 'icon-live': v.is_live }">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="12" rx="2"/><path d="M3 12h18"/><circle cx="7" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/><path d="M5.5 19h13"/>
                            </svg>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="vehicle-item-num">{{ v.vehicle_number }}</div>
                            <div class="vehicle-item-name" v-if="v.vehicle_name">{{ v.vehicle_name }}</div>
                            <div class="vehicle-item-route">{{ v.route_name || 'No Route' }}</div>
                        </div>
                        <div class="vehicle-item-right">
                            <span v-if="v.is_live" class="status-badge live">LIVE</span>
                            <span v-else class="status-badge offline">Offline</span>
                        </div>
                    </button>

                    <div v-if="!filteredVehicles.length" class="panel-msg">
                        No vehicles match "{{ search }}"
                    </div>
                </div>
            </div>

            <!-- RIGHT: Tracking Panel -->
            <div class="right-panel">

                <!-- No selection -->
                <div v-if="!selected" class="card" style="padding:4rem;text-align:center;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5" style="margin:0 auto 1rem;">
                        <path d="M3 11l19-9-9 19-2-8-8-2z"/>
                    </svg>
                    <p style="font-size:1rem;font-weight:600;color:var(--text-primary);">Select a Vehicle</p>
                    <p style="color:var(--text-muted);font-size:0.85rem;margin-top:0.25rem;">Choose a vehicle from the list to start GPS tracking.</p>
                </div>

                <template v-else>
                    <!-- Vehicle info card -->
                    <div class="card vehicle-info">
                        <div class="vehicle-info-inner">
                            <div class="vehicle-info-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="12" rx="2"/><path d="M3 12h18"/><circle cx="7" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/>
                                </svg>
                            </div>
                            <div style="flex:1;">
                                <div class="vi-title">{{ selected.vehicle_name || selected.vehicle_number }}</div>
                                <div class="vi-sub">{{ selected.vehicle_number }}</div>
                            </div>
                            <span v-if="selected.route_code" class="badge badge-blue">{{ selected.route_code }}</span>
                        </div>
                        <div class="vi-details">
                            <div class="vi-row">
                                <span class="vi-label">Route</span>
                                <span class="vi-val">{{ selected.route_name || 'None' }}</span>
                            </div>
                            <div class="vi-row">
                                <span class="vi-label">Driver</span>
                                <span class="vi-val">{{ selected.driver_name }}</span>
                            </div>
                            <div class="vi-row" v-if="selected.capacity">
                                <span class="vi-label">Capacity</span>
                                <span class="vi-val">{{ selected.capacity }} seats</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tracking control card -->
                    <div class="card tracking-card" :class="{ 'tracking-active': tracking }">
                        <div class="tracking-status-area">
                            <div class="pulse-container">
                                <div v-if="tracking" class="pulse-ring"></div>
                                <div class="status-orb" :class="tracking ? 'orb-active' : 'orb-inactive'">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                        <path d="M3 11l19-9-9 19-2-8-8-2z" :opacity="tracking ? 1 : 0.7"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="tracking-label" :class="{ active: tracking }">
                                {{ tracking ? 'Tracking Active' : 'Ready to Track' }}
                            </div>
                            <p class="tracking-sublabel">
                                {{ tracking
                                    ? `Sharing location for ${selected.vehicle_number} every ${SEND_INTERVAL}s`
                                    : `Start tracking to share live GPS for ${selected.vehicle_number}` }}
                            </p>
                        </div>

                        <!-- Stats -->
                        <div v-if="tracking" class="stats-grid">
                            <div class="stat-box">
                                <div class="stat-value">{{ elapsedDisplay }}</div>
                                <div class="stat-label">Duration</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value">{{ sendCount }}</div>
                                <div class="stat-label">Updates Sent</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value">{{ speedDisplay }}</div>
                                <div class="stat-label">Speed</div>
                            </div>
                        </div>

                        <!-- Coordinates -->
                        <div v-if="tracking && lastCoords" class="coords-row">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>
                            </svg>
                            <span class="coords-text">{{ lastCoords.latitude.toFixed(5) }}, {{ lastCoords.longitude.toFixed(5) }}</span>
                        </div>

                        <!-- Error -->
                        <div v-if="lastError" class="send-error">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                            </svg>
                            {{ lastError }}
                        </div>

                        <!-- Toggle button -->
                        <button
                            class="track-btn"
                            :class="tracking ? 'track-btn-stop' : 'track-btn-start'"
                            @click="toggleTracking"
                        >
                            <svg v-if="!tracking" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <polygon points="5 3 19 12 5 21 5 3"/>
                            </svg>
                            <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/>
                            </svg>
                            {{ tracking ? 'Stop Tracking' : 'Start Tracking' }}
                        </button>

                        <p class="info-note">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px;">
                                <circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/>
                            </svg>
                            Location updates are sent every {{ SEND_INTERVAL }}s. Keep this tab open for continuous tracking. Parents will see this vehicle on the Live Tracking map.
                        </p>
                    </div>
                </template>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
.page-header-title { font-size: 1.25rem; font-weight: 700; color: var(--text-primary); }
.page-header-sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem; }
.text-green { color: #16a34a; font-weight: 600; }
.ml-2 { margin-left: 0.5rem; }

.live-indicator {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: #fef2f2; color: #ef4444; font-size: 0.7rem; font-weight: 800;
    padding: 0.3rem 0.75rem; border-radius: 9999px; letter-spacing: 0.05em;
}
.live-dot {
    width: 7px; height: 7px; border-radius: 50%; background: #ef4444;
    animation: blink 1.2s infinite;
}
@keyframes blink { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }

.dt-layout {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 1.25rem;
    align-items: start;
}
@media (max-width: 900px) {
    .dt-layout { grid-template-columns: 1fr; }
}

.vehicle-list-panel { overflow: hidden; }

.search-box {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.6rem 1rem;
    border-bottom: 1px solid var(--border);
}
.search-input {
    flex: 1; border: none; outline: none; font-size: 0.85rem;
    color: var(--text-primary); background: transparent;
}
.search-input::placeholder { color: var(--text-muted); }

.vehicle-scroll { max-height: 520px; overflow-y: auto; }

.vehicle-item {
    display: flex; align-items: center; gap: 0.75rem; width: 100%;
    padding: 0.75rem 1rem;
    border: none; border-bottom: 1px solid var(--border);
    background: #fff; cursor: pointer; text-align: left;
    transition: background 0.15s;
}
.vehicle-item:hover { background: #f9fafb; }
.vehicle-item.active { background: #eff6ff; border-left: 3px solid #3b82f6; }
.vehicle-item.disabled { opacity: 0.4; pointer-events: none; }

.vehicle-item-icon {
    width: 36px; height: 36px; border-radius: 8px;
    background: #f3f4f6; color: #6b7280;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.vehicle-item-icon.icon-live { background: #f0fdf4; color: #16a34a; }

.vehicle-item-num { font-size: 0.85rem; font-weight: 700; color: var(--text-primary); }
.vehicle-item-name { font-size: 0.75rem; color: var(--text-secondary); }
.vehicle-item-route { font-size: 0.7rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.vehicle-item-right { flex-shrink: 0; }

.status-badge {
    display: inline-block; padding: 0.15rem 0.45rem; border-radius: 9999px;
    font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;
}
.status-badge.live { background: #dcfce7; color: #16a34a; }
.status-badge.offline { background: #f3f4f6; color: #9ca3af; }

.panel-msg { padding: 2rem; text-align: center; font-size: 0.85rem; color: var(--text-muted); }

.right-panel { display: flex; flex-direction: column; gap: 1.25rem; }

.vehicle-info { overflow: hidden; }
.vehicle-info-inner {
    display: flex; align-items: center; gap: 1rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
}
.vehicle-info-icon {
    width: 44px; height: 44px; border-radius: 10px;
    background: #f0fdf4; color: #16a34a;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.vi-title { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.vi-sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.1rem; }
.vi-details { padding: 0.75rem 1.25rem; }
.vi-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.35rem 0; font-size: 0.85rem;
}
.vi-label { color: var(--text-muted); }
.vi-val { color: var(--text-primary); font-weight: 600; }

.tracking-card {
    padding: 2rem; text-align: center;
    transition: border-color 0.3s, box-shadow 0.3s;
}
.tracking-card.tracking-active {
    border: 1.5px solid rgba(34, 197, 94, 0.35);
    box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.08);
}

.tracking-status-area { margin-bottom: 1.5rem; }
.pulse-container {
    width: 76px; height: 76px; margin: 0 auto 1rem;
    position: relative; display: flex; align-items: center; justify-content: center;
}
.pulse-ring {
    position: absolute; inset: 0; border-radius: 50%;
    background: rgba(34, 197, 94, 0.25);
    animation: pulse-grow 1.6s ease-out infinite;
}
@keyframes pulse-grow {
    0% { transform: scale(1); opacity: 0.5; }
    100% { transform: scale(1.5); opacity: 0; }
}
.status-orb {
    width: 64px; height: 64px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    position: relative; z-index: 1; transition: background 0.3s;
}
.orb-active { background: #22c55e; }
.orb-inactive { background: #d1d5db; }

.tracking-label { font-size: 1.1rem; font-weight: 800; color: var(--text-muted); }
.tracking-label.active { color: #16a34a; }
.tracking-sublabel { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem; line-height: 1.4; max-width: 380px; margin-inline: auto; }

.stats-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem;
    margin-bottom: 1rem;
    background: var(--surface, #f9fafb); border-radius: 10px; padding: 1rem;
}
.stat-box { text-align: center; }
.stat-value { font-size: 1.1rem; font-weight: 800; color: var(--text-primary); }
.stat-label { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; margin-top: 0.15rem; }

.coords-row {
    display: inline-flex; align-items: center; gap: 0.35rem;
    font-size: 0.75rem; color: var(--text-muted); margin-bottom: 1rem;
}
.coords-text { font-family: 'SF Mono', 'Fira Code', monospace; }

.send-error {
    display: inline-flex; align-items: center; gap: 0.35rem;
    font-size: 0.75rem; color: #ef4444;
    background: #fef2f2; padding: 0.35rem 0.75rem; border-radius: 6px;
    margin-bottom: 1rem;
}

.track-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
    width: 100%; max-width: 320px; margin: 0 auto;
    padding: 0.85rem 2rem; border-radius: 12px;
    font-size: 0.95rem; font-weight: 800; color: #fff;
    border: none; cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;
}
.track-btn:hover { transform: translateY(-1px); }
.track-btn:active { transform: translateY(0); }
.track-btn-start { background: #22c55e; box-shadow: 0 4px 14px rgba(34,197,94,0.3); }
.track-btn-start:hover { background: #16a34a; }
.track-btn-stop { background: #ef4444; box-shadow: 0 4px 14px rgba(239,68,68,0.3); }
.track-btn-stop:hover { background: #dc2626; }

.info-note {
    display: flex; align-items: flex-start; gap: 0.4rem; justify-content: center;
    font-size: 0.75rem; color: var(--text-muted); margin-top: 1.25rem; line-height: 1.4;
}

.card { background: #fff; border: 1px solid var(--border); border-radius: 12px; }
.card-header { padding: 0.75rem 1.25rem; }
.card-title { font-size: 0.8rem; font-weight: 700; color: var(--text-primary); text-transform: uppercase; letter-spacing: 0.04em; }
.badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; }
.badge-blue { background: #eff6ff; color: #3b82f6; }
.badge-muted { background: #f3f4f6; color: #6b7280; }
.spinner {
    width: 32px; height: 32px; margin: 0 auto;
    border: 3px solid var(--border); border-top-color: var(--primary);
    border-radius: 50%; animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
