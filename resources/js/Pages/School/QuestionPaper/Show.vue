<script setup>
import { ref } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    paper: Object,
});

const showAnswers = ref(false);

const typeLabels = {
    mcq: 'Multiple Choice',
    short_answer: 'Short Answer',
    long_answer: 'Long Answer',
    fill_blank: 'Fill in the Blanks',
    true_false: 'True / False',
};
</script>

<template>
    <SchoolLayout title="Question Paper">
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">{{ paper.title }}</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ paper.course_class?.name }} &middot; {{ paper.subject?.name }} &middot;
                        {{ paper.total_marks }} Marks &middot; {{ paper.duration_minutes }} min
                        <span v-if="paper.exam_type"> &middot; {{ paper.exam_type }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="showAnswers = !showAnswers"
                            class="text-sm px-3 py-1.5 rounded-lg border"
                            :class="showAnswers ? 'bg-green-50 border-green-300 text-green-700' : 'bg-gray-50 border-gray-300 text-gray-600'">
                        {{ showAnswers ? 'Hide Answers' : 'Show Answers' }}
                    </button>
                    <a :href="`/school/question-papers/${paper.id}/pdf`" target="_blank"
                       class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Download PDF
                    </a>
                    <a :href="`/school/question-papers/${paper.id}/pdf?with_answers=1`" target="_blank"
                       class="inline-flex items-center px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition">
                        PDF with Answers
                    </a>
                    <a href="/school/question-papers" class="text-sm text-gray-600 hover:text-gray-800">&larr; Back</a>
                </div>
            </div>

            <!-- General Instructions -->
            <div v-if="paper.instructions" class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                <p class="text-sm font-semibold text-amber-800 mb-1">General Instructions:</p>
                <p class="text-sm text-amber-700 whitespace-pre-line">{{ paper.instructions }}</p>
            </div>

            <!-- Sections -->
            <div v-for="section in paper.sections" :key="section.id"
                 class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <div class="bg-gray-50 px-6 py-3 border-b">
                    <span class="font-semibold text-gray-800">{{ section.name }}</span>
                    <span class="text-sm text-gray-500 ml-2">
                        ({{ typeLabels[section.question_type] || section.question_type }} &middot;
                        {{ section.marks_per_question }} marks each &middot;
                        {{ section.items?.length || section.num_questions }} questions)
                    </span>
                </div>
                <div v-if="section.instructions" class="px-6 py-2 text-sm text-gray-500 italic bg-gray-50 border-b">
                    {{ section.instructions }}
                </div>

                <div class="divide-y divide-gray-100">
                    <div v-for="(item, qi) in section.items" :key="item.id" class="px-6 py-4">
                        <div class="flex items-start gap-3">
                            <span class="text-sm font-semibold text-gray-500 mt-0.5 w-8 shrink-0">Q{{ qi + 1 }}.</span>
                            <div class="flex-1 space-y-2">
                                <p class="text-sm text-gray-800">{{ item.question_text }}</p>
                                <!-- MCQ options -->
                                <div v-if="section.question_type === 'mcq'" class="grid grid-cols-2 gap-1 mt-1">
                                    <span v-for="opt in ['a','b','c','d']" :key="opt" class="text-sm text-gray-600">
                                        <span class="font-medium">{{ opt.toUpperCase() }})</span> {{ item['option_' + opt] }}
                                    </span>
                                </div>
                                <!-- Answer -->
                                <p v-if="showAnswers && item.correct_answer" class="text-sm text-green-700 font-medium">
                                    Ans: {{ item.correct_answer }}
                                </p>
                            </div>
                            <span class="text-xs text-gray-400 shrink-0">[{{ item.marks }}M]</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Meta -->
            <div class="text-sm text-gray-400 text-center pb-8">
                Created by {{ paper.created_by?.name || 'Unknown' }} on {{ new Date(paper.created_at).toLocaleDateString() }}
            </div>
        </div>
    </SchoolLayout>
</template>
