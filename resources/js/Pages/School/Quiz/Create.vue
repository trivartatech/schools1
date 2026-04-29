<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { useForm } from '@inertiajs/vue3';
import { useToast } from '@/Composables/useToast';
import { ref, computed, watch } from 'vue';

const props = defineProps({ quiz: Object, subjects: Array, classes: Array });
const toast = useToast();

const isEdit = computed(() => !!props.quiz);

const form = useForm({
    title:                   props.quiz?.title ?? '',
    description:             props.quiz?.description ?? '',
    subject_id:              props.quiz?.subject_id ?? '',
    type:                    props.quiz?.type ?? 'mcq',
    duration_minutes:        props.quiz?.duration_minutes ?? 30,
    total_marks:             props.quiz?.total_marks ?? 10,
    pass_marks:              props.quiz?.pass_marks ?? 5,
    shuffle_questions:       props.quiz?.shuffle_questions ?? false,
    shuffle_options:         props.quiz?.shuffle_options ?? false,
    show_result_immediately: props.quiz?.show_result_immediately ?? true,
    status:                  props.quiz?.status ?? 'draft',
    start_at:                props.quiz?.start_at?.slice(0, 16) ?? '',
    end_at:                  props.quiz?.end_at?.slice(0, 16) ?? '',
    target_classes:          props.quiz?.target_classes ?? [],
    target_sections:         props.quiz?.target_sections ?? [],
    questions:               props.quiz?.questions?.map(q => ({
        question_text:  q.question_text,
        type:           q.type,
        marks:          q.marks,
        options:        Array.isArray(q.options) ? q.options : (q.options ? JSON.parse(q.options) : []),
        correct_answer: q.correct_answer,
        explanation:    q.explanation ?? '',
    })) ?? [],
});

// ── Live total marks ──────────────────────────────────────────────
const liveTotalMarks = computed(() =>
    form.questions.reduce((s, q) => s + parseFloat(q.marks || 0), 0)
);

const passMarksWarning = computed(() => {
    const t = liveTotalMarks.value;
    const p = parseFloat(form.pass_marks || 0);
    return t > 0 && p > t;
});

// ── Question builder ──────────────────────────────────────────────
const blankQuestion = () => ({
    question_text: '',
    type: 'mcq',
    marks: 1,
    options: [
        { text: '', is_correct: false },
        { text: '', is_correct: false },
        { text: '', is_correct: false },
        { text: '', is_correct: false },
    ],
    correct_answer: '',
    explanation: '',
});

const addQuestion = () => form.questions.push(blankQuestion());
const removeQuestion = (i) => form.questions.splice(i, 1);

const duplicateQuestion = (i) => {
    const clone = JSON.parse(JSON.stringify(form.questions[i]));
    form.questions.splice(i + 1, 0, clone);
};

const moveQuestion = (i, dir) => {
    const j = i + dir;
    if (j < 0 || j >= form.questions.length) return;
    const [q] = form.questions.splice(i, 1);
    form.questions.splice(j, 0, q);
};

const addOption = (q) => q.options.push({ text: '', is_correct: false });
const removeOption = (q, i) => {
    q.options.splice(i, 1);
    if (String(q.correct_answer) === String(i)) q.correct_answer = '';
};

const setCorrectOption = (q, i) => {
    q.options.forEach((o, idx) => o.is_correct = idx === i);
    q.correct_answer = String(i);
};

// ── Tabs ──────────────────────────────────────────────────────────
const activeTab = ref('details');
const tabs = computed(() => [
    { key: 'details',   label: 'Details' },
    { key: 'questions', label: 'Questions', count: form.questions.length },
    { key: 'targeting', label: 'Targeting',
      count: (form.target_classes?.length || 0) + (form.target_sections?.length || 0) || undefined },
]);

// Auto-flip to Questions tab if user submits with errors there
watch(() => form.errors, (errs) => {
    if (errs.questions) activeTab.value = 'questions';
});

// ── Section options derived from selected classes ─────────────────
const sectionOptions = computed(() => {
    const out = [];
    (props.classes || []).forEach(c => {
        if (form.target_classes.length && !form.target_classes.includes(c.id)) return;
        (c.sections || []).forEach(sec => {
            out.push({ id: sec.id, label: `${c.name} / ${sec.name}` });
        });
    });
    return out;
});

// ── Submit ────────────────────────────────────────────────────────
const submit = () => {
    if (!form.questions.length) {
        toast.error('Add at least one question.');
        activeTab.value = 'questions';
        return;
    }
    if (!form.title.trim()) {
        toast.error('Quiz title is required.');
        activeTab.value = 'details';
        return;
    }
    if (passMarksWarning.value) {
        toast.error('Pass marks cannot exceed total marks.');
        activeTab.value = 'details';
        return;
    }

    form.total_marks = liveTotalMarks.value;

    if (isEdit.value) {
        form.put(`/school/quiz/${props.quiz.id}`, { preserveScroll: false });
    } else {
        form.post('/school/quiz', { preserveScroll: false });
    }
};

if (form.questions.length === 0) addQuestion();
</script>

<template>
    <SchoolLayout :title="isEdit ? 'Edit Quiz' : 'Create Quiz'">
        <PageHeader
            :title="isEdit ? 'Edit Quiz' : 'Create Quiz'"
            :subtitle="isEdit ? `Editing &ldquo;${quiz.title}&rdquo;` : 'Define quiz details, build questions, and target your students.'"
            back-href="/school/quiz"
            back-label="← Back to quizzes"
        >
            <template #meta>
                <span class="badge badge-blue">{{ form.questions.length }} Question{{ form.questions.length === 1 ? '' : 's' }}</span>
                <span class="badge" :class="passMarksWarning ? 'badge-red' : 'badge-gray'">
                    {{ liveTotalMarks }} Total Marks
                </span>
                <span class="badge badge-gray">Pass: {{ form.pass_marks }}</span>
            </template>
        </PageHeader>

        <form @submit.prevent="submit">
            <Tabs v-model="activeTab" :tabs="tabs">

                <!-- ─── Tab: Details ───────────────────────────────────── -->
                <template #tab-details>
                    <div class="card">
                        <div class="card-header"><span class="card-title">Quiz Details</span></div>
                        <div class="card-body form-grid-2">
                            <div class="form-field full">
                                <label>Title <span class="req">*</span></label>
                                <input v-model="form.title" required placeholder="e.g. Midterm — Algebra Basics" />
                                <p v-if="form.errors.title" class="field-error">{{ form.errors.title }}</p>
                            </div>

                            <div class="form-field full">
                                <label>Description</label>
                                <textarea v-model="form.description" rows="2" placeholder="Optional — instructions, scope, etc."></textarea>
                            </div>

                            <div class="form-field">
                                <label>Subject</label>
                                <select v-model="form.subject_id">
                                    <option value="">— None —</option>
                                    <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label>Quiz Type <span class="req">*</span></label>
                                <select v-model="form.type">
                                    <option value="mcq">MCQ Only</option>
                                    <option value="descriptive">Descriptive Only</option>
                                    <option value="mixed">Mixed</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label>Duration (minutes) <span class="req">*</span></label>
                                <input v-model="form.duration_minutes" type="number" min="1" max="480" required />
                            </div>

                            <div class="form-field">
                                <label>Pass Marks <span class="req">*</span></label>
                                <input v-model="form.pass_marks" type="number" step="0.5" min="0" required />
                                <p v-if="passMarksWarning" class="field-error">
                                    Pass marks ({{ form.pass_marks }}) exceed total marks ({{ liveTotalMarks }}).
                                </p>
                            </div>

                            <div class="form-field">
                                <label>Status <span class="req">*</span></label>
                                <select v-model="form.status">
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label>Start At</label>
                                <input v-model="form.start_at" type="datetime-local" />
                            </div>

                            <div class="form-field">
                                <label>End At</label>
                                <input v-model="form.end_at" type="datetime-local" />
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><span class="card-title">Behaviour</span></div>
                        <div class="card-body">
                            <label class="check-row">
                                <input type="checkbox" v-model="form.shuffle_questions" />
                                <span>
                                    <strong>Shuffle Questions</strong>
                                    <small>Each student gets questions in a different order.</small>
                                </span>
                            </label>
                            <label class="check-row">
                                <input type="checkbox" v-model="form.shuffle_options" />
                                <span>
                                    <strong>Shuffle MCQ Options</strong>
                                    <small>Randomize order of options on multiple-choice questions.</small>
                                </span>
                            </label>
                            <label class="check-row">
                                <input type="checkbox" v-model="form.show_result_immediately" />
                                <span>
                                    <strong>Show Result Immediately</strong>
                                    <small>Reveal score &amp; correct answers right after the student submits.</small>
                                </span>
                            </label>
                        </div>
                    </div>
                </template>

                <!-- ─── Tab: Questions ─────────────────────────────────── -->
                <template #tab-questions>
                    <div class="card">
                        <div class="card-header q-header">
                            <div>
                                <span class="card-title">Questions</span>
                                <span class="q-summary">
                                    {{ form.questions.length }} question{{ form.questions.length === 1 ? '' : 's' }}
                                    &middot; {{ liveTotalMarks }} total marks
                                </span>
                            </div>
                            <Button type="button" size="sm" @click="addQuestion">+ Add Question</Button>
                        </div>
                        <div class="card-body q-body">
                            <EmptyState
                                v-if="!form.questions.length"
                                variant="compact"
                                title="No questions yet"
                                description="Click + Add Question to get started."
                                action-label="+ Add Question"
                                @action="addQuestion"
                            />

                            <div v-for="(q, qi) in form.questions" :key="qi" class="q-card">
                                <div class="q-card-head">
                                    <strong class="q-num">Q{{ qi + 1 }}</strong>
                                    <div class="q-card-actions">
                                        <Button type="button" variant="icon" size="xs" :disabled="qi === 0" @click="moveQuestion(qi, -1)" aria-label="Move up">↑</Button>
                                        <Button type="button" variant="icon" size="xs" :disabled="qi === form.questions.length - 1" @click="moveQuestion(qi, 1)" aria-label="Move down">↓</Button>
                                        <Button type="button" variant="ghost" size="xs" @click="duplicateQuestion(qi)">Duplicate</Button>
                                        <Button type="button" variant="danger" size="xs" :disabled="form.questions.length === 1" @click="removeQuestion(qi)">Remove</Button>
                                    </div>
                                </div>

                                <div class="q-grid">
                                    <div class="form-field q-text">
                                        <label>Question <span class="req">*</span></label>
                                        <textarea v-model="q.question_text" rows="2" required placeholder="Type your question here..."></textarea>
                                    </div>
                                    <div class="form-field">
                                        <label>Type</label>
                                        <select v-model="q.type">
                                            <option value="mcq">MCQ</option>
                                            <option value="true_false">True / False</option>
                                            <option value="short_answer">Short Answer</option>
                                            <option value="descriptive">Descriptive</option>
                                        </select>
                                    </div>
                                    <div class="form-field q-marks">
                                        <label>Marks</label>
                                        <input v-model="q.marks" type="number" step="0.5" min="0" required />
                                    </div>
                                </div>

                                <!-- MCQ Options -->
                                <div v-if="q.type === 'mcq'" class="opt-block">
                                    <div class="opt-label">Options <span class="muted">(click radio to mark correct)</span></div>
                                    <div v-for="(opt, oi) in q.options" :key="oi" class="opt-row">
                                        <input
                                            type="radio"
                                            :name="`q${qi}_correct`"
                                            :checked="String(q.correct_answer) === String(oi)"
                                            @change="setCorrectOption(q, oi)"
                                        />
                                        <input v-model="opt.text" placeholder="Option text..." class="opt-text" />
                                        <Button
                                            type="button" variant="icon" size="xs"
                                            :disabled="q.options.length <= 2"
                                            @click="removeOption(q, oi)"
                                            aria-label="Remove option"
                                        >×</Button>
                                    </div>
                                    <Button type="button" variant="ghost" size="xs" @click="addOption(q)">+ Add Option</Button>
                                </div>

                                <!-- True / False -->
                                <div v-else-if="q.type === 'true_false'" class="opt-block">
                                    <div class="opt-label">Correct Answer</div>
                                    <div class="tf-row">
                                        <label class="tf-pill">
                                            <input type="radio" v-model="q.correct_answer" value="true" />
                                            <span>True</span>
                                        </label>
                                        <label class="tf-pill">
                                            <input type="radio" v-model="q.correct_answer" value="false" />
                                            <span>False</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Short answer -->
                                <div v-else-if="q.type === 'short_answer'" class="opt-block">
                                    <div class="form-field">
                                        <label>Expected Answer <span class="muted">(case-insensitive auto-grade)</span></label>
                                        <input v-model="q.correct_answer" placeholder="Leave blank to grade manually" />
                                    </div>
                                </div>

                                <!-- Descriptive -->
                                <div v-else-if="q.type === 'descriptive'" class="opt-block">
                                    <div class="hint">Descriptive answers are graded manually from the Results page.</div>
                                </div>

                                <div class="form-field">
                                    <label>Explanation <span class="muted">(shown to student after submission)</span></label>
                                    <input v-model="q.explanation" placeholder="Optional" />
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ─── Tab: Targeting ─────────────────────────────────── -->
                <template #tab-targeting>
                    <div class="card">
                        <div class="card-header"><span class="card-title">Target Audience</span></div>
                        <div class="card-body">
                            <p class="hint" style="margin-top:0;">
                                Leave both empty to make the quiz available to <strong>all students</strong> in the school.
                                Otherwise, pick specific classes and/or sections.
                            </p>

                            <div class="form-field">
                                <label>Classes</label>
                                <div class="check-grid">
                                    <label v-for="c in classes" :key="c.id" class="check-pill">
                                        <input type="checkbox" :value="c.id" v-model="form.target_classes" />
                                        <span>{{ c.name }}</span>
                                    </label>
                                    <p v-if="!classes?.length" class="muted">No classes set up yet.</p>
                                </div>
                            </div>

                            <div class="form-field" style="margin-top:18px;">
                                <label>Sections <span class="muted">(filtered by selected classes)</span></label>
                                <div class="check-grid">
                                    <label v-for="s in sectionOptions" :key="s.id" class="check-pill">
                                        <input type="checkbox" :value="s.id" v-model="form.target_sections" />
                                        <span>{{ s.label }}</span>
                                    </label>
                                    <p v-if="!sectionOptions.length" class="muted">
                                        {{ form.target_classes.length
                                            ? 'No sections under the selected classes.'
                                            : 'Select a class first to see its sections, or leave both empty for all.' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

            </Tabs>

            <!-- Submit row -->
            <div class="submit-row">
                <Button type="button" as="link" href="/school/quiz" variant="secondary">Cancel</Button>
                <Button type="submit" :loading="form.processing">
                    {{ isEdit ? 'Update Quiz' : 'Create Quiz' }}
                </Button>
            </div>
        </form>
    </SchoolLayout>
</template>

<style scoped>
form { display: flex; flex-direction: column; gap: 16px; }

.card { margin-bottom: 16px; }

/* form grid utilities */
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.form-field.full { grid-column: 1 / -1; }

.form-field label {
    display: block;
    font-size: .72rem;
    font-weight: 700;
    color: var(--text-secondary, #475569);
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: 6px;
}
.form-field input:not([type=checkbox]):not([type=radio]),
.form-field select,
.form-field textarea {
    width: 100%;
    border: 1.5px solid var(--border, #e2e8f0);
    border-radius: 8px;
    padding: 8px 12px;
    font-size: .875rem;
    color: var(--text-primary, #0f172a);
    font-family: inherit;
    transition: border-color .15s, box-shadow .15s;
    background: var(--surface, #fff);
}
.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
    border-color: var(--accent, #6366f1);
    outline: none;
    box-shadow: 0 0 0 3px var(--accent-glow, rgba(99,102,241,0.1));
}
.form-field textarea { resize: vertical; }

.req { color: #dc2626; }
.muted { color: var(--text-muted, #94a3b8); font-weight: normal; }
.hint {
    font-size: .8rem;
    color: var(--text-muted, #94a3b8);
    padding: 10px 14px;
    background: var(--border-light, #f1f5f9);
    border-radius: 8px;
    margin: 0;
}
.field-error {
    font-size: .75rem;
    color: #dc2626;
    margin: 4px 0 0;
}

/* Behaviour checkboxes */
.check-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-light, #f1f5f9);
    cursor: pointer;
}
.check-row:last-child { border-bottom: none; }
.check-row input[type=checkbox] {
    margin-top: 2px;
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    accent-color: var(--accent, #6366f1);
}
.check-row strong {
    display: block;
    font-size: .85rem;
    color: var(--text-primary, #0f172a);
    font-weight: 600;
}
.check-row small {
    display: block;
    font-size: .75rem;
    color: var(--text-muted, #94a3b8);
    margin-top: 2px;
}

/* Question card */
.q-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}
.q-summary {
    font-size: .75rem;
    color: var(--text-muted, #94a3b8);
    font-weight: 500;
    margin-left: 10px;
}
.q-body {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.q-card {
    border: 1.5px solid var(--border, #e2e8f0);
    border-radius: 10px;
    padding: 16px;
    background: var(--surface, #fff);
}
.q-card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    gap: 8px;
}
.q-num {
    font-size: .9rem;
    font-weight: 700;
    color: var(--accent, #6366f1);
    background: var(--accent-subtle, rgba(99,102,241,0.12));
    padding: 4px 10px;
    border-radius: 6px;
}
.q-card-actions {
    display: flex;
    gap: 4px;
    align-items: center;
}
.q-grid {
    display: grid;
    grid-template-columns: 1fr 160px 100px;
    gap: 10px;
    margin-bottom: 12px;
}
.q-text { grid-column: 1; }
.q-marks input { text-align: right; }

/* Options */
.opt-block { margin-top: 8px; margin-bottom: 12px; }
.opt-label {
    font-size: .72rem;
    font-weight: 700;
    color: var(--text-secondary, #475569);
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: 8px;
}
.opt-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}
.opt-row input[type=radio] {
    accent-color: var(--accent, #6366f1);
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}
.opt-text {
    flex: 1;
    border: 1.5px solid var(--border, #e2e8f0);
    border-radius: 8px;
    padding: 6px 10px;
    font-size: .85rem;
    background: var(--surface, #fff);
    font-family: inherit;
}
.opt-text:focus {
    border-color: var(--accent, #6366f1);
    outline: none;
}

/* True/False pills */
.tf-row { display: flex; gap: 10px; }
.tf-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border: 1.5px solid var(--border, #e2e8f0);
    border-radius: 8px;
    cursor: pointer;
    background: var(--surface, #fff);
    font-size: .85rem;
}
.tf-pill input[type=radio] { accent-color: var(--accent, #6366f1); }

/* Targeting checkbox grid */
.check-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.check-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border: 1.5px solid var(--border, #e2e8f0);
    border-radius: 999px;
    background: var(--surface, #fff);
    font-size: .8rem;
    cursor: pointer;
    color: var(--text-secondary, #475569);
}
.check-pill input[type=checkbox] {
    accent-color: var(--accent, #6366f1);
    width: 14px;
    height: 14px;
}
.check-pill:has(input:checked) {
    background: var(--accent-subtle, rgba(99,102,241,0.12));
    border-color: var(--accent, #6366f1);
    color: var(--accent, #6366f1);
    font-weight: 600;
}

/* Submit row */
.submit-row {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 4px;
}

@media (max-width: 720px) {
    .form-grid-2 { grid-template-columns: 1fr; }
    .q-grid { grid-template-columns: 1fr; }
    .q-card-head { flex-wrap: wrap; }
}
</style>
