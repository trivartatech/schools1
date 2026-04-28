<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Table from '@/Components/ui/Table.vue';
import SortableTh from '@/Components/ui/SortableTh.vue';
import { useTableSort } from '@/Composables/useTableSort';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useConfirm } from '@/Composables/useConfirm';

const confirm = useConfirm();

const props = defineProps({ quizzes: Array });

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

import { useFormat } from '@/Composables/useFormat';
const { formatDateTime: fmt } = useFormat();

const { sortKey, sortDir, toggleSort, sortRows } = useTableSort('start_at', 'desc');
const sortedQuizzes = computed(() => sortRows(props.quizzes || [], {
    getValue: (row, key) => {
        if (key === 'subject_name') return row.subject?.name ?? '';
        return row[key];
    },
}));
</script>

<template>
    <SchoolLayout title="Online Quizzes">
        <PageHeader title="Online Quizzes">
            <template #actions>
                <Link href="/school/quiz/create" class="btn btn-primary btn-sm">+ Create Quiz</Link>
            </template>
        </PageHeader>

        <div class="card">
            <Table :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
                <thead>
                    <tr>
                        <SortableTh sort-key="title">Quiz</SortableTh>
                        <SortableTh sort-key="subject_name">Subject</SortableTh>
                        <SortableTh sort-key="type">Type</SortableTh>
                        <SortableTh sort-key="questions_count" align="center">Questions</SortableTh>
                        <SortableTh sort-key="duration_minutes" align="center">Duration</SortableTh>
                        <SortableTh sort-key="total_marks" align="center">Marks</SortableTh>
                        <SortableTh sort-key="start_at">Window</SortableTh>
                        <SortableTh sort-key="status">Status</SortableTh>
                        <SortableTh sort-key="attempts_count" align="center">Attempts</SortableTh>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="q in sortedQuizzes" :key="q.id">
                        <td>
                            <div style="font-weight:500;">{{ q.title }}</div>
                            <div style="font-size:.75rem;color:#94a3b8;">by {{ q.created_by?.name }}</div>
                        </td>
                        <td>{{ q.subject?.name || '—' }}</td>
                        <td style="text-transform:capitalize;">{{ q.type }}</td>
                        <td style="text-align:center;">{{ q.questions_count }}</td>
                        <td style="text-align:center;">{{ q.duration_minutes }} min</td>
                        <td style="text-align:center;">{{ q.total_marks }}</td>
                        <td style="font-size:.75rem;">
                            <div>{{ fmt(q.start_at) }}</div>
                            <div style="color:#94a3b8;">to {{ fmt(q.end_at) }}</div>
                        </td>
                        <td><span class="badge" :class="statusBadge(q.status)">{{ q.status }}</span></td>
                        <td style="text-align:center;">{{ q.attempts_count }}</td>
                        <td>
                            <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                <Link :href="`/school/quiz/${q.id}/edit`" class="btn btn-secondary btn-xs">Edit</Link>
                                <Link :href="`/school/quiz/${q.id}/results`" class="btn btn-secondary btn-xs">Results</Link>
                                <Button variant="danger" size="xs" @click="deleteQuiz(q.id)">Del</Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!sortedQuizzes.length">
                        <td colspan="10" style="text-align:center;padding:32px;color:#94a3b8;">No quizzes created yet.</td>
                    </tr>
                </tbody>
            </Table>
        </div>
    </SchoolLayout>
</template>
