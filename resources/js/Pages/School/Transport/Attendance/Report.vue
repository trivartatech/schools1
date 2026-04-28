<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    records: Array,
    summary: Object,
    routes:  Array,
    filters: Object,
});

const from    = ref(props.filters.from);
const to      = ref(props.filters.to);
const routeId = ref(props.filters.route_id || '');

function applyFilters() {
    router.get('/school/transport/attendance/report', {
        from:     from.value,
        to:       to.value,
        route_id: routeId.value || undefined,
    }, { preserveScroll: true });
}

const attendanceRate = computed(() => {
    if (!props.summary.total_records) return 0;
    return Math.round((props.summary.present / props.summary.total_records) * 100);
});

function rateColor(pct) {
    if (pct >= 80) return '#059669';
    if (pct >= 50) return '#d97706';
    return '#dc2626';
}

function statusClass(status) {
    return {
        present: 'badge-green',
        absent:  'badge-red',
        late:    'badge-amber',
    }[status] || 'badge-gray';
}

function tripLabel(type) {
    return type === 'pickup' ? 'Pickup' : 'Drop';
}

import { useFormat } from '@/Composables/useFormat';
const { formatDate } = useFormat();
</script>

<template>
    <SchoolLayout title="Transport Attendance Report">
        <div class="max-w-7xl mx-auto pb-10">

            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-header-title">Attendance Report</h1>
                    <p class="page-header-sub">Bus roll call summary across routes and dates</p>
                </div>
                <Button variant="secondary" onclick="window.print()">
                    <svg class="w-4 h-4" style="margin-right:6px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Print
                </Button>
            </div>

            <!-- Filters -->
            <div class="card filter-card">
                <div class="filter-row">
                    <div class="form-field">
                        <label>From</label>
                        <input v-model="from" type="date">
                    </div>
                    <div class="form-field">
                        <label>To</label>
                        <input v-model="to" type="date">
                    </div>
                    <div class="form-field">
                        <label>Route</label>
                        <select v-model="routeId">
                            <option value="">All Routes</option>
                            <option v-for="r in routes" :key="r.id" :value="r.id">{{ r.route_name }}</option>
                        </select>
                    </div>
                    <div class="form-field" style="align-self: flex-end;">
                        <Button @click="applyFilters">Apply</Button>
                    </div>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="summary-grid">
                <div class="kpi-card">
                    <div class="kpi-icon" style="background:rgba(99,102,241,0.1);">
                        <svg class="w-6 h-6" style="color:#6366f1;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <div class="kpi-content">
                        <p class="kpi-label">Total Records</p>
                        <h3 class="kpi-value" style="color:#4338ca;">{{ summary.total_records }}</h3>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon" style="background:rgba(16,185,129,0.1);">
                        <svg class="w-6 h-6" style="color:#10b981;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="kpi-content">
                        <p class="kpi-label">Present</p>
                        <h3 class="kpi-value" style="color:#059669;">{{ summary.present }}</h3>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon" style="background:rgba(220,38,38,0.1);">
                        <svg class="w-6 h-6" style="color:#dc2626;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="kpi-content">
                        <p class="kpi-label">Absent</p>
                        <h3 class="kpi-value" style="color:#dc2626;">{{ summary.absent }}</h3>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon" :style="{ background: 'rgba(' + (attendanceRate >= 80 ? '5,150,105' : attendanceRate >= 50 ? '217,119,6' : '220,38,38') + ',0.1)' }">
                        <svg class="w-6 h-6" :style="{ color: rateColor(attendanceRate) }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div class="kpi-content">
                        <p class="kpi-label">Attendance Rate</p>
                        <h3 class="kpi-value" :style="{ color: rateColor(attendanceRate) }">{{ attendanceRate }}%</h3>
                    </div>
                </div>
            </div>

            <!-- Records Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Records</h3>
                    <span style="font-size:0.8rem;color:var(--text-muted);">
                        {{ filters.from }} to {{ filters.to }}
                        <span v-if="filters.route_id"> &bull; filtered by route</span>
                    </span>
                </div>
                <div class="card-body" style="padding:0;">
                    <div style="overflow-x:auto;">
                        <Table v-if="records.length">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Route</th>
                                    <th style="text-align:center;">Trip</th>
                                    <th style="text-align:center;">Status</th>
                                    <th style="text-align:center;">Boarded At</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in records" :key="r.id">
                                    <td style="white-space:nowrap;">{{ formatDate(r.date) }}</td>
                                    <td>
                                        <div style="font-weight:600;">{{ r.student?.user?.name || '—' }}</div>
                                        <div style="font-size:0.75rem;color:var(--text-muted);">{{ r.student?.admission_no }}</div>
                                    </td>
                                    <td style="font-size:0.85rem;">{{ r.route?.route_name || '—' }}</td>
                                    <td style="text-align:center;">
                                        <span class="badge badge-gray" style="font-size:0.7rem;">{{ tripLabel(r.trip_type) }}</span>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="badge" :class="statusClass(r.status)" style="text-transform:capitalize;">{{ r.status }}</span>
                                    </td>
                                    <td style="text-align:center;font-size:0.82rem;color:var(--text-muted);">
                                        {{ r.boarded_at || '—' }}
                                    </td>
                                    <td style="font-size:0.8rem;color:var(--text-muted);max-width:180px;white-space:normal;">
                                        {{ r.notes || '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </Table>
                        <div v-else style="text-align:center;padding:4rem;color:var(--text-muted);">
                            No attendance records found for the selected filters.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </SchoolLayout>
</template>

<style scoped>
.filter-card {
    margin-bottom: 1.5rem;
}
.filter-row {
    display: flex;
    align-items: flex-end;
    gap: 1rem;
    flex-wrap: wrap;
}
.filter-row .form-field {
    flex: 1;
    min-width: 150px;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 1024px) { .summary-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .summary-grid { grid-template-columns: 1fr; } }

.kpi-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: transform 0.2s, box-shadow 0.2s;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.kpi-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.kpi-content { flex: 1; min-width: 0; }
.kpi-label {
    font-size: 0.75rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.04em;
    color: #64748b; margin-bottom: 4px;
}
.kpi-value {
    font-size: 1.375rem; font-weight: 800;
    letter-spacing: -0.02em; line-height: 1.2;
}

@media print {
    .btn { display: none !important; }
    .filter-card { display: none !important; }
    .kpi-card { border: 2px solid #e2e8f0; break-inside: avoid; }
}
</style>
