<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useFormat } from '@/Composables/useFormat';

const { formatDateTime } = useFormat();

const props = defineProps({
    schedule:     Object,  // {id, name, class_name}
    section:      Object,  // {id, name}
    columns:      Array,   // [{ss_id, subject_id, subject_name, item_id, item_name, item_code, max_marks, passing_marks}]
    rows:         Array,   // [{id, name, roll_no, admission_no, cells[]}]
    col_stats:    Array,   // [{highest, lowest, average, absent_count, pass_count, fail_count}]
    schoolInfo:   Object,
    academicYear: String,
});

// Group columns by subject for the dual-header row 1
const subjectGroups = computed(() => {
    const groups = [];
    let current = null;
    for (const col of props.columns) {
        if (!current || current.subject_id !== col.subject_id) {
            current = { subject_id: col.subject_id, subject_name: col.subject_name, count: 0 };
            groups.push(current);
        }
        current.count++;
    }
    return groups;
});

// Set of column indices that start a new subject group (for vertical separator)
const groupStartSet = computed(() => {
    const starts = new Set();
    let lastSubject = null;
    props.columns.forEach((col, i) => {
        if (col.subject_id !== lastSubject) { starts.add(i); lastSubject = col.subject_id; }
    });
    return starts;
});

function cellStyle(ci) {
    return groupStartSet.value.has(ci) ? 'border-left: 2px solid #283593;' : '';
}

function failClass(cell, col) {
    if (!cell.entered || cell.is_absent || cell.obtained === null) return '';
    const threshold = col.passing_marks > 0 ? col.passing_marks : col.max_marks * 0.33;
    return cell.obtained < threshold ? 'fail-cell' : '';
}

const statLabels = {
    highest:      'Highest',
    lowest:       'Lowest',
    average:      'Average',
    absent_count: 'Absent',
    pass_count:   'Pass',
    fail_count:   'Fail',
};
</script>

<template>
    <Head title="Mark Summary — Print" />
    <div class="print-shell">

        <!-- No-print controls -->
        <div class="no-print controls-bar">
            <button onclick="window.print()" class="ctrl-btn ctrl-btn--print">Print Mark Summary</button>
            <button onclick="window.close()" class="ctrl-btn ctrl-btn--close">Close</button>
        </div>

        <!-- School header -->
        <div class="ms-header">
            <div class="ms-header__logo">
                <img v-if="schoolInfo?.logo_path" :src="'/storage/' + schoolInfo.logo_path" />
                <div v-else class="logo-ph">{{ schoolInfo?.name?.charAt(0) }}</div>
            </div>
            <div class="ms-header__center">
                <div class="ms-header__society">{{ schoolInfo?.trust_name || schoolInfo?.name }}</div>
                <div class="ms-header__school">{{ schoolInfo?.name }}</div>
                <div class="ms-header__addr">
                    {{ schoolInfo?.address }}<template v-if="schoolInfo?.phone"> | Ph: {{ schoolInfo.phone }}</template>
                </div>
            </div>
            <div class="ms-header__logo">
                <img v-if="schoolInfo?.logo_path" :src="'/storage/' + schoolInfo.logo_path" />
                <div v-else class="logo-ph">{{ schoolInfo?.name?.charAt(0) }}</div>
            </div>
        </div>

        <!-- Title -->
        <div class="ms-title-bar">
            MARK SUMMARY &mdash; {{ academicYear }}
        </div>
        <div class="ms-subtitle">
            {{ schedule.class_name }} &nbsp;|&nbsp; {{ section.name }} &nbsp;|&nbsp; {{ schedule.name }}
        </div>

        <!-- Mark summary table -->
        <div style="overflow-x:auto;">
            <table class="ms-table">
                <thead>
                    <!-- Row 1: Subject group headers -->
                    <tr>
                        <th rowspan="2" class="ms-roll">Roll No</th>
                        <th rowspan="2" class="ms-name">Student Name</th>
                        <th v-for="grp in subjectGroups" :key="grp.subject_id"
                            :colspan="grp.count" class="ms-subj-hdr">
                            {{ grp.subject_name }}
                        </th>
                    </tr>
                    <!-- Row 2: Assessment item headers -->
                    <tr>
                        <th v-for="(col, ci) in columns" :key="col.item_id"
                            class="ms-item-hdr" :style="cellStyle(ci)">
                            {{ col.item_name }}
                            <div class="ms-max">/ {{ col.max_marks }}</div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id">
                        <td class="center">{{ row.roll_no || '—' }}</td>
                        <td class="ms-name-cell">
                            {{ row.name }}
                            <div v-if="row.admission_no" class="adm-no">{{ row.admission_no }}</div>
                        </td>
                        <td v-for="(cell, ci) in row.cells" :key="ci"
                            class="center" :style="cellStyle(ci)">
                            <span v-if="!cell.entered" class="no-entry">—</span>
                            <span v-else-if="cell.is_absent" class="absent-tag">ABS</span>
                            <span v-else :class="['mark-val', failClass(cell, columns[ci])]">
                                {{ cell.obtained }}
                            </span>
                        </td>
                    </tr>
                </tbody>
                <!-- Statistics rows -->
                <tfoot>
                    <tr v-for="stat in ['highest','lowest','average','absent_count','pass_count','fail_count']"
                        :key="stat" class="stat-row">
                        <td colspan="2" class="stat-label-cell">{{ statLabels[stat] }}</td>
                        <td v-for="(s, ci) in col_stats" :key="ci"
                            class="center" :style="cellStyle(ci)">
                            {{ s[stat] ?? '—' }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Print date -->
        <div class="ms-footer">
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
    font-size: 0.68rem;
    color: #1e293b;
}

/* ── Header ── */
.ms-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    border-bottom: 2px solid #1a3764;
}
.ms-header__logo { width: 60px; text-align: center; flex-shrink: 0; }
.ms-header__logo img { width: 52px; height: 52px; object-fit: contain; }
.logo-ph {
    width: 52px; height: 52px;
    background: #1a3764; color: #fff;
    border-radius: 50%; display: flex;
    align-items: center; justify-content: center;
    font-size: 1.2rem; font-weight: 700;
}
.ms-header__center { flex: 1; text-align: center; }
.ms-header__society { font-size: 0.65rem; color: #555; }
.ms-header__school  { font-size: 1rem; font-weight: 800; color: #1a3764; }
.ms-header__addr    { font-size: 0.62rem; color: #666; }

/* ── Title ── */
.ms-title-bar {
    text-align: center;
    font-size: 0.85rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    background: #1a3764;
    color: #fff;
    padding: 5px;
    margin-top: 6px;
}
.ms-subtitle {
    text-align: center;
    font-size: 0.68rem;
    font-weight: 600;
    color: #334155;
    padding: 4px;
    background: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 6px;
}

/* ── Table ── */
.ms-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.65rem;
}

.ms-table th {
    background: #1a3764;
    color: #fff;
    padding: 4px 5px;
    text-align: center;
    font-weight: 700;
    border: 1px solid #243b6e;
    white-space: nowrap;
}

.ms-subj-hdr {
    border-left: 2px solid #3b5ba5 !important;
    font-size: 0.68rem;
}

.ms-item-hdr {
    font-size: 0.6rem;
    font-weight: 600 !important;
    background: #253d7a !important;
}

.ms-max {
    font-size: 0.55rem;
    font-weight: 400;
    color: #93c5fd;
    margin-top: 1px;
}

.ms-table td {
    padding: 3px 5px;
    border: 1px solid #e2e8f0;
    vertical-align: middle;
}

.ms-table tbody tr:nth-child(even) { background: #f8fafc; }

.ms-roll  { min-width: 40px; }
.ms-name  { text-align: left !important; min-width: 110px; }
.ms-name-cell { text-align: left; white-space: nowrap; }
.center   { text-align: center; }

.adm-no   { font-size: 0.55rem; color: #94a3b8; }
.no-entry { color: #cbd5e1; }
.absent-tag {
    background: #fef3c7; color: #92400e;
    font-size: 0.58rem; padding: 1px 3px;
    border-radius: 2px; font-weight: 700;
}
.mark-val { font-weight: 600; }
.fail-cell { color: #dc2626 !important; font-weight: 700; }

/* ── Stats footer ── */
.stat-row td {
    background: #f1f5f9;
    font-weight: 600;
    border-top: 2px solid #e2e8f0;
}
.stat-label-cell {
    text-align: left !important;
    font-weight: 700;
    color: #475569;
    padding-left: 8px;
}

/* ── Print footer ── */
.ms-footer {
    text-align: right;
    font-size: 0.58rem;
    color: #94a3b8;
    padding: 6px 12px 4px;
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
    .ms-table th      { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .ms-title-bar     { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .ms-item-hdr      { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .stat-row td      { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
