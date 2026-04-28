<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ quizzes: Array });

import { useFormat } from '@/Composables/useFormat';
const { formatDateTime: fmt } = useFormat();

const statusLabel = (q) => {
    if (q.attempt_status === 'submitted' || q.attempt_status === 'graded') return 'Completed';
    if (q.attempt_status === 'in_progress') return 'In Progress';
    if (!q.is_active) return 'Not Available';
    return 'Available';
};

const statusClass = (q) => {
    if (q.attempt_status === 'submitted' || q.attempt_status === 'graded') return 'badge-green';
    if (q.attempt_status === 'in_progress') return 'badge-amber';
    if (!q.is_active) return 'badge-gray';
    return 'badge-blue';
};
</script>

<template>
    <SchoolLayout title="My Quizzes">
        <div class="page-header">
            <h1 class="page-header-title">My Quizzes</h1>
        </div>

        <div class="quizzes-grid">
            <div v-for="q in quizzes" :key="q.id" class="quiz-card card">
                <div class="card-body">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:10px;">
                        <h3 style="font-size:1rem;font-weight:700;color:#1e293b;flex:1;">{{ q.title }}</h3>
                        <span class="badge" :class="statusClass(q)">{{ statusLabel(q) }}</span>
                    </div>

                    <div style="font-size:.8rem;color:#64748b;margin-bottom:12px;">
                        <div v-if="q.subject">Subject: {{ q.subject }}</div>
                        <div>Duration: {{ q.duration_minutes }} min &nbsp;·&nbsp; {{ q.total_marks }} marks</div>
                        <div v-if="q.end_at">Closes: {{ fmt(q.end_at) }}</div>
                    </div>

                    <!-- Result badge -->
                    <div v-if="q.percentage !== null" style="background:#f1f5f9;border-radius:8px;padding:10px;margin-bottom:12px;text-align:center;">
                        <div style="font-size:1.25rem;font-weight:700;" :style="{ color: q.passed ? '#16a34a' : '#dc2626' }">{{ q.percentage }}%</div>
                        <div style="font-size:.75rem;color:#94a3b8;">{{ q.passed ? 'Passed' : 'Not Passed' }}</div>
                    </div>

                    <!-- Action -->
                    <div style="text-align:center;">
                        <Link v-if="q.attempt_status === 'in_progress'" :href="`/school/quiz/${q.id}/take`" class="btn btn-amber btn-sm">Continue</Link>
                        <Link v-else-if="!q.attempt_status && q.is_active" :href="`/school/quiz/${q.id}/take`" class="btn btn-primary btn-sm">Start Quiz</Link>
                        <Link v-else-if="q.attempt_id" :href="`/school/quiz/${q.id}/my-result/${q.attempt_id}`" class="btn btn-secondary btn-sm">View Result</Link>
                        <span v-else style="font-size:.8rem;color:#94a3b8;">Not currently available</span>
                    </div>
                </div>
            </div>

            <div v-if="!quizzes?.length" style="grid-column:1/-1;text-align:center;padding:40px;color:#94a3b8;">
                No quizzes assigned to you yet.
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.quizzes-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px; }
.quiz-card .card-body { padding:18px; }
.btn-amber { background:#f59e0b;color:#fff;border-color:#f59e0b; }
</style>
