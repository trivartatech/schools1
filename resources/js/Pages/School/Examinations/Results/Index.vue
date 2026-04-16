<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import axios from 'axios';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({ schedules: Array });

const selectedClassId    = ref('');
const selectedSectionId  = ref('');
const selectedScheduleId = ref('');
const loading   = ref(false);
const errorMsg  = ref('');
const result    = ref(null);
const sortKey   = ref('rank');
const sortDir   = ref('asc');

// ── Cascading filters ──────────────────────────────────────────────────────────
const availableClasses = computed(() => {
    const map = new Map();
    props.schedules.forEach(s => {
        if (s.course_class) map.set(s.course_class.id, s.course_class);
    });
    return Array.from(map.values());
});

const availableSections = computed(() => {
    if (!selectedClassId.value) return [];
    const map = new Map();
    props.schedules
        .filter(s => s.course_class_id == selectedClassId.value)
        .forEach(s => s.sections?.forEach(sec => map.set(sec.id, sec)));
    return Array.from(map.values());
});

const availableExams = computed(() => {
    if (!selectedClassId.value || !selectedSectionId.value) return [];
    return props.schedules.filter(s =>
        s.course_class_id == selectedClassId.value &&
        s.sections?.some(sec => sec.id == selectedSectionId.value)
    );
});

function onClassChange()   { selectedSectionId.value = ''; selectedScheduleId.value = ''; result.value = null; }
function onSectionChange() { selectedScheduleId.value = ''; result.value = null; }

async function load() {
    if (!selectedScheduleId.value || !selectedSectionId.value) return;
    loading.value = true;
    errorMsg.value = '';
    result.value = null;
    try {
        const { data } = await axios.get(route('school.exam-results.data'), {
            params: {
                exam_schedule_id: selectedScheduleId.value,
                section_id:       selectedSectionId.value,
            },
        });
        result.value = data;
    } catch (e) {
        errorMsg.value = e.response?.data?.message || 'Failed to load results.';
    } finally {
        loading.value = false;
    }
}

// ── Sorting ────────────────────────────────────────────────────────────────────
function sort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = key === 'rank' || key === 'roll_no' ? 'asc' : 'desc';
    }
}

const sortedRows = computed(() => {
    if (!result.value) return [];
    return [...result.value.rows].sort((a, b) => {
        let av = a[sortKey.value];
        let bv = b[sortKey.value];
        if (typeof av === 'string') { av = av.toLowerCase(); bv = (bv ?? '').toLowerCase(); }
        if (av === null || av === undefined) av = sortDir.value === 'asc' ? Infinity : -Infinity;
        if (bv === null || bv === undefined) bv = sortDir.value === 'asc' ? Infinity : -Infinity;
        return sortDir.value === 'asc' ? (av > bv ? 1 : -1) : (av < bv ? 1 : -1);
    });
});

const sortIcon = (key) => sortKey.value === key ? (sortDir.value === 'asc' ? ' ▲' : ' ▼') : '';

// ── Helpers ────────────────────────────────────────────────────────────────────
function getSubjectMark(row, subjectId) {
    return row.subjects.find(s => s.subject_id == subjectId) ?? null;
}

function pctClass(pct) {
    if (pct === null) return 'badge-amber';
    if (pct >= 75) return 'badge-green';
    if (pct >= 33) return 'badge-blue';
    return 'badge-red';
}

function openPrint() {
    const ids = result.value.rows.map(r => r.id).join(',');
    const url = `/school/report-cards/print?exam_schedule_id=${selectedScheduleId.value}&section_id=${selectedSectionId.value}&student_ids=${ids}&use_weightage=0`;
    window.open(url, '_blank');
}

function openResultsPrint() {
    const url = route('school.exam-results.print') +
        `?exam_schedule_id=${selectedScheduleId.value}&section_id=${selectedSectionId.value}`;
    window.open(url, '_blank');
}
</script>

<template>
    <Head title="Exam Results" />
    <SchoolLayout title="Exam Results">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Exam Results</h1>
                <p class="page-header-sub">Class-wise result sheet — rank, marks per subject, pass/fail status.</p>
            </div>
            <Button v-if="result" variant="secondary" @click="openResultsPrint" style="margin-right:8px;">
                Print Result Sheet
            </Button>
            <Button v-if="result" @click="openPrint">
                Print Report Cards
            </Button>
        </div>

        <!-- Filters -->
        <div class="card mb-6">
            <div class="card-body">
                <div class="form-row" style="grid-template-columns:1fr 1fr 1fr auto;align-items:flex-end;gap:12px;">
                    <div class="form-field">
                        <label>Class</label>
                        <select v-model="selectedClassId" @change="onClassChange">
                            <option value="">-- Select Class --</option>
                            <option v-for="cls in availableClasses" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Section</label>
                        <select v-model="selectedSectionId" @change="onSectionChange" :disabled="!selectedClassId">
                            <option value="">-- Select Section --</option>
                            <option v-for="sec in availableSections" :key="sec.id" :value="sec.id">{{ sec.name }}</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Exam</label>
                        <select v-model="selectedScheduleId" :disabled="!selectedSectionId">
                            <option value="">-- Select Exam --</option>
                            <option v-for="sc in availableExams" :key="sc.id" :value="sc.id">{{ sc.exam_type?.name }}</option>
                        </select>
                    </div>
                    <div>
                        <Button @click="load" :disabled="!selectedScheduleId || !selectedSectionId || loading">
                            <svg v-if="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                            </svg>
                            {{ loading ? 'Loading…' : 'Load Results' }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error -->
        <div v-if="errorMsg" class="res-error">{{ errorMsg }}</div>

        <!-- Stats Cards -->
        <div v-if="result" class="stats-grid mb-6">
            <div class="stat-card">
                <div class="stat-label">Total</div>
                <div class="stat-value">{{ result.stats.total }}</div>
            </div>
            <div class="stat-card stat-pass">
                <div class="stat-label">Passed</div>
                <div class="stat-value">{{ result.stats.pass }}</div>
                <div class="stat-sub">
                    {{ result.stats.total > 0 ? Math.round(result.stats.pass / result.stats.total * 100) : 0 }}%
                </div>
            </div>
            <div class="stat-card stat-fail">
                <div class="stat-label">Failed</div>
                <div class="stat-value">{{ result.stats.fail }}</div>
            </div>
            <div class="stat-card stat-avg">
                <div class="stat-label">Class Avg</div>
                <div class="stat-value">{{ result.stats.average }}%</div>
            </div>
            <div class="stat-card stat-top">
                <div class="stat-label">Highest</div>
                <div class="stat-value">{{ result.stats.highest }}%</div>
                <div class="stat-sub">{{ result.stats.topper }}</div>
            </div>
        </div>

        <!-- Result Table -->
        <div v-if="result" class="card" style="overflow:hidden;">
            <div class="card-header">
                <span class="card-title">
                    {{ result.schedule.class_name }} — {{ result.section.name }} — {{ result.schedule.name }}
                    <span class="badge badge-blue ml-2">{{ result.stats.total }} Students</span>
                </span>
            </div>
            <div style="overflow-x:auto;">
                <Table>
                    <thead>
                        <tr>
                            <th class="sortable" @click="sort('rank')">Rank{{ sortIcon('rank') }}</th>
                            <th class="sortable" @click="sort('roll_no')">Roll No{{ sortIcon('roll_no') }}</th>
                            <th class="sortable" @click="sort('name')">Student{{ sortIcon('name') }}</th>
                            <th v-for="sub in result.subjects" :key="sub.id"
                                style="text-align:center;min-width:90px;">
                                {{ sub.name }}
                            </th>
                            <th class="sortable" @click="sort('total_obtained')" style="text-align:center;">
                                Total{{ sortIcon('total_obtained') }}
                            </th>
                            <th class="sortable" @click="sort('percentage')" style="text-align:center;">
                                %{{ sortIcon('percentage') }}
                            </th>
                            <th style="text-align:center;">Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in sortedRows" :key="row.id"
                            :class="{ 'row-fail': row.percentage < 33 }">
                            <!-- Rank -->
                            <td style="font-weight:700;">
                                <span v-if="row.rank === 1" class="rank-medal">🥇</span>
                                <span v-else-if="row.rank === 2" class="rank-medal">🥈</span>
                                <span v-else-if="row.rank === 3" class="rank-medal">🥉</span>
                                <span :style="row.rank <= 3 ? 'font-weight:800;color:#1e293b;' : 'color:#64748b;'">
                                    {{ row.rank }}
                                </span>
                            </td>
                            <!-- Roll No -->
                            <td style="color:#64748b;">{{ row.roll_no || '—' }}</td>
                            <!-- Name -->
                            <td style="font-weight:600;">
                                {{ row.name }}
                                <div v-if="row.admission_no" style="font-size:0.7rem;color:#94a3b8;">{{ row.admission_no }}</div>
                            </td>
                            <!-- Per-subject marks -->
                            <td v-for="sub in result.subjects" :key="sub.id" style="text-align:center;">
                                <template v-if="getSubjectMark(row, sub.id) !== null">
                                    <span v-if="getSubjectMark(row, sub.id).absent"
                                        class="badge badge-amber" style="font-size:0.68rem;">ABS</span>
                                    <span v-else>
                                        <span :style="getSubjectMark(row, sub.id).fail
                                            ? 'color:#dc2626;font-weight:700;'
                                            : 'color:#1e293b;font-weight:600;'">
                                            {{ getSubjectMark(row, sub.id).obtained }}
                                        </span>
                                        <span style="color:#94a3b8;font-size:0.72rem;">
                                            /{{ getSubjectMark(row, sub.id).max }}
                                        </span>
                                    </span>
                                </template>
                                <span v-else style="color:#cbd5e1;">—</span>
                            </td>
                            <!-- Total -->
                            <td style="text-align:center;font-weight:700;">
                                {{ row.total_obtained }}
                                <span style="color:#94a3b8;font-size:0.72rem;">/{{ row.total_max }}</span>
                            </td>
                            <!-- % -->
                            <td style="text-align:center;">
                                <span :class="['badge', pctClass(row.percentage)]">{{ row.percentage }}%</span>
                            </td>
                            <!-- Result -->
                            <td style="text-align:center;">
                                <span :class="['badge', row.percentage >= 33 ? 'badge-green' : 'badge-red']"
                                    style="font-weight:800;letter-spacing:0.04em;">
                                    {{ row.has_absent ? 'ABSENT' : (row.percentage >= 33 ? 'PASS' : 'FAIL') }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!result && !loading" class="card card-body" style="text-align:center;padding:3rem;color:#94a3b8;">
            <svg class="w-12 h-12 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:#e2e8f0;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Select a class, section, and exam, then click <strong>Load Results</strong>.
        </div>

    </SchoolLayout>
</template>

<style scoped>
.res-error {
    background: #fff0f0;
    border: 1px solid #fca5a5;
    color: #b91c1c;
    border-radius: 8px;
    padding: 10px 16px;
    margin-bottom: 16px;
    font-size: 0.85rem;
}

/* ── Stats ── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
}
@media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 600px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }

.stat-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
}
.stat-pass { border-color: #86efac; background: linear-gradient(135deg, #f0fdf4, #dcfce7); }
.stat-fail { border-color: #fca5a5; background: linear-gradient(135deg, #fff5f5, #fee2e2); }
.stat-avg  { border-color: #93c5fd; background: linear-gradient(135deg, #eff6ff, #dbeafe); }
.stat-top  { border-color: #fcd34d; background: linear-gradient(135deg, #fffbeb, #fef3c7); }

.stat-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
.stat-value { font-size: 1.75rem; font-weight: 800; color: #1e293b; line-height: 1.1; margin-top: 4px; }
.stat-sub   { font-size: 0.72rem; color: #64748b; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── Table ── */
.sortable { cursor: pointer; user-select: none; white-space: nowrap; }
.sortable:hover { background: #f1f5f9 !important; }

.row-fail { background: #fff8f8; }

.rank-medal { font-size: 1rem; margin-right: 2px; }
</style>
