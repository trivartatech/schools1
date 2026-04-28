<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useFormat } from '@/Composables/useFormat';

const { formatDateTime } = useFormat();

const props = defineProps({
    schedule:     Object,  // {id, name, class_name}
    section:      Object,  // {id, name}
    subjects:     Array,   // [{id, name}]
    rows:         Array,   // student rows with subjects[], rank, percentage, etc.
    stats:        Object,  // {total, pass, fail, highest, lowest, average, topper}
    schoolInfo:   Object,
    academicYear: String,
});

const rowsSortedByRank = computed(() =>
    [...props.rows].sort((a, b) =>
        a.rank !== b.rank ? a.rank - b.rank : (a.roll_no ?? 9999) - (b.roll_no ?? 9999)
    )
);

function getSubjectMark(row, subjectId) {
    return row.subjects.find(s => s.subject_id == subjectId) ?? null;
}
</script>

<template>
    <Head title="Result Sheet — Print" />
    <div class="print-shell">

        <!-- No-print controls -->
        <div class="no-print controls-bar">
            <button onclick="window.print()" class="ctrl-btn ctrl-btn--print">Print Result Sheet</button>
            <button onclick="window.close()" class="ctrl-btn ctrl-btn--close">Close</button>
        </div>

        <!-- School header -->
        <div class="rs-header">
            <div class="rs-header__logo">
                <img v-if="schoolInfo?.logo_path" :src="'/storage/' + schoolInfo.logo_path" />
                <div v-else class="logo-ph">{{ schoolInfo?.name?.charAt(0) }}</div>
            </div>
            <div class="rs-header__center">
                <div class="rs-header__society">{{ schoolInfo?.trust_name || schoolInfo?.name }}</div>
                <div class="rs-header__school">{{ schoolInfo?.name }}</div>
                <div class="rs-header__addr">
                    {{ schoolInfo?.address }}<template v-if="schoolInfo?.phone"> | Ph: {{ schoolInfo.phone }}</template>
                </div>
            </div>
            <div class="rs-header__logo">
                <img v-if="schoolInfo?.logo_path" :src="'/storage/' + schoolInfo.logo_path" />
                <div v-else class="logo-ph">{{ schoolInfo?.name?.charAt(0) }}</div>
            </div>
        </div>

        <!-- Title bar -->
        <div class="rs-title-bar">
            CLASS RESULT SHEET &mdash; {{ academicYear }}
        </div>
        <div class="rs-subtitle">
            {{ schedule.class_name }} &nbsp;|&nbsp; {{ section.name }} &nbsp;|&nbsp; {{ schedule.name }}
        </div>

        <!-- Stats summary -->
        <div class="rs-stats-bar">
            <span>Total: <strong>{{ stats.total }}</strong></span>
            <span class="stat-sep">|</span>
            <span>Pass: <strong style="color:#059669;">{{ stats.pass }}</strong></span>
            <span class="stat-sep">|</span>
            <span>Fail: <strong style="color:#dc2626;">{{ stats.fail }}</strong></span>
            <span class="stat-sep">|</span>
            <span>Class Avg: <strong>{{ stats.average }}%</strong></span>
            <span class="stat-sep">|</span>
            <span>Highest: <strong>{{ stats.highest }}%</strong></span>
            <span class="stat-sep">|</span>
            <span>Topper: <strong>{{ stats.topper || '—' }}</strong></span>
        </div>

        <!-- Result table -->
        <div style="overflow-x:auto;">
            <table class="rs-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Roll No</th>
                        <th class="name-col">Student Name</th>
                        <th v-for="sub in subjects" :key="sub.id" class="subj-col">{{ sub.name }}</th>
                        <th>Total</th>
                        <th>%</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rowsSortedByRank" :key="row.id"
                        :class="{ 'row-fail': row.percentage < 33 }">
                        <td class="center" style="font-weight:700;">{{ row.rank }}</td>
                        <td class="center" style="color:#555;">{{ row.roll_no || '—' }}</td>
                        <td>
                            {{ row.name }}
                            <div v-if="row.admission_no" class="adm-no">{{ row.admission_no }}</div>
                        </td>
                        <td v-for="sub in subjects" :key="sub.id" class="center">
                            <template v-if="getSubjectMark(row, sub.id)">
                                <span v-if="getSubjectMark(row, sub.id).absent" class="absent-tag">ABS</span>
                                <span v-else :class="{ 'fail-mark': getSubjectMark(row, sub.id).fail }">
                                    {{ getSubjectMark(row, sub.id).obtained }}
                                    <span class="max-marks">/{{ getSubjectMark(row, sub.id).max }}</span>
                                </span>
                            </template>
                            <span v-else class="no-entry">—</span>
                        </td>
                        <td class="center" style="font-weight:700;">
                            {{ row.total_obtained }}
                            <span class="max-marks">/{{ row.total_max }}</span>
                        </td>
                        <td class="center" style="font-weight:700;">{{ row.percentage }}%</td>
                        <td class="center">
                            <span :class="['result-badge', row.percentage >= 33 ? 'result-pass' : 'result-fail']">
                                {{ row.has_absent ? 'ABSENT' : (row.percentage >= 33 ? 'PASS' : 'FAIL') }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Print date -->
        <div class="rs-footer">
            Printed on: {{ formatDateTime(new Date()) }}
        </div>

    </div>
</template>

<style scoped>
/* ── Controls (screen only) ── */
.controls-bar {
    display: flex;
    gap: 10px;
    padding: 12px 16px;
    background: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 16px;
}
.ctrl-btn {
    padding: 7px 18px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
}
.ctrl-btn--print { background: #1d4ed8; color: #fff; }
.ctrl-btn--close  { background: #e2e8f0; color: #334155; }

/* ── Shell ── */
.print-shell {
    max-width: 100%;
    background: #fff;
    font-family: 'Arial', sans-serif;
    font-size: 0.75rem;
    color: #1e293b;
}

/* ── Header ── */
.rs-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    border-bottom: 2px solid #1a3764;
}
.rs-header__logo { width: 64px; text-align: center; flex-shrink: 0; }
.rs-header__logo img { width: 56px; height: 56px; object-fit: contain; }
.logo-ph {
    width: 56px; height: 56px;
    background: #1a3764; color: #fff;
    border-radius: 50%; display: flex;
    align-items: center; justify-content: center;
    font-size: 1.4rem; font-weight: 700;
}
.rs-header__center { flex: 1; text-align: center; }
.rs-header__society { font-size: 0.72rem; color: #555; }
.rs-header__school  { font-size: 1.1rem; font-weight: 800; color: #1a3764; }
.rs-header__addr    { font-size: 0.68rem; color: #666; }

/* ── Title ── */
.rs-title-bar {
    text-align: center;
    font-size: 0.9rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    background: #1a3764;
    color: #fff;
    padding: 5px;
    margin-top: 6px;
}
.rs-subtitle {
    text-align: center;
    font-size: 0.73rem;
    font-weight: 600;
    color: #334155;
    padding: 4px;
    background: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
}

/* ── Stats bar ── */
.rs-stats-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 12px;
    padding: 6px 14px;
    font-size: 0.72rem;
    color: #334155;
    background: #fafafa;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 6px;
}
.stat-sep { color: #cbd5e1; }

/* ── Table ── */
.rs-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.7rem;
}
.rs-table th {
    background: #1a3764;
    color: #fff;
    padding: 5px 6px;
    text-align: center;
    font-weight: 700;
    white-space: nowrap;
    border: 1px solid #243b6e;
}
.rs-table td {
    padding: 4px 6px;
    border: 1px solid #e2e8f0;
    vertical-align: middle;
}
.rs-table tbody tr:nth-child(even) { background: #f8fafc; }
.row-fail { background: #fff5f5 !important; }
.name-col { text-align: left !important; min-width: 130px; }
.subj-col { min-width: 70px; }
.center { text-align: center; }

.adm-no   { font-size: 0.62rem; color: #94a3b8; }
.max-marks { font-size: 0.62rem; color: #94a3b8; }
.fail-mark { color: #dc2626; font-weight: 700; }
.absent-tag { background: #fef3c7; color: #92400e; font-size: 0.62rem; padding: 1px 4px; border-radius: 3px; }
.no-entry  { color: #cbd5e1; }

.result-badge {
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    padding: 2px 6px;
    border-radius: 4px;
}
.result-pass { background: #dcfce7; color: #166534; }
.result-fail { background: #fee2e2; color: #991b1b; }

/* ── Footer ── */
.rs-footer {
    text-align: right;
    font-size: 0.62rem;
    color: #94a3b8;
    padding: 8px 12px 4px;
    border-top: 1px solid #e2e8f0;
    margin-top: 8px;
}

/* ── Print ── */
@media print {
    .no-print { display: none !important; }
    body { background: #fff; margin: 0; }
    .print-shell { margin: 0; }
    @page { size: A4 landscape; margin: 8mm; }
    thead { display: table-header-group; }
    .rs-table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .rs-title-bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
