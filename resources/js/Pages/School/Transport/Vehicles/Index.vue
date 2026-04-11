<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    vehicles: Array,
    routes:   Array,
    drivers:  Array,
});

const { can } = usePermissions();

const showModal   = ref(false);
const editingItem = ref(null);
const saving      = ref(false);

const form = reactive({
    vehicle_number: '', vehicle_name: '', driver_id: '', conductor_name: '',
    capacity: '', route_id: '', gps_device_id: '',
    insurance_expiry: '', fitness_expiry: '', pollution_expiry: '', status: 'active',
});

function openModal(vehicle = null) {
    editingItem.value = vehicle;
    if (vehicle) {
        Object.assign(form, {
            vehicle_number:   vehicle.vehicle_number,
            vehicle_name:     vehicle.vehicle_name    || '',
            driver_id:        vehicle.driver_id       || '',
            conductor_name:   vehicle.conductor_name  || '',
            capacity:         vehicle.capacity,
            route_id:         vehicle.route_id        || '',
            gps_device_id:    vehicle.gps_device_id   || '',
            insurance_expiry: vehicle.insurance_expiry || '',
            fitness_expiry:   vehicle.fitness_expiry   || '',
            pollution_expiry: vehicle.pollution_expiry  || '',
            status:           vehicle.status,
        });
    } else {
        Object.assign(form, {
            vehicle_number: '', vehicle_name: '', driver_id: '', conductor_name: '',
            capacity: '', route_id: '', gps_device_id: '',
            insurance_expiry: '', fitness_expiry: '', pollution_expiry: '', status: 'active',
        });
    }
    showModal.value = true;
}

function save() {
    saving.value = true;
    const url    = editingItem.value ? `/school/transport/vehicles/${editingItem.value.id}` : '/school/transport/vehicles';
    const method = editingItem.value ? 'put' : 'post';
    router[method](url, { ...form }, {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false; },
        onFinish:  () => { saving.value = false; },
    });
}

function destroy(vehicle) {
    if (!confirm(`Delete vehicle "${vehicle.vehicle_number}"?`)) return;
    router.delete(`/school/transport/vehicles/${vehicle.id}`, { preserveScroll: true });
}

const statusBadgeClass = (s) => ({
    active:      'badge-green',
    inactive:    'badge-gray',
    maintenance: 'badge-amber',
})[s] || 'badge-gray';

const isExpiringSoon = (date) => {
    if (!date) return false;
    const d = new Date(date);
    const diff = (d - new Date()) / (1000 * 60 * 60 * 24);
    return diff >= 0 && diff <= 30;
};
const isExpired = (date) => date && new Date(date) < new Date();

function expiryClass(date) {
    if (isExpired(date)) return 'expiry-expired';
    if (isExpiringSoon(date)) return 'expiry-soon';
    return '';
}
</script>

<template>
    <SchoolLayout title="Transport Vehicles">

        <div class="page-header">
            <div>
                <h1 class="page-header-title">Vehicles</h1>
                <p class="page-header-sub">Manage school buses and drivers.</p>
            </div>
            <Button v-if="can('create_transport_vehicles')" @click="openModal()">+ Add Vehicle</Button>
        </div>

        <!-- Stats Bar -->
        <div class="stats-grid">
            <div v-for="(s, label) in {
                'Total': vehicles.length,
                'Active': vehicles.filter(v=>v.status==='active').length,
                'Maintenance': vehicles.filter(v=>v.status==='maintenance').length,
                'With GPS': vehicles.filter(v=>v.gps_device_id).length,
            }" :key="label" class="stat-card">
                <p class="stat-value">{{ s }}</p>
                <p class="stat-label">{{ label }}</p>
            </div>
        </div>

        <!-- Vehicles Table -->
        <div class="card">
            <div style="overflow-x: auto;">
                <Table v-if="vehicles.length">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th>Route</th>
                            <th style="text-align: center;">Capacity</th>
                            <th style="text-align: center;">GPS</th>
                            <th style="text-align: center;">Expiries</th>
                            <th style="text-align: center;">Status</th>
                            <th v-if="can('edit_transport_vehicles') || can('delete_transport_vehicles')" style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="v in vehicles" :key="v.id">
                            <td>
                                <div style="font-weight: 600;">{{ v.vehicle_number }}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ v.vehicle_name || '—' }}</div>
                            </td>
                            <td>
                                <div>{{ v.driver?.user?.name || '—' }}</div>
                                <div v-if="v.conductor_name" style="font-size: 0.75rem; color: var(--text-muted);">Cond: {{ v.conductor_name }}</div>
                            </td>
                            <td>{{ v.route?.route_name || '—' }}</td>
                            <td style="text-align: center;">{{ v.capacity }}</td>
                            <td style="text-align: center;">
                                <span v-if="v.gps_device_id" class="badge badge-green" style="font-family: monospace;">{{ v.gps_device_id }}</span>
                                <span v-else style="font-size: 0.75rem; color: var(--text-muted);">No GPS</span>
                            </td>
                            <td style="text-align: center;">
                                <div class="expiry-stack">
                                    <div :class="expiryClass(v.insurance_expiry)">Ins: {{ v.insurance_expiry || '—' }}</div>
                                    <div :class="expiryClass(v.fitness_expiry)">Fit: {{ v.fitness_expiry || '—' }}</div>
                                    <div :class="expiryClass(v.pollution_expiry)">Pol: {{ v.pollution_expiry || '—' }}</div>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge" :class="statusBadgeClass(v.status)" style="text-transform: capitalize;">{{ v.status }}</span>
                            </td>
                            <td v-if="can('edit_transport_vehicles') || can('delete_transport_vehicles')" style="text-align: right;">
                                <Button variant="secondary" size="xs" v-if="can('edit_transport_vehicles')" @click="openModal(v)" class="mr-1">Edit</Button>
                                <Button variant="danger" size="xs" v-if="can('delete_transport_vehicles')" @click="destroy(v)">Delete</Button>
                            </td>
                        </tr>
                    </tbody>
                </Table>
                <div v-else style="text-align: center; padding: 4rem; color: var(--text-muted);">
                    No vehicles yet. Add your first bus.
                </div>
            </div>
        </div>

        <!-- Vehicle Modal -->
        <Teleport to="body">
        <div v-if="showModal" class="modal-backdrop" @mousedown.self="showModal = false">
            <div class="modal">
                <div class="card-header" style="position: sticky; top: 0; background: var(--surface); z-index: 10;">
                    <h3 class="card-title">{{ editingItem ? 'Edit Vehicle' : 'Add Vehicle' }}</h3>
                    <button @click="showModal = false" class="modal-close">&times;</button>
                </div>
                <div class="card-body">
                    <form @submit.prevent="save">
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Vehicle Number *</label>
                                <input v-model="form.vehicle_number" type="text" required placeholder="KA05 AB1234" style="font-family: monospace;">
                            </div>
                            <div class="form-field">
                                <label>Vehicle Name</label>
                                <input v-model="form.vehicle_name" type="text" placeholder="Bus 1 / Bus A">
                            </div>
                        </div>
                        <div class="form-row-2" style="margin-top: 1rem;">
                            <div class="form-field">
                                <label>Driver (Staff)</label>
                                <select v-model="form.driver_id">
                                    <option value="">-- Select Driver --</option>
                                    <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.user?.name || 'Staff #' + d.id }}</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Conductor Name</label>
                                <input v-model="form.conductor_name" type="text">
                            </div>
                        </div>
                        <div class="form-row-3" style="margin-top: 1rem;">
                            <div class="form-field">
                                <label>Seating Capacity *</label>
                                <input v-model="form.capacity" type="number" required min="1">
                            </div>
                            <div class="form-field">
                                <label>Assigned Route</label>
                                <select v-model="form.route_id">
                                    <option value="">-- None --</option>
                                    <option v-for="r in routes" :key="r.id" :value="r.id">{{ r.route_name }}</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>GPS Device ID</label>
                                <input v-model="form.gps_device_id" type="text" placeholder="BUS001" style="font-family: monospace;">
                            </div>
                        </div>

                        <div class="cert-section">
                            <p class="section-heading" style="margin-bottom: 0.75rem;">Certificate Expiry Dates</p>
                            <div class="form-row-3">
                                <div class="form-field">
                                    <label>Insurance</label>
                                    <input v-model="form.insurance_expiry" type="date">
                                </div>
                                <div class="form-field">
                                    <label>Fitness</label>
                                    <input v-model="form.fitness_expiry" type="date">
                                </div>
                                <div class="form-field">
                                    <label>Pollution</label>
                                    <input v-model="form.pollution_expiry" type="date">
                                </div>
                            </div>
                        </div>

                        <div class="form-row" style="margin-top: 1rem;">
                            <div class="form-field">
                                <label>Status</label>
                                <select v-model="form.status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="maintenance">Maintenance</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                            <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                            <Button type="submit" :loading="saving">
                                {{ (editingItem ? 'Update' : 'Add Vehicle') }}
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
.stats-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;
}
@media (max-width: 640px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
.stat-card {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
    padding: 1rem; text-align: center;
}
.stat-value { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); }
.stat-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; }

.expiry-stack { font-size: 0.75rem; color: var(--text-muted); }
.expiry-stack > div + div { margin-top: 0.125rem; }
.expiry-expired { color: var(--danger); font-weight: 600; }
.expiry-soon { color: var(--warning); font-weight: 600; }

.cert-section {
    margin-top: 1rem; padding: 1rem; background: var(--bg);
    border: 1px solid var(--border); border-radius: var(--radius);
}

.modal-backdrop {
    position: fixed; inset: 0; background: rgba(15,23,42,.5);
    display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;
}
.modal {
    background: var(--surface); border-radius: 0.75rem; width: 100%; max-width: 40rem;
    max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-close {
    background: none; border: none; font-size: 1.5rem; color: var(--text-muted);
    cursor: pointer; line-height: 1;
}
.modal-close:hover { color: var(--text-primary); }
</style>
