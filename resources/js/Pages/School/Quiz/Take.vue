<script setup>
import Modal from '@/Components/ui/Modal.vue';
import Button from '@/Components/ui/Button.vue';
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue';

const props = defineProps({
    quiz:      Object,
    questions: Array,
    attempt:   Object,
    timeLeft:  Number,
});

const STORAGE_KEY = `quiz-attempt-${props.attempt.id}`;

// ── Timer ─────────────────────────────────────────────────────────
const secondsLeft = ref(props.timeLeft);
let timer = null;

const timerDisplay = computed(() => {
    const m = Math.floor(secondsLeft.value / 60);
    const s = secondsLeft.value % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

const timerClass = computed(() =>
    secondsLeft.value < 120 ? 'is-danger'
    : secondsLeft.value < 300 ? 'is-warn'
    : 'is-ok'
);

// ── Answers + autosave ────────────────────────────────────────────
const answers = ref({});
const tabSwitches = ref(0);

// Initialize from localStorage if available, otherwise blank
function initAnswers() {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
        if (saved && saved.answers) {
            answers.value = saved.answers;
            if (typeof saved.tabSwitches === 'number') tabSwitches.value = saved.tabSwitches;
        }
    } catch { /* ignore corrupted entry */ }

    // Ensure every question has a key
    props.questions.forEach(q => {
        if (!(q.id in answers.value)) answers.value[q.id] = null;
    });
}
initAnswers();

// Persist on every change
watch([answers, tabSwitches], () => {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({
            answers:      answers.value,
            tabSwitches:  tabSwitches.value,
            savedAt:      Date.now(),
        }));
    } catch { /* quota exceeded — ignore */ }
}, { deep: true });

const clearAutosave = () => { try { localStorage.removeItem(STORAGE_KEY); } catch {} };

// ── Navigation ────────────────────────────────────────────────────
const PHASE_TAKE = 'take';
const PHASE_REVIEW = 'review';
const phase = ref(PHASE_TAKE);

const currentQ = ref(0);
const totalQ   = computed(() => props.questions.length);

const isAnswered = (qid) => answers.value[qid] !== null && answers.value[qid] !== undefined && answers.value[qid] !== '';
const answeredCount = computed(() =>
    props.questions.filter(q => isAnswered(q.id)).length
);

const goPrev   = () => { if (currentQ.value > 0) currentQ.value--; };
const goNext   = () => { if (currentQ.value < totalQ.value - 1) currentQ.value++; };
const jumpTo   = (i) => { currentQ.value = i; phase.value = PHASE_TAKE; };
const goReview = () => { phase.value = PHASE_REVIEW; };

// ── Tab-switch tracking ───────────────────────────────────────────
const onVisibilityChange = () => {
    if (document.visibilityState === 'hidden') tabSwitches.value++;
};

// ── Keyboard shortcuts (only when in TAKE phase) ──────────────────
const onKeydown = (e) => {
    if (phase.value !== PHASE_TAKE) return;
    if (submitting.value || result.value) return;

    const target = e.target;
    const isTextarea = target?.tagName === 'TEXTAREA';
    const isInput = target?.tagName === 'INPUT' && target.type !== 'radio';
    if (isTextarea || isInput) return;

    const cur = props.questions[currentQ.value];
    if (!cur) return;

    if (e.key === 'ArrowLeft')  { e.preventDefault(); goPrev(); }
    if (e.key === 'ArrowRight') { e.preventDefault(); goNext(); }

    // A/B/C/D for MCQ option select
    if (cur.type === 'mcq' || cur.type === 'true_false') {
        const k = e.key.toUpperCase();
        if (k >= 'A' && k <= 'Z') {
            const idx = k.charCodeAt(0) - 65;
            const opts = cur.options || (cur.type === 'true_false' ? [{ text: 'True' }, { text: 'False' }] : []);
            if (idx < opts.length) {
                if (cur.type === 'true_false') {
                    answers.value[cur.id] = idx === 0 ? 'true' : 'false';
                } else {
                    answers.value[cur.id] = String(idx);
                }
                e.preventDefault();
            }
        }
    }
};

// ── Lifecycle ─────────────────────────────────────────────────────
onMounted(() => {
    timer = setInterval(() => {
        if (secondsLeft.value > 0) secondsLeft.value--;
        else {
            clearInterval(timer);
            submitQuiz(true);
        }
    }, 1000);
    document.addEventListener('visibilitychange', onVisibilityChange);
    window.addEventListener('keydown', onKeydown);
});
onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisibilityChange);
    window.removeEventListener('keydown', onKeydown);
});

// ── Submit ────────────────────────────────────────────────────────
const submitting = ref(false);
const result     = ref(null);
const showResult = ref(false);

const submitQuiz = async () => {
    if (submitting.value) return;
    submitting.value = true;
    clearInterval(timer);

    const payload = {
        answers: props.questions.map(q => ({
            question_id: q.id,
            answer: answers.value[q.id] ?? null,
        })),
        tab_switches: tabSwitches.value,
    };

    try {
        const res = await fetch(`/school/quiz/${props.quiz.id}/submit`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });
        result.value = await res.json();
        clearAutosave();
    } catch {
        result.value = { error: 'Submission failed. Please contact your teacher.' };
    } finally {
        submitting.value = false;
        showResult.value = true;
    }
};

const goToResults = () => {
    if (result.value?.attempt_id) {
        window.location.href = `/school/quiz/${props.quiz.id}/my-result/${result.value.attempt_id}`;
    } else {
        window.location.href = '/school/quiz/my-quizzes';
    }
};

// ── Helpers for review ────────────────────────────────────────────
const previewAnswer = (q) => {
    const a = answers.value[q.id];
    if (a === null || a === undefined || a === '') return null;
    if (q.type === 'mcq' && q.options && q.options[parseInt(a)]) {
        return `${String.fromCharCode(65 + parseInt(a))}. ${q.options[parseInt(a)].text}`;
    }
    if (q.type === 'true_false') return a === 'true' ? 'True' : 'False';
    return String(a).length > 60 ? String(a).slice(0, 60) + '…' : String(a);
};
</script>

<template>
    <!-- ───────────── Quiz taking screen ───────────── -->
    <div class="quiz-shell">

        <!-- Sticky header bar -->
        <div class="quiz-header">
            <div class="header-title">{{ quiz.title }}</div>
            <div :class="['quiz-timer', timerClass]" aria-live="polite">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
                {{ timerDisplay }}
            </div>
            <div class="header-progress">{{ answeredCount }} / {{ totalQ }} answered</div>
        </div>

        <!-- Question palette -->
        <div class="quiz-nav">
            <button
                v-for="(q, i) in questions"
                :key="q.id"
                type="button"
                @click="jumpTo(i)"
                :class="[
                    'palette-btn',
                    {
                        'is-active':   phase === 'take' && i === currentQ,
                        'is-answered': isAnswered(q.id),
                    }
                ]"
                :aria-label="`Question ${i + 1}${isAnswered(q.id) ? ' (answered)' : ''}`"
            >{{ i + 1 }}</button>
        </div>

        <!-- ── TAKE phase ── -->
        <div v-if="phase === 'take' && questions[currentQ]" class="question-panel">
            <div class="question-card">
                <div class="q-meta-row">
                    <span>Q{{ currentQ + 1 }} of {{ totalQ }}</span>
                    <span class="q-marks">{{ questions[currentQ].marks }} mark{{ questions[currentQ].marks != 1 ? 's' : '' }}</span>
                </div>
                <div class="q-text">{{ questions[currentQ].question_text }}</div>

                <!-- MCQ -->
                <div v-if="questions[currentQ].type === 'mcq'" class="opt-list">
                    <label
                        v-for="(opt, oi) in (questions[currentQ].options || [])"
                        :key="oi"
                        :class="['opt-card', { 'is-selected': String(answers[questions[currentQ].id]) === String(oi) }]"
                    >
                        <input
                            type="radio"
                            :name="`q${questions[currentQ].id}`"
                            :value="String(oi)"
                            v-model="answers[questions[currentQ].id]"
                        />
                        <span class="opt-letter">{{ String.fromCharCode(65 + oi) }}</span>
                        <span class="opt-text">{{ opt.text ?? opt }}</span>
                    </label>
                </div>

                <!-- True/False -->
                <div v-else-if="questions[currentQ].type === 'true_false'" class="opt-list">
                    <label
                        v-for="(label, i) in [{ text: 'True', value: 'true' }, { text: 'False', value: 'false' }]"
                        :key="i"
                        :class="['opt-card', { 'is-selected': answers[questions[currentQ].id] === label.value }]"
                    >
                        <input
                            type="radio"
                            :name="`q${questions[currentQ].id}`"
                            :value="label.value"
                            v-model="answers[questions[currentQ].id]"
                        />
                        <span class="opt-letter">{{ String.fromCharCode(65 + i) }}</span>
                        <span class="opt-text">{{ label.text }}</span>
                    </label>
                </div>

                <!-- Short answer / Descriptive -->
                <div v-else>
                    <textarea
                        v-model="answers[questions[currentQ].id]"
                        :rows="questions[currentQ].type === 'descriptive' ? 6 : 2"
                        placeholder="Write your answer here..."
                        class="answer-textarea"
                    ></textarea>
                </div>

                <!-- Nav -->
                <div class="nav-row">
                    <Button variant="secondary" size="sm" :disabled="currentQ === 0" @click="goPrev">← Prev</Button>
                    <span class="kbd-hint">← / → to navigate · A–Z to pick option</span>
                    <Button v-if="currentQ < totalQ - 1" size="sm" @click="goNext">Next →</Button>
                    <Button v-else variant="warning" size="sm" @click="goReview">Review &amp; Submit →</Button>
                </div>
            </div>

            <div v-if="tabSwitches > 0" class="tab-warn">
                ⚠ Tab switching detected ({{ tabSwitches }}). Stay on this page during the quiz.
            </div>
        </div>

        <!-- ── REVIEW phase ── -->
        <div v-else-if="phase === 'review'" class="review-panel">
            <div class="review-card card">
                <div class="card-header">
                    <span class="card-title">Review your answers</span>
                </div>
                <div class="card-body">
                    <p class="review-summary">
                        <strong>{{ answeredCount }}</strong> of <strong>{{ totalQ }}</strong> answered.
                        <span v-if="answeredCount < totalQ" class="review-warn">
                            {{ totalQ - answeredCount }} unanswered &mdash; you can still submit, but unanswered questions will be marked as 0.
                        </span>
                    </p>
                    <div class="review-list">
                        <div
                            v-for="(q, i) in questions"
                            :key="q.id"
                            class="review-row"
                            :class="{ 'is-blank': !isAnswered(q.id) }"
                        >
                            <div class="review-q-num">Q{{ i + 1 }}</div>
                            <div class="review-q-body">
                                <div class="review-q-text">{{ q.question_text }}</div>
                                <div class="review-q-ans">
                                    <span v-if="isAnswered(q.id)">{{ previewAnswer(q) }}</span>
                                    <span v-else class="muted">— Not answered —</span>
                                </div>
                            </div>
                            <Button variant="ghost" size="xs" @click="jumpTo(i)">Edit</Button>
                        </div>
                    </div>
                </div>
                <div class="card-footer review-footer">
                    <Button variant="secondary" @click="phase = 'take'">← Back to questions</Button>
                    <Button variant="success" :loading="submitting" @click="submitQuiz">Submit Quiz</Button>
                </div>
            </div>
        </div>
    </div>

    <!-- ───────────── Result modal ───────────── -->
    <Modal v-model:open="showResult" :title="result?.error ? 'Submission Error' : 'Quiz Submitted'" persistent hide-close size="sm">
        <div v-if="result?.error" class="result-block result-error">
            <p>{{ result.error }}</p>
        </div>
        <div v-else class="result-block">
            <div class="result-pct" :class="result?.passed ? 'is-pass' : 'is-fail'">
                {{ result?.percentage }}%
            </div>
            <div class="result-text">
                Score: <strong>{{ result?.score }}</strong> / {{ quiz.total_marks }}
            </div>
            <div class="result-status">
                <span class="badge" :class="result?.passed ? 'badge-green' : 'badge-red'">
                    {{ result?.passed === true ? 'PASSED' : result?.passed === false ? 'NOT PASSED' : 'PENDING REVIEW' }}
                </span>
            </div>
            <p v-if="result?.passed === null" class="result-note">
                Some answers need manual grading by your teacher. You'll see the full result later.
            </p>
        </div>
        <template #footer>
            <Button @click="goToResults">{{ result?.error ? 'Back to My Quizzes' : 'View Detailed Result' }}</Button>
        </template>
    </Modal>
</template>

<style scoped>
.quiz-shell {
    min-height: 100vh;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
}

/* Header */
.quiz-header {
    position: sticky;
    top: 0;
    z-index: 50;
    background: var(--surface, #fff);
    border-bottom: 1px solid var(--border, #e2e8f0);
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.header-title {
    font-weight: 600;
    font-size: 1rem;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--text-primary, #0f172a);
}
.quiz-timer {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 1.05rem;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, monospace;
    padding: 6px 14px;
    border-radius: 8px;
    line-height: 1;
}
.quiz-timer svg { width: 16px; height: 16px; }
.is-ok     { color: #059669; background: #d1fae5; }
.is-warn   { color: #d97706; background: #fef3c7; }
.is-danger { color: #dc2626; background: #fee2e2; animation: pulse 1s infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.55; } }

.header-progress {
    font-size: .85rem;
    color: var(--text-secondary, #475569);
}

/* Palette */
.quiz-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 14px 20px;
    background: var(--surface, #fff);
    border-bottom: 1px solid var(--border, #e2e8f0);
}
.palette-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1.5px solid var(--border, #e2e8f0);
    background: var(--surface, #fff);
    cursor: pointer;
    font-size: .8rem;
    font-weight: 600;
    color: var(--text-secondary, #475569);
    transition: all .15s;
    font-family: inherit;
}
.palette-btn:hover { border-color: var(--accent, #6366f1); }
.palette-btn.is-active {
    background: var(--accent, #6366f1);
    color: #fff;
    border-color: var(--accent, #6366f1);
}
.palette-btn.is-answered {
    background: #d1fae5;
    border-color: #6ee7b7;
    color: #065f46;
}
.palette-btn.is-active.is-answered {
    background: var(--accent, #6366f1);
    color: #fff;
    border-color: var(--accent, #6366f1);
}

/* Question card */
.question-panel { padding: 24px 20px; }
.question-card {
    max-width: 760px;
    margin: 0 auto;
    background: var(--surface, #fff);
    border-radius: 12px;
    padding: 28px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
    border: 1px solid var(--border, #e2e8f0);
}

.q-meta-row {
    display: flex;
    justify-content: space-between;
    font-size: .8rem;
    color: var(--text-muted, #94a3b8);
    margin-bottom: 12px;
}
.q-marks {
    font-weight: 700;
    color: var(--accent, #6366f1);
}
.q-text {
    font-size: 1.05rem;
    font-weight: 500;
    color: var(--text-primary, #0f172a);
    line-height: 1.6;
    margin-bottom: 20px;
    white-space: pre-wrap;
}

.opt-list { display: flex; flex-direction: column; gap: 10px; }
.opt-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border: 2px solid var(--border, #e2e8f0);
    border-radius: 10px;
    cursor: pointer;
    transition: all .15s;
    background: var(--surface, #fff);
}
.opt-card:hover { border-color: #93c5fd; }
.opt-card.is-selected {
    border-color: var(--accent, #6366f1);
    background: var(--accent-subtle, rgba(99, 102, 241, 0.08));
}
.opt-card input[type=radio] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.opt-letter {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--border-light, #f1f5f9);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .8rem;
    font-weight: 700;
    flex-shrink: 0;
}
.opt-card.is-selected .opt-letter {
    background: var(--accent, #6366f1);
    color: #fff;
}
.opt-text { flex: 1; color: var(--text-primary, #0f172a); }

.answer-textarea {
    width: 100%;
    border: 2px solid var(--border, #e2e8f0);
    border-radius: 10px;
    padding: 12px;
    resize: vertical;
    font-size: .95rem;
    font-family: inherit;
    color: var(--text-primary, #0f172a);
}
.answer-textarea:focus {
    outline: none;
    border-color: var(--accent, #6366f1);
}

.nav-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 24px;
    flex-wrap: wrap;
}
.kbd-hint {
    font-size: .7rem;
    color: var(--text-muted, #94a3b8);
    flex: 1;
    text-align: center;
}

.tab-warn {
    max-width: 760px;
    margin: 14px auto 0;
    padding: 10px 14px;
    background: #fef3c7;
    border: 1px solid #fde68a;
    border-radius: 8px;
    color: #92400e;
    font-size: .8rem;
    text-align: center;
}

/* Review screen */
.review-panel { padding: 24px 20px; }
.review-card {
    max-width: 760px;
    margin: 0 auto;
    background: var(--surface, #fff);
}
.review-summary {
    margin: 0 0 14px;
    font-size: .9rem;
    color: var(--text-secondary, #475569);
}
.review-warn { display: block; color: #d97706; margin-top: 4px; font-size: .82rem; }

.review-list { display: flex; flex-direction: column; gap: 8px; }
.review-row {
    display: grid;
    grid-template-columns: 50px 1fr auto;
    gap: 12px;
    align-items: center;
    padding: 12px;
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 8px;
    background: var(--surface, #fff);
}
.review-row.is-blank {
    background: #fffbeb;
    border-color: #fde68a;
}
.review-q-num {
    font-weight: 700;
    color: var(--accent, #6366f1);
    font-size: .85rem;
    text-align: center;
}
.review-row.is-blank .review-q-num { color: #d97706; }
.review-q-text {
    font-size: .85rem;
    color: var(--text-primary, #0f172a);
    line-height: 1.4;
    margin-bottom: 4px;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.review-q-ans { font-size: .8rem; color: var(--text-secondary, #475569); }
.muted { color: var(--text-muted, #94a3b8); font-style: italic; }

.review-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 14px 20px;
    border-top: 1px solid var(--border-light, #f1f5f9);
    background: #f8fafc;
}

/* Result modal body */
.result-block { text-align: center; padding: 8px 0; }
.result-pct {
    font-size: 3rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 8px;
}
.result-pct.is-pass { color: #16a34a; }
.result-pct.is-fail { color: #dc2626; }
.result-text {
    font-size: .95rem;
    color: var(--text-secondary, #475569);
}
.result-status { margin-top: 14px; }
.result-status .badge {
    font-size: .85rem;
    padding: 6px 16px;
    letter-spacing: .05em;
}
.result-note {
    margin-top: 14px;
    font-size: .8rem;
    color: var(--text-muted, #94a3b8);
}
.result-error {
    color: #dc2626;
    font-size: .9rem;
}

@media (max-width: 600px) {
    .quiz-header { flex-wrap: wrap; gap: 8px; padding: 10px 14px; }
    .header-progress { width: 100%; text-align: center; }
    .question-card { padding: 20px 16px; }
    .review-row { grid-template-columns: 36px 1fr auto; }
    .kbd-hint { display: none; }
}
</style>
