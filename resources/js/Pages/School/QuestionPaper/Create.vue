<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import axios from 'axios';
import Button from '@/Components/ui/Button.vue';
import { useToast } from '@/Composables/useToast';

const toast = useToast();

const props = defineProps({
    classes: Array,
});

// ── Form state ───────────────────────────────────────────────────────────
const form = ref({
    class_id: '',
    subject_id: '',
    exam_type: '',
    total_marks: 80,
    duration_minutes: 180,
    difficulty: 'mixed',
    title: '',
    instructions: 'All questions are compulsory unless stated otherwise.\nWrite neat and clean answers.',
});

const sections = ref([
    { name: 'Section A', question_type: 'mcq',          marks_per_question: 1, num_questions: 10, instructions: 'Choose the correct option.' },
    { name: 'Section B', question_type: 'short_answer',  marks_per_question: 3, num_questions: 10, instructions: 'Answer in 30-50 words.' },
    { name: 'Section C', question_type: 'long_answer',   marks_per_question: 5, num_questions: 4,  instructions: 'Answer in 100-150 words.' },
]);

const selectedTopics = ref([]);

// ── Dependent data ───────────────────────────────────────────────────────
const subjects       = ref([]);
const topics         = ref([]);
const loadingSub     = ref(false);
const loadingTopics  = ref(false);

const selectedClass = computed(() => props.classes.find(c => c.id == form.value.class_id));
const selectedSubject = computed(() => subjects.value.find(s => s.id == form.value.subject_id));

const calculatedMarks = computed(() =>
    sections.value.reduce((sum, s) => sum + s.marks_per_question * s.num_questions, 0)
);

watch(() => form.value.class_id, async () => {
    form.value.subject_id = '';
    subjects.value = [];
    topics.value = [];
    selectedTopics.value = [];
    if (!form.value.class_id) return;
    loadingSub.value = true;
    try {
        const res = await axios.get('/school/question-papers/subjects', { params: { class_id: form.value.class_id } });
        subjects.value = res.data;
    } catch (e) { console.error(e); }
    loadingSub.value = false;
});

watch(() => form.value.subject_id, async () => {
    topics.value = [];
    selectedTopics.value = [];
    if (!form.value.class_id || !form.value.subject_id) return;
    loadingTopics.value = true;
    try {
        const res = await axios.get('/school/question-papers/topics', {
            params: { class_id: form.value.class_id, subject_id: form.value.subject_id }
        });
        topics.value = res.data;
    } catch (e) { console.error(e); }
    loadingTopics.value = false;
});

// ── Section management ───────────────────────────────────────────────────
const questionTypes = [
    { value: 'mcq',          label: 'Multiple Choice (MCQ)' },
    { value: 'short_answer', label: 'Short Answer' },
    { value: 'long_answer',  label: 'Long Answer' },
    { value: 'fill_blank',   label: 'Fill in the Blanks' },
    { value: 'true_false',   label: 'True / False' },
];

function addSection() {
    const letters = 'ABCDEFGHIJ';
    sections.value.push({
        name: `Section ${letters[sections.value.length] || sections.value.length + 1}`,
        question_type: 'short_answer',
        marks_per_question: 2,
        num_questions: 5,
        instructions: '',
    });
}

function removeSection(i) {
    if (sections.value.length <= 1) return;
    sections.value.splice(i, 1);
}

// ── AI Generation ────────────────────────────────────────────────────────
const step         = ref('configure'); // configure | preview
const generating   = ref(false);
const generatedData = ref(null);
const genError     = ref('');

async function generatePaper() {
    if (!form.value.class_id || !form.value.subject_id) {
        genError.value = 'Please select a class and subject.';
        return;
    }
    if (sections.value.length === 0) {
        genError.value = 'Add at least one section.';
        return;
    }

    genError.value = '';
    generating.value = true;

    try {
        const res = await axios.post('/school/question-papers/generate', {
            class_name:       selectedClass.value?.name || '',
            class_level:      selectedClass.value?.numeric_value || 1,
            subject_name:     selectedSubject.value?.name || '',
            exam_type:        form.value.exam_type,
            total_marks:      form.value.total_marks,
            duration_minutes: form.value.duration_minutes,
            difficulty:       form.value.difficulty,
            topics:           selectedTopics.value.length ? selectedTopics.value.map(id => {
                const t = topics.value.find(tp => tp.id === id);
                return t ? `${t.chapter_name}: ${t.topic_name}` : '';
            }).filter(Boolean) : null,
            sections: sections.value,
        });

        generatedData.value = res.data.sections.map((sec, i) => ({
            ...sections.value[i],
            questions: sec.questions.map(q => ({ ...q, editing: false })),
        }));

        // Auto-generate title
        if (!form.value.title) {
            form.value.title = `${selectedSubject.value?.name || 'Subject'} ${form.value.exam_type || 'Exam'} - ${selectedClass.value?.name || 'Class'}`;
        }

        step.value = 'preview';
    } catch (e) {
        genError.value = e.response?.data?.error || 'Failed to generate questions. Please try again.';
    }
    generating.value = false;
}

// ── Regenerate single section ────────────────────────────────────────────
const regeneratingIdx = ref(null);

async function regenerateSection(idx) {
    regeneratingIdx.value = idx;
    const sec = sections.value[idx];

    // Collect existing questions from other sections to avoid duplicates
    const existingQs = generatedData.value
        .filter((_, i) => i !== idx)
        .flatMap(s => s.questions.map(q => q.question_text));

    try {
        const res = await axios.post('/school/question-papers/regenerate-section', {
            class_name:   selectedClass.value?.name || '',
            class_level:  selectedClass.value?.numeric_value || 1,
            subject_name: selectedSubject.value?.name || '',
            difficulty:   form.value.difficulty,
            topics:       selectedTopics.value.length ? selectedTopics.value.map(id => {
                const t = topics.value.find(tp => tp.id === id);
                return t ? `${t.chapter_name}: ${t.topic_name}` : '';
            }).filter(Boolean) : null,
            section: sec,
            existing_questions: existingQs,
        });

        generatedData.value[idx].questions = res.data.questions.map(q => ({ ...q, editing: false }));
    } catch (e) {
        toast.error(e.response?.data?.error || 'Failed to regenerate section.');
    }
    regeneratingIdx.value = null;
}

// ── Save paper ───────────────────────────────────────────────────────────
const saving = ref(false);
const saveErrors = ref({});

function savePaper() {
    saving.value = true;
    saveErrors.value = {};

    const payload = {
        class_id:         form.value.class_id,
        subject_id:       form.value.subject_id,
        title:            form.value.title,
        exam_type:        form.value.exam_type,
        total_marks:      form.value.total_marks,
        duration_minutes: form.value.duration_minutes,
        difficulty:       form.value.difficulty,
        instructions:     form.value.instructions,
        sections: generatedData.value.map(sec => ({
            name:               sec.name,
            question_type:      sec.question_type,
            marks_per_question: sec.marks_per_question,
            num_questions:      sec.num_questions,
            instructions:       sec.instructions || null,
            questions: sec.questions.map(q => ({
                question_text:  q.question_text,
                option_a:       q.option_a || null,
                option_b:       q.option_b || null,
                option_c:       q.option_c || null,
                option_d:       q.option_d || null,
                correct_answer: q.correct_answer || null,
                marks:          q.marks,
            })),
        })),
    };

    router.post('/school/question-papers', payload, {
        onError: (e) => { saveErrors.value = e; },
        onFinish: () => { saving.value = false; },
    });
}

// ── Show/hide answer key ─────────────────────────────────────────────────
const showAnswers = ref(false);

function typeLabel(type) {
    return questionTypes.find(t => t.value === type)?.label || type;
}
</script>

<template>
    <SchoolLayout title="Generate Question Paper">
        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Generate Question Paper</h1>
                    <p class="text-sm text-gray-500 mt-1">Configure and generate AI-powered question papers</p>
                </div>
                <a href="/school/question-papers" class="text-sm text-gray-600 hover:text-gray-800">
                    &larr; Back to List
                </a>
            </div>

            <!-- ═══════ STEP 1: CONFIGURE ═══════ -->
            <template v-if="step === 'configure'">
                <!-- Basic Info -->
                <div class="bg-white rounded-xl shadow-sm border p-6 space-y-5">
                    <h2 class="text-lg font-semibold text-gray-800">Paper Configuration</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Class *</label>
                            <select v-model="form.class_id" class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Select Class</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                            <select v-model="form.subject_id" class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500" :disabled="loadingSub || !subjects.length">
                                <option value="">{{ loadingSub ? 'Loading...' : 'Select Subject' }}</option>
                                <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Exam Type</label>
                            <input v-model="form.exam_type" type="text" placeholder="e.g., Mid-Term, Final, Unit Test"
                                   class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Difficulty</label>
                            <select v-model="form.difficulty" class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                                <option value="mixed">Mixed (30% Easy, 50% Medium, 20% Hard)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Total Marks</label>
                            <input v-model.number="form.total_marks" type="number" min="10" max="200"
                                   class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes)</label>
                            <input v-model.number="form.duration_minutes" type="number" min="15" max="300"
                                   class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                    </div>
                </div>

                <!-- Topics (optional) -->
                <div v-if="topics.length" class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-gray-800">Syllabus Topics <span class="text-sm font-normal text-gray-400">(optional)</span></h2>
                    <p class="text-sm text-gray-500">Select specific topics to focus on, or leave empty for full syllabus coverage.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-48 overflow-y-auto">
                        <label v-for="t in topics" :key="t.id" class="flex items-start gap-2 p-2 rounded hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" :value="t.id" v-model="selectedTopics" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm">
                                <span class="font-medium text-gray-700">{{ t.chapter_name }}</span>
                                <span v-if="t.topic_name" class="text-gray-500"> - {{ t.topic_name }}</span>
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Sections Builder -->
                <div class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800">Paper Sections</h2>
                        <div class="flex items-center gap-4">
                            <span class="text-sm" :class="calculatedMarks === form.total_marks ? 'text-green-600 font-medium' : 'text-amber-600'">
                                Calculated: {{ calculatedMarks }} / {{ form.total_marks }} marks
                            </span>
                            <button @click="addSection" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">+ Add Section</button>
                        </div>
                    </div>

                    <div v-for="(sec, i) in sections" :key="i"
                         class="border rounded-lg p-4 space-y-3 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <input v-model="sec.name" class="text-sm font-semibold border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 w-40" />
                            <button v-if="sections.length > 1" @click="removeSection(i)" class="text-red-500 hover:text-red-700 text-sm">Remove</button>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Question Type</label>
                                <select v-model="sec.question_type" class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option v-for="qt in questionTypes" :key="qt.value" :value="qt.value">{{ qt.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Marks / Question</label>
                                <input v-model.number="sec.marks_per_question" type="number" min="1" max="20"
                                       class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">No. of Questions</label>
                                <input v-model.number="sec.num_questions" type="number" min="1" max="30"
                                       class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Subtotal</label>
                                <div class="text-sm font-medium text-gray-700 mt-2">{{ sec.marks_per_question * sec.num_questions }} marks</div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Section Instructions (optional)</label>
                            <input v-model="sec.instructions" type="text" placeholder="e.g., Answer any 5 out of 7"
                                   class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                    </div>
                </div>

                <!-- Generate Button -->
                <div class="flex items-center justify-between">
                    <p v-if="genError" class="text-sm text-red-600">{{ genError }}</p>
                    <div></div>
                    <Button @click="generatePaper"
                            :disabled="generating"
                           >
                        <svg v-if="generating" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        {{ generating ? 'Generating with AI...' : 'Generate Question Paper' }}
                    </Button>
                </div>
            </template>

            <!-- ═══════ STEP 2: PREVIEW & EDIT ═══════ -->
            <template v-if="step === 'preview' && generatedData">
                <!-- Paper Title & Controls -->
                <div class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Paper Title *</label>
                            <input v-model="form.title" type="text"
                                   class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">General Instructions</label>
                            <textarea v-model="form.instructions" rows="2"
                                      class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <button @click="showAnswers = !showAnswers"
                                class="text-sm px-3 py-1.5 rounded-lg border"
                                :class="showAnswers ? 'bg-green-50 border-green-300 text-green-700' : 'bg-gray-50 border-gray-300 text-gray-600'">
                            {{ showAnswers ? 'Hide Answer Key' : 'Show Answer Key' }}
                        </button>
                        <button @click="step = 'configure'" class="text-sm text-gray-600 hover:text-gray-800">
                            &larr; Back to Configure
                        </button>
                        <button @click="generatePaper" :disabled="generating"
                                class="text-sm text-amber-600 hover:text-amber-800 font-medium">
                            {{ generating ? 'Regenerating...' : 'Regenerate All' }}
                        </button>
                    </div>
                    <p v-if="Object.keys(saveErrors).length" class="text-sm text-red-600">Please fix errors before saving.</p>
                </div>

                <!-- Generated Sections -->
                <div v-for="(sec, si) in generatedData" :key="si"
                     class="bg-white rounded-xl shadow-sm border overflow-hidden">
                    <!-- Section Header -->
                    <div class="bg-gray-50 px-6 py-3 flex items-center justify-between border-b">
                        <div>
                            <span class="font-semibold text-gray-800">{{ sec.name }}</span>
                            <span class="text-sm text-gray-500 ml-2">({{ typeLabel(sec.question_type) }} &middot; {{ sec.marks_per_question }} marks each &middot; {{ sec.questions.length }} questions)</span>
                        </div>
                        <button @click="regenerateSection(si)"
                                :disabled="regeneratingIdx === si"
                                class="text-sm text-indigo-600 hover:text-indigo-800 font-medium disabled:opacity-50">
                            {{ regeneratingIdx === si ? 'Regenerating...' : 'Regenerate Section' }}
                        </button>
                    </div>
                    <div v-if="sec.instructions" class="px-6 py-2 text-sm text-gray-500 italic bg-gray-50 border-b">
                        {{ sec.instructions }}
                    </div>

                    <!-- Questions -->
                    <div class="divide-y divide-gray-100">
                        <div v-for="(q, qi) in sec.questions" :key="qi" class="px-6 py-4">
                            <div class="flex items-start gap-3">
                                <span class="text-sm font-semibold text-gray-500 mt-0.5 w-8 shrink-0">Q{{ qi + 1 }}.</span>
                                <div class="flex-1 space-y-2">
                                    <!-- Question text (editable) -->
                                    <div v-if="q.editing">
                                        <textarea v-model="q.question_text" rows="2"
                                                  class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                                        <!-- MCQ options editable -->
                                        <div v-if="sec.question_type === 'mcq'" class="grid grid-cols-2 gap-2 mt-2">
                                            <div v-for="opt in ['a','b','c','d']" :key="opt" class="flex items-center gap-1">
                                                <span class="text-xs font-medium text-gray-500 uppercase w-4">{{ opt }})</span>
                                                <input v-model="q['option_' + opt]" type="text"
                                                       class="flex-1 border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                            </div>
                                        </div>
                                        <div v-if="showAnswers" class="mt-2">
                                            <label class="text-xs text-gray-500">Answer:</label>
                                            <input v-model="q.correct_answer" type="text"
                                                   class="ml-2 border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500 w-64" />
                                        </div>
                                        <button @click="q.editing = false" class="text-xs text-indigo-600 mt-1">Done editing</button>
                                    </div>
                                    <div v-else>
                                        <p class="text-sm text-gray-800 cursor-pointer hover:text-indigo-600" @click="q.editing = true" title="Click to edit">
                                            {{ q.question_text }}
                                        </p>
                                        <!-- MCQ options display -->
                                        <div v-if="sec.question_type === 'mcq'" class="grid grid-cols-2 gap-1 mt-2">
                                            <span v-for="opt in ['a','b','c','d']" :key="opt" class="text-sm text-gray-600">
                                                <span class="font-medium">{{ opt.toUpperCase() }})</span> {{ q['option_' + opt] }}
                                            </span>
                                        </div>
                                        <!-- Answer key -->
                                        <p v-if="showAnswers && q.correct_answer" class="text-sm text-green-700 mt-1 font-medium">
                                            Ans: {{ q.correct_answer }}
                                        </p>
                                    </div>
                                </div>
                                <span class="text-xs text-gray-400 shrink-0">[{{ q.marks }}M]</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save / Download Actions -->
                <div class="flex items-center justify-end gap-3 pb-8">
                    <Button @click="savePaper"
                            :disabled="saving"
                           >
                        {{ saving ? 'Saving...' : 'Save Question Paper' }}
                    </Button>
                </div>
            </template>
        </div>
    </SchoolLayout>
</template>
