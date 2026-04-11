<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import SlidePanel from '@/Components/SlidePanel.vue';
import { useDelete } from '@/Composables/useDelete';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    designations: Array
});

const form = useForm({
    name: '',
    parent_id: ''
});

const isEditing = ref(false);
const editingId = ref(null);
const editForm = useForm({
    name: '',
    parent_id: ''
});

const panelOpen = ref(false);

const openCreate = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    panelOpen.value = true;
};

const openEdit = (d) => {
    isEditing.value = true;
    editingId.value = d.id;
    editForm.name = d.name;
    editForm.parent_id = d.parent_id ?? '';
    editForm.clearErrors();
    panelOpen.value = true;
};

const closePanel = () => {
    panelOpen.value = false;
};

const submit = () => {
    form.post('/school/designations', {
        preserveScroll: true,
        onSuccess: () => { form.reset(); closePanel(); },
    });
};

const startEdit = (d) => {
    isEditing.value = true;
    editingId.value = d.id;
    editForm.name = d.name;
    editForm.parent_id = d.parent_id ?? '';
    editForm.clearErrors();
};

const cancelEdit = () => {
    isEditing.value = false;
    editingId.value = null;
    editForm.reset();
};

const submitEdit = () => {
    editForm.put(`/school/designations/${editingId.value}`, {
        preserveScroll: true,
        onSuccess: () => { cancelEdit(); closePanel(); },
    });
};

const { del } = useDelete();

const toggleStatus = (id) => {
    router.patch(`/school/designations/${id}/toggle`, {}, { preserveScroll: true });
};

const deleteDesignation = (id, name) => {
    del(`/school/designations/${id}`, `Delete designation "${name}"?`);
};

// Options for parent dropdown (exclude current designation when editing)
const parentOptions = (excludeId = null) =>
    props.designations.filter(d => d.id !== excludeId);
</script>

<template>
    <SchoolLayout title="Designations">

        <div class="page-header">
            <div>
                <h1 class="page-header-title">Designations</h1>
                <p class="page-header-sub">Manage job titles and reporting hierarchy for staff.</p>
            </div>
            <Button @click="openCreate">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Designation
            </Button>
        </div>

        <!-- Table -->
        <div class="card" style="overflow:hidden;">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                <span class="card-title">All Designations</span>
                <span class="badge badge-blue">{{ designations.length }} Total</span>
            </div>
            <Table>
                <thead>
                    <tr>
                        <th>Designation Name</th>
                        <th>Reports To</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="designations.length === 0">
                        <td colspan="4" class="empty-row">No designations found. Add your first one to get started.</td>
                    </tr>
                    <tr v-for="d in designations" :key="d.id">
                        <td>
                            <span class="desig-name">{{ d.name }}</span>
                        </td>
                        <td>
                            <span v-if="d.parent" class="parent-label">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="vertical-align:middle;margin-right:3px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                {{ d.parent.name }}
                            </span>
                            <span v-else class="badge badge-gray">Top Level</span>
                        </td>
                        <td>
                            <span v-if="d.is_active" class="badge badge-green">Active</span>
                            <span v-else class="badge badge-red">Inactive</span>
                        </td>
                        <td>
                            <div class="action-group">
                                <Button variant="secondary" size="xs" @click="toggleStatus(d.id)"
                                    :style="d.is_active ? 'color:#d97706;border-color:#fde68a;' : 'color:#059669;border-color:#a7f3d0;'">
                                    {{ d.is_active ? 'Deactivate' : 'Activate' }}
                                </Button>
                                <Button variant="secondary" size="xs" @click="openEdit(d)">Edit</Button>
                                <Button variant="danger" size="xs" @click="deleteDesignation(d.id, d.name)">Delete</Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </Table>
        </div>

        <!-- Slide Panel -->
        <SlidePanel :open="panelOpen" :title="isEditing ? 'Edit Designation' : 'Add Designation'" @close="closePanel">
            <form @submit.prevent="isEditing ? submitEdit() : submit()" style="display:flex;flex-direction:column;gap:18px;">

                <div class="form-field">
                    <label>Designation Name <span style="color:var(--danger);">*</span></label>
                    <input
                        :value="isEditing ? editForm.name : form.name"
                        @input="e => isEditing ? (editForm.name = e.target.value) : (form.name = e.target.value)"
                        type="text"
                        placeholder="e.g. Senior Teacher"
                        required>
                    <div v-if="(isEditing ? editForm : form).errors.name" class="form-error">
                        {{ (isEditing ? editForm : form).errors.name }}
                    </div>
                </div>

                <div class="form-field">
                    <label>Parent Designation <span style="color:#94a3b8;font-weight:400;font-size:0.8em;">(Optional)</span></label>
                    <select
                        :value="isEditing ? editForm.parent_id : form.parent_id"
                        @change="e => isEditing ? (editForm.parent_id = e.target.value) : (form.parent_id = e.target.value)">
                        <option value="">— None (Top Level) —</option>
                        <option v-for="d in parentOptions(isEditing ? editingId : null)" :key="d.id" :value="d.id">
                            {{ d.name }}
                        </option>
                    </select>
                    <div v-if="(isEditing ? editForm : form).errors.parent_id" class="form-error">
                        {{ (isEditing ? editForm : form).errors.parent_id }}
                    </div>
                    <span style="font-size:0.75rem;color:#94a3b8;">Used to define reporting hierarchy between roles.</span>
                </div>

                <div style="display:flex;gap:10px;padding-top:4px;">
                    <Button type="submit" :loading="(isEditing ? editForm : form).processing" class="flex-1">
                        {{ (isEditing ? 'Update Designation' : 'Save Designation') }}
                    </Button>
                    <Button variant="secondary" type="button" @click="closePanel">Cancel</Button>
                </div>

            </form>
        </SlidePanel>

    </SchoolLayout>
</template>

<style scoped>
.desig-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: #0f172a;
}
.parent-label {
    font-size: 0.8125rem;
    color: #6366f1;
    font-weight: 500;
}
.action-group {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}
.empty-row {
    text-align: center;
    padding: 48px 24px;
    color: #94a3b8;
    font-size: 0.875rem;
}
</style>
