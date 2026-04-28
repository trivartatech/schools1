<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';

// Read AI comments saved by Index.vue before opening this window
const aiComments = ref({});
onMounted(() => {
    try {
        const stored = localStorage.getItem('rc_ai_comments');
        if (stored) aiComments.value = JSON.parse(stored);
    } catch {}
});

const props = defineProps({
    scheduleData: Object,
    students: Array,
    sectionData: Object,
    schoolInfo: Object,
    academicYear: String,
    useWeightage: Boolean,
    reportType: { type: String, default: 'exam' },
    termName:   { type: String, default: null },
    gradeScale: Array,
});

const isWeighted = computed(() => props.useWeightage);

const modePillText = computed(() => {
    if (props.reportType === 'term') {
        const t = props.termName || 'Term';
        return props.useWeightage ? `⚖ Term — ${t} (Weighted)` : `📋 Term — ${t} (Tabulation)`;
    }
    if (props.reportType === 'cumulative') {
        return props.useWeightage ? '⚖ Cumulative — Annual' : '📋 Cumulative — Annual (Tabulation)';
    }
    return props.useWeightage ? '⚖ Single Exam (Weighted Contribution)' : 'Single Exam';
});

// Unique exam types (columns) ordered as they appear in contributions
const examTypeCols = computed(() => {
    const first = props.students[0]?.report_calculated;
    if (!first || first.mode !== 'weighted') return [];
    return first.exam_types ?? [];
});

// Group exam types by term for header spanning
const termGroups = computed(() => {
    const groups = {};
    for (const et of examTypeCols.value) {
        const key = et.term_id ?? 0;
        if (!groups[key]) groups[key] = { term_name: et.term_name, cols: [] };
        groups[key].cols.push(et);
    }
    return Object.values(groups);
});

function gradeColor(grade) {
    const map = { 'A1':'#059669','A2':'#10b981','B1':'#1a56db','B2':'#3b82f6','C1':'#7c3aed','C2':'#8b5cf6','D':'#d97706','E':'#dc2626' };
    return map[grade] || '#334155';
}
function getResult(rc) {
    if (!rc) return 'N/A';
    const p = rc.overall_percentage;
    if (p >= 91) return 'Outstanding'; if (p >= 81) return 'Excellent';
    if (p >= 71) return 'Very Good';  if (p >= 61) return 'Good';
    if (p >= 51) return 'Average';    if (p >= 33) return 'Pass';
    return 'FAIL';
}
// Get contribution for a subject under a specific exam type code
function getC(subject, code) {
    return subject.contributions?.find(c => c.code === code) ?? null;
}
// Calculate total obtained / total max for single mode
function singleTotal(rc) {
    return `${rc.total_obtained} / ${rc.total_max}`;
}
</script>

<template>
    <Head title="Report Cards — Print" />
    <div class="print-shell">

        <!-- No-print Controls -->
        <div class="no-print controls-bar">
            <button onclick="window.print()" class="ctrl-btn ctrl-btn--print">🖨️ Print All Report Cards</button>
            <button onclick="window.close()" class="ctrl-btn ctrl-btn--close">✕ Close</button>
            <span class="mode-pill" :style="isWeighted ? 'background:#6d28d9' : 'background:#1169cd'">
                {{ modePillText }}
            </span>
        </div>

        <!-- ══════════════ ONE CARD PER STUDENT ══════════════ -->
        <div v-for="(student, idx) in students" :key="student.id"
             class="report-card" :class="{ 'page-break': idx > 0 }">

            <!-- ─── SCHOOL HEADER ─── -->
            <div class="rc-header">
                <div class="rc-header__logo">
                    <img v-if="schoolInfo?.logo_path" :src="'/storage/'+schoolInfo.logo_path" />
                    <div v-else class="logo-ph">{{ schoolInfo?.name?.charAt(0) }}</div>
                </div>
                <div class="rc-header__center">
                    <div class="rc-header__society">{{ schoolInfo?.trust_name || schoolInfo?.name }}</div>
                    <div class="rc-header__school">{{ schoolInfo?.name }}</div>
                    <div class="rc-header__addr">
                        {{ schoolInfo?.address }}<template v-if="schoolInfo?.phone"> | Ph: {{ schoolInfo.phone }}</template>
                    </div>
                </div>
                <div class="rc-header__logo">
                    <img v-if="schoolInfo?.logo_path" :src="'/storage/'+schoolInfo.logo_path" />
                    <div v-else class="logo-ph">{{ schoolInfo?.name?.charAt(0) }}</div>
                </div>
            </div>

            <!-- ─── TITLE BAR ─── -->
            <div class="rc-title-bar">
                REPORT CARD – {{ academicYear }}
            </div>

            <!-- ─── STUDENT INFO + PHOTO ─── -->
            <div class="rc-student">
                <div class="rc-student__left">
                    <div class="rc-info-row">
                        <span class="rc-info-label">STUDENT NAME</span>
                        <strong class="rc-info-value">: {{ student.first_name }} {{ student.last_name }}</strong>
                    </div>
                    <div class="rc-info-row">
                        <span class="rc-info-label">FATHER NAME</span>
                        <span class="rc-info-value">: {{ student.student_parent?.father_name || '—' }}</span>
                    </div>
                    <div class="rc-info-row">
                        <span class="rc-info-label">MOTHER NAME</span>
                        <span class="rc-info-value">: {{ student.student_parent?.mother_name || '—' }}</span>
                    </div>
                </div>
                <div class="rc-student__right">
                    <div class="rc-info-row">
                        <span class="rc-info-label">CLASS</span>
                        <strong class="rc-info-value">: {{ scheduleData?.course_class?.name }}</strong>
                    </div>
                    <div class="rc-info-row">
                        <span class="rc-info-label">ADMISSION NO.</span>
                        <span class="rc-info-value">: {{ student.admission_no || '—' }}</span>
                    </div>
                    <div class="rc-info-row">
                        <span class="rc-info-label">SECTION</span>
                        <span class="rc-info-value">: {{ sectionData?.name }}</span>
                    </div>
                    <div class="rc-info-row" v-if="student.report_calculated?.rank">
                        <span class="rc-info-label">CLASS RANK</span>
                        <strong class="rc-info-value" style="color:#1169cd;">
                            : {{ student.report_calculated.rank }} / {{ student.report_calculated.total_count }}
                        </strong>
                    </div>
                </div>
                <div class="rc-student__photo">
                    <img v-if="student.photo_path" :src="'/storage/'+student.photo_path" />
                    <div v-else class="photo-ph">{{ student.first_name?.charAt(0) }}</div>
                </div>
            </div>

            <!-- ─── WEIGHTED MARKS TABLE (term ON / cumulative ON / single-exam ON) ─── -->
            <template v-if="isWeighted && student.report_calculated?.mode === 'weighted'">
                <table class="rc-table">
                    <thead>
                        <!-- ROW 1: Term group spanning -->
                        <tr>
                            <th rowspan="2" class="th-subject">SUBJECT</th>
                            <template v-for="grp in termGroups" :key="grp.term_name">
                                <th :colspan="grp.cols.length * 2" class="th-term">{{ grp.term_name }}</th>
                            </template>
                            <th rowspan="2" class="th-summary">O</th>
                            <th rowspan="2" class="th-summary">%</th>
                            <th rowspan="2" class="th-summary">G</th>
                        </tr>
                        <!-- ROW 2: Per exam type -->
                        <tr>
                            <template v-for="et in examTypeCols" :key="et.code">
                                <th class="th-exam" colspan="2">{{ et.name }}<br/><span class="th-wt">({{ et.weightage }}%)</span></th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(sub, si) in student.report_calculated.subjects" :key="si"
                            :class="si % 2 === 0 ? 'tr-even' : 'tr-odd'">
                            <td class="td-subject">{{ sub.subject_name }}</td>
                            <template v-for="et in examTypeCols" :key="et.code">
                                <td class="td-score">{{ getC(sub, et.code)?.weighted ?? '—' }}</td>
                                <td class="td-grade">
                                    <span v-if="getC(sub, et.code)?.grade"
                                        :style="`color:${gradeColor(getC(sub, et.code).grade)};font-weight:700;`">
                                        {{ getC(sub, et.code).grade }}
                                    </span>
                                    <span v-else class="dim">—</span>
                                </td>
                            </template>
                            <td class="td-total">{{ sub.weighted_total ?? '—' }}</td>
                            <td class="td-total">{{ sub.percentage }}%</td>
                            <td class="td-grade-final">
                                <span v-if="sub.grade" :style="`color:${gradeColor(sub.grade)};`">{{ sub.grade }}</span>
                                <span v-else class="dim">—</span>
                            </td>
                        </tr>
                        <!-- Total row -->
                        <tr class="tr-total">
                            <td class="td-subject"><strong>TOTAL</strong></td>
                            <td :colspan="examTypeCols.length * 2"></td>
                            <td class="td-total"><strong>{{ student.report_calculated.total_weighted }}</strong></td>
                            <td class="td-total"><strong>{{ student.report_calculated.overall_percentage }}%</strong></td>
                            <td class="td-grade-final" :style="`color:${gradeColor(student.report_calculated.subjects[0]?.grade)};`">
                                <strong>{{ student.report_calculated.subjects[0]?.grade ?? '—' }}</strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>

            <!-- ─── TABULATION TABLE (term OFF / cumulative OFF) ─── -->
            <template v-else-if="!isWeighted && student.report_calculated?.mode === 'weighted'">
                <table class="rc-table">
                    <thead>
                        <tr>
                            <th rowspan="2" class="th-subject">SUBJECT</th>
                            <template v-for="grp in termGroups" :key="grp.term_name + '_tab'">
                                <th :colspan="grp.cols.length * 2" class="th-term">{{ grp.term_name }}</th>
                            </template>
                        </tr>
                        <tr>
                            <template v-for="et in examTypeCols" :key="et.code + '_tab'">
                                <th class="th-exam" colspan="2">{{ et.name }}</th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(sub, si) in student.report_calculated.subjects" :key="si"
                            :class="si % 2 === 0 ? 'tr-even' : 'tr-odd'">
                            <td class="td-subject">{{ sub.subject_name }}</td>
                            <template v-for="et in examTypeCols" :key="et.code + '_tabcell'">
                                <td class="td-score" colspan="2">
                                    <template v-if="getC(sub, et.code)">
                                        <span v-if="getC(sub, et.code).obtained === 'ABS'" style="color:#dc2626;font-weight:700;">ABS</span>
                                        <span v-else>{{ getC(sub, et.code).obtained }}/{{ getC(sub, et.code).max }}</span>
                                    </template>
                                    <span v-else class="dim">—</span>
                                </td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </template>

            <!-- ─── SINGLE EXAM MARKS TABLE ─── -->
            <template v-else>
                <table class="rc-table">
                    <thead>
                        <tr>
                            <th class="th-subject">SUBJECT</th>
                            <th class="th-exam" style="text-align:center;" colspan="2">{{ scheduleData?.exam_type?.name }}</th>
                            <th class="th-summary">MAX</th>
                            <th class="th-summary">OBT.</th>
                            <th class="th-summary">%</th>
                            <th class="th-summary">G</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(sub, si) in student.report_calculated?.subjects" :key="si"
                            :class="si % 2 === 0 ? 'tr-even' : 'tr-odd'">
                            <td class="td-subject">{{ sub.subject_name }}</td>
                            <td class="td-score" colspan="2">
                                <span v-if="sub.obtained === 'ABS'" style="color:#dc2626;font-weight:700;">ABSENT</span>
                                <span v-else>{{ sub.obtained }}</span>
                            </td>
                            <td class="td-total">{{ sub.max }}</td>
                            <td class="td-total">{{ sub.obtained === 'ABS' ? 'ABS' : sub.obtained }}</td>
                            <td class="td-total">{{ sub.percentage }}%</td>
                            <td class="td-grade-final">
                                <span v-if="sub.grade" :style="`color:${gradeColor(sub.grade)};`">{{ sub.grade }}</span>
                                <span v-else class="dim">—</span>
                            </td>
                        </tr>
                        <tr class="tr-total">
                            <td class="td-subject" colspan="3"><strong>TOTAL</strong></td>
                            <td class="td-total"><strong>{{ student.report_calculated?.total_max }}</strong></td>
                            <td class="td-total"><strong>{{ student.report_calculated?.total_obtained }}</strong></td>
                            <td class="td-total"><strong>{{ student.report_calculated?.overall_percentage }}%</strong></td>
                            <td class="td-grade-final">
                                <strong :style="`color:#1169cd;`">
                                    {{ student.report_calculated?.subjects[0]?.grade ?? '—' }}
                                </strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>

            <!-- ─── BOTTOM 3-COLUMN SECTION ─── -->
            <div class="rc-bottom">

                <!-- Attendance -->
                <div class="rc-box rc-box--attend">
                    <div class="rc-box__title">ATTENDANCE</div>
                    <table class="rc-mini-table">
                        <tr><td>Total Working Days</td><td class="mini-val">—</td></tr>
                        <tr><td>Days Attended</td><td class="mini-val">—</td></tr>
                    </table>
                </div>

                <!-- Co-Scholastic -->
                <div class="rc-box rc-box--coscholastic">
                    <div class="rc-box__title">CO-SCHOLASTIC AREAS</div>
                    <template v-if="student.report_calculated?.co_scholastic?.length">
                        <table class="rc-mini-table">
                            <thead>
                                <tr>
                                    <th style="text-align:left;">Subject</th>
                                    <th v-for="exam in student.report_calculated.co_scholastic[0].exams"
                                        :key="exam.code" style="text-align:center;min-width:44px;">
                                        {{ exam.name }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="cs in student.report_calculated.co_scholastic" :key="cs.subject_name"
                                    style="background:#fffde7;">
                                    <td style="font-weight:600;color:#1a2472;">{{ cs.subject_name }}</td>
                                    <td v-for="exam in cs.exams" :key="exam.code"
                                        class="mini-val"
                                        :style="`color:${gradeColor(exam.grade)};font-weight:700;`">
                                        {{ exam.grade }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </template>
                    <div v-else class="rc-mini-empty">
                        No co-scholastic subjects assigned to this exam.
                    </div>
                </div>

                <!-- Grade Scale -->
                <div class="rc-box rc-box--grade">
                    <div class="rc-box__title">SCHOLASTIC GRADE DETAILS</div>
                    <table class="rc-mini-table" v-if="gradeScale?.length">
                        <thead>
                            <tr><th>Marks Range</th><th>Grade</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="g in gradeScale" :key="g.id">
                                <td>{{ g.min_percentage }}–{{ g.max_percentage }}</td>
                                <td class="mini-val" :style="`color:${gradeColor(g.name)};font-weight:700;`">{{ g.name }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="rc-mini-empty">No grade scale configured.</div>
                </div>

            </div>

            <!-- ─── RESULT BAR ─── -->
            <div class="rc-result-bar">
                <span class="rc-result-label">Result / Promoted To: </span>
                <span class="rc-result-value" :style="`color:${getResult(student.report_calculated) === 'FAIL' ? '#dc2626' : '#059669'};`">
                    {{ getResult(student.report_calculated) }}
                </span>
            </div>

            <!-- ─── AI TEACHER REMARK ─── -->
            <div v-if="aiComments[student.id]" class="rc-ai-remark">
                <span class="rc-ai-remark-label">Teacher's Remark:</span>
                <span class="rc-ai-remark-text">{{ aiComments[student.id] }}</span>
            </div>

            <!-- ─── SIGNATURES ─── -->
            <div class="rc-signatures">
                <div class="sig-block">
                    <div class="sig-line"></div>
                    <div class="sig-name">CLASS TEACHER</div>
                    <div class="sig-sub">Signature</div>
                </div>
                <div class="sig-block">
                    <div class="sig-line"></div>
                    <div class="sig-name">PRINCIPAL / HEADMASTER</div>
                    <div class="sig-sub">Signature</div>
                </div>
            </div>

        </div><!-- end report-card -->
    </div>
</template>

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Arial', 'Helvetica', sans-serif; background: #e8e8e8; color: #1a1a2e; }

/* ════ CONTROLS ════ */
.controls-bar { background: linear-gradient(90deg,#1169cd,#0d50a3); padding: 12px 24px; display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:100; }
.ctrl-btn { padding:8px 18px;border-radius:6px;font-weight:700;cursor:pointer;font-size:0.85rem;border:none; }
.ctrl-btn--print { background:#fff;color:#1169cd; }
.ctrl-btn--close  { background:rgba(255,255,255,0.15);color:#fff;border:1px solid rgba(255,255,255,0.3); }
.mode-pill { font-size:0.72rem;padding:4px 12px;border-radius:20px;color:#fff;font-weight:700;letter-spacing:0.03em; }

/* ════ PAGE ════ */
.print-shell { max-width: 880px; margin: 20px auto; }
.report-card { background:#fffef5; border:2px solid #e2d890; border-radius:4px; margin-bottom:28px; overflow:hidden; }
.page-break { page-break-before: always; }

/* ════ HEADER ════ */
.rc-header { background: linear-gradient(135deg, #1a2472 0%, #283593 50%, #1a2472 100%); padding:16px 20px; display:flex;align-items:center;gap:12px; border-bottom:4px solid #f5a623; }
.rc-header__logo img, .logo-ph { width:70px;height:70px;border-radius:50%;object-fit:cover;border:3px solid #f5a623;background:#fff;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:900;color:#1a2472; }
.rc-header__center { flex:1;text-align:center; }
.rc-header__society { font-size:0.72rem;color:#f5d97a;font-weight:600;letter-spacing:0.1em;text-transform:uppercase; }
.rc-header__school { font-size:1.2rem;font-weight:900;color:#fff;letter-spacing:0.04em;text-transform:uppercase;line-height:1.2;margin:3px 0; }
.rc-header__addr { font-size:0.68rem;color:rgba(255,255,255,0.75); }

/* ════ TITLE BAR ════ */
.rc-title-bar { background:#e8e0a0;border-bottom:2px solid #d4bc50;text-align:center;padding:8px;font-size:0.9rem;font-weight:800;letter-spacing:0.12em;color:#1a2472;text-transform:uppercase; }

/* ════ STUDENT INFO ════ */
.rc-student { display:flex;gap:0;border-bottom:2px solid #e2d890;padding:10px 16px; }
.rc-student__left, .rc-student__right { flex:1; }
.rc-student__photo img, .photo-ph { width:75px;height:90px;border-radius:4px;object-fit:cover;border:2px solid #e2d890;background:#eff6ff;display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:800;color:#1a2472;flex-shrink:0; }
.rc-info-row { display:flex;align-items:baseline;gap:6px;padding:2px 0;font-size:0.78rem; }
.rc-info-label { min-width:112px;color:#475569;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.04em; }
.rc-info-value { color:#1a1a2e;font-size:0.82rem; }

/* ════ MARKS TABLE ════ */
.rc-table { width:100%;border-collapse:collapse;margin:0; }

.rc-table th { background:#283593;color:#fff;padding:6px 5px;font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;border:1px solid #1a2472; text-align:center; }
.th-subject { text-align:left!important;padding-left:10px!important;min-width:90px;background:#1a2472!important; }
.th-term { background:#1a56db!important;font-size:0.7rem!important; }
.th-exam { background:#283593!important;font-size:0.66rem!important;line-height:1.3; }
.th-wt { font-weight:400;opacity:0.8;font-size:0.6rem; }
.th-summary { background:#1a2472!important;min-width:38px; }

.rc-table td { padding:5px 5px;font-size:0.78rem;border:1px solid #e2d890;vertical-align:middle; }
.td-subject { font-weight:600;color:#1a2472;padding-left:10px;background:inherit; }
.td-score { text-align:center;color:#1a1a2e;font-weight:600; }
.td-grade { text-align:center;font-size:0.72rem; }
.td-total { text-align:center;font-weight:700;color:#1a2472; }
.td-grade-final { text-align:center;font-weight:800; }

.tr-even { background:#fffde7; }
.tr-odd  { background:#fff; }
.tr-total { background:#f0e9c5!important; border-top:2px solid #d4bc50; }
.tr-total td { border-top:2px solid #c0a830!important; }

.dim { color:#94a3b8;font-size:0.65rem; }

/* ════ BOTTOM 3-COL ════ */
.rc-bottom { display:grid;grid-template-columns:1fr 1.4fr 1.1fr;gap:0;border-top:2px solid #e2d890; }
.rc-box { border-right:1px solid #e2d890;padding:8px 10px; }
.rc-box:last-child { border-right:none; }
.rc-box__title { font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:#fff;background:#283593;padding:4px 8px;margin:-8px -10px 8px;border-bottom:2px solid #f5a623; }
.rc-mini-table { width:100%;border-collapse:collapse;font-size:0.72rem; }
.rc-mini-table th { background:#e8e0a0;color:#1a2472;font-weight:700;padding:4px 6px;border:1px solid #e2d890;font-size:0.65rem;text-align:center; }
.rc-mini-table td { padding:3px 6px;border:1px solid #e8e4c0;color:#374151; }
.mini-val { text-align:center;font-weight:700; }
.rc-mini-empty { font-size:0.7rem;color:#94a3b8;font-style:italic;padding:6px 0; }

/* ════ RESULT BAR ════ */
.rc-result-bar { background:#eff6ff;border-top:2px solid #e2d890;border-bottom:1px solid #e2d890;padding:8px 16px;font-size:0.82rem;display:flex;align-items:center;gap:8px; }
.rc-result-label { font-weight:700;color:#1a2472;text-transform:uppercase;letter-spacing:0.04em; }
.rc-result-value { font-size:1rem;font-weight:900;text-transform:uppercase;letter-spacing:0.06em; }

/* ════ AI REMARK ════ */
.rc-ai-remark {
    margin: 0 32px 10px;
    padding: 8px 14px;
    background: #faf5ff;
    border-left: 3px solid #8b5cf6;
    border-radius: 0 6px 6px 0;
    font-size: 0.78rem;
    color: #3b0764;
}
.rc-ai-remark-label { font-weight: 700; margin-right: 6px; color: #6d28d9; }
.rc-ai-remark-text  { font-style: italic; }

/* ════ SIGNATURES ════ */
.rc-signatures { display:flex;justify-content:space-between;padding:16px 40px 12px;border-top:1px solid #e2d890;gap:24px; }
.sig-block { text-align:center;flex:1; }
.sig-line { border-bottom:1.5px solid #334155;margin-bottom:5px;height:32px; }
.sig-name { font-size:0.72rem;font-weight:800;color:#1a2472;text-transform:uppercase;letter-spacing:0.04em; }
.sig-sub { font-size:0.62rem;color:#64748b; }

/* ════ PRINT ════ */
@media print {
    .no-print { display:none!important; }
    body { background:#fff; }
    .print-shell { max-width:100%;margin:0;padding:0; }
    .report-card { border:1px solid #ccc;margin:0;page-break-after:always; }
    .page-break { page-break-before:always; }
}
</style>
