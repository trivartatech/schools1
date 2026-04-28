<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Table from '@/Components/ui/Table.vue';
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useConfirm } from '@/Composables/useConfirm';

const confirm = useConfirm();

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

async function destroy(item) {
    const ok = await confirm({
        title: 'Delete hostel?',
        message: `"${item.name}" will be permanently removed.`,
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/hostel/hostels/${item.id}`);
}
</script>

<template>
    <SchoolLayout title="Hostels">

        <PageHeader title="Hostel Buildings" subtitle="Manage hostel facilities, wardens, and capacity.">
            <template #actions>
                <Button @click="openModal()">+ New Hostel</Button>
            </template>
        </PageHeader>

        <div class="card">
            <Table :empty="!hostels.length">
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
                </tbody>
                <template #empty>
                    <EmptyState
                        title="No hostels yet"
                        description="Add your first hostel building to get started."
                        action-label="+ New Hostel"
                        @action="openModal()"
                    />
                </template>
            </Table>
        </div>

        <!-- MODAL -->
        <Modal v-model:open="showModal" :title="editing ? 'Edit Hostel' : 'Create Hostel'" size="md">
            <form @submit.prevent="save" id="hostel-form">
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
                <div class="form-row-3" style="margin-top: 1rem;">
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
                <div style="margin-top: 1rem; padding: 1rem; background: var(--bg); border: 1px solid var(--border); border-radius: 0.5rem;">
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
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                <Button type="submit" form="hostel-form" :loading="loading">Save</Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
/* Form layout — Tailwind preflight workaround. Scoped here so styles
   only affect this page's <Modal> contents (data-v travels with teleport). */
.form-row { display: flex; }
.form-row > .form-field { flex: 1; }
.form-row-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}
.form-field { display: flex; flex-direction: column; gap: 0.35rem; }
.form-field label {
    font-size: 0.78rem; font-weight: 600; color: #374151;
}
.form-field input,
.form-field select,
.form-field textarea {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    background: #fff;
    color: #111827;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.form-field textarea { min-height: 80px; resize: vertical; }
.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.section-heading {
    font-size: 0.72rem; font-weight: 700; color: #6b7280;
    text-transform: uppercase; letter-spacing: 0.05em;
}
</style>
