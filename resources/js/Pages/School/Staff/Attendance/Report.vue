<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Table from '@/Components/ui/Table.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    report: Array,
    month: Number,
    year: Number,
    daysInMonth: Number,
    summary: Object,
});

const curMonth = ref(props.month);
const curYear  = ref(props.year);

const monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];

const navigate = (delta) => {
    let m = curMonth.value + delta;
    let y = curYear.value;
    if (m > 12) { m = 1; y++; }
    if (m < 1)  { m = 12; y--; }
    curMonth.value = m;
    curYear.value  = y;
    router.get(route('school.staff-attendance.report'), { month: m, year: y }, { preserveState: true, replace: true });
};

const search = ref('');

const filtered = computed(() => {
    if (!search.value) return props.report;
    const q = search.value.toLowerCase();
    return props.report.filter(r =>
        r.name.toLowerCase().includes(q) ||
        r.employee_id?.toLowerCase().includes(q) ||
        r.department?.toLowerCase().includes(q)
    );
});

const statusColor = (s) => {
    const map = { present: '#22c55e', absent: '#ef4444', late: '#eab308', half_day: '#f97316', leave: '#3b82f6', holiday: '#6366f1' };
    return map[s] || '#e2e8f0';
};
const statusLabel = (s) => {
    const map = { present: 'P', absent: 'A', late: 'L', half_day: 'HD', leave: 'LV', holiday: 'H' };
    return map[s] || '';
};

const pct = (v, total) => total > 0 ? Math.round((v / total) * 100) : 0;
</script>

<template>
    <SchoolLayout title="Staff Attendance Report">
        <PageHeader title="Staff Attendance Report" subtitle="Monthly attendance overview for all staff">
            <template #actions>
                <Button variant="secondary" as="a" :href="route('school.staff-attendance.index')">Mark Attendance</Button>

            </template>
        </PageHeader>

        <!-- Month Nav -->
        <div class="month-nav">
            <Button variant="icon" size="sm" aria-label="Previous month" @click="navigate(-1)">&laquo;</Button>
            <h2 class="month-title">{{ monthNames[curMonth] }} {{ curYear }}</h2>
            <Button variant="icon" size="sm" aria-label="Next month" @click="navigate(1)">&raquo;</Button>
        </div>

        <!-- Summary -->
        <div class="stats-row">
            <div class="stat-card"><div class="stat-label">Total Staff</div><div class="stat-value">{{ summary.total_staff }}</div></div>
            <div class="stat-card stat-green"><div class="stat-label">Avg Present/Day</div><div class="stat-value">{{ summary.avg_present }}</div></div>
            <div class="stat-card stat-red"><div class="stat-label">Total Absent Days</div><div class="stat-value">{{ summary.total_absent }}</div></div>
            <div class="stat-card stat-amber"><div class="stat-label">Total Late Days</div><div class="stat-value">{{ summary.total_late }}</div></div>
        </div>

        <div class="card">
            <div class="toolbar">
                <input v-model="search" type="text" placeholder="Search staff..." class="search-input" />
                <div class="legend">
                    <span class="legend-item"><span class="legend-dot" style="background:#22c55e;"></span>Present</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#ef4444;"></span>Absent</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#eab308;"></span>Late</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#f97316;"></span>Half Day</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#3b82f6;"></span>Leave</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#6366f1;"></span>Holiday</span>
                </div>
            </div>

            <Table class="report-table">
                <thead>
                    <tr>
                        <th class="sticky-col" style="min-width:180px;">Staff</th>
                        <th v-for="d in daysInMonth" :key="d" class="day-th">{{ d }}</th>
                        <th class="count-th">P</th>
                        <th class="count-th">A</th>
                        <th class="count-th">L</th>
                        <th class="count-th">%</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in filtered" :key="row.staff_id">
                        <td class="sticky-col staff-col">
                            <div class="staff-name">{{ row.name }}</div>
                            <div class="staff-sub">{{ row.employee_id }} <span v-if="row.department">/ {{ row.department }}</span></div>
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
                        <td class="count-cell count-pct">{{ pct(row.counts.present, row.counts.working_days) }}%</td>
                    </tr>
                    <tr v-if="filtered.length === 0">
                        <td :colspan="daysInMonth + 5" class="empty">No staff found.</td>
                    </tr>
                </tbody>
            </Table>
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

.report-table :deep(table) { min-width:900px; }
.report-table :deep(th) { padding:8px 4px !important; font-size:.65rem; text-align:center !important; }
.report-table :deep(td) { padding:8px 4px !important; font-size:.78rem; text-align:center !important; }
.report-table :deep(.sticky-col) { position:sticky; left:0; background:#fff; z-index:2; text-align:left !important; padding-left:14px !important; border-right:1px solid #e2e8f0; }
.report-table :deep(thead .sticky-col) { background:#f8fafc; }
.report-table :deep(tbody tr:hover .sticky-col) { background:#fafbff; }

.staff-col { min-width:180px; }
.staff-name { font-weight:600; color:#1e293b; font-size:.8rem; }
.staff-sub { font-size:.65rem; color:#94a3b8; }

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
</style>
