<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import SchoolLayout from '@/Layouts/SchoolLayout.vue'
import SlidePanel from '@/Components/SlidePanel.vue'
import { useDelete } from '@/Composables/useDelete'
import Table from '@/Components/ui/Table.vue';

const props = defineProps({ departments: Array })
const panelOpen = ref(false)
const isEditing = ref(false)
const editingId = ref(null)

const form = useForm({ name: '', type: '' })

const openCreate = () => { isEditing.value = false; editingId.value = null; form.reset(); form.clearErrors(); panelOpen.value = true }
const openEdit = (d) => {
    isEditing.value = true; editingId.value = d.id
    form.name = d.name; form.type = d.type || ''
    form.clearErrors(); panelOpen.value = true
}
const closePanel = () => { panelOpen.value = false }
const submit = () => {
    const opts = { onSuccess: () => closePanel(), onError: (e) => form.setError(e) }
    if (isEditing.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/school/departments/${editingId.value}`, opts)
    } else {
        form.transform((data) => data).post('/school/departments', opts)
    }
}
const { del } = useDelete();
const destroy = (id) => del(`/school/departments/${id}`, 'Delete this department?')
</script>

<template>
    <SchoolLayout title="Departments">
        <PageHeader title="Departments" subtitle="Manage academic streams and HR staffing groups.">
            <template #actions>
                <Button @click="openCreate">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Add Department
                            </Button>
            </template>
        </PageHeader>

        <div class="card" style="overflow:hidden;">
            <Table>
                <thead>
                    <tr>
                        <th>Department Name</th>
                        <th>Type / Category</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="departments.length === 0">
                        <td colspan="3" style="text-align:center;padding:48px 24px;color:#94a3b8;">No departments found. Add your first one to get started.</td>
                    </tr>
                    <tr v-for="dept in departments" :key="dept.id">
                        <td style="font-weight:600;color:#0f172a;">{{ dept.name }}</td>
                        <td>
                            <span v-if="dept.type" class="badge badge-indigo" style="text-transform:capitalize;">{{ dept.type.replace('_', ' ') }}</span>
                            <span v-else class="badge badge-gray">General</span>
                        </td>
                        <td>
                            <div style="display:flex;gap:8px;">
                                <Button variant="secondary" size="xs" @click="openEdit(dept)">Edit</Button>
                                <Button variant="danger" size="xs" @click="destroy(dept.id)">Delete</Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </Table>
        </div>

        <SlidePanel :open="panelOpen" :title="isEditing ? 'Edit Department' : 'Add Department'" @close="closePanel">
            <form @submit.prevent="submit" class="form-row" style="display:flex;flex-direction:column;gap:18px;">
                <div class="form-field">
                    <label>Department Name <span style="color:#ef4444;">*</span></label>
                    <input v-model="form.name" type="text" placeholder="e.g. Science, HR, Math Department" required />
                    <span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
                </div>
                <div class="form-field">
                    <label>Category / Type <span style="color:#94a3b8;font-weight:400;">(Optional)</span></label>
                    <select v-model="form.type">
                        <option value="">— General —</option>
                        <option value="teaching">Teaching / Academic</option>
                        <option value="non_teaching">Non-Teaching Staff</option>
                        <option value="administrative">Administrative / Office</option>
                        <option value="management">Management / Leadership</option>
                        <option value="support">Utility & Support</option>
                    </select>
                    <span style="font-size:0.75rem;color:#94a3b8;">Helps group staff members in the HR module.</span>
                </div>
                <div style="display:flex;gap:10px;padding-top:4px;">
                    <Button type="submit" :loading="form.processing" class="flex-1">
                        {{ (isEditing ? 'Update Department' : 'Save Department') }}
                    </Button>
                    <Button variant="secondary" type="button" @click="closePanel">Cancel</Button>
                </div>
            </form>
        </SlidePanel>
    </SchoolLayout>
</template>
