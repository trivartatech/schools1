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
import { useSchoolStore } from '@/stores/useSchoolStore';

const confirm = useConfirm();
const school = useSchoolStore();

const props = defineProps({
    vehicles:   Array,
    routes:     Array,
    drivers:    Array,
    conductors: Array,
});

const { can } = usePermissions();

const showModal   = ref(false);
const editingItem = ref(null);
const saving      = ref(false);

const form = reactive({
    vehicle_number: '', vehicle_name: '', driver_id: '', conductor_id: '', conductor_name: '',
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
            conductor_id:     vehicle.conductor_id    || '',
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
            vehicle_number: '', vehicle_name: '', driver_id: '', conductor_id: '', conductor_name: '',
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

async function destroy(vehicle) {
    const ok = await confirm({
        title: 'Delete vehicle?',
        message: `"${vehicle.vehicle_number}" will be permanently removed.`,
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
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

const statCards = computed(() => [
    { label: 'Total',       value: props.vehicles.length,                                          color: 'accent' },
    { label: 'Active',      value: props.vehicles.filter(v => v.status === 'active').length,      color: 'success' },
    { label: 'Maintenance', value: props.vehicles.filter(v => v.status === 'maintenance').length, color: 'warning' },
    { label: 'With GPS',    value: props.vehicles.filter(v => v.gps_device_id).length,            color: 'info' },
]);
</script>

<template>
    <SchoolLayout title="Transport Vehicles">

        <PageHeader title="Vehicles" subtitle="Manage school buses and drivers.">
            <template #actions>
                <Button v-if="can('create_transport_vehicles')" @click="openModal()">+ Add Vehicle</Button>
            </template>
        </PageHeader>

        <!-- Stats Bar -->
        <StatsRow :cols="4" :stats="statCards" />

        <!-- Vehicles Table -->
        <div class="card">
            <Table :empty="!vehicles.length">
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
                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                <span v-if="v.conductor?.user?.name">Cond: {{ v.conductor.user.name }}</span>
                                <span v-else-if="v.conductor_name">Cond: {{ v.conductor_name }}</span>
                            </div>
                        </td>
                        <td>{{ v.route?.route_name || '—' }}</td>
                        <td style="text-align: center;">{{ v.capacity }}</td>
                        <td style="text-align: center;">
                            <span v-if="v.gps_device_id" class="badge badge-green" style="font-family: monospace;">{{ v.gps_device_id }}</span>
                            <span v-else style="font-size: 0.75rem; color: var(--text-muted);">No GPS</span>
                        </td>
                        <td style="text-align: center;">
                            <div class="expiry-stack">
                                <div :class="expiryClass(v.insurance_expiry)">Ins: {{ v.insurance_expiry ? school.fmtDate(v.insurance_expiry) : '—' }}</div>
                                <div :class="expiryClass(v.fitness_expiry)">Fit: {{ v.fitness_expiry ? school.fmtDate(v.fitness_expiry) : '—' }}</div>
                                <div :class="expiryClass(v.pollution_expiry)">Pol: {{ v.pollution_expiry ? school.fmtDate(v.pollution_expiry) : '—' }}</div>
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
                <template #empty>
                    <EmptyState
                        title="No vehicles yet"
                        description="Add your first bus to start tracking drivers, GPS, and certificate expiries."
                        :action-label="can('create_transport_vehicles') ? '+ Add Vehicle' : ''"
                        @action="openModal()"
                    />
                </template>
            </Table>
        </div>

        <!-- Vehicle Modal -->
        <Modal v-model:open="showModal" :title="editingItem ? 'Edit Vehicle' : 'Add Vehicle'" size="lg">
            <form @submit.prevent="save" id="vehicle-form">
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
                        <label>Conductor (Staff)</label>
                        <select v-model="form.conductor_id">
                            <option value="">-- Select Conductor --</option>
                            <option v-for="c in conductors" :key="c.id" :value="c.id">{{ c.user?.name || 'Staff #' + c.id }}</option>
                        </select>
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
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                <Button type="submit" form="vehicle-form" :loading="saving">
                    {{ editingItem ? 'Update' : 'Add Vehicle' }}
                </Button>
            </template>
        </Modal>
    </SchoolLayout>
</template>

<style scoped>
.expiry-stack { font-size: 0.75rem; color: var(--text-muted); }
.expiry-stack > div + div { margin-top: 0.125rem; }
.expiry-expired { color: var(--danger); font-weight: 600; }
.expiry-soon { color: var(--warning); font-weight: 600; }

.cert-section {
    margin-top: 1rem; padding: 1rem; background: var(--bg);
    border: 1px solid var(--border); border-radius: var(--radius);
}
.section-heading {
    font-size: 0.72rem; font-weight: 700; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.05em;
}
</style>
