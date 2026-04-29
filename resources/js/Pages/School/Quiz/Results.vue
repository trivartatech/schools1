<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Table from '@/Components/ui/Table.vue';
import SortableTh from '@/Components/ui/SortableTh.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { useTableSort } from '@/Composables/useTableSort';
import { useFormat } from '@/Composables/useFormat';
import { computed, ref } from 'vue';

const props = defineProps({ quiz: Object });
const { formatDateTime: fmt } = useFormat();

const attempts = computed(() => props.quiz.attempts ?? []);

// ── Filters ────────────────────────────────────────────────────────
const search = ref('');
const statusFilter = ref('');
const resultFilter = ref('');

const filterActive = computed(() => !!(search.value || statusFilter.value || resultFilter.value));
const clearFilters = () => { search.value = ''; statusFilter.value = ''; resultFilter.value = ''; };

const filteredAttempts = computed(() =>
    attempts.value.filter(a => {
        if (search.value) {
            const name = `${a.student?.first_name ?? ''} ${a.student?.last_name ?? ''}`.toLowerCase();
            if (!name.includes(search.value.toLowerCase())) return false;
        }
        if (statusFilter.value && a.status !== statusFilter.value) return false;
        if (resultFilter.value === 'pass' && a.passed !== true) return false;
        if (resultFilter.value === 'fail' && a.passed !== false) return false;
        if (resultFilter.value === 'pending' && a.passed !== null) return false;
        return true;
    })
);

// ── Sort ───────────────────────────────────────────────────────────
const { sortKey, sortDir, toggleSort, sortRows } = useTableSort('percentage', 'desc');
const sortedAttempts = computed(() => sortRows(filteredAttempts.value, {
    getValue: (row, key) => {
        if (key === 'student_name') return `${row.student?.first_name ?? ''} ${row.student?.last_name ?? ''}`.trim();
        if (key === 'percentage') return row.percentage !== null ? Number(row.percentage) : null;
        if (key === 'score') return row.score !== null ? Number(row.score) : null;
        if (key === 'passed') return row.passed === null ? null : (row.passed ? 1 : 0);
        return row[key];
    },
}));

// ── Stats ──────────────────────────────────────────────────────────
const avgScore = computed(() => {
    const graded = attempts.value.filter(a => a.percentage !== null);
    if (!graded.length) return null;
    return (graded.reduce((s, a) => s + parseFloat(a.percentage), 0) / graded.length).toFixed(1);
});
const passRate = computed(() => {
    const graded = attempts.value.filter(a => a.passed !== null);
    if (!graded.length) return null;
    return Math.round(graded.filter(a => a.passed).length / graded.length * 100);
});
const pendingGrading = computed(() => attempts.value.filter(a => a.status === 'submitted').length);

const stats = computed(() => [
    { label: 'Total Attempts', value: attempts.value.length, color: 'accent' },
    { label: 'Average Score',  value: avgScore.value !== null ? `${avgScore.value}%` : '—', color: 'success' },
    { label: 'Pass Rate',      value: passRate.value !== null ? `${passRate.value}%` : '—', color: 'purple' },
    { label: 'Pending Grading', value: pendingGrading.value, color: 'warning' },
]);
</script>

<template>
    <SchoolLayout :title="`Results — ${quiz.title}`">
        <PageHeader
            :title="`${quiz.title} — Results`"
            back-href="/school/quiz"
            back-label="← Back to quizzes"
        >
            <template #meta>
                <span class="badge badge-blue">{{ quiz.questions?.length ?? 0 }} Questions</span>
                <span class="badge badge-gray">{{ quiz.total_marks }} Total Marks</span>
            </template>
        </PageHeader>

        <StatsRow :cols="4" :stats="stats" />

        <FilterBar :active="filterActive" @clear="clearFilters">
            <div class="fb-search">
                <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input v-model="search" type="search" placeholder="Search student name...">
            </div>
            <select v-model="statusFilter" style="width:160px;">
                <option value="">All Statuses</option>
                <option value="in_progress">In Progress</option>
                <option value="submitted">Submitted</option>
                <option value="auto_submitted">Auto-Submitted</option>
                <option value="graded">Graded</option>
            </select>
            <select v-model="resultFilter" style="width:140px;">
                <option value="">All Results</option>
                <option value="pass">Pass</option>
                <option value="fail">Fail</option>
                <option value="pending">Pending</option>
            </select>
        </FilterBar>

        <div class="card">
            <Table :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
                <thead>
                    <tr>
                        <SortableTh sort-key="student_name">Student</SortableTh>
                        <SortableTh sort-key="started_at">Started</SortableTh>
                        <SortableTh sort-key="submitted_at">Submitted</SortableTh>
                        <SortableTh sort-key="score" align="center">Score</SortableTh>
                        <SortableTh sort-key="percentage" align="center">%</SortableTh>
                        <SortableTh sort-key="passed" align="center">Result</SortableTh>
                        <SortableTh sort-key="tab_switches" align="center">Tab Switches</SortableTh>
                        <SortableTh sort-key="status">Status</SortableTh>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in sortedAttempts" :key="a.id">
                        <td class="student-cell">{{ a.student?.first_name }} {{ a.student?.last_name }}</td>
                        <td class="time-cell">{{ fmt(a.started_at) }}</td>
                        <td class="time-cell">{{ a.submitted_at ? fmt(a.submitted_at) : '—' }}</td>
                        <td class="num-cell">{{ a.score !== null ? `${a.score} / ${quiz.total_marks}` : '—' }}</td>
                        <td class="num-cell">{{ a.percentage !== null ? `${a.percentage}%` : '—' }}</td>
                        <td style="text-align:center;">
                            <span v-if="a.passed === true"  class="badge badge-green">Pass</span>
                            <span v-else-if="a.passed === false" class="badge badge-red">Fail</span>
                            <span v-else class="badge badge-gray">Pending</span>
                        </td>
                        <td class="num-cell" :class="{ 'tab-warn': a.tab_switches > 2 }">{{ a.tab_switches }}</td>
                        <td><span class="badge badge-gray status-badge">{{ a.status.replace('_', ' ') }}</span></td>
                    </tr>
                </tbody>
            </Table>
            <EmptyState
                v-if="!sortedAttempts.length"
                :title="filterActive ? 'No attempts match your filters' : 'No attempts yet'"
                :description="filterActive
                    ? 'Try clearing your filters or adjust your search.'
                    : 'Once students start taking this quiz, their attempts will appear here.'"
                tone="muted"
            />
        </div>
    </SchoolLayout>
</template>

<style scoped>
.student-cell { font-weight: 600; color: var(--text-primary, #0f172a); }
.time-cell    { font-size: .78rem; color: var(--text-secondary, #475569); }
.num-cell     { text-align: center; font-family: monospace; font-weight: 600; }
.tab-warn     { color: #dc2626; }
.status-badge { text-transform: capitalize; }
</style>
