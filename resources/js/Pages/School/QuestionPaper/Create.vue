<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import axios from 'axios';
import { useToast } from '@/Composables/useToast';

const toast = useToast();

const props = defineProps({
    classes: Array,
});

// ── Form state ───────────────────────────────────────────────────────────
const form = ref({
    class_id:         '',
    subject_id:       '',
    exam_type:        '',
    total_marks:      80,
    duration_minutes: 180,
    difficulty:       'mixed',
    title:            '',
    instructions:     'All questions are compulsory unless stated otherwise.\nWrite neat and clean answers.',
});

const sections = ref([
    { name: 'Section A', question_type: 'mcq',          marks_per_question: 1, num_questions: 10, instructions: 'Choose the correct option.' },
    { name: 'Section B', question_type: 'short_answer',  marks_per_question: 3, num_questions: 10, instructions: 'Answer in 30-50 words.' },
    { name: 'Section C', question_type: 'long_answer',   marks_per_question: 5, num_questions: 4,  instructions: 'Answer in 100-150 words.' },
]);

const selectedTopics = ref([]);

// ── Dependent data ───────────────────────────────────────────────────────
const subjects      = ref([]);
const topics        = ref([]);
const loadingSub    = ref(false);
const loadingTopics = ref(false);

const selectedClass   = computed(() => props.classes.find(c => c.id == form.value.class_id));
const selectedSubject = computed(() => subjects.value.find(s => s.id == form.value.subject_id));

const calculatedMarks = computed(() =>
    sections.value.reduce((sum, s) => sum + s.marks_per_question * s.num_questions, 0)
);

const marksMatch = computed(() => calculatedMarks.value === form.value.total_marks);

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
const step          = ref('configure'); // configure | generating | preview
const generating    = ref(false);
const generatedData = ref(null);
const genError      = ref('');

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
    step.value = 'generating';

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

        if (!form.value.title) {
            form.value.title = `${selectedSubject.value?.name || 'Subject'} ${form.value.exam_type || 'Exam'} - ${selectedClass.value?.name || 'Class'}`;
        }

        step.value = 'preview';
    } catch (e) {
        genError.value = e.response?.data?.error || 'Failed to generate questions. Please try again.';
        step.value = 'configure';
    }
    generating.value = false;
}

// ── Regenerate single section ────────────────────────────────────────────
const regeneratingIdx = ref(null);

async function regenerateSection(idx) {
    regeneratingIdx.value = idx;
    const sec = sections.value[idx];

    const existingQs = generatedData.value
        .filter((_, i) => i !== idx)
        .flatMap(s => s.questions.map(q => q.question_text));

    try {
        const res = await axios.post('/school/question-papers/regenerate-section', {
            class_name:         selectedClass.value?.name || '',
            class_level:        selectedClass.value?.numeric_value || 1,
            subject_name:       selectedSubject.value?.name || '',
            difficulty:         form.value.difficulty,
            topics:             selectedTopics.value.length ? selectedTopics.value.map(id => {
                const t = topics.value.find(tp => tp.id === id);
                return t ? `${t.chapter_name}: ${t.topic_name}` : '';
            }).filter(Boolean) : null,
            section:            sec,
            existing_questions: existingQs,
        });
        generatedData.value[idx].questions = res.data.questions.map(q => ({ ...q, editing: false }));
    } catch (e) {
        toast.error(e.response?.data?.error || 'Failed to regenerate section.');
    }
    regeneratingIdx.value = null;
}

// ── Save paper ───────────────────────────────────────────────────────────
const saving     = ref(false);
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
        onError:  (e) => { saveErrors.value = e; },
        onFinish: () => { saving.value = false; },
    });
}

// ── Helpers ──────────────────────────────────────────────────────────────
const showAnswers = ref(false);

function typeLabel(type) {
    return questionTypes.find(t => t.value === type)?.label || type;
}

const genDots = ref('.');
let dotInterval = null;
watch(generating, (val) => {
    if (val) {
        dotInterval = setInterval(() => {
            genDots.value = genDots.value.length >= 3 ? '.' : genDots.value + '.';
        }, 500);
    } else {
        clearInterval(dotInterval);
        genDots.value = '.';
    }
});
</script>

<template>
    <SchoolLayout title="Generate Question Paper">

        <!-- Page Header -->
        <div class="page-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <Button variant="icon" size="sm" as="link" href="/school/question-papers" aria-label="Back">
                    <template #icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </template>
                </Button>
                <div>
                    <h1 class="page-header-title">Generate Question Paper</h1>
                    <p class="page-header-sub">Configure and generate AI-powered question papers.</p>
                </div>
            </div>
            <!-- Step indicator -->
            <div class="step-indicator">
                <span class="step-dot" :class="{ 'step-dot--active': step === 'configure', 'step-dot--done': step !== 'configure' }">
                    <svg v-if="step !== 'configure'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    <span v-else>1</span>
                </span>
                <span class="step-label" :class="{ 'step-label--active': step === 'configure' }">Configure</span>
                <div class="step-line" :class="{ 'step-line--done': step === 'preview' }"></div>
                <span class="step-dot" :class="{ 'step-dot--active': step === 'preview', 'step-dot--pending': step !== 'preview' }">2</span>
                <span class="step-label" :class="{ 'step-label--active': step === 'preview' }">Review &amp; Edit</span>
            </div>
        </div>

        <div style="max-width:860px;margin:0 auto;">

            <!-- ═══════ STEP 1: CONFIGURE ═══════ -->
            <template v-if="step === 'configure'">

                <!-- Paper Configuration -->
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-header">
                        <span class="card-title">Paper Configuration</span>
                    </div>
                    <div class="card-body">
                        <div class="form-row form-row-2">
                            <div class="form-field">
                                <label>Class *</label>
                                <select v-model="form.class_id">
                                    <option value="">Select Class</option>
                                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Subject *</label>
                                <select v-model="form.subject_id" :disabled="loadingSub || !subjects.length">
                                    <option value="">{{ loadingSub ? 'Loading subjects...' : 'Select Subject' }}</option>
                                    <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Exam Type</label>
                                <input v-model="form.exam_type" type="text" placeholder="e.g., Mid-Term, Final, Unit Test" />
                            </div>
                            <div class="form-field">
                                <label>Difficulty</label>
                                <select v-model="form.difficulty">
                                    <option value="easy">Easy</option>
                                    <option value="medium">Medium</option>
                                    <option value="hard">Hard</option>
                                    <option value="mixed">Mixed (30% Easy, 50% Medium, 20% Hard)</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Total Marks</label>
                                <input v-model.number="form.total_marks" type="number" min="10" max="200" />
                            </div>
                            <div class="form-field">
                                <label>Duration (minutes)</label>
                                <input v-model.number="form.duration_minutes" type="number" min="15" max="300" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Syllabus Topics -->
                <div v-if="topics.length" class="card" style="margin-bottom:20px;">
                    <div class="card-header">
                        <span class="card-title">Syllabus Topics</span>
                        <span style="font-size:0.8125rem;color:#94a3b8;">Optional — leave unchecked for full syllabus</span>
                    </div>
                    <div class="card-body">
                        <div class="topics-grid">
                            <label v-for="t in topics" :key="t.id" class="topic-item">
                                <input type="checkbox" :value="t.id" v-model="selectedTopics" />
                                <span>
                                    <span class="topic-chapter">{{ t.chapter_name }}</span>
                                    <span v-if="t.topic_name" class="topic-name"> — {{ t.topic_name }}</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Sections Builder -->
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-header">
                        <span class="card-title">Paper Sections</span>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <span class="marks-badge" :class="marksMatch ? 'marks-badge--ok' : 'marks-badge--warn'">
                                {{ calculatedMarks }} / {{ form.total_marks }} marks
                                <span v-if="marksMatch"> ✓</span>
                                <span v-else> !</span>
                            </span>
                            <button @click="addSection" class="add-section-btn">+ Add Section</button>
                        </div>
                    </div>
                    <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
                        <div v-for="(sec, i) in sections" :key="i" class="section-builder-card">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                                <input v-model="sec.name" class="section-name-input" />
                                <button v-if="sections.length > 1" @click="removeSection(i)" class="remove-section-btn">Remove</button>
                            </div>
                            <div class="form-row" style="grid-template-columns:2fr 1fr 1fr 1fr;">
                                <div class="form-field">
                                    <label>Question Type</label>
                                    <select v-model="sec.question_type">
                                        <option v-for="qt in questionTypes" :key="qt.value" :value="qt.value">{{ qt.label }}</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label>Marks / Q</label>
                                    <input v-model.number="sec.marks_per_question" type="number" min="1" max="20" />
                                </div>
                                <div class="form-field">
                                    <label>No. of Q</label>
                                    <input v-model.number="sec.num_questions" type="number" min="1" max="30" />
                                </div>
                                <div class="form-field">
                                    <label>Subtotal</label>
                                    <div class="subtotal-display">{{ sec.marks_per_question * sec.num_questions }} marks</div>
                                </div>
                            </div>
                            <div class="form-field" style="margin-top:8px;">
                                <label>Section Instructions <span style="font-weight:400;color:#94a3b8;">(optional)</span></label>
                                <input v-model="sec.instructions" type="text" placeholder="e.g., Answer any 5 out of 7" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error + Generate Button -->
                <div style="display:flex;align-items:center;justify-content:space-between;padding:4px 0 24px;">
                    <p v-if="genError" class="form-error" style="font-size:0.875rem;">{{ genError }}</p>
                    <div v-else></div>
                    <Button @click="generatePaper" :disabled="!form.class_id || !form.subject_id">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Generate Question Paper
                    </Button>
                </div>
            </template>

            <!-- ═══════ AI GENERATING STATE ═══════ -->
            <template v-if="step === 'generating'">
                <div class="card ai-gen-card">
                    <div class="card-body" style="padding:64px 32px;text-align:center;">
                        <div class="ai-gen-spinner">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M13 10V3L4 14h7v7l9-11h-7z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h2 style="font-size:1.25rem;font-weight:800;color:#0f172a;margin:24px 0 8px;">
                            AI is crafting your question paper{{ genDots }}
                        </h2>
                        <p style="color:#64748b;font-size:0.9375rem;margin:0 0 4px;">
                            Generating {{ sections.length }} section{{ sections.length > 1 ? 's' : '' }} for
                            <strong>{{ selectedSubject?.name }}</strong> — <strong>{{ selectedClass?.name }}</strong>
                        </p>
                        <p style="color:#94a3b8;font-size:0.8125rem;margin:0;">
                            This usually takes 15–30 seconds
                        </p>
                        <div class="ai-gen-pills">
                            <span v-for="sec in sections" :key="sec.name" class="ai-gen-pill">
                                {{ sec.name }}: {{ sec.num_questions }} × {{ typeLabel(sec.question_type) }}
                            </span>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ═══════ STEP 2: PREVIEW & EDIT ═══════ -->
            <template v-if="step === 'preview' && generatedData">

                <!-- Preview Controls -->
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-body">
                        <div class="form-row form-row-2" style="margin-bottom:14px;">
                            <div class="form-field">
                                <label>Paper Title *</label>
                                <input v-model="form.title" type="text" />
                            </div>
                            <div class="form-field">
                                <label>General Instructions</label>
                                <textarea v-model="form.instructions" rows="2"></textarea>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <button @click="showAnswers = !showAnswers" class="answer-toggle-btn"
                                    :class="{ 'answer-toggle-btn--on': showAnswers }">
                                {{ showAnswers ? 'Hide Answer Key' : 'Show Answer Key' }}
                            </button>
                            <button @click="step = 'configure'" class="text-action-btn">
                                ← Back to Configure
                            </button>
                            <button @click="generatePaper" :disabled="generating" class="text-action-btn text-action-btn--warn">
                                ↺ Regenerate All
                            </button>
                            <p v-if="Object.keys(saveErrors).length" style="font-size:0.8125rem;color:#dc2626;margin:0;">
                                Please fix errors before saving.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Generated Sections -->
                <div style="display:flex;flex-direction:column;gap:16px;margin-bottom:24px;">
                    <div v-for="(sec, si) in generatedData" :key="si" class="card" style="overflow:hidden;">

                        <!-- Section Header -->
                        <div class="card-header" style="background:#f8fafc;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span style="font-weight:700;color:#0f172a;">{{ sec.name }}</span>
                                <span class="badge badge-indigo">{{ typeLabel(sec.question_type) }}</span>
                                <span style="font-size:0.8125rem;color:#64748b;">{{ sec.marks_per_question }}M &times; {{ sec.questions.length }}Q = {{ sec.marks_per_question * sec.questions.length }}M</span>
                            </div>
                            <button @click="regenerateSection(si)"
                                    :disabled="regeneratingIdx !== null"
                                    class="regen-btn">
                                <svg v-if="regeneratingIdx === si" class="regen-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                {{ regeneratingIdx === si ? 'Regenerating...' : '↺ Regenerate Section' }}
                            </button>
                        </div>

                        <!-- Section instructions -->
                        <div v-if="sec.instructions" style="padding:8px 20px;font-size:0.8125rem;color:#64748b;font-style:italic;border-bottom:1px solid var(--border-light);background:#fafbfc;">
                            {{ sec.instructions }}
                        </div>

                        <!-- Questions -->
                        <div>
                            <div v-for="(q, qi) in sec.questions" :key="qi" class="preview-question-row">
                                <span class="q-num">{{ qi + 1 }}.</span>
                                <div style="flex:1;">

                                    <!-- Editing mode -->
                                    <div v-if="q.editing" style="display:flex;flex-direction:column;gap:8px;">
                                        <textarea v-model="q.question_text" rows="2" style="width:100%;"></textarea>
                                        <div v-if="sec.question_type === 'mcq'" class="form-row form-row-2">
                                            <div v-for="opt in ['a','b','c','d']" :key="opt" class="form-field" style="flex-direction:row;align-items:center;gap:6px;">
                                                <span style="font-size:0.75rem;font-weight:700;color:#6366f1;min-width:20px;">{{ opt.toUpperCase() }})</span>
                                                <input v-model="q['option_' + opt]" type="text" style="flex:1;" />
                                            </div>
                                        </div>
                                        <div v-if="showAnswers" style="display:flex;align-items:center;gap:8px;">
                                            <label style="margin:0;white-space:nowrap;">Answer:</label>
                                            <input v-model="q.correct_answer" type="text" style="flex:1;" />
                                        </div>
                                        <button @click="q.editing = false" style="font-size:0.75rem;color:#6366f1;cursor:pointer;background:none;border:none;text-align:left;padding:0;">
                                            Done editing
                                        </button>
                                    </div>

                                    <!-- View mode -->
                                    <div v-else>
                                        <p class="q-text" @click="q.editing = true" title="Click to edit">
                                            {{ q.question_text }}
                                        </p>
                                        <div v-if="sec.question_type === 'mcq'" class="mcq-preview-opts">
                                            <span v-for="opt in ['a','b','c','d']" :key="opt" class="mcq-preview-opt">
                                                <span class="mcq-opt-letter">{{ opt.toUpperCase() }}</span>
                                                {{ q['option_' + opt] }}
                                            </span>
                                        </div>
                                        <div v-if="showAnswers && q.correct_answer" class="answer-pill">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:12px;height:12px;flex-shrink:0;">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            {{ q.correct_answer }}
                                        </div>
                                    </div>

                                </div>
                                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;padding-top:1px;">
                                    <span class="q-marks">[{{ q.marks }}M]</span>
                                    <button v-if="!q.editing" @click="q.editing = true"
                                            style="font-size:0.7rem;color:#94a3b8;cursor:pointer;background:none;border:none;padding:0;">
                                        edit
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Save Actions -->
                <div style="display:flex;justify-content:flex-end;gap:10px;padding-bottom:32px;">
                    <Button variant="secondary" @click="step = 'configure'">← Back</Button>
                    <Button @click="savePaper" :disabled="saving">
                        <svg v-if="saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        {{ saving ? 'Saving...' : 'Save Question Paper' }}
                    </Button>
                </div>
            </template>

        </div>
    </SchoolLayout>
</template>

<style scoped>
/* ── Step indicator ── */
.step-indicator {
    display: flex; align-items: center; gap: 8px;
    font-size: 0.8125rem;
}
.step-dot {
    width: 26px; height: 26px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 700;
    background: #f1f5f9; color: #94a3b8;
    border: 2px solid #e2e8f0;
    flex-shrink: 0;
}
.step-dot svg { width: 13px; height: 13px; }
.step-dot--active { background: #6366f1; color: #fff; border-color: #6366f1; }
.step-dot--done   { background: #d1fae5; color: #065f46; border-color: #6ee7b7; }
.step-dot--pending { background: #f1f5f9; color: #94a3b8; }
.step-label { color: #94a3b8; font-weight: 500; }
.step-label--active { color: #0f172a; font-weight: 700; }
.step-line {
    width: 40px; height: 2px; background: #e2e8f0; border-radius: 2px;
}
.step-line--done { background: #6ee7b7; }

/* ── Topics ── */
.topics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; max-height: 200px; overflow-y: auto; }
.topic-item { display: flex; align-items: flex-start; gap: 8px; padding: 6px 8px; border-radius: 6px; cursor: pointer; font-size: 0.875rem; }
.topic-item:hover { background: #f8fafc; }
.topic-chapter { font-weight: 600; color: #334155; }
.topic-name { color: #64748b; }

/* ── Marks badge ── */
.marks-badge {
    padding: 4px 10px; border-radius: 20px;
    font-size: 0.75rem; font-weight: 700;
}
.marks-badge--ok   { background: #d1fae5; color: #065f46; }
.marks-badge--warn { background: #fef3c7; color: #78350f; }

/* ── Add/Remove section ── */
.add-section-btn {
    font-size: 0.8125rem; color: #6366f1; font-weight: 600;
    cursor: pointer; background: none; border: none; padding: 0;
}
.add-section-btn:hover { color: #4f46e5; text-decoration: underline; }
.remove-section-btn {
    font-size: 0.8125rem; color: #dc2626; font-weight: 500;
    cursor: pointer; background: none; border: none; padding: 0;
}

/* ── Section builder card ── */
.section-builder-card {
    border: 1.5px solid #e2e8f0; border-radius: 10px;
    padding: 14px; background: #fafbfc;
}
.section-name-input {
    font-size: 0.875rem; font-weight: 700; color: #0f172a;
    border: 1.5px solid #e2e8f0; border-radius: 6px;
    padding: 6px 10px; width: 140px;
    outline: none; background: #fff;
}
.section-name-input:focus { border-color: #6366f1; }
.subtotal-display {
    padding: 9px 12px; font-size: 0.875rem;
    font-weight: 700; color: #059669;
    background: #f0fdf4; border-radius: var(--radius);
    border: 1.5px solid #bbf7d0;
}

/* ── AI Generation loading card ── */
.ai-gen-card { margin-bottom: 20px; }
.ai-gen-spinner {
    width: 72px; height: 72px; border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto;
    animation: ai-pulse 1.5s ease-in-out infinite;
    box-shadow: 0 0 0 0 rgba(99,102,241,0.4);
}
.ai-gen-spinner svg {
    width: 32px; height: 32px;
    color: #fff;
    animation: ai-bolt 1.5s ease-in-out infinite;
}
@keyframes ai-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(99,102,241,0.4); }
    50%       { box-shadow: 0 0 0 16px rgba(99,102,241,0); }
}
@keyframes ai-bolt {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: 0.7; transform: scale(0.88); }
}
.ai-gen-pills { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-top: 24px; }
.ai-gen-pill {
    padding: 4px 12px; border-radius: 20px;
    background: #ede9fe; color: #5b21b6;
    font-size: 0.75rem; font-weight: 600;
}

/* ── Preview controls ── */
.answer-toggle-btn {
    padding: 7px 14px; border-radius: 8px;
    font-size: 0.8125rem; font-weight: 600;
    border: 1.5px solid #d1d5db; background: #f8fafc;
    color: #475569; cursor: pointer;
    transition: all 0.15s;
}
.answer-toggle-btn:hover { border-color: #6366f1; color: #6366f1; }
.answer-toggle-btn--on { background: #f0fdf4; border-color: #86efac; color: #166534; }
.text-action-btn {
    font-size: 0.8125rem; color: #64748b; font-weight: 500;
    cursor: pointer; background: none; border: none; padding: 4px 8px;
}
.text-action-btn:hover { color: #0f172a; }
.text-action-btn--warn { color: #d97706; }
.text-action-btn--warn:hover { color: #92400e; }

/* ── Regenerate button ── */
.regen-btn {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 0.8125rem; font-weight: 600; color: #6366f1;
    cursor: pointer; background: none; border: none; padding: 0;
    transition: color 0.12s;
}
.regen-btn:hover { color: #4f46e5; }
.regen-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.regen-spin { width: 14px; height: 14px; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Question rows ── */
.preview-question-row {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid #f1f5f9;
}
.preview-question-row:last-child { border-bottom: none; }
.q-num { font-size: 0.8125rem; font-weight: 700; color: #94a3b8; min-width: 26px; padding-top: 2px; }
.q-text {
    font-size: 0.875rem; color: #0f172a; margin: 0 0 6px; line-height: 1.6;
    cursor: pointer;
}
.q-text:hover { color: #6366f1; }
.q-marks { font-size: 0.75rem; color: #94a3b8; font-weight: 600; white-space: nowrap; }

/* MCQ preview */
.mcq-preview-opts { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-top: 6px; }
.mcq-preview-opt {
    display: flex; align-items: center; gap: 7px;
    font-size: 0.8125rem; color: #374151;
    padding: 4px 8px; border-radius: 5px;
    border: 1px solid #e2e8f0; background: #f8fafc;
}
.mcq-opt-letter {
    width: 20px; height: 20px; border-radius: 50%;
    background: #e0e7ff; color: #3730a3;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.625rem; font-weight: 700; flex-shrink: 0;
}

/* Answer pill */
.answer-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px;
    background: #f0fdf4; color: #166534;
    font-size: 0.75rem; font-weight: 600;
    border: 1px solid #bbf7d0;
    margin-top: 6px;
}
</style>
