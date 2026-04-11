<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, reactive, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    routes: Array,
});

const { can } = usePermissions();

// ─── Route Modal ────────────────────────────────────────────────────────────
const showRouteModal = ref(false);
const editingRoute   = ref(null);
const saving         = ref(false);

const routeForm = reactive({
    route_name: '', route_code: '', start_location: '',
    end_location: '', distance: '', estimated_time: '', status: 'active',
});

function openRouteModal(route = null) {
    editingRoute.value = route;
    if (route) {
        Object.assign(routeForm, {
            route_name: route.route_name, route_code: route.route_code,
            start_location: route.start_location || '', end_location: route.end_location || '',
            distance: route.distance || '', estimated_time: route.estimated_time || '',
            status: route.status,
        });
    } else {
        Object.assign(routeForm, { route_name: '', route_code: '', start_location: '',
            end_location: '', distance: '', estimated_time: '', status: 'active' });
    }
    showRouteModal.value = true;
}

function saveRoute() {
    saving.value = true;
    const url  = editingRoute.value
        ? `/school/transport/routes/${editingRoute.value.id}`
        : '/school/transport/routes';
    const method = editingRoute.value ? 'put' : 'post';
    router[method](url, { ...routeForm }, {
        preserveScroll: true,
        onSuccess: () => { showRouteModal.value = false; },
        onFinish:  () => { saving.value = false; },
    });
}

function deleteRoute(route) {
    if (!confirm(`Delete route "${route.route_name}"? All associated stops will also be deleted.`)) return;
    router.delete(`/school/transport/routes/${route.id}`, { preserveScroll: true });
}

// ─── Stop Panel ─────────────────────────────────────────────────────────────
const selectedRoute = ref(null);
const stops         = ref([]);
const loadingStops  = ref(false);
const showStopPanel = ref(false);
const showStopModal = ref(false);
const editingStop   = ref(null);
const savingStop    = ref(false);

const stopForm = reactive({
    route_id: '', stop_name: '', stop_code: '', pickup_time: '',
    drop_time: '', distance_from_school: '', fee: '', stop_order: 0,
    latitude: '', longitude: '',
});

async function viewStops(route) {
    selectedRoute.value = route;
    showStopPanel.value = true;
    loadingStops.value  = true;
    try {
        const res = await fetch(`/school/transport/routes/${route.id}/stops`);
        stops.value = await res.json();
    } finally {
        loadingStops.value = false;
    }
}

function openStopModal(stop = null) {
    editingStop.value = stop;
    if (stop) {
        Object.assign(stopForm, {
            route_id: stop.route_id, stop_name: stop.stop_name, stop_code: stop.stop_code || '',
            pickup_time: stop.pickup_time || '', drop_time: stop.drop_time || '',
            distance_from_school: stop.distance_from_school || '', fee: stop.fee || '',
            stop_order: stop.stop_order || 0, latitude: stop.latitude || '', longitude: stop.longitude || '',
        });
    } else {
        Object.assign(stopForm, {
            route_id: selectedRoute.value?.id || '', stop_name: '', stop_code: '',
            pickup_time: '', drop_time: '', distance_from_school: '', fee: '',
            stop_order: stops.value.length, latitude: '', longitude: '',
        });
    }
    showStopModal.value = true;
}

function saveStop() {
    savingStop.value = true;
    const url    = editingStop.value
        ? `/school/transport/stops/${editingStop.value.id}`
        : '/school/transport/stops';
    const method = editingStop.value ? 'put' : 'post';
    router[method](url, { ...stopForm }, {
        preserveScroll: true,
        onSuccess: () => { showStopModal.value = false; viewStops(selectedRoute.value); },
        onFinish:  () => { savingStop.value = false; },
    });
}

function deleteStop(stop) {
    if (!confirm(`Delete stop "${stop.stop_name}"?`)) return;
    router.delete(`/school/transport/stops/${stop.id}`, {
        preserveScroll: true,
        onSuccess: () => viewStops(selectedRoute.value),
    });
}


</script>

<template>
    <SchoolLayout title="Transport Routes">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Transport Routes</h1>
                <p class="page-header-sub">Manage routes and their stops</p>
            </div>
            <Button v-if="can('create_transport_routes')" @click="openRouteModal()">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Route
            </Button>
        </div>

        <!-- Stats Bar -->
        <div class="stats-grid">
            <div class="card">
                <div class="card-body" style="display:flex;align-items:center;gap:0.75rem;">
                    <div style="width:2.5rem;height:2.5rem;border-radius:0.5rem;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;">
                        <svg class="w-5 h-5" style="color:var(--accent)" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    </div>
                    <div><p style="font-size:0.75rem;color:#6b7280;">Total Routes</p><p style="font-size:1.25rem;font-weight:700;color:#111827;">{{ routes.length }}</p></div>
                </div>
            </div>
            <div class="card">
                <div class="card-body" style="display:flex;align-items:center;gap:0.75rem;">
                    <div style="width:2.5rem;height:2.5rem;border-radius:0.5rem;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;">
                        <svg class="w-5 h-5" style="color:var(--success)" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div><p style="font-size:0.75rem;color:#6b7280;">Active Routes</p><p style="font-size:1.25rem;font-weight:700;color:#111827;">{{ routes.filter(r => r.status === 'active').length }}</p></div>
                </div>
            </div>
            <div class="card">
                <div class="card-body" style="display:flex;align-items:center;gap:0.75rem;">
                    <div style="width:2.5rem;height:2.5rem;border-radius:0.5rem;background:rgba(245,158,11,0.1);display:flex;align-items:center;justify-content:center;">
                        <svg class="w-5 h-5" style="color:var(--warning)" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                    </div>
                    <div><p style="font-size:0.75rem;color:#6b7280;">Total Stops</p><p style="font-size:1.25rem;font-weight:700;color:#111827;">{{ routes.reduce((s,r) => s + (r.stops_count || 0), 0) }}</p></div>
                </div>
            </div>
        </div>

        <!-- Routes Table -->
        <div class="card mb-5">
            <div class="card-header">
                <span class="card-title">All Routes</span>
            </div>
            <div class="card-body" style="padding:0;">
                <Table v-if="routes.length">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Route Name</th>
                            <th>From → To</th>
                            <th style="text-align:center;">Distance</th>
                            <th style="text-align:center;">Stops</th>
                            <th style="text-align:center;">Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="route in routes" :key="route.id">
                            <td style="font-family:monospace;color:var(--accent);font-weight:600;">{{ route.route_code }}</td>
                            <td>
                                <span style="font-weight:600;color:#111827;">{{ route.route_name }}</span>
                                <p style="font-size:0.75rem;color:#9ca3af;margin-top:2px;" v-if="route.estimated_time">~{{ route.estimated_time }}</p>
                            </td>
                            <td>
                                <span v-if="route.start_location || route.end_location">
                                    {{ route.start_location || '—' }} → {{ route.end_location || '—' }}
                                </span>
                                <span v-else style="color:#d1d5db;">—</span>
                            </td>
                            <td style="text-align:center;">{{ route.distance ? route.distance + ' km' : '—' }}</td>
                            <td style="text-align:center;">
                                <button @click="viewStops(route)" class="badge badge-blue" style="cursor:pointer;border:none;">
                                    {{ route.stops_count || 0 }} stops
                                </button>
                            </td>
                            <td style="text-align:center;">
                                <span :class="route.status === 'active' ? 'badge badge-green' : 'badge badge-gray'" style="text-transform:capitalize;">
                                    {{ route.status }}
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.5rem;">
                                    <Button variant="secondary" size="xs" @click="viewStops(route)" title="View Stops">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                    </Button>
                                    <Button variant="secondary" size="xs" v-if="can('edit_transport_routes')" @click="openRouteModal(route)" title="Edit">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </Button>
                                    <Button variant="danger" size="xs" v-if="can('delete_transport_routes')" @click="deleteRoute(route)" title="Delete">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </Table>
                <div v-else style="text-align:center;padding:4rem 0;color:#9ca3af;">
                    <svg class="w-12 h-12" style="margin:0 auto 0.75rem;color:#e5e7eb;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    <p style="font-size:0.875rem;">No routes yet. Create your first route.</p>
                </div>
            </div>
        </div>

        <!-- Stops Side Panel -->
        <div v-if="showStopPanel && selectedRoute" class="card mb-5">
            <div class="card-header" style="justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <svg class="w-4 h-4" style="color:var(--accent)" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                    <span class="card-title">Stops — {{ selectedRoute.route_name }}</span>
                    <span class="badge badge-blue">{{ stops.length }} stops</span>
                </div>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <Button size="sm" v-if="can('create_transport_routes')" @click="openStopModal()">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Stop
                    </Button>
                    <Button variant="secondary" size="sm" @click="showStopPanel = false">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </Button>
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div v-if="loadingStops" style="padding:2rem;text-align:center;color:#9ca3af;font-size:0.875rem;">Loading stops...</div>
                <Table v-else-if="stops.length">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Stop Name</th>
                            <th style="text-align:center;">Pickup</th>
                            <th style="text-align:center;">Drop</th>
                            <th style="text-align:center;">Distance</th>
                            <th style="text-align:center;">Fee (₹)</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="stop in stops" :key="stop.id">
                            <td style="text-align:center;color:#9ca3af;">{{ stop.stop_order + 1 }}</td>
                            <td>
                                <span style="font-weight:500;color:#111827;">{{ stop.stop_name }}</span>
                                <span v-if="stop.stop_code" style="margin-left:0.5rem;font-size:0.75rem;color:#9ca3af;font-family:monospace;">{{ stop.stop_code }}</span>
                            </td>
                            <td style="text-align:center;">{{ stop.pickup_time || '—' }}</td>
                            <td style="text-align:center;">{{ stop.drop_time || '—' }}</td>
                            <td style="text-align:center;">{{ stop.distance_from_school ? stop.distance_from_school + ' km' : '—' }}</td>
                            <td style="text-align:center;font-weight:600;color:var(--success);">{{ stop.fee > 0 ? '₹' + stop.fee : '—' }}</td>
                            <td style="text-align:right;">
                                <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.25rem;">
                                    <Button variant="secondary" size="xs" v-if="can('edit_transport_routes')" @click="openStopModal(stop)">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </Button>
                                    <Button variant="danger" size="xs" v-if="can('delete_transport_routes')" @click="deleteStop(stop)">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </Table>
                <div v-else style="padding:2rem;text-align:center;color:#9ca3af;font-size:0.875rem;">No stops yet. Add the first stop for this route.</div>
            </div>
        </div>

        <!-- Route Modal -->
        <Teleport to="body">
        <div v-if="showRouteModal" class="modal-backdrop" @mousedown.self="showRouteModal = false">
            <div class="modal">
                <div class="card-header" style="justify-content:space-between;">
                    <span class="card-title">{{ editingRoute ? 'Edit Route' : 'New Route' }}</span>
                    <Button variant="secondary" size="xs" @click="showRouteModal = false">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </Button>
                </div>
                <div class="card-body">
                    <form @submit.prevent="saveRoute">
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Route Name *</label>
                                <input v-model="routeForm.route_name" type="text" required placeholder="e.g. Whitefield Route">
                            </div>
                            <div class="form-field">
                                <label>Route Code *</label>
                                <input v-model="routeForm.route_code" type="text" required placeholder="e.g. R001" style="font-family:monospace;">
                            </div>
                        </div>
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Start Location</label>
                                <input v-model="routeForm.start_location" type="text" placeholder="School / Origin">
                            </div>
                            <div class="form-field">
                                <label>End Location</label>
                                <input v-model="routeForm.end_location" type="text" placeholder="Final destination">
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-field">
                                <label>Distance (km)</label>
                                <input v-model="routeForm.distance" type="number" step="0.1" min="0">
                            </div>
                            <div class="form-field">
                                <label>Est. Time</label>
                                <input v-model="routeForm.estimated_time" type="text" placeholder="e.g. 45 mins">
                            </div>
                            <div class="form-field">
                                <label>Status</label>
                                <select v-model="routeForm.status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:0.75rem;padding-top:0.5rem;">
                            <Button variant="secondary" type="button" @click="showRouteModal = false">Cancel</Button>
                            <Button type="submit" :loading="saving">
                                {{ (editingRoute ? 'Update Route' : 'Create Route') }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- Stop Modal -->
        <Teleport to="body">
        <div v-if="showStopModal" class="modal-backdrop" @mousedown.self="showStopModal = false">
            <div class="modal">
                <div class="card-header" style="justify-content:space-between;">
                    <span class="card-title">{{ editingStop ? 'Edit Stop' : 'Add Stop' }}</span>
                    <Button variant="secondary" size="xs" @click="showStopModal = false">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </Button>
                </div>
                <div class="card-body">
                    <form @submit.prevent="saveStop">
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Stop Name *</label>
                                <input v-model="stopForm.stop_name" type="text" required placeholder="e.g. ITPL Junction">
                            </div>
                            <div class="form-field">
                                <label>Stop Code</label>
                                <input v-model="stopForm.stop_code" type="text" placeholder="e.g. S01" style="font-family:monospace;">
                            </div>
                        </div>
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Pickup Time</label>
                                <input v-model="stopForm.pickup_time" type="time">
                            </div>
                            <div class="form-field">
                                <label>Drop Time</label>
                                <input v-model="stopForm.drop_time" type="time">
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-field">
                                <label>Distance (km)</label>
                                <input v-model="stopForm.distance_from_school" type="number" step="0.1" min="0">
                            </div>
                            <div class="form-field">
                                <label>Transport Fee (₹)</label>
                                <input v-model="stopForm.fee" type="number" step="0.01" min="0">
                            </div>
                            <div class="form-field">
                                <label>Order</label>
                                <input v-model="stopForm.stop_order" type="number" min="0">
                            </div>
                        </div>
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Latitude</label>
                                <input v-model="stopForm.latitude" type="number" step="any" placeholder="12.9716">
                            </div>
                            <div class="form-field">
                                <label>Longitude</label>
                                <input v-model="stopForm.longitude" type="number" step="any" placeholder="77.5946">
                            </div>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:0.75rem;padding-top:0.5rem;">
                            <Button variant="secondary" type="button" @click="showStopModal = false">Cancel</Button>
                            <Button type="submit" :loading="savingStop">
                                {{ (editingStop ? 'Update Stop' : 'Add Stop') }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </Teleport>
    </SchoolLayout>
</template>

<style scoped>
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.25rem; }
@media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }
.modal-backdrop {
    position: fixed; inset: 0; background: rgba(15,23,42,.5);
    display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;
}
.modal {
    background: var(--surface); border-radius: 0.75rem; width: 100%; max-width: 32rem;
    max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
</style>
