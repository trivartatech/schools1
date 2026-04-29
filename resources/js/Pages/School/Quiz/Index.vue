<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Table from '@/Components/ui/Table.vue';
import SortableTh from '@/Components/ui/SortableTh.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { useTableSort } from '@/Composables/useTableSort';
import { useConfirm } from '@/Composables/useConfirm';
import { useFormat } from '@/Composables/useFormat';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const confirm = useConfirm();
const { formatDateTime: fmt } = useFormat();

const props = defineProps({ quizzes: Array });

// ── Filters ────────────────────────────────────────────────────────
const search = ref('');
const subjectFilter = ref('');
const typeFilter = ref('');
const statusFilter = ref('');

const filterActive = computed(() =>
    !!(search.value || subjectFilter.value || typeFilter.value || statusFilter.value)
);
const clearFilters = () => {
    search.value = '';
    subjectFilter.value = '';
    typeFilter.value = '';
    statusFilter.value = '';
};

const subjectOptions = computed(() => {
    const seen = new Map();
    (props.quizzes || []).forEach(q => {
        if (q.subject?.id) seen.set(q.subject.id, q.subject.name);
    });
    return Array.from(seen, ([id, name]) => ({ id, name }));
});

const filteredQuizzes = computed(() =>
    (props.quizzes || []).filter(q => {
        if (search.value && !q.title.toLowerCase().includes(search.value.toLowerCase())) return false;
        if (subjectFilter.value && String(q.subject?.id ?? '') !== String(subjectFilter.value)) return false;
        if (typeFilter.value && q.type !== typeFilter.value) return false;
        if (statusFilter.value && q.status !== statusFilter.value) return false;
        return true;
    })
);

// ── Stats ──────────────────────────────────────────────────────────
const stats = computed(() => {
    const list = props.quizzes || [];
    return [
        { label: 'Total Quizzes', value: list.length, color: 'accent' },
        { label: 'Published',     value: list.filter(q => q.status === 'published').length, color: 'success' },
        { label: 'Drafts',        value: list.filter(q => q.status === 'draft').length, color: 'warning' },
        { label: 'Total Attempts', value: list.reduce((s, q) => s + (q.attempts_count ?? 0), 0), color: 'info' },
    ];
});

// ── Sorting ────────────────────────────────────────────────────────
const { sortKey, sortDir, toggleSort, sortRows } = useTableSort('start_at', 'desc');
const sortedQuizzes = computed(() => sortRows(filteredQuizzes.value, {
    getValue: (row, key) => key === 'subject_name' ? (row.subject?.name ?? '') : row[key],
}));

const statusBadge = (s) => ({ draft: 'badge-gray', published: 'badge-green', closed: 'badge-amber' }[s] ?? 'badge-gray');

const deleteQuiz = async (id) => {
    const ok = await confirm({
        title: 'Delete quiz?',
        message: 'This quiz and all its questions will be permanently removed.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/quiz/${id}`, { preserveScroll: true });
};
</script>

<template>
    <SchoolLayout title="Online Quizzes">
        <PageHeader
            title="Online Quizzes"
            subtitle="Create and manage MCQ + descriptive quizzes for your students."
        >
            <template #actions>
                <Button as="link" href="/school/quiz/create">+ Create Quiz</Button>
            </template>
        </PageHeader>

        <StatsRow :cols="4" :stats="stats" />

        <FilterBar :active="filterActive" @clear="clearFilters">
            <div class="fb-search">
                <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input v-model="search" type="search" placeholder="Search by title...">
            </div>
            <select v-model="subjectFilter" style="width:160px;">
                <option value="">All Subjects</option>
                <option v-for="s in subjectOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <select v-model="typeFilter" style="width:140px;">
                <option value="">All Types</option>
                <option value="mcq">MCQ</option>
                <option value="descriptive">Descriptive</option>
                <option value="mixed">Mixed</option>
            </select>
            <select v-model="statusFilter" style="width:140px;">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="closed">Closed</option>
            </select>
        </FilterBar>

        <div class="card">
            <Table :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
                <thead>
                    <tr>
                        <SortableTh sort-key="title">Quiz</SortableTh>
                        <SortableTh sort-key="subject_name">Subject</SortableTh>
                        <SortableTh sort-key="type">Type</SortableTh>
                        <SortableTh sort-key="questions_count" align="center">Q</SortableTh>
                        <SortableTh sort-key="duration_minutes" align="center">Duration</SortableTh>
                        <SortableTh sort-key="total_marks" align="center">Marks</SortableTh>
                        <SortableTh sort-key="start_at">Window</SortableTh>
                        <SortableTh sort-key="status">Status</SortableTh>
                        <SortableTh sort-key="attempts_count" align="center">Attempts</SortableTh>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="q in sortedQuizzes" :key="q.id">
                        <td>
                            <div class="quiz-title">{{ q.title }}</div>
                            <div class="quiz-author">by {{ q.created_by?.name ?? '—' }}</div>
                        </td>
                        <td>{{ q.subject?.name || '—' }}</td>
                        <td style="text-transform:capitalize;">{{ q.type }}</td>
                        <td style="text-align:center;">{{ q.questions_count }}</td>
                        <td style="text-align:center;">{{ q.duration_minutes }} min</td>
                        <td style="text-align:center;">{{ q.total_marks }}</td>
                        <td class="window-cell">
                            <div v-if="q.start_at">{{ fmt(q.start_at) }}</div>
                            <div v-if="q.end_at" class="muted">to {{ fmt(q.end_at) }}</div>
                            <div v-if="!q.start_at && !q.end_at" class="muted">Always open</div>
                        </td>
                        <td><span class="badge" :class="statusBadge(q.status)">{{ q.status }}</span></td>
                        <td style="text-align:center;">{{ q.attempts_count }}</td>
                        <td style="text-align:right;">
                            <div class="row-actions">
                                <Button as="link" :href="`/school/quiz/${q.id}/edit`" variant="secondary" size="xs">Edit</Button>
                                <Button as="link" :href="`/school/quiz/${q.id}/results`" variant="secondary" size="xs">Results</Button>
                                <Button variant="danger" size="xs" @click="deleteQuiz(q.id)">Delete</Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </Table>
            <EmptyState
                v-if="!sortedQuizzes.length"
                :title="filterActive ? 'No quizzes match your filters' : 'No quizzes yet'"
                :description="filterActive ? 'Try clearing your filters or refining your search.' : 'Create your first quiz to start assessing students online.'"
                :action-label="filterActive ? '' : '+ Create Quiz'"
                :action-href="filterActive ? '' : '/school/quiz/create'"
            />
        </div>
    </SchoolLayout>
</template>

<style scoped>
.quiz-title { font-weight:600; color:var(--text-primary); }
.quiz-author { font-size:.72rem; color:var(--text-muted); margin-top:2px; }
.window-cell { font-size:.75rem; }
.window-cell .muted { color:var(--text-muted); }
.row-actions { display:inline-flex; gap:4px; flex-wrap:wrap; justify-content:flex-end; }
</style>
