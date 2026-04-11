<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

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
    const payload = JSON.parse(JSON.stringify(form.value));
    processingForm.value = true;
    formErrors.value = {};
    if (editingSystem.value) {
        router.put(`/school/grading-systems/${editingSystem.value.id}`, payload, {
            onSuccess: () => closeModal(),
            onError: (e) => { formErrors.value = e; },
            onFinish: () => { processingForm.value = false; },
        });
    } else {
        router.post('/school/grading-systems', payload, {
            onSuccess: () => closeModal(),
            onError: (e) => { formErrors.value = e; },
            onFinish: () => { processingForm.value = false; },
        });
    }
};

const closeModal = () => {
    isModalOpen.value = false;
    formErrors.value = {};
};

const deleteSystem = (id) => {
    if (confirm('Are you sure you want to delete this grading system?')) {
        router.delete(`/school/grading-systems/${id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <SchoolLayout title="Exam Grades">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Exam Grades &amp; Scales</h1>
                <p class="page-header-sub">Manage scholastic and co-scholastic grading parameters mapping percentage to grades.</p>
            </div>
            <Button @click="openCreateModal">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Grading System
            </Button>
        </div>

        <div class="card">
            <div class="card-body" style="padding:0;overflow-x:auto;">
                <Table>
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
                        <tr v-if="gradingSystems.length === 0">
                            <td colspan="4" style="text-align:center;padding:24px;color:#94a3b8;">
                                No grading systems configured for this academic year yet.
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="isModalOpen" class="modal-backdrop">
            <div class="modal" style="width:100%;max-width:900px;max-height:90vh;display:flex;flex-direction:column;">
                <div class="modal-header" style="flex-shrink:0;">
                    <h3 class="modal-title">{{ editingSystem ? 'Edit Grading System' : 'Create Grading System' }}</h3>
                    <button @click="closeModal" class="modal-close">&times;</button>
                </div>

                <form @submit.prevent="submit" style="display:flex;flex-direction:column;flex:1;min-height:0;">
                    <div class="modal-body" style="flex:1;overflow-y:auto;">

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
                                <Button size="xs" type="button" @click="addGradeRow">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                    Add Grade Level
                                </Button>
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
                                            <button type="button" @click="removeGradeRow(index)" style="color:#ef4444;background:none;border:none;cursor:pointer;padding:4px;" title="Remove row">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
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
                    </div>

                    <div class="modal-footer" style="flex-shrink:0;">
                        <Button variant="secondary" type="button" @click="closeModal">Cancel</Button>
                        <Button type="submit" :loading="processingForm">
                            Save Grading System
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.modal-backdrop {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15,23,42,0.5); backdrop-filter: blur(2px);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.modal {
    background: #fff; border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
}
.modal-header {
    padding: 16px 20px; border-bottom: 1px solid #e2e8f0;
    display: flex; justify-content: space-between; align-items: center;
}
.modal-title { font-size: 1rem; font-weight: 700; color: #1e293b; }
.modal-close {
    background: none; border: none; font-size: 1.5rem; line-height: 1; color: #94a3b8;
    cursor: pointer; padding: 0 4px; transition: color 0.2s;
}
.modal-close:hover { color: #0f172a; }
.modal-body { padding: 20px 20px; }
.modal-footer {
    padding: 16px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc;
    border-radius: 0 0 12px 12px; display: flex; justify-content: flex-end; gap: 10px;
}
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
</style>
