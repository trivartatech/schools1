<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import axios from 'axios';
import Table from '@/Components/ui/Table.vue';

// ── AI Comments ──
const aiComments     = ref({}); // { [student_id]: comment string }
const aiLoading      = ref(false);
const aiError        = ref('');
const showComments   = ref(false);

async function generateAiComments() {
    if (!students.value.length) return;
    aiLoading.value = true;
    aiError.value   = '';
    try {
        const { data } = await axios.post('/school/ai/report-card-comments', {
            students:   students.value,
            exam_name:  scheduleInfo.value?.exam_type?.name ?? 'Exam',
            class_name: scheduleInfo.value?.course_class?.name ?? 'Class',
        });
        const map = {};
        (data.comments ?? []).forEach(c => { map[c.id] = c.comment; });
        aiComments.value = map;
        showComments.value = true;
    } catch (e) {
        aiError.value = e.response?.data?.error ?? 'Failed to generate AI comments. Try again.';
    } finally {
        aiLoading.value = false;
    }
}

function saveCommentsToStorage() {
    localStorage.setItem('rc_ai_comments', JSON.stringify(aiComments.value));
}

const props = defineProps({ schedules: Array });

const selectedClassId   = ref('');
const selectedSectionId = ref('');
const selectedScheduleId = ref('');
const useWeightage       = ref(false);
const loading    = ref(false);
const students   = ref([]);
const scheduleInfo = ref(null);
const selectedIds = ref([]);
const errorMsg = ref('');

// Computed: All unique classes from schedules
const availableClasses = computed(() => {
    const map = new Map();
    props.schedules.forEach(s => {
        if (s.course_class) {
            map.set(s.course_class.id, s.course_class);
        }
    });
    return Array.from(map.values());
});

// Computed: Sections available for the selected Class
const availableSections = computed(() => {
    if (!selectedClassId.value) return [];
    // Get all sections from all schedules belonging to this class
    const map = new Map();
    props.schedules
        .filter(s => s.course_class_id == selectedClassId.value)
        .forEach(s => {
            s.sections?.forEach(sec => {
                map.set(sec.id, sec);
            });
        });
    return Array.from(map.values());
});

// Computed: Exams available for the selected Class + Section
const availableExams = computed(() => {
    if (!selectedClassId.value || !selectedSectionId.value) return [];
    return props.schedules.filter(s => 
        s.course_class_id == selectedClassId.value && 
        s.sections.some(sec => sec.id == selectedSectionId.value)
    );
});

function onClassChange() {
    selectedSectionId.value = '';
    selectedScheduleId.value = '';
    resetData();
}

function onSectionChange() {
    selectedScheduleId.value = '';
    resetData();
}

function onScheduleChange() {
    resetData();
}

function resetData() {
    students.value = [];
    selectedIds.value = [];
    scheduleInfo.value = null;
    errorMsg.value = '';
}

// When toggle changes, reload if already loaded
function onToggle() {
    if (students.value.length) loadPreview();
}

async function loadPreview() {
    if (!selectedScheduleId.value || !selectedSectionId.value) return;
    loading.value = true;
    errorMsg.value = '';
    try {
        const res = await axios.post('/school/report-cards/generate', {
            exam_schedule_id: selectedScheduleId.value,
            section_id: selectedSectionId.value,
            use_weightage: useWeightage.value,
        });
        students.value   = res.data.students;
        scheduleInfo.value = res.data.schedule;
        selectedIds.value = students.value.map(s => s.id);
    } catch(e) {
        errorMsg.value = e.response?.data?.message || 'Failed to load student data.';
    } finally {
        loading.value = false;
    }
}

// Column definitions depend on mode
const isWeighted = computed(() => students.value[0]?.report_calculated?.mode === 'weighted');

const scholasticSubjects = computed(() => {
    return students.value[0]?.report_calculated?.subjects?.map(s => s.subject_name) ?? [];
});

const coScholasticSubjects = computed(() => {
    return students.value[0]?.report_calculated?.co_scholastic?.map(s => s.subject_name) ?? [];
});

const examTypeHeaders = computed(() => {
    if (!isWeighted.value) return [];
    return students.value[0]?.report_calculated?.exam_types ?? [];
});

// Helper to find co-scholastic grade for a student and subject
function getCoGrade(student, subName, examCode = null) {
    const cs = student.report_calculated?.co_scholastic?.find(c => c.subject_name === subName);
    if (!cs) return '—';
    if (!examCode) {
        // Return first exam's grade if no code specified (single exam mode)
        return cs.exams[0]?.grade ?? '—';
    }
    return cs.exams.find(e => e.code === examCode)?.grade ?? '—';
}

function toggleAll(ev) {
    selectedIds.value = ev.target.checked ? students.value.map(s => s.id) : [];
}
function toggleOne(id) {
    const idx = selectedIds.value.indexOf(id);
    if (idx >= 0) selectedIds.value.splice(idx, 1);
    else selectedIds.value.push(id);
}

function openPrint() {
    if (!selectedIds.value.length) return;
    // Save AI comments to localStorage so Print.vue can read them
    saveCommentsToStorage();
    const url = `/school/report-cards/print?exam_schedule_id=${selectedScheduleId.value}&section_id=${selectedSectionId.value}&student_ids=${selectedIds.value.join(',')}&use_weightage=${useWeightage.value ? 1 : 0}`;
    window.open(url, '_blank');
}

function gradeColor(grade) {
    const map = { 'A1':'#059669','A2':'#10b981','B1':'#0284c7','B2':'#38bdf8','C1':'#7c3aed','C2':'#a78bfa','D':'#d97706','E':'#dc2626' };
    return map[grade] || '#64748b';
}
</script>

<template>
    <Head title="Report Cards" />
    <SchoolLayout title="Report Cards">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">📋 Report Card Generator</h1>
                <p class="page-header-sub">Generate CBSE academic report cards — single exam or weighted cumulative.</p>
            </div>
            <div v-if="students.length" style="display:flex;gap:10px;align-items:center;">
                <!-- AI Comments Button -->
                <button @click="generateAiComments" class="btn-ai-comments" :disabled="aiLoading">
                    <span v-if="aiLoading" class="ai-spin">⏳</span>
                    <span v-else>✨</span>
                    {{ aiLoading ? 'Generating…' : (showComments ? 'Regenerate AI Comments' : 'Generate AI Comments') }}
                </button>
                <!-- Print Button -->
                <Button @click="openPrint" :disabled="!selectedIds.length">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Print {{ selectedIds.length }} Report Card{{ selectedIds.length !== 1 ? 's' : '' }}
                </Button>
            </div>
        </div>

        <!-- Filters + Toggle Bar -->
        <div class="card mb-6">
            <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">
                <!-- Row 1: Dropdowns + Load -->
                <div class="form-row" style="grid-template-columns:1fr 1fr 1fr auto;align-items:flex-end;gap:12px;">
                    <div class="form-field">
                        <label>Class *</label>
                        <select v-model="selectedClassId" @change="onClassChange">
                            <option value="">-- Select Class --</option>
                            <option v-for="cls in availableClasses" :key="cls.id" :value="cls.id">
                                {{ cls.name }}
                            </option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Section *</label>
                        <select v-model="selectedSectionId" @change="onSectionChange" :disabled="!selectedClassId">
                            <option value="">-- Select Section --</option>
                            <option v-for="sec in availableSections" :key="sec.id" :value="sec.id">
                                {{ sec.name }}
                            </option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Exam *</label>
                        <select v-model="selectedScheduleId" @change="onScheduleChange" :disabled="!selectedSectionId">
                            <option value="">-- Select Exam --</option>
                            <option v-for="sc in availableExams" :key="sc.id" :value="sc.id">
                                {{ sc.exam_type?.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Button @click="loadPreview" :disabled="!selectedScheduleId || !selectedSectionId || loading">
                            <svg v-if="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                            </svg>
                            {{ loading ? 'Loading…' : 'Load Students' }}
                        </Button>
                    </div>
                </div>

                <!-- Row 2: Weightage Toggle -->
                <div class="weightage-toggle-row">
                    <label class="toggle-wrap" :class="{ 'toggle-wrap--active': useWeightage }">
                        <button type="button" class="toggle-btn" :class="{ 'toggle-btn--on': useWeightage }"
                            @click="useWeightage = !useWeightage; onToggle()" role="switch" :aria-checked="useWeightage">
                            <span class="toggle-knob" :class="{ 'toggle-knob--on': useWeightage }"></span>
                        </button>
                        <div class="toggle-label-area">
                            <span class="toggle-label-title">Apply CBSE Weightage</span>
                            <span class="toggle-label-desc" v-if="!useWeightage">
                                Showing raw marks from selected exam only
                            </span>
                            <span class="toggle-label-desc toggle-label-desc--active" v-else>
                                Weighted cumulative: PT1 (10%) + HY (30%) + PT2 (10%) + Annual (50%) = 100%
                            </span>
                        </div>
                        <span class="toggle-badge" v-if="useWeightage">CBSE ✓</span>
                    </label>
                </div>
            </div>
        </div>

        <div v-if="errorMsg"  class="rc-error-banner">⚠️ {{ errorMsg }}</div>
        <div v-if="aiError"   class="rc-error-banner" style="background:#fdf4ff;border-color:#e9d5ff;color:#6d28d9;">✨ {{ aiError }}</div>

        <!-- AI Comments Banner -->
        <div v-if="showComments && !aiLoading" class="ai-comments-banner">
            <span>✨ AI comments generated for {{ Object.keys(aiComments).length }} students — visible in the table below. Click any comment to edit it before printing.</span>
        </div>

        <!-- Empty State -->
        <div v-if="!students.length && !loading" class="card card-body rc-empty-state">
            <svg class="w-14 h-14 mx-auto mb-3" style="color:#e2e8f0;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Select a class, section, and exam, then click <strong>Load Students</strong>.
        </div>

        <!-- ─── SINGLE EXAM TABLE ─── -->
        <div v-else-if="students.length && !isWeighted" class="card" style="overflow:hidden;">
            <div class="card-header">
                <span class="card-title">
                    {{ scheduleInfo?.course_class?.name }} — {{ scheduleInfo?.exam_type?.name }}
                    <span class="badge badge-blue ml-2">{{ students.length }} Students</span>
                    <span class="badge badge-gray ml-1">Raw Marks</span>
                </span>
                <label class="select-all-label">
                    <input type="checkbox" :checked="selectedIds.length === students.length" @change="toggleAll" />
                    Select All
                </label>
            </div>
            <div style="overflow-x:auto;">
                <Table>
                    <thead>
                        <tr>
                            <th style="width:42px;"></th>
                            <th>Roll No</th>
                            <th>Student Name</th>
                            <th v-for="sn in scholasticSubjects" :key="sn" style="text-align:center;min-width:130px;">
                                {{ sn }}
                            </th>
                            <th v-for="csn in coScholasticSubjects" :key="csn" style="text-align:center;min-width:100px;background:#fffde7;">
                                {{ csn }} (Co)
                            </th>
                            <th style="text-align:center;">Total</th>
                            <th style="text-align:center;">%</th>
                            <th style="text-align:center;">Rank</th>
                            <th v-if="showComments" style="min-width:220px;background:#faf5ff;color:#6d28d9;">✨ AI Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="st in students" :key="st.id" @click="toggleOne(st.id)" style="cursor:pointer;"
                            :style="selectedIds.includes(st.id) ? 'background:#eff6ff;' : ''">
                            <td><input type="checkbox" :checked="selectedIds.includes(st.id)" @click.stop="toggleOne(st.id)" /></td>
                            <td>{{ st.roll_no || '-' }}</td>
                            <td style="font-weight:600;">{{ st.first_name }} {{ st.last_name }}</td>
                            <td v-for="sub in st.report_calculated?.subjects" :key="sub.subject_name" style="text-align:center;">
                                <span v-if="sub.obtained === 'ABS'" class="badge badge-red">ABS</span>
                                <span v-else>
                                    {{ sub.obtained }}/{{ sub.max }}
                                    <span v-if="sub.grade" class="badge ml-1"
                                        :style="`background:${gradeColor(sub.grade)}20;color:${gradeColor(sub.grade)};`">
                                        {{ sub.grade }}
                                    </span>
                                </span>
                            </td>
                            <td v-for="csn in coScholasticSubjects" :key="csn" style="text-align:center;background:#fffdf0;">
                                <span class="badge"
                                    :style="`background:${gradeColor(getCoGrade(st, csn))}20;color:${gradeColor(getCoGrade(st, csn))};font-weight:700;`"
                                >
                                    {{ getCoGrade(st, csn) }}
                                </span>
                            </td>
                            <td style="text-align:center;font-weight:700;">
                                {{ st.report_calculated?.total_obtained }}/{{ st.report_calculated?.total_max }}
                            </td>
                            <td style="text-align:center;">
                                <span class="badge"
                                    :class="st.report_calculated?.overall_percentage >= 33 ? 'badge-green' : 'badge-red'">
                                    {{ st.report_calculated?.overall_percentage }}%
                                </span>
                            </td>
                            <td style="text-align:center;font-weight:700;color:#1169cd;">
                                {{ st.report_calculated?.rank ?? '—' }}
                            </td>
                            <td v-if="showComments" style="background:#faf5ff;" @click.stop>
                                <textarea class="ai-comment-input"
                                    v-model="aiComments[st.id]"
                                    placeholder="AI comment…"
                                    rows="2"
                                ></textarea>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <!-- ─── WEIGHTED CUMULATIVE TABLE ─── -->
        <div v-else-if="students.length && isWeighted" class="card" style="overflow:hidden;">
            <div class="card-header">
                <span class="card-title">
                    {{ scheduleInfo?.course_class?.name }} — Cumulative Weighted Report
                    <span class="badge badge-blue ml-2">{{ students.length }} Students</span>
                    <span class="badge ml-1" style="background:#eef0ff;color:#4c1d95;">⚖ Weightage Applied</span>
                </span>
                <label class="select-all-label">
                    <input type="checkbox" :checked="selectedIds.length === students.length" @change="toggleAll" />
                    Select All
                </label>
            </div>

            <!-- Weightage Legend -->
            <div class="weightage-legend">
                <span>Exam weightage:</span>
                <span v-for="et in examTypeHeaders" :key="et.code" class="weightage-pill">
                    {{ et.name }} <strong>{{ et.weightage }}%</strong>
                </span>
            </div>

            <div style="overflow-x:auto;">
                <Table>
                    <thead>
                        <tr>
                            <th style="width:42px;" rowspan="2"></th>
                            <th rowspan="2">Roll No</th>
                            <th rowspan="2">Student Name</th>
                            <th v-for="sn in scholasticSubjects" :key="sn" colspan="2" style="text-align:center;min-width:150px;background:#f0f6ff;">
                                {{ sn }}
                            </th>
                            <th v-for="csn in coScholasticSubjects" :key="csn" style="text-align:center;min-width:110px;background:#fffde7;" rowspan="2">
                                {{ csn }} (Grade)
                            </th>
                            <th rowspan="2" style="text-align:center;">Final %</th>
                            <th v-if="showComments" rowspan="2" style="min-width:220px;background:#faf5ff;color:#6d28d9;">✨ AI Comment</th>
                        </tr>
                        <tr>
                            <template v-for="sn in scholasticSubjects" :key="sn + '_sub'">
                                <th style="text-align:center;background:#f8fafb;font-size:0.65rem;color:#64748b;">Weighted</th>
                                <th style="text-align:center;background:#f8fafb;font-size:0.65rem;color:#64748b;">Grade</th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="st in students" :key="st.id" @click="toggleOne(st.id)" style="cursor:pointer;"
                            :style="selectedIds.includes(st.id) ? 'background:#eff6ff;' : ''">
                            <td><input type="checkbox" :checked="selectedIds.includes(st.id)" @click.stop="toggleOne(st.id)" /></td>
                            <td>{{ st.roll_no || '-' }}</td>
                            <td style="font-weight:600;">{{ st.first_name }} {{ st.last_name }}</td>
                            <template v-for="sub in st.report_calculated?.subjects" :key="sub.subject_name">
                                <td style="text-align:center;font-weight:600;">
                                    {{ sub.weighted_total ?? '—' }}
                                    <span class="wt-max">/{{ st.report_calculated?.total_weightage }}</span>
                                </td>
                                <td style="text-align:center;">
                                    <span v-if="sub.grade" class="badge"
                                        :style="`background:${gradeColor(sub.grade)}20;color:${gradeColor(sub.grade)};`">
                                        {{ sub.grade }}
                                    </span>
                                    <span v-else class="badge badge-gray">—</span>
                                </td>
                            </template>
                            <td v-for="csn in coScholasticSubjects" :key="'weighted_'+csn" style="text-align:center;background:#fdfce8;">
                                <span class="badge"
                                    :style="`background:${gradeColor(getCoGrade(st, csn))}20;color:${gradeColor(getCoGrade(st, csn))};font-weight:700;`"
                                >
                                    {{ getCoGrade(st, csn) }}
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge"
                                    :class="st.report_calculated?.overall_percentage >= 33 ? 'badge-green' : 'badge-red'">
                                    {{ st.report_calculated?.overall_percentage }}%
                                </span>
                            </td>
                            <td v-if="showComments" style="background:#faf5ff;" @click.stop>
                                <textarea class="ai-comment-input"
                                    v-model="aiComments[st.id]"
                                    placeholder="AI comment…"
                                    rows="2"
                                ></textarea>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* ─── Weightage Toggle ─── */
.weightage-toggle-row {
    display: flex;
    align-items: center;
}

.toggle-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    border-radius: 10px;
    border: 1.5px solid #e2e8f0;
    background: #f8fafc;
    cursor: pointer;
    transition: all 0.15s;
    user-select: none;
    width: 100%;
}
.toggle-wrap--active {
    border-color: #6d28d9;
    background: linear-gradient(135deg, #eef0ff 0%, #f5f3ff 100%);
}

.toggle-btn {
    position: relative;
    width: 44px;
    height: 24px;
    border-radius: 12px;
    background: #cbd5e1;
    border: none;
    cursor: pointer;
    transition: background 0.2s;
    flex-shrink: 0;
}
.toggle-btn--on { background: #6d28d9; }

.toggle-knob {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    transition: transform 0.2s;
}
.toggle-knob--on { transform: translateX(20px); }

.toggle-label-area { flex: 1; }
.toggle-label-title { font-size: 0.875rem; font-weight: 700; color: #1e293b; display: block; }
.toggle-label-desc { font-size: 0.75rem; color: #64748b; display: block; margin-top: 1px; }
.toggle-label-desc--active { color: #5b21b6; font-weight: 500; }

.toggle-badge {
    font-size: 0.65rem;
    padding: 3px 8px;
    border-radius: 20px;
    background: #6d28d9;
    color: #fff;
    font-weight: 700;
    letter-spacing: 0.03em;
}

/* ─── Weightage Legend ─── */
.weightage-legend {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 20px;
    background: #f5f3ff;
    border-bottom: 1px solid #e9d5ff;
    font-size: 0.78rem;
    color: #5b21b6;
    flex-wrap: wrap;
}
.weightage-pill {
    background: #ede9fe;
    color: #5b21b6;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.72rem;
}

/* ─── Misc ─── */
.rc-error-banner { background:#fff0f0;border:1px solid #ffc9c9;color:#c0392b;border-radius:8px;padding:10px 16px;margin-bottom:16px;font-size:0.85rem; }
.rc-empty-state { text-align:center;padding:3rem;color:#94a3b8; }
.select-all-label { display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.8rem;font-weight:600;color:#475569; }
.wt-max { font-size:0.65rem;color:#94a3b8; }

/* ─── AI Comments ─── */
.btn-ai-comments {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.15s, transform 0.15s;
    box-shadow: 0 2px 10px rgba(99,102,241,0.3);
}
.btn-ai-comments:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
.btn-ai-comments:disabled { opacity: 0.55; cursor: not-allowed; }
.ai-spin { animation: spin 1s linear infinite; display:inline-block; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.ai-comments-banner {
    background: linear-gradient(135deg, #faf5ff, #ede9fe);
    border: 1px solid #c4b5fd;
    border-radius: 10px;
    padding: 10px 16px;
    margin-bottom: 16px;
    font-size: 0.82rem;
    color: #5b21b6;
    font-weight: 500;
}

.ai-comment-input {
    width: 100%;
    border: 1.5px solid #e9d5ff;
    border-radius: 8px;
    padding: 6px 9px;
    font-size: 0.78rem;
    font-family: inherit;
    resize: vertical;
    background: #fff;
    color: #3b0764;
    line-height: 1.4;
    transition: border-color 0.15s;
}
.ai-comment-input:focus { outline: none; border-color: #8b5cf6; }
</style>
