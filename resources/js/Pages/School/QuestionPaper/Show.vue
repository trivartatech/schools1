<script setup>
import { ref } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    paper: Object,
});

const showAnswers = ref(false);

const typeLabels = {
    mcq:          'Multiple Choice',
    short_answer: 'Short Answer',
    long_answer:  'Long Answer',
    fill_blank:   'Fill in the Blanks',
    true_false:   'True / False',
};

const DIFFICULTY_BADGE = {
    easy:   'badge-green',
    medium: 'badge-amber',
    hard:   'badge-red',
    mixed:  'badge-indigo',
};
</script>

<template>
    <SchoolLayout title="Question Paper">

        <!-- Page Header -->
        <PageHeader>
            <template #title>
                <h1 class="page-header-title">{{ paper.title }}</h1>
            </template>
            <template #subtitle>
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:6px;">
                    <span class="badge badge-gray">{{ paper.course_class?.name }}</span>
                    <span class="badge badge-blue">{{ paper.subject?.name }}</span>
                    <span v-if="paper.exam_type" class="badge badge-purple">{{ paper.exam_type }}</span>
                    <span class="badge" :class="DIFFICULTY_BADGE[paper.difficulty] || 'badge-gray'">
                        {{ paper.difficulty?.charAt(0).toUpperCase() + paper.difficulty?.slice(1) }}
                    </span>
                    <span style="font-size:0.8125rem;color:#64748b;">
                        {{ paper.total_marks }} Marks &middot; {{ paper.duration_minutes }} min
                    </span>
                </div>
            </template>
            <template #actions>
                <button @click="showAnswers = !showAnswers" class="answer-toggle"
                        :class="{ 'answer-toggle--on': showAnswers }">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ showAnswers ? 'Hide Answers' : 'Show Answers' }}
                </button>
                <Button variant="secondary" size="sm" as="link"
                        :href="`/school/question-papers/${paper.id}/pdf?with_answers=1`"
                        target="_blank">
                    Answer Key PDF
                </Button>
                <Button size="sm" as="link"
                        :href="`/school/question-papers/${paper.id}/pdf`"
                        target="_blank">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Download PDF
                </Button>
                <Button variant="icon" size="sm" as="link" href="/school/question-papers" aria-label="Back">
                    <template #icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </template>
                </Button>
            </template>
        </PageHeader>

        <!-- General Instructions -->
        <div v-if="paper.instructions" class="card" style="margin-bottom:20px;border-left:4px solid #f59e0b;">
            <div class="card-body" style="padding:14px 20px;">
                <p style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#92400e;margin:0 0 6px;">General Instructions</p>
                <p style="font-size:0.875rem;color:#78350f;white-space:pre-line;margin:0;">{{ paper.instructions }}</p>
            </div>
        </div>

        <!-- Sections -->
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div v-for="section in paper.sections" :key="section.id" class="card" style="overflow:hidden;">

                <!-- Section Header -->
                <div class="card-header" style="background:#f8fafc;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="font-weight:700;color:#0f172a;">{{ section.name }}</span>
                        <span class="badge badge-indigo">{{ typeLabels[section.question_type] || section.question_type }}</span>
                    </div>
                    <div style="font-size:0.8125rem;color:#64748b;">
                        {{ section.marks_per_question }} mark{{ section.marks_per_question > 1 ? 's' : '' }} each
                        &middot; {{ section.items?.length || section.num_questions }} questions
                        &middot; <strong style="color:#0f172a;">{{ section.marks_per_question * (section.items?.length || section.num_questions) }} marks</strong>
                    </div>
                </div>

                <!-- Section Instructions -->
                <div v-if="section.instructions" style="padding:8px 20px;font-size:0.8125rem;color:#64748b;font-style:italic;border-bottom:1px solid var(--border-light);background:#fafbfc;">
                    {{ section.instructions }}
                </div>

                <!-- Questions -->
                <div style="divide-y:1px solid #f1f5f9;">
                    <div v-for="(item, qi) in section.items" :key="item.id" class="question-row">
                        <span class="q-num">{{ qi + 1 }}.</span>
                        <div style="flex:1;">
                            <p class="q-text">{{ item.question_text }}</p>

                            <!-- MCQ Options -->
                            <div v-if="section.question_type === 'mcq'" class="mcq-options">
                                <div v-for="opt in ['a','b','c','d']" :key="opt"
                                     class="mcq-opt"
                                     :class="{ 'mcq-opt--correct': showAnswers && item.correct_answer?.toLowerCase() === opt }">
                                    <span class="mcq-opt-letter">{{ opt.toUpperCase() }}</span>
                                    <span>{{ item['option_' + opt] }}</span>
                                </div>
                            </div>

                            <!-- Answer -->
                            <div v-if="showAnswers && item.correct_answer" class="answer-pill">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:13px;height:13px;flex-shrink:0;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                {{ item.correct_answer }}
                            </div>
                        </div>
                        <span class="q-marks">[{{ item.marks }}M]</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align:center;padding:24px 0 8px;font-size:0.8125rem;color:#94a3b8;">
            Created by <strong style="color:#64748b;">{{ paper.created_by?.name || 'Unknown' }}</strong>
            on {{ school.fmtDate(paper.created_at) }}
        </div>

    </SchoolLayout>
</template>

<style scoped>
.answer-toggle {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 8px;
    font-size: 0.8125rem; font-weight: 600;
    border: 1.5px solid #d1d5db; background: #f8fafc;
    color: #475569; cursor: pointer;
    transition: all 0.15s;
}
.answer-toggle:hover { border-color: #6366f1; color: #6366f1; background: #eef2ff; }
.answer-toggle--on { background: #f0fdf4; border-color: #86efac; color: #166534; }
.answer-toggle .w-4 { width: 16px; height: 16px; }

.question-row {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
}
.question-row:last-child { border-bottom: none; }

.q-num { font-size: 0.8125rem; font-weight: 700; color: #94a3b8; min-width: 28px; padding-top: 1px; }
.q-text { font-size: 0.875rem; color: #0f172a; margin: 0 0 8px; line-height: 1.6; }
.q-marks { font-size: 0.75rem; color: #94a3b8; font-weight: 600; white-space: nowrap; padding-top: 2px; }

.mcq-options { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 8px; }
.mcq-opt {
    display: flex; align-items: center; gap: 8px;
    padding: 6px 10px; border-radius: 6px;
    border: 1px solid #e2e8f0; background: #f8fafc;
    font-size: 0.8125rem; color: #374151;
    transition: all 0.12s;
}
.mcq-opt--correct { background: #f0fdf4; border-color: #86efac; color: #166534; font-weight: 600; }
.mcq-opt-letter {
    width: 22px; height: 22px; border-radius: 50%;
    background: #e0e7ff; color: #3730a3;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.6875rem; font-weight: 700; flex-shrink: 0;
}
.mcq-opt--correct .mcq-opt-letter { background: #bbf7d0; color: #166534; }

.answer-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 20px;
    background: #f0fdf4; color: #166534;
    font-size: 0.8rem; font-weight: 600;
    border: 1px solid #bbf7d0;
    margin-top: 6px;
}
</style>
