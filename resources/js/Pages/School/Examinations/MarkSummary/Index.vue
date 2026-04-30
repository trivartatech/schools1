<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Table from '@/Components/ui/Table.vue';
import SortableTh from '@/Components/ui/SortableTh.vue';
import { useTableSort } from '@/Composables/useTableSort';
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import axios from 'axios';
import { useToast } from '@/Composables/useToast';

const toast = useToast();

const props = defineProps({ schedules: Array });

const selectedClassId    = ref('');
const selectedSectionId  = ref('');
const selectedScheduleId = ref('');
const loading  = ref(false);
const errorMsg = ref('');
const result   = ref(null); // null | {schedule, section, columns[], rows[], col_stats[]}

// ── Cascading filters (identical logic to Results/Index.vue) ──────────────────
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

// ── Data loading ──────────────────────────────────────────────────────────────
async function load() {
    if (!selectedScheduleId.value || !selectedSectionId.value) return;
    if (loading.value) return; // guard against double-fetch while in flight
    loading.value = true;
    errorMsg.value = '';
    result.value = null;
    try {
        const { data } = await axios.get(route('school.exam-mark-summary.data'), {
            params: {
                exam_schedule_id: selectedScheduleId.value,
                section_id:       selectedSectionId.value,
            },
        });
        result.value = data;
    } catch (e) {
        const msg = e.response?.data?.message || 'Failed to load mark summary.';
        errorMsg.value = msg;
        toast.error(msg);
    } finally {
        loading.value = false;
    }
}

// ── Dual-header computeds ─────────────────────────────────────────────────────
// Group columns by subject for row-1 header (subject name + colspan)
const subjectGroups = computed(() => {
    if (!result.value) return [];
    const groups = [];
    let current = null;
    for (const col of result.value.columns) {
        if (!current || current.subject_id !== col.subject_id) {
            current = { subject_id: col.subject_id, subject_name: col.subject_name, count: 0 };
            groups.push(current);
        }
        current.count++;
    }
    return groups;
});

// Set of column indices that begin a new subject group (for left border separator)
const groupStartSet = computed(() => {
    if (!result.value) return new Set();
    const starts = new Set();
    let lastSubject = null;
    result.value.columns.forEach((col, i) => {
        if (col.subject_id !== lastSubject) { starts.add(i); lastSubject = col.subject_id; }
    });
    return starts;
});

// ── Cell helpers ──────────────────────────────────────────────────────────────
function cellStyle(ci) {
    return groupStartSet.value.has(ci) ? 'border-left: 2px solid #283593;' : '';
}

function failClass(cell, col) {
    if (!cell.entered || cell.is_absent || cell.obtained === null) return '';
    const threshold = col.passing_marks > 0 ? col.passing_marks : col.max_marks * 0.33;
    return cell.obtained < threshold ? 'fail-cell' : '';
}

// ── Print ─────────────────────────────────────────────────────────────────────
function openPrint() {
    if (!result.value) {
        toast.warning('Load summary first.');
        return;
    }
    const url = route('school.exam-mark-summary.print') +
        `?exam_schedule_id=${selectedScheduleId.value}&section_id=${selectedSectionId.value}`;
    window.open(url, '_blank');
}

const statLabels = {
    highest:      'Highest',
    lowest:       'Lowest',
    average:      'Average',
    absent_count: 'Absent',
    pass_count:   'Pass',
    fail_count:   'Fail',
};

// Sort by roll number / student name
const { sortKey, sortDir, toggleSort, sortRows } = useTableSort('roll_no', 'asc');
const sortedRows = computed(() => {
    if (!result.value) return [];
    return sortRows(result.value.rows, {
        getValue: (row, key) => {
            if (key === 'roll_no') {
                const n = parseInt(row.roll_no, 10);
                return isNaN(n) ? row.roll_no : n;
            }
            return row[key];
        },
    });
});
</script>

<template>
    <Head title="Mark Summary" />
    <SchoolLayout title="Mark Summary">

        <!-- Page Header -->
        <PageHeader title="Mark Summary" subtitle="Assessment item-wise marks for all students with class statistics.">
            <template #actions>
                <Button v-if="result" @click="openPrint">
                                Print Mark Summary
                            </Button>
            </template>
        </PageHeader>

        <!-- Filters -->
        <FilterBar :active="!!(selectedClassId || selectedSectionId || selectedScheduleId)"
                   @clear="selectedClassId=''; selectedSectionId=''; selectedScheduleId=''; result=null">
            <div class="form-field">
                <label>Class</label>
                <select v-model="selectedClassId" @change="onClassChange" style="width:160px;">
                    <option value="">-- Select Class --</option>
                    <option v-for="cls in availableClasses" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                </select>
            </div>
            <div class="form-field">
                <label>Section</label>
                <select v-model="selectedSectionId" @change="onSectionChange" :disabled="!selectedClassId" style="width:160px;">
                    <option value="">-- Select Section --</option>
                    <option v-for="sec in availableSections" :key="sec.id" :value="sec.id">{{ sec.name }}</option>
                </select>
            </div>
            <div class="form-field">
                <label>Exam</label>
                <select v-model="selectedScheduleId" :disabled="!selectedSectionId" style="width:200px;">
                    <option value="">-- Select Exam --</option>
                    <option v-for="sc in availableExams" :key="sc.id" :value="sc.id">{{ sc.exam_type?.name }}</option>
                </select>
            </div>
            <Button @click="load" :disabled="!selectedScheduleId || !selectedSectionId || loading" :loading="loading">
                {{ loading ? 'Loading…' : 'Load Summary' }}
            </Button>
        </FilterBar>

        <!-- Error -->
        <div v-if="errorMsg" class="ms-error">{{ errorMsg }}</div>

        <!-- Mark Summary Table -->
        <div v-if="result" class="card" style="overflow:hidden;">
            <div class="card-header">
                <span class="card-title">
                    {{ result.schedule.class_name }} — {{ result.section.name }} — {{ result.schedule.name }}
                    <span class="badge badge-blue ml-2">{{ result.rows.length }} Students</span>
                </span>
            </div>
            <Table class="ms-table" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
                <thead>
                    <!-- Row 1: Subject group headers -->
                    <tr>
                        <SortableTh sort-key="roll_no" rowspan="2" class="ms-fixed">Roll No</SortableTh>
                        <SortableTh sort-key="name" rowspan="2" class="ms-fixed ms-name">Student Name</SortableTh>
                        <th v-for="grp in subjectGroups" :key="grp.subject_id"
                            :colspan="grp.count" class="ms-subj-hdr">
                            {{ grp.subject_name }}
                        </th>
                    </tr>
                    <!-- Row 2: Assessment item headers -->
                    <tr>
                        <th v-for="(col, ci) in result.columns" :key="col.item_id"
                            class="ms-item-hdr" :style="cellStyle(ci)">
                            {{ col.item_name }}
                            <div class="ms-max">/ {{ col.max_marks }}</div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in sortedRows" :key="row.id">
                        <td class="ms-fixed center">{{ row.roll_no || '—' }}</td>
                        <td class="ms-fixed ms-name">
                            {{ row.name }}
                            <div v-if="row.admission_no" class="adm-no">{{ row.admission_no }}</div>
                        </td>
                        <td v-for="(cell, ci) in row.cells" :key="ci"
                            class="center" :style="cellStyle(ci)">
                            <span v-if="!cell.entered" class="no-entry">—</span>
                            <span v-else-if="cell.is_absent" class="absent-tag">ABS</span>
                            <span v-else :class="['mark-val', failClass(cell, result.columns[ci])]">
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
                        <td v-for="(s, ci) in result.col_stats" :key="ci"
                            class="center" :style="cellStyle(ci)">
                            {{ s[stat] ?? '—' }}
                        </td>
                    </tr>
                </tfoot>
            </Table>
        </div>

        <!-- Empty State -->
        <div v-if="!result && !loading" class="card card-body" style="text-align:center;padding:3rem;color:#94a3b8;">
            <svg class="w-12 h-12 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:#e2e8f0;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Select a class, section, and exam, then click <strong>Load Summary</strong>.
        </div>

    </SchoolLayout>
</template>

<style scoped>
.ms-error {
    background: #fff0f0;
    border: 1px solid #fca5a5;
    color: #b91c1c;
    border-radius: 8px;
    padding: 10px 16px;
    margin-bottom: 16px;
    font-size: 0.85rem;
}

/* ── Table — visual overrides over <Table> primitive ── */
.ms-table { font-size: 0.78rem; }

.ms-table :deep(th) {
    background: #1a3764 !important;
    color: #fff !important;
    padding: 6px 8px !important;
    text-align: center !important;
    font-weight: 700 !important;
    border: 1px solid #243b6e !important;
    white-space: nowrap;
    text-transform: none !important;
    letter-spacing: 0 !important;
}

.ms-table :deep(.ms-subj-hdr) {
    border-left: 2px solid #3b5ba5 !important;
    font-size: 0.8rem;
}

.ms-table :deep(.ms-item-hdr) {
    font-size: 0.7rem;
    font-weight: 600 !important;
    background: #253d7a !important;
}

.ms-max {
    font-size: 0.6rem;
    font-weight: 400;
    color: #93c5fd;
    margin-top: 1px;
}

.ms-table :deep(td) {
    padding: 5px 7px !important;
    border: 1px solid #e2e8f0 !important;
    vertical-align: middle;
}

.ms-table :deep(tbody tr:nth-child(even)) { background: #f8fafc; }
.ms-table :deep(tbody tr:hover) { background: #eff6ff !important; }

.ms-fixed { white-space: nowrap; }
.ms-name  { text-align: left !important; min-width: 140px; }
.center   { text-align: center; }

.adm-no   { font-size: 0.62rem; color: #94a3b8; }
.no-entry { color: #cbd5e1; }
.absent-tag {
    background: #fef3c7; color: #92400e;
    font-size: 0.65rem; padding: 1px 5px;
    border-radius: 3px; font-weight: 700;
}
.mark-val { font-weight: 600; color: #1e293b; }
.fail-cell { color: #dc2626 !important; font-weight: 700; }

/* ── Stats footer ── */
.stat-row td {
    background: #f1f5f9;
    font-size: 0.72rem;
    font-weight: 600;
    border-top: 2px solid #e2e8f0;
}
.stat-label-cell {
    text-align: left !important;
    font-weight: 700;
    color: #475569;
    padding-left: 10px;
}
</style>
