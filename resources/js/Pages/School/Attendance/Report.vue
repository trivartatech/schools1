<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Table from '@/Components/ui/Table.vue';
import { reactive, ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import ExportDropdown from '@/Components/ExportDropdown.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    classes: Array,
    sections: Array,
    report: Array,
    daysInMonth: Number,
    summary: Object,
    selectedClassId: Number,
    selectedSectionId: Number,
    selectedMonth: String,
});

const filter = reactive({
    class_id:   props.selectedClassId || '',
    section_id: props.selectedSectionId || '',
    month:      props.selectedMonth || school.currentMonth(),
});

const applyFilter = () => {
    router.get('/school/attendance/report', filter, { preserveState: true, replace: true });
};

const onClassChange = () => {
    filter.section_id = '';
    applyFilter();
};

const navigateMonth = (delta) => {
    const [y, m] = filter.month.split('-').map(Number);
    let nm = m + delta, ny = y;
    if (nm > 12) { nm = 1; ny++; }
    if (nm < 1)  { nm = 12; ny--; }
    filter.month = `${ny}-${String(nm).padStart(2, '0')}`;
    applyFilter();
};

const monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];

const monthLabel = computed(() => {
    const [y, m] = filter.month.split('-').map(Number);
    return `${monthNames[m]} ${y}`;
});

const search = ref('');

const filtered = computed(() => {
    if (!search.value) return props.report;
    const q = search.value.toLowerCase();
    return props.report.filter(r =>
        r.name.toLowerCase().includes(q) ||
        (r.roll_no && String(r.roll_no).toLowerCase().includes(q))
    );
});

const statusColor = (s) => {
    const map = { present: '#22c55e', absent: '#ef4444', late: '#eab308', half_day: '#f97316', leave: '#3b82f6' };
    return map[s] || '#e2e8f0';
};
const statusLabel = (s) => {
    const map = { present: 'P', absent: 'A', late: 'L', half_day: 'HD', leave: 'LV' };
    return map[s] || '';
};

const pct = (row) => {
    const wd = row.counts.working_days;
    if (wd === 0) return 0;
    const effective = row.counts.present + (row.counts.late * 0.5) + (row.counts.half_day * 0.5);
    return Math.round((effective / wd) * 100);
};
</script>

<template>
    <SchoolLayout title="Attendance Report">
        <PageHeader title="Student Attendance Report" subtitle="Monthly day-by-day attendance overview">
            <template #actions>
                <ExportDropdown
                    base-url="/school/export/attendance"
                    :params="{ class_id: filter.class_id, section_id: filter.section_id, month: filter.month }"
                />
                <Button as="link" href="/school/attendance">Mark Attendance</Button>

            </template>
        </PageHeader>

        <!-- Filters -->
        <FilterBar>
            <div class="form-field">
                <label>Class</label>
                <select v-model="filter.class_id" @change="onClassChange">
                    <option value="">Select Class</option>
                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <div class="form-field">
                <label>Section</label>
                <select v-model="filter.section_id" @change="applyFilter" :disabled="!filter.class_id">
                    <option value="">All Sections</option>
                    <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div class="form-field">
                <label>Month</label>
                <input v-model="filter.month" type="month" @change="applyFilter">
            </div>
        </FilterBar>

        <template v-if="selectedClassId && report && report.length > 0">
            <!-- Month Nav -->
            <div class="month-nav">
                <Button variant="icon" size="sm" aria-label="Previous month" @click="navigateMonth(-1)">&laquo;</Button>
                <h2 class="month-title">{{ monthLabel }}</h2>
                <Button variant="icon" size="sm" aria-label="Next month" @click="navigateMonth(1)">&raquo;</Button>
            </div>

            <!-- Summary -->
            <div class="stats-row">
                <div class="stat-card"><div class="stat-label">Total Students</div><div class="stat-value">{{ summary.total_students }}</div></div>
                <div class="stat-card stat-green"><div class="stat-label">Avg Present/Day</div><div class="stat-value">{{ summary.avg_present }}</div></div>
                <div class="stat-card stat-red"><div class="stat-label">Total Absent Days</div><div class="stat-value">{{ summary.total_absent }}</div></div>
                <div class="stat-card stat-amber"><div class="stat-label">Total Late Days</div><div class="stat-value">{{ summary.total_late }}</div></div>
            </div>

            <!-- Calendar Grid -->
            <div class="card">
                <div class="toolbar">
                    <input v-model="search" type="text" placeholder="Search student..." class="search-input" />
                    <div class="legend">
                        <span class="legend-item"><span class="legend-dot" style="background:#22c55e;"></span>Present</span>
                        <span class="legend-item"><span class="legend-dot" style="background:#ef4444;"></span>Absent</span>
                        <span class="legend-item"><span class="legend-dot" style="background:#eab308;"></span>Late</span>
                        <span class="legend-item"><span class="legend-dot" style="background:#f97316;"></span>Half Day</span>
                        <span class="legend-item"><span class="legend-dot" style="background:#3b82f6;"></span>Leave</span>
                    </div>
                </div>

                <Table class="report-table">
                    <thead>
                        <tr>
                            <th class="sticky-col" style="min-width:60px;">Roll</th>
                            <th class="sticky-col-name">Student</th>
                            <th v-for="d in daysInMonth" :key="d" class="day-th">{{ d }}</th>
                            <th class="count-th">P</th>
                            <th class="count-th">A</th>
                            <th class="count-th">L</th>
                            <th class="count-th">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in filtered" :key="row.student_id">
                            <td class="sticky-col roll-col">{{ row.roll_no || '—' }}</td>
                            <td class="sticky-col-name student-col">
                                <div class="student-name">{{ row.name }}</div>
                            </td>
                            <td v-for="d in daysInMonth" :key="d" class="day-cell">
                                <span v-if="row.days[d]" class="day-dot" :style="{ background: statusColor(row.days[d]) }" :title="row.days[d]">
                                    {{ statusLabel(row.days[d]) }}
                                </span>
                                <span v-else class="day-empty">-</span>
                            </td>
                            <td class="count-cell count-green">{{ row.counts.present }}</td>
                            <td class="count-cell count-red">{{ row.counts.absent }}</td>
                            <td class="count-cell count-amber">{{ row.counts.late }}</td>
                            <td class="count-cell count-pct">{{ pct(row) }}%</td>
                        </tr>
                        <tr v-if="filtered.length === 0">
                            <td :colspan="daysInMonth + 6" class="empty">No students found.</td>
                        </tr>
                    </tbody>
                </Table>

                <div class="report-legend">
                    <span class="legend-note">* Late and half-day count as 0.5 day for % calculation</span>
                    <div class="legend-items">
                        <span class="li"><span class="ld" style="background:#22c55e;"></span> &ge; 90%</span>
                        <span class="li"><span class="ld" style="background:#f59e0b;"></span> 75-89%</span>
                        <span class="li"><span class="ld" style="background:#ef4444;"></span> &lt; 75%</span>
                    </div>
                </div>
            </div>
        </template>

        <!-- Empty: class selected, no data -->
        <div v-else-if="selectedClassId" class="card" style="text-align:center;padding:48px;color:var(--text-muted);">
            <svg class="w-12 h-12" style="margin:0 auto 12px;color:var(--border);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p style="font-size:0.875rem;">No attendance data found for the selected filters.</p>
        </div>

        <!-- Empty: nothing selected -->
        <div v-else class="card" style="text-align:center;padding:48px;color:var(--text-muted);">
            <svg class="w-12 h-12" style="margin:0 auto 12px;color:var(--border);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <p style="font-size:0.875rem;">Select a class and month to view the attendance report.</p>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.month-nav { display:flex; align-items:center; gap:16px; margin-bottom:16px; }
.month-title { font-size:1.1rem; font-weight:700; color:#1e293b; }


.stats-row { display:grid; grid-template-columns:repeat(auto-fill, minmax(160px,1fr)); gap:12px; margin-bottom:18px; }
.stat-card { background:#fff; border-radius:10px; padding:14px 16px; border:1.5px solid #e2e8f0; }
.stat-label { font-size:.72rem; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:.05em; }
.stat-value { font-size:1.5rem; font-weight:800; color:#1e293b; margin-top:4px; }
.stat-green { border-left:4px solid #22c55e; }
.stat-red   { border-left:4px solid #ef4444; }
.stat-amber { border-left:4px solid #f59e0b; }

.toolbar { display:flex; flex-wrap:wrap; gap:12px; align-items:center; padding:14px 18px; border-bottom:1px solid #f1f5f9; }
.search-input { border:1.5px solid #e2e8f0; border-radius:8px; padding:7px 12px; font-size:.84rem; outline:none; font-family:inherit; min-width:180px; }
.search-input:focus { border-color:#6366f1; }
.legend { display:flex; gap:12px; flex-wrap:wrap; margin-left:auto; }
.legend-item { display:flex; align-items:center; gap:4px; font-size:.72rem; color:#64748b; font-weight:500; }
.legend-dot { width:10px; height:10px; border-radius:50%; }

.report-table :deep(table) { min-width: 900px; }
.report-table :deep(th) { padding:8px 4px !important; font-size:.65rem; text-align:center !important; }
.report-table :deep(td) { padding:8px 4px !important; font-size:.78rem; text-align:center !important; }

.report-table :deep(.sticky-col) { position:sticky; left:0; background:#fff; z-index:3; border-right:1px solid #e2e8f0; }
.report-table :deep(.sticky-col-name) { position:sticky; left:60px; background:#fff; z-index:3; text-align:left !important; padding-left:10px !important; border-right:1px solid #e2e8f0; min-width:160px; }
.report-table :deep(thead .sticky-col),
.report-table :deep(thead .sticky-col-name) { background:#f8fafc; }
.report-table :deep(tbody tr:hover .sticky-col),
.report-table :deep(tbody tr:hover .sticky-col-name) { background:#fafbff; }

.roll-col { min-width:60px; font-size:.75rem; color:#94a3b8; font-family:monospace; }
.student-col { min-width:160px; }
.student-name { font-weight:600; color:#1e293b; font-size:.8rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:180px; }

.day-th { min-width:28px; }
.day-cell { padding:4px 2px !important; }
.day-dot { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; border-radius:4px; font-size:.6rem; font-weight:700; color:#fff; }
.day-empty { color:#e2e8f0; font-size:.7rem; }

.count-th { min-width:36px; font-weight:800 !important; }
.count-cell { font-weight:700; font-size:.78rem; }
.count-green { color:#22c55e; }
.count-red   { color:#ef4444; }
.count-amber { color:#eab308; }
.count-pct   { color:#6366f1; font-weight:800; }

.empty { text-align:center !important; color:#94a3b8; padding:40px !important; }

.report-legend { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; padding:12px 18px; border-top:1px solid #f1f5f9; background:#fafbfc; }
.legend-note { font-size:.72rem; color:#94a3b8; }
.legend-items { display:flex; gap:14px; }
.li { display:flex; align-items:center; gap:4px; font-size:.72rem; color:#64748b; font-weight:500; }
.ld { width:8px; height:8px; border-radius:50%; }
</style>
