<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Table from '@/Components/ui/Table.vue';
import { ref, reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useConfirm } from '@/Composables/useConfirm';

const confirm = useConfirm();

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

async function deleteRoute(route) {
    const ok = await confirm({
        title: 'Delete route?',
        message: `"${route.route_name}" and all associated stops will be deleted.`,
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
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

async function deleteStop(stop) {
    const ok = await confirm({
        title: 'Delete stop?',
        message: `"${stop.stop_name}" will be removed from this route.`,
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/transport/stops/${stop.id}`, {
        preserveScroll: true,
        onSuccess: () => viewStops(selectedRoute.value),
    });
}

const statCards = computed(() => [
    { label: 'Total Routes',  value: props.routes.length,                                          color: 'accent' },
    { label: 'Active Routes', value: props.routes.filter(r => r.status === 'active').length,       color: 'success' },
    { label: 'Total Stops',   value: props.routes.reduce((s, r) => s + (r.stops_count || 0), 0),   color: 'warning' },
]);
</script>

<template>
    <SchoolLayout title="Transport Routes">

        <PageHeader title="Transport Routes" subtitle="Manage routes and their stops">
            <template #actions>
                <Button v-if="can('create_transport_routes')" @click="openRouteModal()">+ New Route</Button>
            </template>
        </PageHeader>

        <!-- Stats Bar -->
        <StatsRow :cols="3" :stats="statCards" />

        <!-- Routes Table -->
        <div class="card mb-5">
            <div class="card-header">
                <span class="card-title">All Routes</span>
            </div>
            <Table :empty="!routes.length">
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
                                <Button variant="secondary" size="xs" @click="viewStops(route)" title="View Stops">View Stops</Button>
                                <Button variant="secondary" size="xs" v-if="can('edit_transport_routes')" @click="openRouteModal(route)" title="Edit">Edit</Button>
                                <Button variant="danger" size="xs" v-if="can('delete_transport_routes')" @click="deleteRoute(route)" title="Delete">Delete</Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <template #empty>
                    <EmptyState
                        title="No routes yet"
                        description="Create your first transport route to start scheduling stops and assignments."
                        :action-label="can('create_transport_routes') ? '+ New Route' : ''"
                        @action="openRouteModal()"
                    />
                </template>
            </Table>
        </div>

        <!-- Stops Side Panel -->
        <div v-if="showStopPanel && selectedRoute" class="card mb-5">
            <div class="card-header" style="justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <span class="card-title">Stops — {{ selectedRoute.route_name }}</span>
                    <span class="badge badge-blue">{{ stops.length }} stops</span>
                </div>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <Button size="sm" v-if="can('create_transport_routes')" @click="openStopModal()">+ Add Stop</Button>
                    <Button variant="secondary" size="sm" @click="showStopPanel = false">Close</Button>
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div v-if="loadingStops" style="padding:2rem;text-align:center;color:#9ca3af;font-size:0.875rem;">Loading stops...</div>
                <Table v-else :empty="!stops.length">
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
                                    <Button variant="secondary" size="xs" v-if="can('edit_transport_routes')" @click="openStopModal(stop)">Edit</Button>
                                    <Button variant="danger" size="xs" v-if="can('delete_transport_routes')" @click="deleteStop(stop)">Delete</Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <template #empty>
                        <EmptyState
                            variant="compact"
                            title="No stops yet"
                            description="Add the first stop for this route."
                        />
                    </template>
                </Table>
            </div>
        </div>

        <!-- Route Modal -->
        <Modal v-model:open="showRouteModal" :title="editingRoute ? 'Edit Route' : 'New Route'" size="md">
            <form @submit.prevent="saveRoute" id="route-form">
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
                <div class="form-row-2" style="margin-top:14px;">
                    <div class="form-field">
                        <label>Start Location</label>
                        <input v-model="routeForm.start_location" type="text" placeholder="School / Origin">
                    </div>
                    <div class="form-field">
                        <label>End Location</label>
                        <input v-model="routeForm.end_location" type="text" placeholder="Final destination">
                    </div>
                </div>
                <div class="form-row-3" style="margin-top:14px;">
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
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showRouteModal = false">Cancel</Button>
                <Button type="submit" form="route-form" :loading="saving">
                    {{ editingRoute ? 'Update Route' : 'Create Route' }}
                </Button>
            </template>
        </Modal>

        <!-- Stop Modal -->
        <Modal v-model:open="showStopModal" :title="editingStop ? 'Edit Stop' : 'Add Stop'" size="md">
            <form @submit.prevent="saveStop" id="stop-form">
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
                <div class="form-row-2" style="margin-top:14px;">
                    <div class="form-field">
                        <label>Pickup Time</label>
                        <input v-model="stopForm.pickup_time" type="time">
                    </div>
                    <div class="form-field">
                        <label>Drop Time</label>
                        <input v-model="stopForm.drop_time" type="time">
                    </div>
                </div>
                <div class="form-row-3" style="margin-top:14px;">
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
                <div class="form-row-2" style="margin-top:14px;">
                    <div class="form-field">
                        <label>Latitude</label>
                        <input v-model="stopForm.latitude" type="number" step="any" placeholder="12.9716">
                    </div>
                    <div class="form-field">
                        <label>Longitude</label>
                        <input v-model="stopForm.longitude" type="number" step="any" placeholder="77.5946">
                    </div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showStopModal = false">Cancel</Button>
                <Button type="submit" form="stop-form" :loading="savingStop">
                    {{ editingStop ? 'Update Stop' : 'Add Stop' }}
                </Button>
            </template>
        </Modal>
    </SchoolLayout>
</template>

<style scoped>
.mb-5 { margin-bottom: 1.25rem; }
</style>
