<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Table from '@/Components/ui/Table.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    hostels: Array, report: Array, daysInMonth: Number, summary: Object,
    hostelId: Number, month: Number, year: Number, slot: String,
});

const selHostel = ref(props.hostelId || '');
const curMonth  = ref(props.month);
const curYear   = ref(props.year);
const selSlot   = ref(props.slot || 'night');
const search    = ref('');

const monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

const apply = () => {
    router.get(route('school.hostel.roll-call.report'), {
        hostel_id: selHostel.value, month: curMonth.value, year: curYear.value, slot: selSlot.value,
    }, { preserveState: true, replace: true });
};

const navigate = (d) => {
    let m = curMonth.value + d, y = curYear.value;
    if (m > 12) { m = 1; y++; } if (m < 1) { m = 12; y--; }
    curMonth.value = m; curYear.value = y; apply();
};

const filtered = computed(() => {
    if (!search.value) return props.report;
    const q = search.value.toLowerCase();
    return props.report.filter(r => r.name.toLowerCase().includes(q) || r.admission_no?.toLowerCase().includes(q));
});

const statusColor = (s) => ({ present:'#22c55e', absent:'#ef4444', leave:'#3b82f6', medical:'#f97316' })[s] || '#e2e8f0';
const statusLabel = (s) => ({ present:'P', absent:'A', leave:'LV', medical:'M' })[s] || '';
const pct = (v, t) => t > 0 ? Math.round((v/t)*100) : 0;
</script>

<template>
<SchoolLayout title="Hostel Roll Call Report">
    <PageHeader title="Hostel Roll Call Report" subtitle="Monthly bed check overview">
        <template #actions>
            <Button variant="secondary" as="a" :href="route('school.hostel.roll-call.index')">Mark Roll Call</Button>
        </template>
    </PageHeader>

    <!-- Filters -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-body">
            <div class="form-row form-row-4" style="align-items:flex-end;">
                <div class="form-field">
                    <label>Hostel</label>
                    <select v-model="selHostel" @change="apply">
                        <option value="">Select Hostel</option>
                        <option v-for="h in hostels" :key="h.id" :value="h.id">{{ h.name }}</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Slot</label>
                    <select v-model="selSlot" @change="apply">
                        <option value="night">Night Check</option>
                        <option value="morning">Morning Check</option>
                    </select>
                </div>
                <div></div><div></div>
            </div>
        </div>
    </div>

    <template v-if="selHostel && report.length > 0">
        <div class="month-nav">
            <Button variant="icon" size="sm" aria-label="Previous month" @click="navigate(-1)">&laquo;</Button>
            <h2 class="month-title">{{ monthNames[curMonth] }} {{ curYear }}</h2>
            <Button variant="icon" size="sm" aria-label="Next month" @click="navigate(1)">&raquo;</Button>
        </div>

        <div class="stats-row">
            <div class="stat-card"><div class="stat-label">Students</div><div class="stat-value">{{ summary.total_students }}</div></div>
            <div class="stat-card stat-green"><div class="stat-label">Avg Present</div><div class="stat-value">{{ summary.avg_present }}</div></div>
            <div class="stat-card stat-red"><div class="stat-label">Total Absent</div><div class="stat-value">{{ summary.total_absent }}</div></div>
            <div class="stat-card stat-amber"><div class="stat-label">Total Medical</div><div class="stat-value">{{ summary.total_medical }}</div></div>
        </div>

        <div class="card">
            <div class="toolbar">
                <input v-model="search" placeholder="Search student..." class="search-input" />
                <div class="legend">
                    <span class="legend-item"><span class="legend-dot" style="background:#22c55e;"></span>Present</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#ef4444;"></span>Absent</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#3b82f6;"></span>Leave</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#f97316;"></span>Medical</span>
                </div>
            </div>
            <Table class="report-table">
                <thead><tr>
                    <th class="sticky-col" style="min-width:180px;">Student</th>
                    <th v-for="d in daysInMonth" :key="d" class="day-th">{{ d }}</th>
                    <th class="count-th">P</th><th class="count-th">A</th><th class="count-th">%</th>
                </tr></thead>
                <tbody>
                    <tr v-for="row in filtered" :key="row.student_id">
                        <td class="sticky-col student-col">
                            <div class="student-name">{{ row.name }}</div>
                            <div class="student-sub">{{ row.admission_no }}</div>
                        </td>
                        <td v-for="d in daysInMonth" :key="d" class="day-cell">
                            <span v-if="row.days[d]" class="day-dot" :style="{background:statusColor(row.days[d])}" :title="row.days[d]">{{ statusLabel(row.days[d]) }}</span>
                            <span v-else class="day-empty">-</span>
                        </td>
                        <td class="count-cell count-green">{{ row.counts.present }}</td>
                        <td class="count-cell count-red">{{ row.counts.absent }}</td>
                        <td class="count-cell count-pct">{{ pct(row.counts.present, row.counts.working_days) }}%</td>
                    </tr>
                    <tr v-if="!filtered.length"><td :colspan="daysInMonth+4" class="empty">No data found.</td></tr>
                </tbody>
            </Table>
        </div>
    </template>
    <div v-else class="card" style="text-align:center;padding:48px;color:var(--text-muted);">
        {{ selHostel ? 'No roll call data for this period.' : 'Select a hostel to view the report.' }}
    </div>
</SchoolLayout>
</template>

<style scoped>
.month-nav { display:flex; align-items:center; gap:16px; margin-bottom:16px; }
.month-title { font-size:1.1rem; font-weight:700; color:#1e293b; }

.stats-row { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px,1fr)); gap:12px; margin-bottom:16px; }
.stat-card { background:#fff; border-radius:10px; padding:12px 14px; border:1.5px solid #e2e8f0; }
.stat-label { font-size:.7rem; color:#64748b; font-weight:600; text-transform:uppercase; }
.stat-value { font-size:1.4rem; font-weight:800; color:#1e293b; margin-top:2px; }
.stat-green { border-left:4px solid #22c55e; } .stat-red { border-left:4px solid #ef4444; } .stat-amber { border-left:4px solid #f97316; }
.toolbar { display:flex; flex-wrap:wrap; gap:12px; align-items:center; padding:14px 18px; border-bottom:1px solid #f1f5f9; }
.search-input { border:1.5px solid #e2e8f0; border-radius:8px; padding:7px 12px; font-size:.84rem; outline:none; min-width:180px; font-family:inherit; }
.legend { display:flex; gap:12px; flex-wrap:wrap; margin-left:auto; }
.legend-item { display:flex; align-items:center; gap:4px; font-size:.72rem; color:#64748b; font-weight:500; }
.legend-dot { width:10px; height:10px; border-radius:50%; }
.report-table :deep(table) { min-width:900px; }
.report-table :deep(th) { padding:8px 4px !important; font-size:.65rem; text-align:center !important; }
.report-table :deep(td) { padding:8px 4px !important; font-size:.78rem; text-align:center !important; }
.report-table :deep(.sticky-col) { position:sticky; left:0; background:#fff; z-index:2; text-align:left !important; padding-left:14px !important; border-right:1px solid #e2e8f0; }
.report-table :deep(thead .sticky-col) { background:#f8fafc; }
.report-table :deep(tbody tr:hover .sticky-col) { background:#fafbff; }
.student-col { min-width:180px; } .student-name { font-weight:600; color:#1e293b; font-size:.8rem; } .student-sub { font-size:.65rem; color:#94a3b8; }
.day-th { min-width:28px; } .day-cell { padding:4px 2px!important; }
.day-dot { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; border-radius:4px; font-size:.6rem; font-weight:700; color:#fff; }
.day-empty { color:#e2e8f0; font-size:.7rem; }
.count-th { min-width:36px; font-weight:800!important; } .count-cell { font-weight:700; font-size:.78rem; }
.count-green { color:#22c55e; } .count-red { color:#ef4444; } .count-pct { color:#6366f1; font-weight:800; }
.empty { text-align:center!important; color:#94a3b8; padding:40px!important; }
</style>
