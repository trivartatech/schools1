<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { useFormat } from '@/Composables/useFormat';
import { computed, ref } from 'vue';

const props = defineProps({ quizzes: Array });
const { formatDateTime: fmt } = useFormat();

const COMPLETED_STATUSES = ['submitted', 'graded', 'auto_submitted'];

const filter = ref('all');

const counts = computed(() => {
    const list = props.quizzes || [];
    return {
        total:      list.length,
        available:  list.filter(q => !q.attempt_status && q.is_active).length,
        inProgress: list.filter(q => q.attempt_status === 'in_progress').length,
        completed:  list.filter(q => COMPLETED_STATUSES.includes(q.attempt_status)).length,
    };
});

const stats = computed(() => [
    { label: 'Available',   value: counts.value.available,  color: 'info' },
    { label: 'In Progress', value: counts.value.inProgress, color: 'warning' },
    { label: 'Completed',   value: counts.value.completed,  color: 'success' },
]);

const filtered = computed(() => {
    const list = props.quizzes || [];
    if (filter.value === 'available')  return list.filter(q => !q.attempt_status && q.is_active);
    if (filter.value === 'inProgress') return list.filter(q => q.attempt_status === 'in_progress');
    if (filter.value === 'completed')  return list.filter(q => COMPLETED_STATUSES.includes(q.attempt_status));
    return list;
});

const statusLabel = (q) => {
    if (COMPLETED_STATUSES.includes(q.attempt_status)) return 'Completed';
    if (q.attempt_status === 'in_progress') return 'In Progress';
    if (!q.is_active) return 'Not Available';
    return 'Available';
};
const statusClass = (q) => {
    if (COMPLETED_STATUSES.includes(q.attempt_status)) return 'badge-green';
    if (q.attempt_status === 'in_progress') return 'badge-amber';
    if (!q.is_active) return 'badge-gray';
    return 'badge-blue';
};

const hasResult = (q) => q.percentage !== null && q.percentage !== undefined;
</script>

<template>
    <SchoolLayout title="My Quizzes">
        <PageHeader title="My Quizzes" subtitle="Online quizzes assigned to you." />

        <StatsRow :cols="3" :stats="stats" />

        <div class="quiz-tabs">
            <Button variant="tab" size="sm" :active="filter === 'all'"        @click="filter = 'all'">All ({{ counts.total }})</Button>
            <Button variant="tab" size="sm" :active="filter === 'available'"  @click="filter = 'available'">Available ({{ counts.available }})</Button>
            <Button variant="tab" size="sm" :active="filter === 'inProgress'" @click="filter = 'inProgress'">In Progress ({{ counts.inProgress }})</Button>
            <Button variant="tab" size="sm" :active="filter === 'completed'"  @click="filter = 'completed'">Completed ({{ counts.completed }})</Button>
        </div>

        <div v-if="filtered.length" class="quizzes-grid">
            <div v-for="q in filtered" :key="q.id" class="quiz-card card">
                <div class="card-body">
                    <div class="quiz-card-head">
                        <h3 class="quiz-card-title">{{ q.title }}</h3>
                        <span class="badge" :class="statusClass(q)">{{ statusLabel(q) }}</span>
                    </div>

                    <div class="quiz-card-meta">
                        <div v-if="q.subject"><strong>Subject:</strong> {{ q.subject }}</div>
                        <div><strong>Duration:</strong> {{ q.duration_minutes }} min &middot; {{ q.total_marks }} marks</div>
                        <div v-if="q.end_at"><strong>Closes:</strong> {{ fmt(q.end_at) }}</div>
                    </div>

                    <div v-if="hasResult(q)" class="quiz-card-score" :class="q.passed ? 'is-pass' : 'is-fail'">
                        <div class="score-pct">{{ q.percentage }}%</div>
                        <div class="score-label">{{ q.passed ? 'Passed' : 'Not Passed' }}</div>
                    </div>

                    <div class="quiz-card-actions">
                        <Button v-if="q.attempt_status === 'in_progress'"
                            as="link" :href="`/school/quiz/${q.id}/take`"
                            variant="warning" size="sm" block>
                            Continue
                        </Button>
                        <Button v-else-if="!q.attempt_status && q.is_active"
                            as="link" :href="`/school/quiz/${q.id}/take`"
                            size="sm" block>
                            Start Quiz
                        </Button>
                        <Button v-else-if="q.attempt_id"
                            as="link" :href="`/school/quiz/${q.id}/my-result/${q.attempt_id}`"
                            variant="secondary" size="sm" block>
                            View Result
                        </Button>
                        <span v-else class="quiz-card-na">Not currently available</span>
                    </div>
                </div>
            </div>
        </div>

        <EmptyState
            v-else
            :title="filter === 'all' ? 'No quizzes yet' : 'Nothing in this view'"
            :description="filter === 'all'
                ? 'No quizzes have been assigned to you. Check back later.'
                : 'No quizzes match this filter.'"
            tone="muted"
        />
    </SchoolLayout>
</template>

<style scoped>
.quiz-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 16px;
    border-bottom: 1px solid var(--border, #e2e8f0);
    padding-bottom: 4px;
    flex-wrap: wrap;
}
.quizzes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.quiz-card {
    display: flex;
    flex-direction: column;
}
.quiz-card .card-body {
    padding: 18px;
    display: flex;
    flex-direction: column;
    height: 100%;
}
.quiz-card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 10px;
}
.quiz-card-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary, #0f172a);
    margin: 0;
    flex: 1;
    line-height: 1.3;
}
.quiz-card-meta {
    font-size: .78rem;
    color: var(--text-muted, #94a3b8);
    margin-bottom: 14px;
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.quiz-card-meta strong {
    color: var(--text-secondary, #475569);
    font-weight: 600;
}
.quiz-card-score {
    background: var(--border-light, #f1f5f9);
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 14px;
    text-align: center;
}
.quiz-card-score.is-pass { background: #d1fae5; }
.quiz-card-score.is-fail { background: #fee2e2; }
.score-pct {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-primary, #0f172a);
    line-height: 1;
}
.is-pass .score-pct { color: #059669; }
.is-fail .score-pct { color: #dc2626; }
.score-label {
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--text-muted, #94a3b8);
    margin-top: 4px;
}
.is-pass .score-label { color: #059669; }
.is-fail .score-label { color: #dc2626; }
.quiz-card-actions { margin-top: auto; }
.quiz-card-na {
    display: block;
    text-align: center;
    font-size: .8rem;
    color: var(--text-muted, #94a3b8);
    padding: 8px;
}
</style>
