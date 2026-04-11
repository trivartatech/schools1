<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    hostels: Array,
    users: Array,
});

const showModal = ref(false);
const editing  = ref(null);
const loading  = ref(false);
const errors   = ref({});

const form = reactive({
    name: '', type: 'Boys', warden_id: '', intake_capacity: '', address: '', description: '', blocks: '', floors: '', room_types: ''
});

function openModal(item = null) {
    editing.value = item;
    errors.value  = {};
    if (item) {
        Object.assign(form, {
            name: item.name, type: item.type, warden_id: item.warden_id || '',
            intake_capacity: item.intake_capacity || '', address: item.address || '', description: item.description || '',
            blocks: item.blocks ? item.blocks.join(', ') : '',
            floors: item.floors ? item.floors.join(', ') : '',
            room_types: item.room_types ? item.room_types.join(', ') : ''
        });
    } else {
        Object.assign(form, { name: '', type: 'Boys', warden_id: '', intake_capacity: '', address: '', description: '', blocks: '', floors: '', room_types: '' });
    }
    showModal.value = true;
}

function save() {
    loading.value = true;
    let payload = { ...form };
    payload.blocks = form.blocks ? form.blocks.split(',').map(s => s.trim()).filter(Boolean) : [];
    payload.floors = form.floors ? form.floors.split(',').map(s => s.trim()).filter(Boolean) : [];
    payload.room_types = form.room_types ? form.room_types.split(',').map(s => s.trim()).filter(Boolean) : [];

    if (editing.value) {
        router.put(`/school/hostel/hostels/${editing.value.id}`, payload, {
            onSuccess: () => { showModal.value = false; },
            onError:   (e) => { errors.value = e; },
            onFinish:  () => { loading.value = false; },
        });
    } else {
        router.post(`/school/hostel/hostels`, payload, {
            onSuccess: () => { showModal.value = false; },
            onError:   (e) => { errors.value = e; },
            onFinish:  () => { loading.value = false; },
        });
    }
}

function destroy(item) {
    if(confirm('Delete this hostel?')) {
        router.delete(`/school/hostel/hostels/${item.id}`);
    }
}
</script>

<template>
    <SchoolLayout title="Hostels">

        <div class="page-header">
            <div>
                <h1 class="page-header-title">Hostel Buildings</h1>
                <p class="page-header-sub">Manage hostel facilities, wardens, and capacity.</p>
            </div>
            <Button @click="openModal()">+ New Hostel</Button>
        </div>

        <div class="card">
            <div style="overflow-x: auto;">
                <Table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Warden</th>
                            <th>Capacity</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="h in hostels" :key="h.id">
                            <td style="font-weight: 500;">{{ h.name }}</td>
                            <td>
                                <span class="badge"
                                      :class="{
                                        'badge-blue': h.type === 'Boys',
                                        'badge-purple': h.type === 'Girls',
                                        'badge-blue': h.type === 'Co-ed'
                                      }">
                                    {{ h.type }}
                                </span>
                            </td>
                            <td>{{ h.warden?.name || 'Unassigned' }}</td>
                            <td>{{ h.intake_capacity }}</td>
                            <td style="text-align: right;">
                                <Button variant="secondary" size="xs" @click="openModal(h)" class="mr-1.5">Edit</Button>
                                <Button variant="danger" size="xs" @click="destroy(h)">Delete</Button>
                            </td>
                        </tr>
                        <tr v-if="!hostels.length">
                            <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">No hostels found.</td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <!-- MODAL -->
        <Teleport to="body">
        <div v-if="showModal" class="modal-backdrop" @mousedown.self="showModal = false">
            <div class="modal">
                <div class="card-header">
                    <h3 class="card-title">{{ editing ? 'Edit' : 'Create' }} Hostel</h3>
                </div>
                <div class="card-body">
                    <form @submit.prevent="save">
                        <div v-if="Object.keys(errors).length"
                             style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;font-size:0.82rem;color:#dc2626;margin-bottom:14px;">
                            <div v-for="(msg, field) in errors" :key="field">{{ msg }}</div>
                        </div>
                        <div class="form-row">
                            <div class="form-field">
                                <label>Name</label>
                                <input v-model="form.name" required>
                            </div>
                        </div>
                        <div class="form-row-2" style="margin-top: 1rem;">
                            <div class="form-field">
                                <label>Type</label>
                                <select v-model="form.type" required>
                                    <option>Boys</option>
                                    <option>Girls</option>
                                    <option>Co-ed</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Warden</label>
                                <select v-model="form.warden_id">
                                    <option value="">None</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Capacity</label>
                                <input v-model="form.intake_capacity" type="number">
                            </div>
                        </div>
                        <div class="form-row" style="margin-top: 1rem;">
                            <div class="form-field">
                                <label>Address</label>
                                <textarea v-model="form.address"></textarea>
                            </div>
                        </div>
                        <div style="margin-top: 1rem; padding: 1rem; background: var(--surface-muted); border: 1px solid var(--border); border-radius: 0.5rem;">
                            <p class="section-heading" style="margin-bottom: 0.75rem;">Room Configuration Defaults</p>
                            <div class="form-row">
                                <div class="form-field">
                                    <label>Available Blocks (comma separated)</label>
                                    <input v-model="form.blocks" placeholder="e.g. Block A, Block B">
                                </div>
                            </div>
                            <div class="form-row" style="margin-top: 0.75rem;">
                                <div class="form-field">
                                    <label>Available Floors (comma separated)</label>
                                    <input v-model="form.floors" placeholder="e.g. Ground Floor, 1st Floor">
                                </div>
                            </div>
                            <div class="form-row" style="margin-top: 0.75rem;">
                                <div class="form-field">
                                    <label>Available Room Types (comma separated)</label>
                                    <input v-model="form.room_types" placeholder="e.g. AC, Non-AC, Deluxe">
                                </div>
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
.modal-backdrop {
    position: fixed; inset: 0; background: rgba(15,23,42,.5);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.modal {
    background: #fff; border-radius: 0.75rem; width: 100%; max-width: 32rem;
    max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
</style>





