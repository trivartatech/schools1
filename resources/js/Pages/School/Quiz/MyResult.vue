<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ quiz: Object, attempt: Object });

const responses = props.attempt.responses ?? [];

const answerText = (response) => {
    if (response.answer === null || response.answer === undefined) return 'Not answered';
    const q = response.question;
    if (q.type === 'mcq' && q.options && q.options[parseInt(response.answer)]) {
        return q.options[parseInt(response.answer)].text;
    }
    return response.answer;
};

const correctText = (response) => {
    const q = response.question;
    if (q.type === 'mcq' && q.options && q.correct_answer !== null) {
        const idx = parseInt(q.correct_answer);
        return q.options[idx]?.text ?? q.correct_answer;
    }
    return q.correct_answer ?? '—';
};
</script>

<template>
    <SchoolLayout title="Quiz Result">
        <PageHeader>
            <template #title>
                <h1 class="page-header-title">{{ quiz.title }} — My Result</h1>
            </template>
        </PageHeader>

        <!-- Score summary -->
        <div class="card" style="max-width:500px;margin-bottom:20px;">
            <div class="card-body" style="text-align:center;padding:28px;">
                <div style="font-size:3rem;margin-bottom:8px;">{{ attempt.passed ? '🎉' : '📖' }}</div>
                <div style="font-size:2rem;font-weight:700;" :style="{ color: attempt.passed ? '#16a34a' : '#dc2626' }">
                    {{ attempt.percentage }}%
                </div>
                <div style="color:#64748b;margin-top:4px;">{{ attempt.score }} / {{ quiz.total_marks }} marks</div>
                <div class="badge mt-3" :class="attempt.passed ? 'badge-green' : 'badge-red'" style="font-size:.9rem;padding:6px 16px;margin-top:10px;">
                    {{ attempt.passed ? 'PASSED' : 'NOT PASSED' }}
                </div>
            </div>
        </div>

        <!-- Per-question review -->
        <div class="card">
            <div class="card-header"><span class="card-title">Question Review</span></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">
                <div v-for="(resp, i) in responses" :key="resp.id"
                     :style="{ border: '2px solid', borderColor: resp.is_correct === true ? '#86efac' : (resp.is_correct === false ? '#fca5a5' : '#e2e8f0'), borderRadius: '8px', padding: '14px' }">
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                        <strong>Q{{ i + 1 }}: {{ resp.question?.question_text }}</strong>
                        <span v-if="resp.is_correct !== null" class="badge" :class="resp.is_correct ? 'badge-green' : 'badge-red'">
                            {{ resp.is_correct ? '✓ Correct' : '✗ Wrong' }}
                        </span>
                        <span v-else class="badge badge-gray">Pending</span>
                    </div>
                    <div style="font-size:.85rem;">
                        <div><span style="color:#64748b;">Your answer:</span> <strong>{{ answerText(resp) }}</strong></div>
                        <div v-if="resp.is_correct === false"><span style="color:#64748b;">Correct answer:</span> <strong style="color:#16a34a;">{{ correctText(resp) }}</strong></div>
                        <div v-if="resp.question?.explanation" style="margin-top:6px;padding:8px;background:#f1f5f9;border-radius:6px;color:#475569;">
                            💡 {{ resp.question.explanation }}
                        </div>
                    </div>
                    <div style="font-size:.75rem;color:#94a3b8;margin-top:6px;">Marks: {{ resp.marks_awarded ?? '—' }} / {{ resp.question?.marks }}</div>
                </div>
            </div>
        </div>

        <div style="margin-top:16px;">
            <Link href="/school/quiz/my-quizzes" class="btn btn-secondary btn-sm">Back to My Quizzes</Link>
        </div>
    </SchoolLayout>
</template>
