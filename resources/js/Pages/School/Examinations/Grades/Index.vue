<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';

const confirm = useConfirm();
const toast = useToast();

const props = defineProps({
    gradingSystems: Array
});

const isModalOpen = ref(false);
const editingSystem = ref(null);
const processingForm = ref(false);
const formErrors = ref({});

const form = ref({
    name: '',
    type: 'scholastic',
    description: '',
    grades: []
});

const openCreateModal = () => {
    editingSystem.value = null;
    form.value = { name: '', type: 'scholastic', description: '', grades: [{ id: null, name: '', min_percentage: '', max_percentage: '', grade_point: '', description: '', color_code: '#1169cd', is_fail: false }] };
    formErrors.value = {};
    isModalOpen.value = true;
};

const openEditModal = (system) => {
    editingSystem.value = system;
    form.value = {
        name: system.name,
        type: system.type,
        description: system.description || '',
        grades: system.grades && system.grades.length > 0
            ? system.grades.map(g => ({ id: g.id, name: g.name, min_percentage: g.min_percentage, max_percentage: g.max_percentage, grade_point: g.grade_point, description: g.description || '', color_code: g.color_code || '#1169cd', is_fail: g.is_fail ? true : false }))
            : [{ id: null, name: '', min_percentage: '', max_percentage: '', grade_point: '', description: '', color_code: '#1169cd', is_fail: false }]
    };
    formErrors.value = {};
    isModalOpen.value = true;
};

const addGradeRow = () => {
    form.value.grades.push({ id: null, name: '', min_percentage: '', max_percentage: '', grade_point: '', description: '', color_code: '#1169cd', is_fail: false });
};

const removeGradeRow = (index) => {
    form.value.grades.splice(index, 1);
};

const submit = () => {
    if (processingForm.value) return; // prevent double-submit
    const payload = JSON.parse(JSON.stringify(form.value));
    processingForm.value = true;
    formErrors.value = {};
    const onError = (e) => {
        formErrors.value = e;
        toast.error('Please fix the highlighted fields and try again.');
    };
    if (editingSystem.value) {
        router.put(`/school/grading-systems/${editingSystem.value.id}`, payload, {
            onSuccess: () => closeModal(),
            onError,
            onFinish: () => { processingForm.value = false; },
        });
    } else {
        router.post('/school/grading-systems', payload, {
            onSuccess: () => closeModal(),
            onError,
            onFinish: () => { processingForm.value = false; },
        });
    }
};

const closeModal = () => {
    isModalOpen.value = false;
    formErrors.value = {};
};

const deleteSystem = async (id) => {
    const ok = await confirm({
        title: 'Delete grading system?',
        message: 'This grading system will be permanently removed.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/grading-systems/${id}`, {
        preserveScroll: true,
        onError: () => toast.error('Could not delete grading system.'),
    });
};
</script>

<template>
    <SchoolLayout title="Exam Grades">
        <PageHeader title="Exam Grades & Scales" subtitle="Manage scholastic and co-scholastic grading parameters mapping percentage to grades.">
            <template #actions>
                <Button @click="openCreateModal">+ Create Grading System</Button>
            </template>
        </PageHeader>

        <div class="card">
            <div class="card-body" style="padding:0;overflow-x:auto;">
                <Table :empty="gradingSystems.length === 0">
                    <thead>
                        <tr>
                            <th>System Name</th>
                            <th>Type</th>
                            <th>Grades Configuration</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="system in gradingSystems" :key="system.id">
                            <td>
                                <div style="font-weight:500;">{{ system.name }}</div>
                                <div v-if="system.description" style="font-size:.75rem;color:#64748b;margin-top:2px;">{{ system.description }}</div>
                            </td>
                            <td>
                                <span class="badge" :class="system.type === 'scholastic' ? 'badge-blue' : 'badge-purple'">
                                    {{ system.type === 'scholastic' ? 'Scholastic' : 'Co-Scholastic' }}
                                </span>
                            </td>
                            <td>
                                <div class="grades-preview">
                                    <span v-for="grade in system.grades" :key="grade.id" class="grade-pill"
                                        :style="grade.color_code ? `border-left: 3px solid ${grade.color_code}` : ''"
                                        :title="`${grade.min_percentage}% - ${grade.max_percentage}%`">
                                        {{ grade.name }}
                                        <small v-if="grade.grade_point">({{ grade.grade_point }})</small>
                                        <span v-if="grade.is_fail" style="margin-left:4px;font-size:.625rem;color:#dc2626;font-weight:700;text-transform:uppercase;" title="Failing Grade">F</span>
                                    </span>
                                </div>
                            </td>
                            <td style="text-align:right;">
                                <Button variant="secondary" size="sm" @click="openEditModal(system)">Edit</Button>
                                <Button variant="danger" size="sm" @click="deleteSystem(system.id)" class="ml-1.5">Delete</Button>
                            </td>
                        </tr>
                    </tbody>
                    <template #empty>
                        <EmptyState
                            title="No grading systems configured"
                            description="Configure grading systems for this academic year."
                            action-label="+ Create Grading System"
                            @action="openCreateModal"
                        />
                    </template>
                </Table>
            </div>
        </div>

        <!-- Modal -->
        <Modal v-model:open="isModalOpen" :title="editingSystem ? 'Edit Grading System' : 'Create Grading System'" size="xl">
            <form @submit.prevent="submit" id="grading-form">
                <!-- System Details -->
                <div style="margin-bottom:24px;">
                    <div class="section-heading" style="margin-bottom:14px;">Base Configuration</div>
                    <div class="form-row form-row-2">
                        <div class="form-field">
                            <label>System Name *</label>
                            <input type="text" v-model="form.name" required placeholder="e.g. CBSE 8-Point Scale" />
                            <div class="form-error" v-if="formErrors.name">{{ formErrors.name }}</div>
                        </div>
                        <div class="form-field">
                            <label>Scale Type *</label>
                            <select v-model="form.type" required>
                                <option value="scholastic">Scholastic (Main Subjects)</option>
                                <option value="co_scholastic">Co-Scholastic (e.g. Discipline, Arts)</option>
                            </select>
                            <div class="form-error" v-if="formErrors.type">{{ formErrors.type }}</div>
                        </div>
                        <div class="form-field" style="grid-column:span 2;">
                            <label>Description (Optional)</label>
                            <input type="text" v-model="form.description" placeholder="Brief info about this scale" />
                        </div>
                    </div>
                </div>

                <!-- Grades Table Builder -->
                <div>
                    <div class="section-heading" style="margin-bottom:14px;display:flex;justify-content:space-between;align-items:flex-end;">
                        <span>Grade Distribution mapped to Percentages</span>
                        <Button size="xs" type="button" @click="addGradeRow">+ Add Grade Level</Button>
                    </div>

                    <Table style="vertical-align:top;">
                        <thead>
                            <tr>
                                <th style="width:15%">Grade *</th>
                                <th style="width:14%">Min % *</th>
                                <th style="width:14%">Max % *</th>
                                <th style="width:12%">Grade Pt.</th>
                                <th style="width:25%">Remark/Description</th>
                                <th style="width:8%;text-align:center;">Color</th>
                                <th style="width:6%;text-align:center;">Fail?</th>
                                <th style="width:6%;text-align:center;">Del</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(grade, index) in form.grades" :key="index" :style="grade.is_fail ? 'background:#fff5f5;' : ''">
                                <td>
                                    <input type="text" v-model="grade.name" required placeholder="A1, B2" class="sub-input" style="padding:6px 8px;" />
                                    <div class="form-error" v-if="formErrors[`grades.${index}.name`]">{{ formErrors[`grades.${index}.name`] }}</div>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" max="100" v-model="grade.min_percentage" required placeholder="91" class="sub-input" style="padding:6px 8px;" />
                                    <div class="form-error" v-if="formErrors[`grades.${index}.min_percentage`]">{{ formErrors[`grades.${index}.min_percentage`] }}</div>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" max="100" v-model="grade.max_percentage" required placeholder="100" class="sub-input" style="padding:6px 8px;" />
                                    <div class="form-error" v-if="formErrors[`grades.${index}.max_percentage`]">{{ formErrors[`grades.${index}.max_percentage`] }}</div>
                                </td>
                                <td>
                                    <input type="number" step="0.1" min="0" v-model="grade.grade_point" placeholder="10.0" class="sub-input" style="padding:6px 8px;" />
                                    <div class="form-error" v-if="formErrors[`grades.${index}.grade_point`]">{{ formErrors[`grades.${index}.grade_point`] }}</div>
                                </td>
                                <td>
                                    <input type="text" v-model="grade.description" placeholder="Outstanding" class="sub-input" style="padding:6px 8px;" />
                                </td>
                                <td style="text-align:center;vertical-align:middle;">
                                    <input type="color" v-model="grade.color_code" style="width:32px;height:32px;padding:0;cursor:pointer;border:none;border-radius:4px;" title="Pick a color for this grade label" />
                                </td>
                                <td style="text-align:center;vertical-align:middle;">
                                    <input type="checkbox" v-model="grade.is_fail" style="width:16px;height:16px;cursor:pointer;" title="Mark as failing grade" />
                                </td>
                                <td style="text-align:center;vertical-align:middle;">
                                    <button type="button" @click="removeGradeRow(index)" style="color:#ef4444;background:none;border:none;cursor:pointer;padding:4px;" title="Remove row">×</button>
                                </td>
                            </tr>
                            <tr v-if="form.grades.length === 0">
                                <td colspan="8" style="text-align:center;padding:16px;color:#94a3b8;font-size:.875rem;">
                                    Click "Add Grade Level" to start building your percentage scale map.
                                </td>
                            </tr>
                        </tbody>
                    </Table>
                    <div class="form-error" style="margin-top:4px;" v-if="formErrors.grades">{{ formErrors.grades }}</div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="closeModal">Cancel</Button>
                <Button type="submit" form="grading-form" :loading="processingForm">
                    Save Grading System
                </Button>
            </template>
        </Modal>
    </SchoolLayout>
</template>

<style scoped>
.sub-input {
    width: 100%; padding: 6px 8px; font-size: .8125rem; color: #1e293b;
    background: #fff; border: 1.5px solid #cbd5e1; border-radius: 6px; outline: none;
    transition: border-color .15s; font-family: inherit;
}
.sub-input:focus { border-color: #4f46e5; box-shadow: 0 0 0 2px rgba(79,70,229,.15); }
.sub-input:disabled { background: #f8fafc; color: #94a3b8; cursor: not-allowed; }

.grades-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.grade-pill {
    background-color: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #334155;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.grade-pill small {
    color: #64748b;
    font-size: 0.65rem;
    font-weight: 500;
}
.section-heading { font-size: .875rem; font-weight: 600; color: #475569; }
.form-error { font-size: .72rem; color: #dc2626; }

/* Form fields — Tailwind preflight workaround */
.form-field { display: flex; flex-direction: column; gap: 5px; }
.form-field label { font-size: 0.78rem; font-weight: 600; color: #374151; }
.form-field input,
.form-field select {
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
.form-field input:focus,
.form-field select:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
</style>
