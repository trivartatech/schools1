<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import SlidePanel from '@/Components/SlidePanel.vue';
import { useDelete } from '@/Composables/useDelete';
import Table from '@/Components/ui/Table.vue';

const props = defineProps(['academicYears']);
const panelOpen = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({ name: '', start_date: '', end_date: '', is_current: false, status: 'active' });

const openCreate = () => { isEditing.value = false; form.reset(); panelOpen.value = true; };
const openEdit = (year) => {
    isEditing.value = true; editingId.value = year.id;
    form.name = year.name;
    form.start_date = year.start_date ? year.start_date.split('T')[0] : '';
    form.end_date = year.end_date ? year.end_date.split('T')[0] : '';
    form.is_current = !!year.is_current;
    form.status = year.status;
    panelOpen.value = true;
};
const closePanel = () => { panelOpen.value = false; form.reset(); };
const submit = () => {
    if (isEditing.value) {
        form.transform((data) => ({ ...data, _method: 'put' }))
            .post(`/school/academic-years/${editingId.value}`, { 
                onSuccess: () => closePanel(), 
                onError: (e) => form.setError(e) 
            });
    } else {
        form.transform((data) => data) // reset transform
            .post('/school/academic-years', { onSuccess: () => closePanel() });
    }
};
const { del } = useDelete();
const destroy = (id) => del(`/school/academic-years/${id}`, 'Delete this academic year?');
</script>

<template>
    <SchoolLayout title="Academic Years">
        <div class="page-header">
            <div>
                <h2 class="page-header-title">Academic Years &amp; Sessions</h2>
                <p class="page-header-sub">Configure and manage academic year sessions for your school.</p>
            </div>
            <Button variant="success" @click="openCreate">+ New Academic Year</Button>
        </div>

        <div class="card">
            <Table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="year in academicYears" :key="year.id">
                        <td>
                            <span style="font-weight:500;color:var(--text-primary);">{{ year.name }}</span>
                            <span v-if="year.is_current" class="badge badge-green" style="margin-left:0.5rem;">Current</span>
                        </td>
                        <td style="color:var(--text-secondary);">{{ year.start_date ? new Date(year.start_date).toLocaleDateString() : '—' }}</td>
                        <td style="color:var(--text-secondary);">{{ year.end_date ? new Date(year.end_date).toLocaleDateString() : '—' }}</td>
                        <td>
                            <span :class="year.status === 'active' ? 'badge badge-blue' : 'badge badge-gray'" style="text-transform:capitalize;">{{ year.status }}</span>
                        </td>
                        <td style="text-align:right;">
                            <Button variant="secondary" size="sm" @click="openEdit(year)">Edit</Button>
                            <Button variant="danger" size="sm" @click="destroy(year.id)" class="ml-2">Delete</Button>
                        </td>
                    </tr>
                    <tr v-if="academicYears.length === 0">
                        <td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted);">No academic years configured.</td>
                    </tr>
                </tbody>
            </Table>
        </div>

        <SlidePanel :open="panelOpen" :title="isEditing ? 'Edit Academic Year' : 'New Academic Year'" @close="closePanel">
            <form @submit.prevent="submit">
                <div class="form-field">
                    <label>Name (e.g., 2025-26) <span style="color:var(--danger);">*</span></label>
                    <input v-model="form.name" type="text" required />
                    <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                </div>
                <div class="form-field">
                    <label>Start Date <span style="color:var(--danger);">*</span></label>
                    <input v-model="form.start_date" type="date" required />
                </div>
                <div class="form-field">
                    <label>End Date <span style="color:var(--danger);">*</span></label>
                    <input v-model="form.end_date" type="date" required />
                </div>
                <div class="form-field">
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input v-model="form.is_current" type="checkbox" />
                        <span>Set as Current Academic Year</span>
                    </label>
                </div>
                <div v-if="isEditing" class="form-field">
                    <label>Status</label>
                    <select v-model="form.status">
                        <option value="active">Active</option>
                        <option value="frozen">Frozen (Read-Only Archive)</option>
                    </select>
                </div>
                <div style="display:flex;gap:0.75rem;padding-top:0.5rem;">
                    <Button variant="success" type="submit" :loading="form.processing" class="flex-1">
                        {{ isEditing ? 'Save Changes' : 'Create Year' }}
                    </Button>
                    <Button variant="secondary" type="button" @click="closePanel">Cancel</Button>
                </div>
            </form>
        </SlidePanel>
    </SchoolLayout>
</template>
