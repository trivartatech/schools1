<template>
    <Head title="AI Intelligence Hub" />
    <SchoolLayout title="AI Intelligence Hub">

        <PageHeader :title="`🧠 AI Intelligence Hub`" :subtitle="`Smart insights and natural-language data queries for ${schoolName}`">
            <template #actions>
                <div class="export-wrap">
                    <button class="btn-export" @click.stop="toggleExportMenu" :disabled="exportBusy">
                        <span v-if="exportBusy" class="spin">⏳</span>
                        <span v-else>⬇</span>
                        Export
                    </button>
                    <div v-if="exportOpen" class="export-menu" @click.stop>
                        <button @click="exportPdf">PDF report</button>
                        <button @click="exportExcel">Excel workbook</button>
                    </div>
                </div>
            </template>
        </PageHeader>

        <!-- ── Filter bar ── -->
        <FilterBar>
            <DateRangeFilter
                :from="filters.from"
                :to="filters.to"
                @change="onRangeChange"
            />
            <label class="cmp-toggle">
                <input type="checkbox" v-model="filters.compare" @change="onComparisonChange" />
                Compare with previous period
            </label>
            <div class="views-dropdown">
                <button class="views-btn" @click.stop="toggleViewsMenu">
                    📌 Views
                </button>
                <div v-if="viewsOpen" class="views-menu" @click.stop>
                    <button class="views-action" @click="openSaveView">+ Save current view</button>
                    <div class="views-divider" v-if="savedViews.length"></div>
                    <div v-for="v in savedViews" :key="v.id" class="views-row">
                        <button class="views-load" @click="loadView(v)">{{ v.name }}</button>
                        <button class="views-del" @click.stop="deleteView(v)" title="Delete">✕</button>
                    </div>
                    <div v-if="!savedViews.length" class="views-empty">No saved views yet</div>
                </div>
            </div>
        </FilterBar>

        <!-- ── Two-column layout ── -->
        <div class="ai-hub-grid">

            <!-- ═══ LEFT: SMART INSIGHTS ═══ -->
            <div class="ai-section">
                <div class="ai-section-header">
                    <div class="ai-section-title">
                        <span class="ai-section-icon">📊</span>
                        <div>
                            <div class="ai-section-name">Smart Insights</div>
                            <div class="ai-section-sub">AI analysis of your school's live data</div>
                        </div>
                    </div>
                    <div class="header-right">
                        <span v-if="generatedLabel" class="generated-label">{{ generatedLabel }}</span>
                        <button class="btn-ai-action" @click="generateInsights(true)" :disabled="insightLoading">
                            <span v-if="insightLoading" class="ai-spin">⏳</span>
                            <span v-else>✨</span>
                            {{ insightLoading ? 'Analysing…' : (insights.length ? 'Regenerate' : 'Generate Insights') }}
                        </button>
                    </div>
                </div>

                <!-- Snapshot stats bar -->
                <div v-if="snapshot" class="snapshot-bar">
                    <div class="snap-item">
                        <div class="snap-value">{{ snapshot.students?.total ?? '—' }}</div>
                        <div class="snap-label">Students</div>
                    </div>
                    <div class="snap-item">
                        <div class="snap-value" :class="attColor">
                            {{ snapshot.attendance?.percentage != null ? snapshot.attendance.percentage + '%' : '—' }}
                            <span v-if="filters.compare && snapshot.comparison?.attendance_pct != null"
                                class="snap-delta" :class="deltaClass(snapshot.attendance?.percentage, snapshot.comparison.attendance_pct)">
                                {{ deltaArrow(snapshot.attendance?.percentage, snapshot.comparison.attendance_pct) }}
                                {{ Math.abs(deltaValue(snapshot.attendance?.percentage, snapshot.comparison.attendance_pct)).toFixed(1) }}
                            </span>
                        </div>
                        <div class="snap-label">Attendance</div>
                    </div>
                    <div class="snap-item">
                        <div class="snap-value" :class="staffAttColor">{{ snapshot.staff?.attendance_pct != null ? snapshot.staff.attendance_pct + '%' : '—' }}</div>
                        <div class="snap-label">Staff Present</div>
                    </div>
                    <div class="snap-item">
                        <div class="snap-value">
                            ₹{{ formatNum(snapshot.fees?.collected_in_range) }}
                            <span v-if="filters.compare && snapshot.comparison?.fees_collected != null"
                                class="snap-delta" :class="deltaClass(snapshot.fees?.collected_in_range, snapshot.comparison.fees_collected)">
                                {{ deltaArrow(snapshot.fees?.collected_in_range, snapshot.comparison.fees_collected) }}
                            </span>
                        </div>
                        <div class="snap-label">Collected (Range)</div>
                    </div>
                    <div class="snap-item">
                        <div class="snap-value snap-danger">₹{{ formatNum(snapshot.fees?.total_pending) }}</div>
                        <div class="snap-label">Pending Fees</div>
                    </div>
                    <div class="snap-item">
                        <div class="snap-value snap-danger">{{ snapshot.attendance?.low_attendance_students?.length ?? 0 }}</div>
                        <div class="snap-label">Low Attendance</div>
                    </div>
                </div>

                <!-- Error -->
                <div v-if="insightError" class="ai-err">⚠️ {{ insightError }}</div>

                <!-- Empty state -->
                <div v-if="!insights.length && !insightLoading && !insightError" class="ai-empty">
                    <div class="ai-empty-icon">📈</div>
                    <div>Generating AI-powered analysis of your school's live data…</div>
                    <div class="ai-empty-sub">Covers attendance, fees, enrollment, staff & more</div>
                </div>

                <!-- Loading skeleton -->
                <div v-if="insightLoading" class="insights-grid">
                    <div v-for="i in 8" :key="i" class="insight-card insight-skeleton">
                        <div class="sk-line sk-title"></div>
                        <div class="sk-line sk-metric"></div>
                        <div class="sk-line sk-body"></div>
                        <div class="sk-line sk-body sk-short"></div>
                    </div>
                </div>

                <!-- Insight cards -->
                <div v-if="insights.length && !insightLoading" class="insights-grid">
                    <div v-for="(ins, i) in insights" :key="i"
                        class="insight-card"
                        :class="`insight-${ins.severity}`">
                        <div class="insight-top">
                            <div class="insight-top-left">
                                <span class="insight-icon">{{ ins.icon }}</span>
                                <span class="insight-category">{{ ins.category }}</span>
                            </div>
                            <div class="insight-top-right">
                                <span class="insight-trend" :class="`trend-${ins.trend}`">
                                    {{ ins.trend === 'up' ? '▲' : ins.trend === 'down' ? '▼' : '→' }}
                                </span>
                                <span class="insight-badge" :class="`badge-${ins.severity}`">
                                    {{ ins.severity === 'success' ? '✅ Good' : ins.severity === 'warning' ? '⚠️ Watch' : '🚨 Urgent' }}
                                </span>
                            </div>
                        </div>
                        <div v-if="ins.metric" class="insight-metric" :class="`metric-${ins.severity}`">{{ ins.metric }}</div>
                        <div class="insight-title">{{ ins.title }}</div>
                        <div class="insight-body">{{ ins.insight }}</div>
                        <div class="insight-action">
                            <span class="insight-action-label">→</span> {{ ins.action }}
                        </div>
                        <a v-if="ins.link" :href="ins.link" class="insight-drill">View affected →</a>
                    </div>
                </div>
            </div>

            <!-- ═══ RIGHT: ASK YOUR DATA ═══ -->
            <div class="ai-section">
                <div class="ai-section-header">
                    <div class="ai-section-title">
                        <span class="ai-section-icon">💬</span>
                        <div>
                            <div class="ai-section-name">Ask Your Data</div>
                            <div class="ai-section-sub">Natural-language queries on live school data</div>
                        </div>
                    </div>
                </div>

                <!-- Suggested questions -->
                <div class="suggestions-wrap">
                    <div class="suggestions-label">Try asking:</div>
                    <div class="suggestions-list">
                        <button v-for="q in suggestedQuestions" :key="q"
                            class="suggestion-chip"
                            @click="askQuestion(q)">
                            {{ q }}
                        </button>
                    </div>
                </div>

                <!-- QA History -->
                <div class="qa-history" ref="qaHistoryEl">
                    <div v-if="!qaHistory.length" class="ai-empty">
                        <div class="ai-empty-icon">🔍</div>
                        <div>Ask any question about your school data in plain English.</div>
                        <div class="ai-empty-sub">e.g. "How many students are absent today?"</div>
                    </div>

                    <div v-for="(qa, i) in qaHistory" :key="i" class="qa-item">
                        <div class="qa-question">
                            <span class="qa-q-icon">🙋</span>
                            <div class="qa-q-text">{{ qa.question }}</div>
                        </div>
                        <div class="qa-answer" :class="{ 'qa-answer--loading': qa.loading && !qa.answer }">
                            <span class="qa-a-icon">🤖</span>
                            <div v-if="qa.loading && !qa.answer" class="qa-typing">
                                <span></span><span></span><span></span>
                            </div>
                            <div v-else-if="qa.error" class="qa-error">{{ qa.error }}</div>
                            <div v-else class="qa-a-text" v-html="formatAnswer(qa.answer)"></div>
                        </div>
                        <div v-if="qa.follow_ups && qa.follow_ups.length && !qa.loading" class="qa-follow-ups">
                            <button v-for="f in qa.follow_ups" :key="f"
                                class="qa-followup-chip"
                                @click="askQuestion(f)">
                                {{ f }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Input -->
                <div class="qa-input-wrap">
                    <textarea
                        ref="qaInputEl"
                        v-model="question"
                        class="qa-input"
                        placeholder="Ask anything about your school data…"
                        rows="1"
                        @keydown.enter.exact.prevent="submitQuestion"
                        @input="autoResize"
                        :disabled="qaLoading"
                    ></textarea>
                    <button class="qa-send" @click="submitQuestion" :disabled="!question.trim() || qaLoading">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                    </button>
                </div>
                <div class="qa-hint">Enter to send · Shift+Enter for new line</div>
            </div>
        </div>

        <!-- ── Charts row ── -->
        <div class="charts-row" v-if="chartsLoaded">
            <div class="chart-card">
                <div class="chart-head">
                    <div class="chart-title">Attendance Trend</div>
                    <button class="explain-btn" @click="explainChart('attendance_trend', charts.attendance_trend)">✨ Explain</button>
                </div>
                <TrendChart
                    type="line"
                    :labels="charts.attendance_trend.map(r => (r.date || '').slice(5))"
                    :datasets="[{ label: 'Attendance %', data: charts.attendance_trend.map(r => r.rate), color: '#6366f1' }]"
                    :height="220"
                    :y-percent="true"
                />
            </div>

            <div class="chart-card">
                <div class="chart-head">
                    <div class="chart-title">Fee Collection (12 mo)</div>
                    <button class="explain-btn" @click="explainChart('fee_collection', charts.fee_collection)">✨ Explain</button>
                </div>
                <TrendChart
                    type="bar"
                    :labels="charts.fee_collection.map(r => r.month)"
                    :datasets="[
                        { label: 'Collected', data: charts.fee_collection.map(r => r.collected), color: '#10b981' },
                        { label: 'Target',    data: charts.fee_collection.map(r => r.target),    color: '#94a3b8' },
                    ]"
                    :legend="true"
                    :height="220"
                    currency="₹"
                />
            </div>

            <div class="chart-card">
                <div class="chart-head">
                    <div class="chart-title">Enrollment by Class</div>
                    <button class="explain-btn" @click="explainChart('enrollment_by_class', charts.enrollment_by_class)">✨ Explain</button>
                </div>
                <DonutChart
                    :labels="charts.enrollment_by_class.map(r => r.class)"
                    :data="charts.enrollment_by_class.map(r => r.count)"
                    :height="220"
                />
            </div>

            <div class="chart-card">
                <div class="chart-head">
                    <div class="chart-title">Top 10 Fee Defaulters</div>
                    <button class="explain-btn" @click="explainChart('top_defaulters', charts.top_defaulters)">✨ Explain</button>
                </div>
                <TrendChart
                    type="bar"
                    :labels="charts.top_defaulters.map(r => r.student)"
                    :datasets="[{ label: 'Outstanding', data: charts.top_defaulters.map(r => r.balance), color: '#ef4444' }]"
                    :height="220"
                    currency="₹"
                />
            </div>
        </div>

        <!-- ── Explain trend slide panel ── -->
        <SlidePanel :open="explainOpen" :title="explainTitle" width="w-[28rem]" @close="closeExplain">
            <div v-if="explaining && !explainText" class="qa-typing"><span></span><span></span><span></span></div>
            <div class="explain-body" v-else>{{ explainText }}</div>
        </SlidePanel>

        <!-- ── Save view modal (replaces native prompt) ── -->
        <Modal v-model:open="saveViewOpen" title="Save current view" size="sm">
            <div class="form-field">
                <label>View name</label>
                <input
                    type="text"
                    v-model="saveViewName"
                    placeholder="e.g. Q1 enrolment trend"
                    class="form-input"
                    style="width:100%;border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-size:0.85rem;"
                    @keyup.enter="saveCurrentView"
                />
            </div>
            <template #footer>
                <Button variant="secondary" @click="saveViewOpen = false">Cancel</Button>
                <Button :disabled="!saveViewName.trim()" @click="saveCurrentView">Save</Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, reactive } from 'vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import DateRangeFilter from '@/Components/ui/DateRangeFilter.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Modal from '@/Components/ui/Modal.vue';
import Button from '@/Components/ui/Button.vue';
import TrendChart from '@/Components/dashboard/TrendChart.vue';
import DonutChart from '@/Components/dashboard/DonutChart.vue';
import SlidePanel from '@/Components/SlidePanel.vue';
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';
import axios from 'axios';

const toast   = useToast();
const confirm = useConfirm();

const props = defineProps({
    initialInsights:    { type: Array,  default: () => [] },
    initialSnapshot:    { type: Object, default: null },
    initialGeneratedAt: { type: String, default: null },
    allowedDrillLinks:  { type: Array,  default: () => [] },
});

const schoolName = computed(() => usePage().props?.school?.name ?? 'your school');

// ── Filters ───────────────────────────────────────────
const today = new Date().toISOString().slice(0, 10);
const monthStart = (() => {
    const d = new Date();
    d.setDate(1);
    return d.toISOString().slice(0, 10);
})();

const initialQS = new URLSearchParams(window.location.search);
const filters = reactive({
    from:    initialQS.get('from')    || monthStart,
    to:      initialQS.get('to')      || today,
    compare: initialQS.get('compare') === '1',
});

function syncUrl() {
    const url = new URL(window.location.href);
    url.searchParams.set('from', filters.from);
    url.searchParams.set('to',   filters.to);
    if (filters.compare) url.searchParams.set('compare', '1');
    else url.searchParams.delete('compare');
    window.history.replaceState({}, '', url.toString());
}

// ── Smart Insights ────────────────────────────────────
const insights       = ref(props.initialInsights);
const snapshot       = ref(props.initialSnapshot);
const insightLoading = ref(false);
const insightError   = ref('');
const generatedAt    = ref(props.initialGeneratedAt);

const generatedLabel = computed(() => {
    if (!generatedAt.value) return '';
    const mins = Math.round((Date.now() - new Date(generatedAt.value)) / 60000);
    if (mins < 1)  return 'Just now';
    if (mins < 60) return `${mins} min ago`;
    const hrs = Math.floor(mins / 60);
    return hrs === 1 ? '1 hr ago' : `${hrs} hrs ago`;
});

const attColor = computed(() => {
    const p = snapshot.value?.attendance?.percentage;
    if (p == null) return '';
    if (p >= 85) return 'snap-success';
    if (p >= 70) return 'snap-warning';
    return 'snap-danger';
});

const staffAttColor = computed(() => {
    const p = snapshot.value?.staff?.attendance_pct;
    if (p == null) return '';
    if (p >= 90) return 'snap-success';
    if (p >= 75) return 'snap-warning';
    return 'snap-danger';
});

function deltaValue(curr, prev) {
    if (curr == null || prev == null) return 0;
    return curr - prev;
}
function deltaArrow(curr, prev) {
    const d = deltaValue(curr, prev);
    if (d > 0.05) return '▲';
    if (d < -0.05) return '▼';
    return '→';
}
function deltaClass(curr, prev) {
    const d = deltaValue(curr, prev);
    if (d > 0.05) return 'snap-success';
    if (d < -0.05) return 'snap-danger';
    return '';
}

async function generateInsights(force = false) {
    insightLoading.value = true;
    insightError.value   = '';
    if (force) insights.value = [];
    try {
        const { data } = await axios.post('/school/ai/insights/generate', {
            force,
            from: filters.from,
            to:   filters.to,
            compare: filters.compare,
        });
        insights.value    = data.insights     ?? [];
        snapshot.value    = data.snapshot     ?? null;
        generatedAt.value = data.generated_at ?? null;
    } catch (e) {
        insightError.value = e.response?.data?.error ?? 'Failed to generate insights.';
    } finally {
        insightLoading.value = false;
    }
}

function formatNum(val) {
    if (val == null) return '—';
    if (val >= 100000) return (val / 100000).toFixed(1) + 'L';
    if (val >= 1000)   return (val / 1000).toFixed(1) + 'K';
    return val;
}

// ── Charts ────────────────────────────────────────────
const charts = reactive({
    attendance_trend:    [],
    fee_collection:      [],
    enrollment_by_class: [],
    top_defaulters:      [],
    exam_performance:    [],
});
const chartsLoaded = ref(false);

async function loadCharts() {
    try {
        const { data } = await axios.get('/school/ai/insights/charts', {
            params: { from: filters.from, to: filters.to },
        });
        Object.assign(charts, data);
        chartsLoaded.value = true;
    } catch (e) {
        console.warn('Failed to load charts', e);
        chartsLoaded.value = true;
    }
}

// ── Explain trend ─────────────────────────────────────
const explainOpen  = ref(false);
const explainTitle = ref('');
const explainText  = ref('');
const explaining   = ref(false);

async function explainChart(name, series) {
    explainOpen.value = true;
    explainTitle.value = `✨ Explain — ${name.replace(/_/g, ' ')}`;
    explainText.value = '';
    explaining.value = true;

    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const resp = await fetch('/school/ai/explain-chart', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ chart: name, series }),
        });
        if (!resp.ok || !resp.body) {
            explainText.value = 'Could not load explanation.';
            return;
        }
        const reader = resp.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        while (true) {
            const { value, done } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            // Parse complete SSE events
            let idx;
            while ((idx = buffer.indexOf('\n\n')) !== -1) {
                const evt = buffer.slice(0, idx);
                buffer = buffer.slice(idx + 2);
                const dataLine = evt.split('\n').find(l => l.startsWith('data: '));
                if (!dataLine) continue;
                try {
                    const parsed = JSON.parse(dataLine.slice(6));
                    if (parsed.t) explainText.value += parsed.t;
                } catch {}
            }
        }
    } catch (e) {
        explainText.value = 'Explanation failed.';
    } finally {
        explaining.value = false;
    }
}

function closeExplain() {
    explainOpen.value = false;
    explainText.value = '';
}

// ── Saved views ──────────────────────────────────────
const savedViews = ref([]);
const viewsOpen  = ref(false);

async function loadSavedViews() {
    try {
        const { data } = await axios.get('/school/ai/insights/views');
        savedViews.value = data.views ?? [];
    } catch {}
}

function toggleViewsMenu() {
    viewsOpen.value = !viewsOpen.value;
    if (viewsOpen.value) loadSavedViews();
}

// View-name modal (replaces native prompt)
const saveViewOpen = ref(false);
const saveViewName = ref('');
function openSaveView() {
    saveViewName.value = '';
    saveViewOpen.value = true;
}
async function saveCurrentView() {
    const name = saveViewName.value.trim();
    if (!name) return;
    try {
        await axios.post('/school/ai/insights/views', {
            name,
            filters: { from: filters.from, to: filters.to, compare: filters.compare },
        });
        saveViewOpen.value = false;
        toast.success('View saved');
        await loadSavedViews();
    } catch (e) {
        toast.error('Failed to save view');
    }
}

function loadView(v) {
    const f = v.filters_json || {};
    if (f.from) filters.from = f.from;
    if (f.to)   filters.to   = f.to;
    filters.compare = !!f.compare;
    viewsOpen.value = false;
    onRangeChange({ from: filters.from, to: filters.to });
}

async function deleteView(v) {
    const ok = await confirm({
        title: 'Delete view',
        message: `Delete view "${v.name}"?`,
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    try {
        await axios.delete(`/school/ai/insights/views/${v.id}`);
        toast.success('View deleted');
        await loadSavedViews();
    } catch (e) {
        toast.error('Failed to delete');
    }
}

// ── Filter changes ────────────────────────────────────
function onRangeChange({ from, to }) {
    filters.from = from;
    filters.to   = to;
    syncUrl();
    refreshAll();
}
function onComparisonChange() {
    syncUrl();
    generateInsights(true);
}
async function refreshAll() {
    await Promise.all([generateInsights(true), loadCharts(), refreshSuggestions()]);
}

// ── Export ────────────────────────────────────────────
const exportOpen = ref(false);
const exportBusy = ref(false);
function toggleExportMenu() { exportOpen.value = !exportOpen.value; }

async function exportPdf() {
    exportOpen.value = false;
    exportBusy.value = true;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const resp = await fetch('/school/ai/insights/export-pdf', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                from: filters.from,
                to:   filters.to,
                chartImages: {},
            }),
        });
        if (!resp.ok) throw new Error('Export failed');
        const blob = await resp.blob();
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = `ai-insights-${filters.from}-to-${filters.to}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
    } catch {
        toast.error('PDF export failed');
    } finally {
        exportBusy.value = false;
    }
}

function exportExcel() {
    exportOpen.value = false;
    const url = new URL('/school/ai/insights/export-excel', window.location.origin);
    url.searchParams.set('from', filters.from);
    url.searchParams.set('to',   filters.to);
    window.location.href = url.toString();
}

// ── Q&A ──────────────────────────────────────────────
const question     = ref('');
const qaLoading    = ref(false);
const qaHistory    = ref([]);
const qaHistoryEl  = ref(null);
const qaInputEl    = ref(null);
const suggestedQuestions = ref([
    'How many students are absent today?',
    'What is today\'s fee collection?',
    'Who are the top fee defaulters?',
]);

async function refreshSuggestions(lastQuestion = '') {
    try {
        const { data } = await axios.post('/school/ai/suggestions', {
            page:          window.location.pathname,
            context:       'insights',
            last_question: lastQuestion,
            count:         6,
        });
        if (Array.isArray(data.suggestions) && data.suggestions.length) {
            suggestedQuestions.value = data.suggestions;
        }
    } catch {}
}

async function askQuestion(q) {
    question.value = q;
    await submitQuestion();
}

async function submitQuestion() {
    const q = question.value.trim();
    if (!q || qaLoading.value) return;

    const entry = reactive({ question: q, answer: '', follow_ups: [], loading: true, error: '' });
    qaHistory.value.push(entry);
    question.value = '';
    qaLoading.value = true;
    resetInputHeight();

    await nextTick();
    scrollQaToBottom();

    const history = qaHistory.value
        .filter(qa => qa.answer && !qa.error && !qa.loading)
        .slice(-5)
        .map(qa => ({ q: qa.question, a: qa.answer }));

    try {
        await streamQA(entry, q, history);
        // After answer is complete, fetch follow-ups via the non-stream endpoint with no extra LLM cost (we already have answer).
        // Using a lightweight 2nd call only for follow_ups would double cost. Instead we ask the suggestions endpoint for follow-ups using the question.
        await refreshSuggestions(q);
    } catch (e) {
        entry.error = e.response?.data?.error ?? 'Failed to get answer.';
    } finally {
        entry.loading = false;
        qaLoading.value = false;
        await nextTick();
        scrollQaToBottom();
    }
}

async function streamQA(entry, q, history) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const resp = await fetch('/school/ai/query/stream', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'text/event-stream',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ question: q, history, from: filters.from, to: filters.to }),
    });

    if (!resp.ok || !resp.body) {
        // Fallback to JSON endpoint
        const j = await axios.post('/school/ai/query', { question: q, history, from: filters.from, to: filters.to });
        entry.answer     = j.data.answer ?? '';
        entry.follow_ups = j.data.follow_ups ?? [];
        return;
    }
    const reader = resp.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    while (true) {
        const { value, done } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });
        let idx;
        while ((idx = buffer.indexOf('\n\n')) !== -1) {
            const evt = buffer.slice(0, idx);
            buffer = buffer.slice(idx + 2);
            const dataLine = evt.split('\n').find(l => l.startsWith('data: '));
            if (!dataLine) continue;
            try {
                const parsed = JSON.parse(dataLine.slice(6));
                if (parsed.t) {
                    entry.answer += parsed.t;
                    await nextTick();
                    scrollQaToBottom();
                }
            } catch {}
        }
    }
}

function scrollQaToBottom() {
    if (qaHistoryEl.value) qaHistoryEl.value.scrollTop = qaHistoryEl.value.scrollHeight;
}

function autoResize(e) {
    e.target.style.height = 'auto';
    e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
}

function resetInputHeight() {
    if (qaInputEl.value) qaInputEl.value.style.height = 'auto';
}

function escapeHtml(text) {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatAnswer(text) {
    if (!text) return '';
    return escapeHtml(text)
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/`(.+?)`/g, '<code>$1</code>')
        .replace(/^[-•]\s(.+)/gm, '<li>$1</li>')
        .replace(/^\d+\.\s(.+)/gm, '<li>$1</li>')
        .replace(/(<li>[\s\S]*?<\/li>)+/g, '<ul>$&</ul>')
        .replace(/\n\n/g, '<br><br>')
        .replace(/\n/g, '<br>')
        .replace(/(₹[\d,]+(?:\.\d+)?[KkLl]?|\d+(?:\.\d+)?%|\b\d{3,}(?:,\d{3})*(?:\.\d+)?\b)/g,
            '<span class="answer-num">$1</span>');
}

onMounted(() => {
    document.addEventListener('click', () => {
        viewsOpen.value = false;
        exportOpen.value = false;
    });
    if (!insights.value.length) generateInsights(false);
    loadCharts();
    refreshSuggestions();
});
</script>

<style scoped>
.ai-hub-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    align-items: start;
}
@media (max-width: 900px) {
    .ai-hub-grid { grid-template-columns: 1fr; }
}

.cmp-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
    color: var(--text-muted);
    cursor: pointer;
    user-select: none;
}
.cmp-toggle input { accent-color: #6366f1; }

.views-dropdown { position: relative; }
.views-btn {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 0.78rem;
    color: #475569;
    cursor: pointer;
}
.views-btn:hover { background: #e0e7ff; color: #4338ca; }
.views-menu {
    position: absolute;
    top: calc(100% + 4px);
    right: 0;
    min-width: 220px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.12);
    padding: 6px;
    z-index: 30;
}
.views-action {
    width: 100%;
    text-align: left;
    background: none;
    border: none;
    padding: 7px 10px;
    border-radius: 6px;
    color: #4f46e5;
    font-weight: 600;
    font-size: 0.78rem;
    cursor: pointer;
}
.views-action:hover { background: #ede9fe; }
.views-divider { height: 1px; background: #e2e8f0; margin: 4px 0; }
.views-row { display: flex; align-items: center; gap: 4px; }
.views-load {
    flex: 1;
    text-align: left;
    background: none;
    border: none;
    padding: 7px 10px;
    border-radius: 6px;
    color: #1e293b;
    font-size: 0.78rem;
    cursor: pointer;
}
.views-load:hover { background: #f1f5f9; }
.views-del {
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.85rem;
}
.views-del:hover { color: #dc2626; background: #fee2e2; }
.views-empty { padding: 8px 10px; color: #94a3b8; font-size: 0.75rem; text-align: center; }

.export-wrap { position: relative; }
.btn-export {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    background: #fff;
    color: #4f46e5;
    border: 1px solid #c7d2fe;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-export:hover { background: #ede9fe; }
.btn-export:disabled { opacity: 0.55; cursor: not-allowed; }
.export-menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    min-width: 160px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.12);
    padding: 4px;
    z-index: 30;
}
.export-menu button {
    width: 100%;
    text-align: left;
    background: none;
    border: none;
    padding: 8px 12px;
    border-radius: 6px;
    color: #1e293b;
    font-size: 0.82rem;
    cursor: pointer;
}
.export-menu button:hover { background: #f1f5f9; }

.spin { animation: spin 1s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }

.ai-section {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    min-height: 500px;
}
.ai-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
    background: var(--surface-secondary);
    flex-shrink: 0;
}
.ai-section-title { display: flex; align-items: center; gap: 12px; }
.ai-section-icon  { font-size: 1.5rem; }
.ai-section-name  { font-weight: 700; font-size: 1rem; color: var(--text-primary); }
.ai-section-sub   { font-size: 0.75rem; color: var(--text-muted); margin-top: 2px; }

.header-right {
    display: flex;
    align-items: center;
    gap: 10px;
}
.generated-label {
    font-size: 0.7rem;
    color: var(--text-muted);
    white-space: nowrap;
}

.btn-ai-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.15s;
    white-space: nowrap;
}
.btn-ai-action:disabled { opacity: 0.55; cursor: not-allowed; }
.ai-spin { animation: spin 1s linear infinite; display: inline-block; }

.snapshot-bar {
    display: flex;
    gap: 0;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.snap-item {
    flex: 1;
    text-align: center;
    padding: 10px 6px;
    border-right: 1px solid var(--border);
}
.snap-item:last-child { border-right: none; }
.snap-value   { font-size: 1rem; font-weight: 800; color: var(--text-primary); display: inline-flex; align-items: center; gap: 4px; justify-content: center; }
.snap-label   { font-size: 0.6rem; color: var(--text-muted); margin-top: 2px; font-weight: 500; }
.snap-success { color: #059669; }
.snap-warning { color: #d97706; }
.snap-danger  { color: #dc2626; }
.snap-delta { font-size: 0.65rem; font-weight: 700; }

.ai-empty { padding: 40px 24px; text-align: center; color: var(--text-muted); font-size: 0.88rem; flex: 1; }
.ai-empty-icon { font-size: 2.5rem; margin-bottom: 10px; }
.ai-empty-sub  { font-size: 0.75rem; color: var(--text-muted); margin-top: 6px; }
.ai-err { margin: 12px 16px; padding: 10px 14px; background: #fee2e2; border-radius: 8px; color: #dc2626; font-size: 0.82rem; }

.insights-grid {
    padding: 16px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    overflow-y: auto;
    flex: 1;
}
.insight-card {
    border-radius: 12px;
    padding: 14px;
    border: 1px solid;
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.insight-success { background: #f0fdf4; border-color: #bbf7d0; }
.insight-warning { background: #fffbeb; border-color: #fde68a; }
.insight-danger  { background: #fff1f2; border-color: #fecdd3; }

.insight-top       { display: flex; align-items: center; justify-content: space-between; }
.insight-top-left  { display: flex; align-items: center; gap: 5px; }
.insight-top-right { display: flex; align-items: center; gap: 5px; }
.insight-icon      { font-size: 0.95rem; }
.insight-category  { font-size: 0.65rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }

.insight-trend  { font-size: 0.72rem; font-weight: 800; line-height: 1; }
.trend-up       { color: #059669; }
.trend-down     { color: #dc2626; }
.trend-stable   { color: #94a3b8; }

.insight-badge { font-size: 0.62rem; padding: 2px 7px; border-radius: 20px; font-weight: 700; white-space: nowrap; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-danger  { background: #ffe4e6; color: #9f1239; }

.insight-metric {
    font-size: 1.55rem;
    font-weight: 800;
    line-height: 1;
    margin: 2px 0 0;
    letter-spacing: -0.02em;
}
.metric-success { color: #059669; }
.metric-warning { color: #d97706; }
.metric-danger  { color: #dc2626; }

.insight-title  { font-size: 0.8rem; font-weight: 700; color: #1e293b; line-height: 1.3; }
.insight-body   { font-size: 0.73rem; color: #475569; line-height: 1.5; flex: 1; }
.insight-action { font-size: 0.7rem; color: #6366f1; font-weight: 500; margin-top: 2px; }
.insight-action-label { font-weight: 700; }
.insight-drill {
    font-size: 0.7rem;
    color: #4f46e5;
    text-decoration: none;
    margin-top: 4px;
    font-weight: 600;
    border-top: 1px dashed #cbd5e1;
    padding-top: 6px;
}
.insight-drill:hover { text-decoration: underline; }

.insight-skeleton { background: #f8fafc; border-color: #e2e8f0; animation: pulse-sk 1.5s ease-in-out infinite; }
@keyframes pulse-sk { 0%,100% { opacity:1; } 50% { opacity:0.5; } }
.sk-line   { background: #e2e8f0; border-radius: 4px; margin-bottom: 8px; }
.sk-title  { height: 12px; width: 70%; }
.sk-metric { height: 22px; width: 40%; margin-bottom: 8px; }
.sk-body   { height: 10px; width: 100%; }
.sk-short  { width: 55%; }

.suggestions-wrap  { padding: 14px 16px 10px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.suggestions-label { font-size: 0.72rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; }
.suggestions-list  { display: flex; flex-wrap: wrap; gap: 6px; }
.suggestion-chip {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 5px 12px;
    font-size: 0.75rem;
    color: #475569;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
    white-space: nowrap;
}
.suggestion-chip:hover { background: #ede9fe; border-color: #c4b5fd; color: #5b21b6; }

.qa-history {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    scroll-behavior: smooth;
    min-height: 220px;
    max-height: 400px;
}
.qa-history::-webkit-scrollbar { width: 4px; }
.qa-history::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 2px; }

.qa-item { display: flex; flex-direction: column; gap: 8px; }

.qa-question {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    justify-content: flex-end;
}
.qa-q-icon { font-size: 1rem; flex-shrink: 0; }
.qa-q-text {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    padding: 9px 13px;
    border-radius: 14px 14px 4px 14px;
    font-size: 0.83rem;
    max-width: 85%;
    line-height: 1.4;
}

.qa-answer {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}
.qa-a-icon { font-size: 1rem; flex-shrink: 0; }
.qa-a-text {
    background: #f1f5f9;
    color: #1e293b;
    padding: 10px 13px;
    border-radius: 4px 14px 14px 14px;
    font-size: 0.82rem;
    line-height: 1.55;
    max-width: 90%;
}
.qa-a-text :deep(ul)           { padding-left: 16px; margin: 4px 0; }
.qa-a-text :deep(li)           { margin: 2px 0; }
.qa-a-text :deep(strong)       { font-weight: 700; }
.qa-a-text :deep(code)         { background: rgba(0,0,0,0.07); padding: 1px 4px; border-radius: 3px; font-size: 0.85em; }
.qa-a-text :deep(.answer-num)  {
    display: inline-block;
    background: #ede9fe;
    color: #5b21b6;
    font-weight: 700;
    padding: 0 4px;
    border-radius: 4px;
    font-size: 0.88em;
}

.qa-error {
    color: #dc2626;
    font-size: 0.8rem;
    padding: 8px 12px;
    background: #fee2e2;
    border-radius: 8px;
}

.qa-follow-ups {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    padding-left: 28px;
    margin-top: -4px;
}
.qa-followup-chip {
    background: #ede9fe;
    border: 1px solid #c4b5fd;
    border-radius: 20px;
    padding: 4px 10px;
    font-size: 0.71rem;
    color: #5b21b6;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
    white-space: nowrap;
}
.qa-followup-chip:hover { background: #ddd6fe; border-color: #a78bfa; }
.qa-typing {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 12px 16px;
    background: #f1f5f9;
    border-radius: 4px 14px 14px 14px;
}
.qa-typing span {
    width: 7px; height: 7px;
    background: #94a3b8; border-radius: 50%;
    animation: bounce 1.2s infinite ease-in-out;
}
.qa-typing span:nth-child(2) { animation-delay: 0.2s; }
.qa-typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes bounce { 0%,80%,100% { transform:translateY(0); } 40% { transform:translateY(-6px); } }

.qa-input-wrap {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    padding: 12px 14px 8px;
    border-top: 1px solid var(--border);
    flex-shrink: 0;
}
.qa-input {
    flex: 1;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    padding: 9px 12px;
    font-size: 0.84rem;
    font-family: inherit;
    resize: none;
    outline: none;
    transition: border-color 0.15s;
    max-height: 120px;
    overflow-y: auto;
    color: #1e293b;
}
.qa-input:focus { border-color: #6366f1; }
.qa-input::placeholder { color: #94a3b8; }
.qa-send {
    width: 38px; height: 38px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none; border-radius: 10px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: #fff; flex-shrink: 0;
    transition: opacity 0.15s;
}
.qa-send:disabled { opacity: 0.45; cursor: not-allowed; }
.qa-hint { text-align: center; font-size: 0.68rem; color: #cbd5e1; padding-bottom: 8px; flex-shrink: 0; }

/* Charts row */
.charts-row {
    margin-top: 24px;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}
@media (max-width: 1100px) {
    .charts-row { grid-template-columns: 1fr; }
}
.chart-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 16px;
    box-shadow: var(--shadow-sm);
}
.chart-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.chart-title { font-weight: 700; font-size: 0.92rem; color: var(--text-primary); }
.explain-btn {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    border: none;
    padding: 5px 12px;
    border-radius: 18px;
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
}
.explain-btn:hover { opacity: 0.9; }
.explain-body {
    font-size: 0.88rem;
    color: #1e293b;
    line-height: 1.6;
    white-space: pre-wrap;
}
</style>
