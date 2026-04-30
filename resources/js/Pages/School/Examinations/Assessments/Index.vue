<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';

const confirm = useConfirm();
const toast = useToast();

const props = defineProps({
    assessments: Array,
});

const view = ref('list');
const editingAssessment = ref(null);
const items = ref([{ id: null, name: '', code: '' }]);

const form = useForm({ name: '', description: '' });

const addItem = () => items.value.push({ id: null, name: '', code: '' });
const removeItem = (i) => { if (items.value.length > 1) items.value.splice(i, 1); };

const openCreate = () => {
    editingAssessment.value = null;
    form.reset(); form.clearErrors();
    items.value = [{ id: null, name: '', code: '' }];
    view.value = 'create';
};

const openEdit = (a) => {
    editingAssessment.value = a;
    form.name = a.name;
    form.description = a.description || '';
    items.value = a.items.length
        ? a.items.map(i => ({ id: i.id, name: i.name, code: i.code || '' }))
        : [{ id: null, name: '', code: '' }];
    form.clearErrors();
    view.value = 'edit';
};

const submitting = ref(false);

const submit = () => {
    if (submitting.value) return; // guard against double-submit while in flight
    submitting.value = true;
    const payload = JSON.parse(JSON.stringify({
        name: form.name,
        description: form.description,
        items: items.value,
    }));

    const onError = (e) => {
        Object.assign(form.errors, e);
        toast.error('Please fix the highlighted fields and try again.');
    };
    const onFinish = () => { submitting.value = false; };

    if (view.value === 'edit') {
        router.put(`/school/exam-assessments/${editingAssessment.value.id}`, payload, {
            onSuccess: () => { view.value = 'list'; form.reset(); },
            onError,
            onFinish,
        });
    } else {
        router.post('/school/exam-assessments', payload, {
            onSuccess: () => { view.value = 'list'; form.reset(); },
            onError,
            onFinish,
        });
    }
};

const deleteAssessment = async (id) => {
    const ok = await confirm({
        title: 'Delete Exam Assessment?',
        message: 'This cannot be undone.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/exam-assessments/${id}`, {
        preserveScroll: true,
        onError: () => toast.error('Could not delete exam assessment.'),
    });
};
</script>

<template>
    <SchoolLayout title="Exam Assessment">

        <!-- LIST -->
        <div v-if="view === 'list'">
            <PageHeader title="Exam Assessments" subtitle="Manage assessment groups and their sub-assessment items.">
                <template #actions>
                    <Button @click="openCreate">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        Add Exam Assessment
                                    </Button>
                </template>
            </PageHeader>
            <div class="card">
                <div class="overflow-x-auto">
                    <Table>
                        <thead>
                            <tr>
                                <th>Assessment Name</th>
                                <th>Description</th>
                                <th>Items</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in assessments" :key="a.id">
                                <td class="font-semibold">{{ a.name }}</td>
                                <td class="text-gray-500 text-sm">{{ a.description || '—' }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span v-for="item in a.items" :key="item.id" class="item-pill">
                                            {{ item.name }}<span v-if="item.code" class="item-pill-code">{{ item.code }}</span>
                                        </span>
                                        <span v-if="!a.items.length" class="text-gray-400 text-sm">—</span>
                                    </div>
                                </td>
                                <td class="text-right space-x-2">
                                    <Button variant="secondary" size="sm" @click="openEdit(a)">Edit</Button>
                                    <Button variant="danger" size="sm" @click="deleteAssessment(a.id)">Delete</Button>
                                </td>
                            </tr>
                            <tr v-if="!assessments.length">
                                <td colspan="4" class="text-center py-8 text-gray-500">No exam assessments yet.</td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
            </div>
        </div>

        <!-- FORM -->
        <div v-else>
            <div class="form-page-header">
                <h2 class="form-page-title">{{ view === 'edit' ? 'Edit Exam Assessment' : 'Add Exam Assessment' }}</h2>
                <Button variant="secondary" size="sm" type="button" @click="view = 'list'">List all Exam Assessments</Button>
            </div>

            <form @submit.prevent="submit">
                <div class="card card-body mb-4">
                    <div class="form-row form-row-2">
                        <div class="form-field">
                            <label>Name <span class="text-red-500">*</span></label>
                            <input type="text" v-model="form.name" required placeholder="e.g. Periodic Assessment" />
                            <div class="form-error" v-if="form.errors.name">{{ form.errors.name }}</div>
                        </div>
                        <div class="form-field">
                            <label>Description</label>
                            <input type="text" v-model="form.description" placeholder="Optional description" />
                        </div>
                    </div>
                </div>

                <div class="items-list">
                    <div v-for="(item, idx) in items" :key="idx" class="item-card">
                        <div class="item-card-header">
                            <svg class="w-4 h-4 text-gray-400 cursor-grab" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                            <span class="item-index">{{ idx + 1 }}.</span>
                            <button type="button" @click="removeItem(idx)" class="item-remove" title="Remove">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                            </button>
                        </div>
                        <div class="item-fields">
                            <div class="item-field-col">
                                <label class="item-label">Name <span class="text-red-500">*</span></label>
                                <input type="text" v-model="item.name" required placeholder="Name" class="item-input" />
                            </div>
                            <div class="item-field-col">
                                <label class="item-label">Code</label>
                                <input type="text" v-model="item.code" placeholder="Code" class="item-input" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="button" @click="addItem" class="btn-add-record">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Record
                    </button>
                </div>

                <div class="form-footer">
                    <Button variant="secondary" type="button" @click="view = 'list'">Cancel</Button>
                    <Button type="submit">Save</Button>
                </div>
            </form>
        </div>

    </SchoolLayout>
</template>

<style scoped>
.form-page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; padding-bottom:16px; border-bottom:1px solid #e2e8f0; }
.form-page-title { font-size:1.1rem; font-weight:800; color:#0f172a; }
.items-list { display:flex; flex-direction:column; gap:12px; }
.item-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; }
.item-card-header { display:flex; align-items:center; gap:8px; padding:8px 14px; background:#f8fafc; border-bottom:1px solid #f1f5f9; }
.item-index { font-size:.8125rem; font-weight:700; color:#475569; }
.item-remove { margin-left:auto; background:none; border:none; color:#ef4444; cursor:pointer; padding:2px; display:flex; align-items:center; }
.item-remove:hover { color:#b91c1c; }
.item-fields { display:grid; grid-template-columns:1fr 1fr; gap:16px; padding:16px 20px; }
.item-field-col { display:flex; flex-direction:column; gap:5px; }
.item-label { font-size:.8125rem; font-weight:600; color:#374151; }
.item-input { width:100%; padding:8px 12px; font-size:.875rem; color:#1e293b; background:#fff; border:1.5px solid #cbd5e1; border-radius:8px; outline:none; transition:border-color .15s; font-family:inherit; }
.item-input:focus { border-color:#1169cd; box-shadow:0 0 0 3px rgba(17,105,205,.12); }
.btn-add-record { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; background:#1169cd; color:#fff; font-size:.8125rem; font-weight:700; border:none; border-radius:8px; cursor:pointer; }
.btn-add-record:hover { background:#0d50a3; }
.form-footer { display:flex; align-items:center; justify-content:flex-end; gap:10px; margin-top:24px; padding-top:16px; border-top:1px solid #e2e8f0; }
.item-pill { display:inline-flex; align-items:center; gap:5px; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:20px; padding:2px 10px; font-size:.75rem; font-weight:600; color:#334155; }
.item-pill-code { font-size:.68rem; color:#64748b; font-weight:500; background:#e2e8f0; padding:1px 5px; border-radius:4px; }
</style>
