<script setup>
import Button from '@/Components/ui/Button.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import { reactive, ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    classes:           Array,
    sections:          Array,
    report:            Array,
    enrolledCount:     Number,
    selectedClassId:   Number,
    selectedSectionId: Number,
    from:              String,
    to:                String,
});

const filter = reactive({
    class_id:   props.selectedClassId   || '',
    section_id: props.selectedSectionId || '',
    from:       props.from,
    to:         props.to,
});

const expanded = ref(null);

const onClassChange = () => {
    filter.section_id = '';
    apply();
};
const apply = () => {
    router.get('/school/attendance/date-wise', filter, { preserveState: true, replace: true });
};
const toggle = (date) => {
    expanded.value = expanded.value === date ? null : date;
};

const stats = computed(() => {
    if (!props.report.length) return null;
    const pcts = props.report.map(r => r.pct).filter(p => p !== null);
    if (!pcts.length) return { taken: props.report.length, avg: null, best: null, worst: null };
    const avg   = Math.round(pcts.reduce((a, b) => a + b, 0) / pcts.length * 10) / 10;
    const best  = Math.max(...pcts);
    const worst = Math.min(...pcts);
    const totalAbsent   = props.report.reduce((s, r) => s + r.absent,   0);
    const totalUnmarked = props.report.reduce((s, r) => s + r.unmarked, 0);
    return { taken: props.report.length, avg, best, worst, totalAbsent, totalUnmarked };
});

const pctColor = (pct) => {
    if (pct === null || pct === undefined) return '#94a3b8';
    if (pct >= 90) return '#10b981';
    if (pct >= 75) return '#f59e0b';
    return '#ef4444';
};

const STATUS_LABEL = { present: 'P', absent: 'A', late: 'L', half_day: 'HD', leave: 'LV' };
const STATUS_COLOR = { present: '#22c55e', absent: '#ef4444', late: '#eab308', half_day: '#f97316', leave: '#3b82f6' };
</script>

<template>
    <SchoolLayout title="Date-wise Attendance">

        <div class="page-header">
            <div>
                <h1 class="page-header-title">Date-wise Attendance Report</h1>
                <p class="page-header-sub">Day-by-day attendance breakdown with unmarked count</p>
            </div>
            <div style="display:flex;gap:8px;">
                <Button variant="secondary" as="link" href="/school/attendance/report">Monthly Report</Button>
                <Button as="link" href="/school/attendance">Mark Attendance</Button>
            </div>
        </div>

        <!-- Filters -->
        <FilterBar>
            <div class="form-field">
                <label>Class</label>
                <select v-model="filter.class_id" @change="onClassChange">
                    <option value="">All Classes</option>
                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <div class="form-field">
                <label>Section</label>
                <select v-model="filter.section_id" @change="apply" :disabled="!filter.class_id">
                    <option value="">All Sections</option>
                    <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div class="form-field">
                <label>From</label>
                <input v-model="filter.from" type="date" @change="apply">
            </div>
            <div class="form-field">
                <label>To</label>
                <input v-model="filter.to" type="date" @change="apply">
            </div>
        </FilterBar>

        <!-- Summary cards -->
        <div v-if="stats" class="stats-row">
            <div class="stat-card">
                <div class="stat-label">Days Taken</div>
                <div class="stat-value">{{ stats.taken }}</div>
            </div>
            <div class="stat-card" :class="stats.avg >= 90 ? 'stat-green' : stats.avg >= 75 ? 'stat-amber' : 'stat-red'">
                <div class="stat-label">Avg Attendance</div>
                <div class="stat-value" :style="{ color: pctColor(stats.avg) }">
                    {{ stats.avg !== null ? stats.avg + '%' : '—' }}
                </div>
            </div>
            <div class="stat-card stat-green">
                <div class="stat-label">Best Day</div>
                <div class="stat-value" style="color:#10b981;">{{ stats.best !== null ? stats.best + '%' : '—' }}</div>
            </div>
            <div class="stat-card stat-red">
                <div class="stat-label">Worst Day</div>
                <div class="stat-value" style="color:#ef4444;">{{ stats.worst !== null ? stats.worst + '%' : '—' }}</div>
            </div>
            <div class="stat-card stat-red">
                <div class="stat-label">Total Absences</div>
                <div class="stat-value" style="color:#ef4444;">{{ stats.totalAbsent }}</div>
            </div>
            <div v-if="stats.totalUnmarked > 0" class="stat-card stat-amber">
                <div class="stat-label">Total Unmarked</div>
                <div class="stat-value" style="color:#f59e0b;">{{ stats.totalUnmarked }}</div>
            </div>
            <div v-if="enrolledCount" class="stat-card">
                <div class="stat-label">Enrolled</div>
                <div class="stat-value">{{ enrolledCount }}</div>
            </div>
        </div>

        <!-- Table -->
        <div v-if="report.length > 0" class="card" style="overflow:hidden;">
            <div class="hint-bar">
                Click any row to see class-wise breakdown for that date.
            </div>
            <div class="table-wrap">
                <table class="dw-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th style="width:48px;">Day</th>
                            <th class="col-num col-green">Present</th>
                            <th class="col-num col-red">Absent</th>
                            <th class="col-num col-amber">Late</th>
                            <th class="col-num col-orange">Half Day</th>
                            <th class="col-num col-blue">Leave</th>
                            <th class="col-num col-warn">Unmarked</th>
                            <th class="col-num">Attend. %</th>
                            <th style="width:32px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="row in report" :key="row.date">
                            <tr
                                :class="['dw-row dw-row-clickable', expanded === row.date ? 'dw-row-expanded' : '']"
                                @click="toggle(row.date)"
                            >
                                <td class="date-cell">{{ school.fmtDate(row.date) }}</td>
                                <td class="day-cell">{{ row.day }}</td>
                                <td class="col-num"><span class="badge badge-green">{{ row.present }}</span></td>
                                <td class="col-num"><span class="badge badge-red">{{ row.absent }}</span></td>
                                <td class="col-num"><span v-if="row.late" class="badge badge-amber">{{ row.late }}</span><span v-else class="muted">—</span></td>
                                <td class="col-num"><span v-if="row.half_day" class="badge badge-orange">{{ row.half_day }}</span><span v-else class="muted">—</span></td>
                                <td class="col-num"><span v-if="row.leave" class="badge badge-blue">{{ row.leave }}</span><span v-else class="muted">—</span></td>
                                <td class="col-num">
                                    <span v-if="row.unmarked > 0" class="badge badge-warn">{{ row.unmarked }}</span>
                                    <span v-else class="muted">—</span>
                                </td>
                                <td class="col-num">
                                    <span v-if="row.pct !== null" class="pct-badge" :style="{ background: pctColor(row.pct) + '22', color: pctColor(row.pct), border: '1px solid ' + pctColor(row.pct) + '55' }">
                                        {{ row.pct }}%
                                    </span>
                                    <span v-else class="muted">—</span>
                                </td>
                                <td class="expand-cell">
                                    <svg :style="{ transform: expanded === row.date ? 'rotate(180deg)' : '', transition: 'transform .2s' }" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </td>
                            </tr>

                            <!-- Class-wise breakdown panel -->
                            <tr v-if="expanded === row.date" :key="row.date + '_d'" class="detail-row">
                                <td colspan="10" style="padding:0;">
                                    <div class="detail-panel">
                                        <div v-if="!row.breakdown || row.breakdown.length === 0" class="detail-empty">
                                            No class-wise data available.
                                        </div>
                                        <template v-else>
                                            <div class="breakdown-header">Class-wise breakdown — {{ school.fmtDate(row.date) }} ({{ row.day }})</div>
                                            <table class="breakdown-table">
                                                <thead>
                                                    <tr>
                                                        <th class="bth-class">Class</th>
                                                        <th class="bth-num" style="color:#10b981;">Present</th>
                                                        <th class="bth-num" style="color:#ef4444;">Absent</th>
                                                        <th class="bth-num" style="color:#eab308;">Late</th>
                                                        <th class="bth-num" style="color:#f97316;">Half Day</th>
                                                        <th class="bth-num" style="color:#3b82f6;">Leave</th>
                                                        <th class="bth-num" style="color:#f59e0b;">Unmarked</th>
                                                        <th class="bth-num">Attend. %</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="cls in row.breakdown" :key="cls.class_name" class="breakdown-row">
                                                        <td class="bcell-class">{{ cls.class_name }}</td>
                                                        <td class="bcell-num"><span class="badge badge-green">{{ cls.present }}</span></td>
                                                        <td class="bcell-num"><span class="badge badge-red">{{ cls.absent }}</span></td>
                                                        <td class="bcell-num"><span v-if="cls.late" class="badge badge-amber">{{ cls.late }}</span><span v-else class="muted">—</span></td>
                                                        <td class="bcell-num"><span v-if="cls.half_day" class="badge badge-orange">{{ cls.half_day }}</span><span v-else class="muted">—</span></td>
                                                        <td class="bcell-num"><span v-if="cls.leave" class="badge badge-blue">{{ cls.leave }}</span><span v-else class="muted">—</span></td>
                                                        <td class="bcell-num">
                                                            <span v-if="cls.unmarked > 0" class="badge badge-warn">{{ cls.unmarked }}</span>
                                                            <span v-else class="muted">—</span>
                                                        </td>
                                                        <td class="bcell-num">
                                                            <span v-if="cls.pct !== null" class="pct-badge" :style="{ background: pctColor(cls.pct) + '22', color: pctColor(cls.pct), border: '1px solid ' + pctColor(cls.pct) + '55' }">
                                                                {{ cls.pct }}%
                                                            </span>
                                                            <span v-else class="muted">—</span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="table-footer-note">
                * Late counts as 0.5 day, Half Day counts as 0.5 day for % calculation.
                Unmarked = enrolled students with no record for that date.
            </div>
        </div>

        <!-- Empty states -->
        <div v-else class="card empty-card">
            <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:var(--border);margin:0 auto 12px;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p>No attendance records found for the selected period.</p>
        </div>

    </SchoolLayout>
</template>

<style scoped>
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}
.stat-card {
    background: #fff;
    border-radius: 10px;
    padding: 14px 16px;
    border: 1.5px solid #e2e8f0;
}
.stat-label { font-size: .72rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
.stat-value { font-size: 1.5rem; font-weight: 800; color: #1e293b; margin-top: 4px; }
.stat-green  { border-left: 4px solid #22c55e; }
.stat-red    { border-left: 4px solid #ef4444; }
.stat-amber  { border-left: 4px solid #f59e0b; }

.hint-bar {
    padding: 8px 16px;
    background: #f0f9ff;
    border-bottom: 1px solid #e0f2fe;
    font-size: 0.78rem;
    color: #0369a1;
}

.table-wrap { overflow-x: auto; }
.dw-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.dw-table thead th {
    background: #f8fafc;
    padding: 10px 12px;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .04em;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.dw-table tbody td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.dw-row-clickable { cursor: pointer; transition: background .15s; }
.dw-row-clickable:hover { background: #f8fafc; }
.dw-row-expanded { background: #f0f9ff !important; }

.date-cell { font-weight: 600; font-size: 0.875rem; white-space: nowrap; }
.day-cell  { color: #64748b; font-size: 0.8125rem; }
.col-num   { text-align: center; }
.col-green { color: #10b981; }
.col-red   { color: #ef4444; }
.col-amber { color: #f59e0b; }
.col-orange{ color: #f97316; }
.col-blue  { color: #3b82f6; }
.col-warn  { color: #f59e0b; }
.muted     { color: #cbd5e1; }
.expand-cell { text-align: center; color: #94a3b8; }

.badge {
    display: inline-block;
    min-width: 28px;
    text-align: center;
    padding: 2px 7px;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 700;
}
.badge-green  { background: #dcfce7; color: #166534; }
.badge-red    { background: #fee2e2; color: #991b1b; }
.badge-amber  { background: #fef3c7; color: #92400e; }
.badge-orange { background: #ffedd5; color: #9a3412; }
.badge-blue   { background: #dbeafe; color: #1e40af; }
.badge-warn   { background: #fef3c7; color: #92400e; }

.pct-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 0.8125rem;
    font-weight: 700;
}

/* Detail / breakdown panel */
.detail-row td  { background: #f0f9ff; }
.detail-panel   { padding: 12px 16px 16px; }
.detail-empty   { font-size: 0.8125rem; color: #94a3b8; padding: 8px 0; }

.breakdown-header {
    font-size: 0.78rem;
    font-weight: 700;
    color: #0369a1;
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: 10px;
}

.breakdown-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.breakdown-table thead tr { background: #f8fafc; }
.bth-class {
    padding: 8px 12px;
    text-align: left;
    font-size: 0.72rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: .04em;
    border-bottom: 1px solid #e2e8f0;
    min-width: 100px;
}
.bth-num {
    padding: 8px 10px;
    text-align: center;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    border-bottom: 1px solid #e2e8f0;
    width: 72px;
}
.breakdown-row { border-bottom: 1px solid #f1f5f9; transition: background .1s; }
.breakdown-row:last-child { border-bottom: none; }
.breakdown-row:hover { background: #f8fafc; }
.bcell-class { padding: 8px 12px; font-weight: 600; color: #1e293b; }
.bcell-num   { padding: 8px 10px; text-align: center; }

.table-footer-note {
    padding: 8px 16px;
    font-size: 0.75rem;
    color: #94a3b8;
    border-top: 1px solid #f1f5f9;
    background: #fafafa;
}

.empty-card {
    text-align: center;
    padding: 48px;
    color: var(--text-muted);
}
</style>
