<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Table from '@/Components/ui/Table.vue';
import SortableTh from '@/Components/ui/SortableTh.vue';
import { useTableSort } from '@/Composables/useTableSort';
import { computed } from 'vue';

const props = defineProps({ quiz: Object });

const attempts = computed(() => props.quiz.attempts ?? []);

const { sortKey, sortDir, toggleSort, sortRows } = useTableSort('percentage', 'desc');
const sortedAttempts = computed(() => sortRows(attempts.value, {
    getValue: (row, key) => {
        if (key === 'student_name') return `${row.student?.first_name ?? ''} ${row.student?.last_name ?? ''}`.trim();
        if (key === 'percentage') return row.percentage !== null ? Number(row.percentage) : null;
        if (key === 'score') return row.score !== null ? Number(row.score) : null;
        if (key === 'passed') return row.passed === null ? null : (row.passed ? 1 : 0);
        return row[key];
    },
}));
const avgScore  = computed(() => {
    const graded = attempts.value.filter(a => a.percentage !== null);
    if (!graded.length) return null;
    return (graded.reduce((s, a) => s + parseFloat(a.percentage), 0) / graded.length).toFixed(1);
});
const passRate = computed(() => {
    const graded = attempts.value.filter(a => a.passed !== null);
    if (!graded.length) return null;
    return Math.round(graded.filter(a => a.passed).length / graded.length * 100);
});

import { useFormat } from '@/Composables/useFormat';
const { formatDateTime: fmt } = useFormat();
</script>

<template>
    <SchoolLayout :title="`Results — ${quiz.title}`">
        <PageHeader>
            <template #title>
                <h1 class="page-header-title">{{ quiz.title }} — Results</h1>
            </template>
        </PageHeader>

        <!-- Summary -->
        <div class="results-stats">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-value" style="color:#1d4ed8;">{{ attempts.length }}</div>
                    <div class="stat-label">Total Attempts</div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-value" style="color:#16a34a;">{{ avgScore ?? '—' }}%</div>
                    <div class="stat-label">Average Score</div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-value" style="color:#7c3aed;">{{ passRate !== null ? passRate + '%' : '—' }}</div>
                    <div class="stat-label">Pass Rate</div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-value" style="color:#d97706;">{{ attempts.filter(a => a.status === 'submitted').length }}</div>
                    <div class="stat-label">Pending Grading</div>
                </div>
            </div>
        </div>

        <!-- Attempts table -->
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
                        <td>{{ a.student?.first_name }} {{ a.student?.last_name }}</td>
                        <td style="font-size:.8rem;">{{ fmt(a.started_at) }}</td>
                        <td style="font-size:.8rem;">{{ fmt(a.submitted_at) }}</td>
                        <td style="text-align:center;font-weight:600;">{{ a.score !== null ? a.score + ' / ' + quiz.total_marks : '—' }}</td>
                        <td style="text-align:center;">{{ a.percentage !== null ? a.percentage + '%' : '—' }}</td>
                        <td style="text-align:center;">
                            <span v-if="a.passed !== null" class="badge" :class="a.passed ? 'badge-green' : 'badge-red'">
                                {{ a.passed ? 'Pass' : 'Fail' }}
                            </span>
                            <span v-else class="badge badge-gray">Pending</span>
                        </td>
                        <td style="text-align:center;" :style="{ color: a.tab_switches > 2 ? '#dc2626' : undefined }">{{ a.tab_switches }}</td>
                        <td><span class="badge badge-gray" style="text-transform:capitalize;">{{ a.status }}</span></td>
                    </tr>
                    <tr v-if="!sortedAttempts.length">
                        <td colspan="8" style="text-align:center;padding:32px;color:#94a3b8;">No attempts yet.</td>
                    </tr>
                </tbody>
            </Table>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.results-stats { display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px; }
.stat-card .card-body { padding:16px; }
.stat-value { font-size:1.5rem;font-weight:700; }
.stat-label { font-size:.75rem;color:var(--text-muted);margin-top:4px; }
</style>
