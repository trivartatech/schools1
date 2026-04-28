<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, reactive, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    rooms:   Object,
    hostels: Array,
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermissions();

const showModal = ref(false);
const editing  = ref(null);
const loading  = ref(false);
const errors   = ref({});

const filterForm = reactive({ hostel_id: props.filters?.hostel_id || '' });

function applyFilter() {
    router.get('/school/hostel/rooms', filterForm, { preserveState: true, replace: true });
}

const form = reactive({
    hostel_id: '', block_name: '', floor_name: '', room_number: '', capacity: 1, room_type: '', cost_per_month: 0, status: 'Available'
});

const availableRoomTypes = computed(() => {
    if (!props.hostels) return ['Standard', 'AC'];
    let hostel = props.hostels.find(h => h.id === form.hostel_id);
    if (hostel && hostel.room_types && hostel.room_types.length > 0) return hostel.room_types;
    return ['Standard', 'AC', 'Non-AC', 'Deluxe', 'Dormitory']; // Fallback
});

const availableBlocks = computed(() => {
    if (!props.hostels) return ['Block A', 'Block B'];
    let hostel = props.hostels.find(h => h.id === form.hostel_id);
    if (hostel && hostel.blocks && hostel.blocks.length > 0) return hostel.blocks;
    return ['Block A', 'Block B', 'Main Block']; // Fallback
});

const availableFloors = computed(() => {
    if (!props.hostels) return ['Ground Floor', '1st Floor'];
    let hostel = props.hostels.find(h => h.id === form.hostel_id);
    if (hostel && hostel.floors && hostel.floors.length > 0) return hostel.floors;
    return ['Ground Floor', '1st Floor', '2nd Floor']; // Fallback
});

function openModal(item = null) {
    editing.value = item;
    errors.value  = {};
    if (item) {
        Object.assign(form, {
            hostel_id: item.hostel_id, block_name: item.block_name || '', floor_name: item.floor_name || '',
            room_number: item.room_number, capacity: item.capacity, room_type: item.room_type || '',
            cost_per_month: item.cost_per_month, status: item.status
        });
    } else {
        Object.assign(form, {
            hostel_id: props.hostels[0]?.id || '', block_name: '', floor_name: '',
            room_number: '', capacity: 1, room_type: '', cost_per_month: 0, status: 'Available'
        });
    }
    showModal.value = true;
}

function save() {
    loading.value = true;
    errors.value  = {};
    if (editing.value) {
        router.put(`/school/hostel/rooms/${editing.value.id}`, form, {
            onSuccess: () => { showModal.value = false; },
            onError:   (e) => { errors.value = e; },
            onFinish:  () => { loading.value = false; },
        });
    } else {
        router.post(`/school/hostel/rooms`, form, {
            onSuccess: () => { showModal.value = false; },
            onError:   (e) => { errors.value = e; },
            onFinish:  () => { loading.value = false; },
        });
    }
}

function destroy(item) {
    if(confirm('Delete this room and its beds?')) {
        router.delete(`/school/hostel/rooms/${item.id}`);
    }
}
</script>

<template>
    <SchoolLayout title="Rooms & Beds">

        <div class="page-header">
            <div>
                <h1 class="page-header-title">Rooms &amp; Beds Management</h1>
                <p class="page-header-sub">Configure rooms, beds, and occupancy across hostels.</p>
            </div>
            <Button v-if="can('create_hostel')" @click="openModal()">+ Add Room</Button>
        </div>

        <!-- Filter bar -->
        <div class="card filter-bar">
            <div class="card-body filter-bar-body">
                <div class="form-field" style="margin:0;min-width:180px;">
                    <label>Hostel</label>
                    <select v-model="filterForm.hostel_id" @change="applyFilter">
                        <option value="">All Hostels</option>
                        <option v-for="h in hostels" :key="h.id" :value="h.id">{{ h.name }}</option>
                    </select>
                </div>
                <Button variant="secondary" size="sm" v-if="filterForm.hostel_id" @click="filterForm.hostel_id = ''; applyFilter()">Clear</Button>
            </div>
        </div>

        <div class="card">
            <div style="overflow-x: auto;">
                <Table>
                    <thead>
                        <tr>
                            <th>Room No</th>
                            <th>Hostel / Block / Floor</th>
                            <th>Occupancy</th>
                            <th>Fee / Month</th>
                            <th>Status</th>
                            <th v-if="can('edit_hostel') || can('delete_hostel')" style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rooms.data" :key="r.id">
                            <td style="font-weight: 500;">
                                {{ r.room_number }}
                                <span class="badge badge-gray" style="margin-left: 0.25rem;">{{ r.room_type || 'General' }}</span>
                            </td>
                            <td>{{ r.hostel.name }} / {{ r.block_name || '-' }} / {{ r.floor_name || '-' }}</td>
                            <td>{{ r.beds.filter(b => b.status === "Occupied").length }} / {{ r.capacity }}</td>
                            <td style="font-weight: 600;">₹{{ r.cost_per_month }}</td>
                            <td>
                                <span class="badge" :class="r.status === 'Available' ? 'badge-green' : 'badge-red'">{{ r.status }}</span>
                            </td>
                            <td v-if="can('edit_hostel') || can('delete_hostel')" style="text-align: right;">
                                <Button variant="secondary" size="xs" v-if="can('edit_hostel')" @click="openModal(r)" class="mr-1.5">Edit</Button>
                                <Button variant="danger" size="xs" v-if="can('delete_hostel')" @click="destroy(r)">Delete</Button>
                            </td>
                        </tr>
                        <tr v-if="!rooms.data.length">
                            <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">No rooms configured.</td>
                        </tr>
                    </tbody>
                </Table>
            </div>

            <!-- Pagination -->
            <div v-if="rooms.last_page > 1"
                 style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-top:1px solid var(--border);font-size:0.82rem;color:var(--text-muted);">
                <span>Showing {{ rooms.from }}–{{ rooms.to }} of {{ rooms.total }}</span>
                <div style="display:flex;gap:4px;">
                    <Button v-for="link in rooms.links" :key="link.label"
                            as="link"
                            size="xs"
                            :href="link.url || '#'"
                            :variant="link.active ? 'primary' : 'secondary'"
                            :disabled="!link.url"
                            :class="!link.url ? 'opacity-40 pointer-events-none' : ''"
                            v-html="link.label" preserve-scroll />
                </div>
            </div>
        </div>

        <!-- MODAL -->
        <Teleport to="body">
        <div v-if="showModal" class="modal-backdrop" @mousedown.self="showModal = false">
            <div class="modal">
                <div class="card-header">
                    <h3 class="card-title">{{ editing ? 'Edit' : 'Create' }} Room</h3>
                </div>
                <div class="card-body">
                    <form @submit.prevent="save">

                        <!-- Server errors -->
                        <div v-if="Object.keys(errors).length"
                             style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;font-size:0.82rem;color:#dc2626;margin-bottom:14px;">
                            <div v-for="(msg, field) in errors" :key="field">{{ msg }}</div>
                        </div>

                        <div class="form-row">
                            <div class="form-field">
                                <label>Hostel *</label>
                                <select v-model="form.hostel_id" required>
                                    <option v-for="h in hostels" :key="h.id" :value="h.id">{{ h.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row-2" style="margin-top: 1rem;">
                            <div class="form-field">
                                <label>Block Name</label>
                                <select v-model="form.block_name">
                                    <option value="">Select Block</option>
                                    <option v-for="b in availableBlocks" :key="b" :value="b">{{ b }}</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Floor Name</label>
                                <select v-model="form.floor_name">
                                    <option value="">Select Floor</option>
                                    <option v-for="f in availableFloors" :key="f" :value="f">{{ f }}</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Room Number *</label>
                                <input v-model="form.room_number" required>
                            </div>
                            <div class="form-field">
                                <label>Capacity (Beds) *</label>
                                <input v-model="form.capacity" type="number" required min="1">
                            </div>
                            <div class="form-field">
                                <label>Room Type</label>
                                <select v-model="form.room_type">
                                    <option value="">Select Room Type</option>
                                    <option v-for="rt in availableRoomTypes" :key="rt" :value="rt">{{ rt }}</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Monthly Fee (₹) *</label>
                                <input v-model="form.cost_per_month" type="number" required>
                            </div>
                        </div>
                        <div class="form-row" style="margin-top: 1rem;">
                            <div class="form-field">
                                <label>Status</label>
                                <select v-model="form.status" required>
                                    <option>Available</option>
                                    <option>Full</option>
                                    <option>Maintenance</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                            <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                            <Button type="submit" :loading="loading">Save</Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </Teleport>

    </SchoolLayout>
</template>

<style scoped>
.filter-bar { margin-bottom: 1rem; }
.filter-bar-body { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end; padding: 0.75rem 1rem; }
.modal-backdrop {
    position: fixed; inset: 0; background: rgba(15,23,42,.5);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.modal {
    background: #fff; border-radius: 0.75rem; width: 100%; max-width: 32rem;
    max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
</style>





