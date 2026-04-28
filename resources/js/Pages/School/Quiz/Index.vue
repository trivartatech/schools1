<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { Link, router } from '@inertiajs/vue3';
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
</script>

<template>
    <SchoolLayout title="Online Quizzes">
        <PageHeader title="Online Quizzes">
            <template #actions>
                <Link href="/school/quiz/create" class="btn btn-primary btn-sm">+ Create Quiz</Link>
            </template>
        </PageHeader>

        <div class="card">
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Quiz</th>
                            <th>Subject</th>
                            <th>Type</th>
                            <th style="text-align:center;">Questions</th>
                            <th style="text-align:center;">Duration</th>
                            <th style="text-align:center;">Marks</th>
                            <th>Window</th>
                            <th>Status</th>
                            <th style="text-align:center;">Attempts</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="q in quizzes" :key="q.id">
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
                        <tr v-if="!quizzes?.length">
                            <td colspan="10" style="text-align:center;padding:32px;color:#94a3b8;">No quizzes created yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SchoolLayout>
</template>
