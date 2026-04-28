<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ quiz: Object, subjects: Array, classes: Array });

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
    start_at:                props.quiz?.start_at?.slice(0,16) ?? '',
    end_at:                  props.quiz?.end_at?.slice(0,16) ?? '',
    target_classes:          props.quiz?.target_classes ?? [],
    target_sections:         props.quiz?.target_sections ?? [],
    questions:               props.quiz?.questions ?? [],
});

// ── Question builder ──────────────────────────────────────────────
const addQuestion = () => {
    form.questions.push({ question_text: '', type: 'mcq', marks: 1, options: [{ text: '', is_correct: false }, { text: '', is_correct: false }, { text: '', is_correct: false }, { text: '', is_correct: false }], correct_answer: '', explanation: '' });
};

const removeQuestion = (i) => form.questions.splice(i, 1);

const addOption = (q) => q.options.push({ text: '', is_correct: false });
const removeOption = (q, i) => q.options.splice(i, 1);

const setCorrectOption = (q, i) => {
    q.options.forEach((o, idx) => o.is_correct = idx === i);
    q.correct_answer = String(i);
};

const submit = () => {
    // Auto-calc total marks from questions
    form.total_marks = form.questions.reduce((s, q) => s + parseFloat(q.marks || 0), 0);

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
        <PageHeader>
            <template #title>
                <h1 class="page-header-title">{{ isEdit ? 'Edit Quiz' : 'Create Quiz' }}</h1>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" style="display:flex;flex-direction:column;gap:20px;">

            <!-- Quiz Details -->
            <div class="card">
                <div class="card-header"><span class="card-title">Quiz Details</span></div>
                <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="form-field" style="grid-column:1/-1;">
                        <label>Title *</label>
                        <input v-model="form.title" required />
                    </div>
                    <div class="form-field" style="grid-column:1/-1;">
                        <label>Description</label>
                        <textarea v-model="form.description" rows="2"></textarea>
                    </div>
                    <div class="form-field">
                        <label>Subject</label>
                        <select v-model="form.subject_id">
                            <option value="">— None —</option>
                            <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Quiz Type *</label>
                        <select v-model="form.type">
                            <option value="mcq">MCQ Only</option>
                            <option value="descriptive">Descriptive Only</option>
                            <option value="mixed">Mixed</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Duration (minutes) *</label>
                        <input v-model="form.duration_minutes" type="number" min="1" max="480" required />
                    </div>
                    <div class="form-field">
                        <label>Pass Marks *</label>
                        <input v-model="form.pass_marks" type="number" step="0.5" min="0" required />
                    </div>
                    <div class="form-field">
                        <label>Start At</label>
                        <input v-model="form.start_at" type="datetime-local" />
                    </div>
                    <div class="form-field">
                        <label>End At</label>
                        <input v-model="form.end_at" type="datetime-local" />
                    </div>
                    <div class="form-field">
                        <label>Status *</label>
                        <select v-model="form.status">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:10px;padding-top:20px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" v-model="form.shuffle_questions" />
                            Shuffle Questions
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" v-model="form.shuffle_options" />
                            Shuffle MCQ Options
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" v-model="form.show_result_immediately" />
                            Show Result Immediately After Submit
                        </label>
                    </div>
                </div>
            </div>

            <!-- Questions -->
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="card-title">Questions ({{ form.questions.length }})</span>
                    <Button type="button" variant="secondary" size="sm" @click="addQuestion">+ Add Question</Button>
                </div>
                <div class="card-body" style="display:flex;flex-direction:column;gap:20px;">
                    <div v-for="(q, qi) in form.questions" :key="qi" style="border:1px solid #e2e8f0;border-radius:8px;padding:16px;position:relative;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                            <strong style="color:#374151;">Q{{ qi + 1 }}</strong>
                            <Button type="button" variant="danger" size="xs" @click="removeQuestion(qi)" v-if="form.questions.length > 1">Remove</Button>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr auto auto;gap:10px;margin-bottom:12px;">
                            <div class="form-field" style="margin:0;">
                                <label>Question *</label>
                                <textarea v-model="q.question_text" rows="2" required></textarea>
                            </div>
                            <div class="form-field" style="margin:0;width:140px;">
                                <label>Type</label>
                                <select v-model="q.type">
                                    <option value="mcq">MCQ</option>
                                    <option value="true_false">True/False</option>
                                    <option value="short_answer">Short Answer</option>
                                    <option value="descriptive">Descriptive</option>
                                </select>
                            </div>
                            <div class="form-field" style="margin:0;width:80px;">
                                <label>Marks</label>
                                <input v-model="q.marks" type="number" step="0.5" min="0" required />
                            </div>
                        </div>

                        <!-- MCQ Options -->
                        <div v-if="q.type === 'mcq'" style="margin-top:8px;">
                            <div style="font-size:.8rem;font-weight:600;color:#64748b;margin-bottom:6px;">Options (click radio to mark correct)</div>
                            <div v-for="(opt, oi) in q.options" :key="oi" style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                                <input type="radio" :name="`q${qi}_correct`" :checked="opt.is_correct" @change="setCorrectOption(q, oi)" />
                                <input v-model="opt.text" placeholder="Option text..." style="flex:1;" />
                                <Button type="button" variant="icon" size="xs" @click="removeOption(q, oi)" v-if="q.options.length > 2">&times;</Button>
                            </div>
                            <button type="button" @click="addOption(q)" style="font-size:.75rem;color:#3b82f6;background:none;border:none;cursor:pointer;padding:0;">+ Add Option</button>
                        </div>

                        <!-- True/False -->
                        <div v-else-if="q.type === 'true_false'" style="margin-top:8px;">
                            <div style="font-size:.8rem;font-weight:600;color:#64748b;margin-bottom:6px;">Correct Answer</div>
                            <div style="display:flex;gap:16px;">
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                    <input type="radio" v-model="q.correct_answer" value="true" /> True
                                </label>
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                    <input type="radio" v-model="q.correct_answer" value="false" /> False
                                </label>
                            </div>
                        </div>

                        <!-- Short answer -->
                        <div v-else-if="q.type === 'short_answer'" style="margin-top:8px;">
                            <div class="form-field" style="margin:0;">
                                <label>Expected Answer (for auto-grading)</label>
                                <input v-model="q.correct_answer" placeholder="Leave blank to grade manually" />
                            </div>
                        </div>

                        <div class="form-field" style="margin-top:10px;">
                            <label>Explanation (shown after submission)</label>
                            <input v-model="q.explanation" placeholder="Optional" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <Button type="button" variant="secondary" as="link" href="/school/quiz">Cancel</Button>
                <Button type="submit" :loading="form.processing">{{ isEdit ? 'Update Quiz' : 'Create Quiz' }}</Button>
            </div>
        </form>
    </SchoolLayout>
</template>
