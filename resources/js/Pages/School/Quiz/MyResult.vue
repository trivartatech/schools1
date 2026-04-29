<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import { computed } from 'vue';

const props = defineProps({ quiz: Object, attempt: Object });

const responses = computed(() => props.attempt.responses ?? []);

const answerText = (response) => {
    if (response.answer === null || response.answer === undefined || response.answer === '') return 'Not answered';
    const q = response.question;
    if (q?.type === 'mcq' && q.options && q.options[parseInt(response.answer)]) {
        return q.options[parseInt(response.answer)].text;
    }
    return response.answer;
};

const correctText = (response) => {
    const q = response.question;
    if (q?.type === 'mcq' && q.options && q.correct_answer !== null) {
        const idx = parseInt(q.correct_answer);
        return q.options[idx]?.text ?? q.correct_answer;
    }
    return q?.correct_answer ?? '—';
};

const responseClass = (resp) => {
    if (resp.is_correct === true) return 'is-correct';
    if (resp.is_correct === false) return 'is-wrong';
    return 'is-pending';
};
</script>

<template>
    <SchoolLayout title="Quiz Result">
        <PageHeader
            :title="`${quiz.title} — My Result`"
            back-href="/school/quiz/my-quizzes"
            back-label="← Back to my quizzes"
        />

        <!-- Score summary -->
        <div class="result-summary card" :class="attempt.passed ? 'is-pass' : 'is-fail'">
            <div class="card-body">
                <div class="score-pct">{{ attempt.percentage }}%</div>
                <div class="score-text">{{ attempt.score }} / {{ quiz.total_marks }} marks</div>
                <div class="score-badge">
                    <span class="badge" :class="attempt.passed ? 'badge-green' : 'badge-red'">
                        {{ attempt.passed ? 'PASSED' : 'NOT PASSED' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Per-question review -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Question Review</span>
            </div>
            <div class="card-body review-body">
                <div
                    v-for="(resp, i) in responses"
                    :key="resp.id"
                    class="review-q"
                    :class="responseClass(resp)"
                >
                    <div class="review-q-head">
                        <strong>Q{{ i + 1 }}: {{ resp.question?.question_text }}</strong>
                        <span v-if="resp.is_correct === true"  class="badge badge-green">Correct</span>
                        <span v-else-if="resp.is_correct === false" class="badge badge-red">Wrong</span>
                        <span v-else class="badge badge-gray">Pending</span>
                    </div>
                    <div class="review-q-body">
                        <div><span class="muted-label">Your answer:</span> <strong>{{ answerText(resp) }}</strong></div>
                        <div v-if="resp.is_correct === false">
                            <span class="muted-label">Correct answer:</span>
                            <strong class="correct-strong">{{ correctText(resp) }}</strong>
                        </div>
                        <div v-if="resp.question?.explanation" class="explanation">
                            <span class="muted-label">Explanation:</span>
                            {{ resp.question.explanation }}
                        </div>
                    </div>
                    <div class="review-q-marks">
                        Marks: <strong>{{ resp.marks_awarded ?? '—' }}</strong> / {{ resp.question?.marks }}
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-actions">
            <Button as="link" href="/school/quiz/my-quizzes" variant="secondary">Back to My Quizzes</Button>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.result-summary {
    max-width: 460px;
    margin-bottom: 20px;
}
.result-summary .card-body {
    text-align: center;
    padding: 28px;
}
.result-summary.is-pass {
    border-color: #86efac;
    background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%);
}
.result-summary.is-fail {
    border-color: #fca5a5;
    background: linear-gradient(180deg, #fef2f2 0%, #ffffff 100%);
}
.score-pct {
    font-size: 2.5rem;
    font-weight: 800;
    line-height: 1;
    color: var(--text-primary, #0f172a);
}
.is-pass .score-pct { color: #16a34a; }
.is-fail .score-pct { color: #dc2626; }
.score-text {
    font-size: .9rem;
    color: var(--text-secondary, #475569);
    margin-top: 6px;
}
.score-badge { margin-top: 12px; }
.score-badge .badge {
    font-size: .85rem;
    padding: 6px 16px;
    letter-spacing: .05em;
}

.review-body {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.review-q {
    border: 1.5px solid var(--border, #e2e8f0);
    border-radius: 10px;
    padding: 14px 16px;
    background: var(--surface, #fff);
}
.review-q.is-correct { border-color: #86efac; background: #f0fdf4; }
.review-q.is-wrong   { border-color: #fca5a5; background: #fef2f2; }
.review-q.is-pending { border-color: var(--border, #e2e8f0); }

.review-q-head {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
    align-items: flex-start;
}
.review-q-head strong {
    color: var(--text-primary, #0f172a);
    font-weight: 600;
    line-height: 1.4;
    flex: 1;
}

.review-q-body {
    font-size: .85rem;
    display: flex;
    flex-direction: column;
    gap: 6px;
    color: var(--text-secondary, #475569);
}
.muted-label { color: var(--text-muted, #94a3b8); margin-right: 4px; }
.correct-strong { color: #16a34a; }

.explanation {
    margin-top: 4px;
    padding: 10px 12px;
    background: var(--border-light, #f1f5f9);
    border-radius: 8px;
    font-size: .8rem;
    color: var(--text-secondary, #475569);
}

.review-q-marks {
    font-size: .72rem;
    color: var(--text-muted, #94a3b8);
    margin-top: 8px;
    text-align: right;
}

.footer-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}
</style>
