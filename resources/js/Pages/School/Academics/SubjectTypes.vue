<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref } from 'vue'
import { useForm, router, Link } from '@inertiajs/vue3'
import SchoolLayout from '@/Layouts/SchoolLayout.vue'
import SlidePanel from '@/Components/SlidePanel.vue'
import { useDelete } from '@/Composables/useDelete'
import { useToast } from '@/Composables/useToast';
import Table from '@/Components/ui/Table.vue';

const toast = useToast();

const props = defineProps({ types: Array })

const panelOpen = ref(false)
const isEditing = ref(false)
const editingId = ref(null)

const form = useForm({ label: '', description: '', sort_order: 0 })

const openCreate = () => {
    isEditing.value = false; editingId.value = null; form.reset(); panelOpen.value = true
}
const openEdit = (t) => {
    isEditing.value = true; editingId.value = t.id
    form.label = t.label; form.description = t.description || ''; form.sort_order = t.sort_order
    panelOpen.value = true
}
const closePanel = () => { panelOpen.value = false; form.reset() }

const submit = () => {
    if (isEditing.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/school/subject-types/${editingId.value}`, { onSuccess: () => closePanel(), onError: (e) => form.setError(e) })
    } else {
        form.transform((data) => data).post('/school/subject-types', { onSuccess: () => closePanel() })
    }
}
const { del } = useDelete();
const destroy = (t) => {
    if (!t?.id) return;
    if ((t.subjects_count ?? 0) > 0) {
        toast.warning(`Cannot delete "${t.label}" — ${t.subjects_count} subject(s) are using this type. Re-assign or delete those subjects first.`);
        return;
    }
    del(`/school/subject-types/${t.id}`, `Delete subject type "${t.label}"? This cannot be undone.`);
}
</script>

<template>
    <SchoolLayout title="Subject Types">
        <div class="page-header">
            <div>
                <h2 class="page-header-title">Subject Types / Parts</h2>
                <p class="page-header-sub">Define reusable part labels (e.g. Part A, Part B, Language 1) that appear in the subject form dropdown.</p>
            </div>
            <Button @click="openCreate">+ Add Type</Button>
        </div>

        <div v-if="types.length === 0" class="card py-16 text-center">
            <p class="text-4xl mb-3">🏷️</p>
            <h3 class="text-lg font-semibold text-slate-700 mb-1">No subject types yet</h3>
            <p class="text-sm text-slate-500 mb-4">Create your first type to enable the Part dropdown when adding subjects.</p>
            <Button @click="openCreate">+ Add Type</Button>
        </div>

        <div v-else class="card overflow-hidden">
            <Table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Label</th>
                        <th>Description</th>
                        <th class="text-center">Subjects</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in types" :key="t.id">
                        <td class="text-slate-400">{{ t.sort_order }}</td>
                        <td>
                            <span class="badge badge-amber">{{ t.label }}</span>
                        </td>
                        <td class="text-slate-500">{{ t.description || '—' }}</td>
                        <td class="text-slate-400 text-center">{{ t.subjects_count ?? 0 }}</td>
                        <td class="text-right">
                            <Button variant="secondary" size="xs" @click="openEdit(t)" class="mr-2">Edit</Button>
                            <Button variant="danger" size="xs" @click="destroy(t)" :disabled="(t.subjects_count ?? 0) > 0" :title="(t.subjects_count ?? 0) > 0 ? 'In use by ' + t.subjects_count + ' subject(s)' : 'Delete'">Delete</Button>
                        </td>
                    </tr>
                </tbody>
            </Table>
        </div>

        <div class="card mt-4">
            <div class="card-body text-sm text-blue-700 bg-blue-50 rounded-lg border border-blue-200">
                These labels appear as a dropdown when creating or editing a Subject under "Part". Example: <strong>Part A</strong>, <strong>Part B</strong>, <strong>Language 1</strong>.
            </div>
        </div>

        <!-- Slide Panel -->
        <SlidePanel :open="panelOpen" :title="isEditing ? 'Edit Subject Type' : 'Add Subject Type'" @close="closePanel">
            <form @submit.prevent="submit" class="space-y-5">
                <div class="form-field">
                    <label>Label <span class="text-red-500">*</span></label>
                    <input v-model="form.label" type="text" placeholder="e.g. Part A, Language 1" required />
                    <p v-if="form.errors.label" class="form-error">{{ form.errors.label }}</p>
                </div>
                <div class="form-field">
                    <label>Description (optional)</label>
                    <input v-model="form.description" type="text" placeholder="Brief note on when to use this" />
                </div>
                <div class="form-field">
                    <label>Sort Order</label>
                    <input v-model="form.sort_order" type="number" min="0" placeholder="0" />
                    <p class="text-xs text-slate-500 mt-1">Lower number appears first in dropdown.</p>
                </div>
                <div class="flex gap-3 pt-2">
                    <Button type="submit" :loading="form.processing" class="flex-1">
                        {{ isEditing ? 'Update' : 'Save' }}
                    </Button>
                    <Button variant="secondary" type="button" @click="closePanel">
                        Cancel
                    </Button>
                </div>
            </form>
        </SlidePanel>
    </SchoolLayout>
</template>
