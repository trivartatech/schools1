<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Table from '@/Components/ui/Table.vue';
import axios from 'axios';
import { useSchoolStore } from '@/stores/useSchoolStore';
import { useConfirm } from '@/Composables/useConfirm';

const school = useSchoolStore();
const confirm = useConfirm();

const props = defineProps({
    papers:  Array,
    classes: Array,
    filters: Object,
});

const classId   = ref(props.filters?.class_id || '');
const subjectId = ref(props.filters?.subject_id || '');
const subjects  = ref([]);
const deleting  = ref(null);

async function loadSubjects() {
    if (!classId.value) { subjects.value = []; subjectId.value = ''; return; }
    try {
        const res = await axios.get('/school/question-papers/subjects', { params: { class_id: classId.value } });
        subjects.value = res.data;
    } catch (e) { subjects.value = []; }
}

watch(classId, () => { subjectId.value = ''; loadSubjects(); });

function applyFilter() {
    const params = {};
    if (classId.value)   params.class_id   = classId.value;
    if (subjectId.value) params.subject_id = subjectId.value;
    router.get('/school/question-papers', params, { preserveState: true });
}

async function confirmDelete(paper) {
    const ok = await confirm({
        title: 'Delete question paper?',
        message: `Delete "${paper.title}"?`,
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    deleting.value = paper.id;
    router.delete(`/school/question-papers/${paper.id}`, {
        onFinish: () => { deleting.value = null; },
    });
}

const DIFFICULTY_BADGE = {
    easy:   'badge-green',
    medium: 'badge-amber',
    hard:   'badge-red',
    mixed:  'badge-indigo',
};

const DIFFICULTY_LABEL = {
    easy: 'Easy', medium: 'Medium', hard: 'Hard', mixed: 'Mixed',
};
</script>

<template>
    <SchoolLayout title="AI Question Papers">

        <!-- Page Header -->
        <PageHeader title="AI Question Paper Generator" subtitle="Generate and manage AI-powered exam question papers.">
            <template #actions>
                <Button as="link" href="/school/question-papers/create">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Generate New Paper
                            </Button>
            </template>
        </PageHeader>

        <!-- Filters -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:16px;">
                <div style="flex:1;min-width:150px;">
                    <label>Class</label>
                    <select v-model="classId">
                        <option value="">All Classes</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div style="flex:1;min-width:150px;">
                    <label>Subject</label>
                    <select v-model="subjectId" :disabled="!subjects.length">
                        <option value="">All Subjects</option>
                        <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <Button variant="secondary" @click="applyFilter">Filter</Button>
            </div>
        </div>

        <!-- Papers Table -->
        <div class="card" style="overflow:hidden;">
            <Table :empty="!papers.length" empty-text="No question papers yet. Click Generate New Paper to create one.">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Class</th>
                        <th>Subject</th>
                        <th>Exam Type</th>
                        <th>Difficulty</th>
                        <th>Marks</th>
                        <th>Duration</th>
                        <th>Created</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="paper in papers" :key="paper.id">
                        <td style="font-weight:600;max-width:220px;">
                            <a :href="`/school/question-papers/${paper.id}`" style="color:#0f172a;text-decoration:none;" class="paper-title-link">
                                {{ paper.title }}
                            </a>
                        </td>
                        <td>{{ paper.course_class?.name }}</td>
                        <td>{{ paper.subject?.name }}</td>
                        <td>
                            <span v-if="paper.exam_type" class="badge badge-gray">{{ paper.exam_type }}</span>
                            <span v-else style="color:#94a3b8;font-size:0.8125rem;">—</span>
                        </td>
                        <td>
                            <span v-if="paper.difficulty" class="badge" :class="DIFFICULTY_BADGE[paper.difficulty] || 'badge-gray'">
                                {{ DIFFICULTY_LABEL[paper.difficulty] || paper.difficulty }}
                            </span>
                        </td>
                        <td>{{ paper.total_marks }}</td>
                        <td>{{ paper.duration_minutes }} min</td>
                        <td style="color:#64748b;">{{ school.fmtDate(paper.created_at) }}</td>
                        <td>
                            <div class="paper-actions">
                                <a :href="`/school/question-papers/${paper.id}`" class="paper-action paper-action--view">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    View
                                </a>
                                <a :href="`/school/question-papers/${paper.id}/pdf`" target="_blank" class="paper-action paper-action--pdf">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    PDF
                                </a>
                                <a :href="`/school/question-papers/${paper.id}/pdf?with_answers=1`" target="_blank" class="paper-action paper-action--ans">
                                    Answer Key
                                </a>
                                <button @click="confirmDelete(paper)"
                                        :disabled="deleting === paper.id"
                                        class="paper-action paper-action--delete">
                                    {{ deleting === paper.id ? '...' : 'Delete' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </Table>
        </div>

    </SchoolLayout>
</template>

<style scoped>
.paper-title-link:hover { color: #6366f1; }

.paper-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    justify-content: flex-end;
    flex-wrap: nowrap;
}
.paper-action {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 6px;
    font-size: 0.75rem; font-weight: 600;
    cursor: pointer; border: none; background: none;
    text-decoration: none; white-space: nowrap;
    transition: background 0.12s, color 0.12s;
}
.paper-action svg { width: 13px; height: 13px; }

.paper-action--view  { color: #6366f1; }
.paper-action--view:hover  { background: #e0e7ff; }
.paper-action--pdf   { color: #059669; }
.paper-action--pdf:hover   { background: #d1fae5; }
.paper-action--ans   { color: #d97706; }
.paper-action--ans:hover   { background: #fef3c7; }
.paper-action--delete { color: #dc2626; }
.paper-action--delete:hover { background: #fee2e2; }
.paper-action--delete:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
