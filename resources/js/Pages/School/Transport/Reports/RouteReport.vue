<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import PrintButton from '@/Components/ui/PrintButton.vue';
import { ref, computed } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    routes: Array,
});

const expandedRoutes = ref([]);

function toggleRoute(routeId) {
    const idx = expandedRoutes.value.indexOf(routeId);
    if (idx === -1) {
        expandedRoutes.value.push(routeId);
    } else {
        expandedRoutes.value.splice(idx, 1);
    }
}

function isExpanded(routeId) {
    return expandedRoutes.value.includes(routeId);
}

// Summary computations
const totalRoutes = computed(() => props.routes.length);

const totalStudents = computed(() =>
    props.routes.reduce((sum, r) => sum + (r.total_students || 0), 0)
);

const overallUtilization = computed(() => {
    const totalCapacity = props.routes.reduce((sum, r) => sum + (r.total_capacity || 0), 0);
    const totalStudentsVal = totalStudents.value;
    if (totalCapacity === 0) return 0;
    return Math.round((totalStudentsVal / totalCapacity) * 100);
});

const totalRevenue = computed(() =>
    props.routes.reduce((sum, r) => sum + (r.total_fee_revenue || 0), 0)
);

// Utilization helpers
function utilizationColor(pct) {
    if (pct > 70) return 'utilization-green';
    if (pct >= 30) return 'utilization-amber';
    return 'utilization-red';
}

function utilizationBarBg(pct) {
    if (pct > 70) return '#059669';
    if (pct >= 30) return '#d97706';
    return '#dc2626';
}

function isUnderUtilized(pct) {
    return pct < 30;
}

function isOverUtilized(pct) {
    return pct > 90;
}

// Vehicle status badge
function vehicleStatusClass(status) {
    return ({
        active:      'badge-green',
        inactive:    'badge-gray',
        maintenance: 'badge-amber',
    })[status] || 'badge-gray';
}

// Currency formatter
const formatCurrency = (value) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(value || 0);
</script>

<template>
    <SchoolLayout title="Route Report">
        <div class="max-w-7xl mx-auto pb-10">

            <!-- Page Header -->
            <PageHeader title="Route Optimization Report" subtitle="Analyze route utilization, capacity, and revenue">
                <template #actions>
                    <PrintButton label="Print Report" />
                </template>
            </PageHeader>

            <!-- Summary Stats -->
            <div class="summary-grid">
                <div class="kpi-card">
                    <div class="kpi-icon" style="background:rgba(99,102,241,0.1);">
                        <svg class="w-6 h-6" style="color:#6366f1;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    </div>
                    <div class="kpi-content">
                        <p class="kpi-label">Total Routes</p>
                        <h3 class="kpi-value" style="color:#4338ca;">{{ totalRoutes }}</h3>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon" style="background:rgba(16,185,129,0.1);">
                        <svg class="w-6 h-6" style="color:#10b981;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </div>
                    <div class="kpi-content">
                        <p class="kpi-label">Total Students</p>
                        <h3 class="kpi-value" style="color:#059669;">{{ totalStudents }}</h3>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon" :style="{ background: overallUtilization > 70 ? 'rgba(5,150,105,0.1)' : overallUtilization >= 30 ? 'rgba(217,119,6,0.1)' : 'rgba(220,38,38,0.1)' }">
                        <svg class="w-6 h-6" :style="{ color: overallUtilization > 70 ? '#059669' : overallUtilization >= 30 ? '#d97706' : '#dc2626' }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <div class="kpi-content">
                        <p class="kpi-label">Overall Utilization</p>
                        <h3 class="kpi-value" :style="{ color: overallUtilization > 70 ? '#059669' : overallUtilization >= 30 ? '#d97706' : '#dc2626' }">{{ overallUtilization }}%</h3>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon" style="background:rgba(99,102,241,0.1);">
                        <svg class="w-6 h-6" style="color:#6366f1;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="kpi-content">
                        <p class="kpi-label">Total Revenue</p>
                        <h3 class="kpi-value" style="color:#4338ca;">{{ formatCurrency(totalRevenue) }}</h3>
                    </div>
                </div>
            </div>

            <!-- Per-Route Expandable Cards -->
            <div v-if="routes.length" class="routes-list">
                <div v-for="r in routes" :key="r.id" class="card route-card">
                    <!-- Card Header (always visible) -->
                    <div class="card-header route-card-header" @click="toggleRoute(r.id)" style="cursor:pointer;">
                        <div class="route-header-left">
                            <button class="expand-toggle" :class="{ 'is-expanded': isExpanded(r.id) }">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                            <div>
                                <div class="route-name-row">
                                    <span class="route-name">{{ r.route_name }}</span>
                                    <span class="route-code">{{ r.route_code }}</span>
                                    <span v-if="isUnderUtilized(r.utilization_pct)" class="badge badge-red">Under-utilized</span>
                                    <span v-if="isOverUtilized(r.utilization_pct)" class="badge badge-amber">Over-utilized</span>
                                </div>
                                <div class="route-meta">
                                    <span v-if="r.distance">{{ r.distance }} km</span>
                                    <span v-if="r.estimated_time">{{ r.estimated_time }}</span>
                                    <span :class="r.status === 'active' ? 'badge badge-green' : 'badge badge-gray'" style="text-transform:capitalize;font-size:0.65rem;">{{ r.status }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="route-header-right">
                            <div class="utilization-section">
                                <div class="utilization-text">
                                    <span class="students-count">{{ r.total_students }} / {{ r.total_capacity }}</span>
                                    <span class="utilization-pct" :class="utilizationColor(r.utilization_pct)">{{ r.utilization_pct }}%</span>
                                </div>
                                <div class="utilization-bar-track">
                                    <div
                                        class="utilization-bar-fill"
                                        :style="{ width: Math.min(r.utilization_pct, 100) + '%', background: utilizationBarBg(r.utilization_pct) }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Expanded Detail -->
                    <div v-if="isExpanded(r.id)" class="card-body route-detail">
                        <!-- Vehicles Table -->
                        <div class="detail-section">
                            <h4 class="detail-section-title">Vehicles</h4>
                            <div class="overflow-x-auto">
                                <Table v-if="r.vehicles && r.vehicles.length">
                                    <thead>
                                        <tr>
                                            <th>Number</th>
                                            <th>Name</th>
                                            <th style="text-align:center;">Capacity</th>
                                            <th style="text-align:center;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="v in r.vehicles" :key="v.id">
                                            <td style="font-family:monospace;font-weight:600;color:var(--accent);">{{ v.vehicle_number }}</td>
                                            <td>{{ v.vehicle_name || '--' }}</td>
                                            <td style="text-align:center;">{{ v.capacity }}</td>
                                            <td style="text-align:center;">
                                                <span class="badge" :class="vehicleStatusClass(v.status)" style="text-transform:capitalize;">{{ v.status }}</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </Table>
                                <p v-else class="empty-message">No vehicles assigned to this route.</p>
                            </div>
                        </div>

                        <!-- Stops Table -->
                        <div class="detail-section">
                            <h4 class="detail-section-title">Stops Breakdown</h4>
                            <div class="overflow-x-auto">
                                <Table v-if="r.stops && r.stops.length">
                                    <thead>
                                        <tr>
                                            <th style="width:50px;text-align:center;">#</th>
                                            <th>Stop Name</th>
                                            <th style="text-align:center;">Students</th>
                                            <th style="text-align:right;">Fee</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="stop in r.stops" :key="stop.id">
                                            <td style="text-align:center;color:#94a3b8;">{{ stop.stop_order + 1 }}</td>
                                            <td>
                                                <span style="font-weight:500;color:#111827;">{{ stop.stop_name }}</span>
                                                <span v-if="stop.stop_code" style="margin-left:6px;font-size:0.72rem;color:#94a3b8;font-family:monospace;">{{ stop.stop_code }}</span>
                                            </td>
                                            <td style="text-align:center;font-weight:600;">{{ stop.student_count || 0 }}</td>
                                            <td style="text-align:right;font-weight:600;color:var(--success);">
                                                {{ stop.fee > 0 ? formatCurrency(stop.fee) : '--' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </Table>
                                <p v-else class="empty-message">No stops defined for this route.</p>
                            </div>
                        </div>

                        <!-- Revenue -->
                        <div class="detail-section revenue-section">
                            <div class="revenue-row">
                                <span class="revenue-label">Total Fee Revenue</span>
                                <span class="revenue-value">{{ formatCurrency(r.total_fee_revenue) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="card">
                <div class="card-body" style="text-align:center;padding:4rem 0;color:#9ca3af;">
                    <svg class="w-12 h-12" style="margin:0 auto 0.75rem;color:#e5e7eb;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    <p style="font-size:0.875rem;">No transport routes found. Create routes to view the optimization report.</p>
                </div>
            </div>

        </div>
    </SchoolLayout>
</template>

<style scoped>
/* Summary Grid */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.kpi-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: transform 0.2s, box-shadow 0.2s;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}
.kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.kpi-content {
    flex: 1;
    min-width: 0;
}
.kpi-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    margin-bottom: 4px;
}
.kpi-value {
    font-size: 1.375rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Routes List */
.routes-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.route-card {
    overflow: hidden;
}

.route-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    user-select: none;
}
.route-card-header:hover {
    background: #fafbff;
}

.route-header-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
    min-width: 0;
}

.expand-toggle {
    background: none;
    border: none;
    padding: 4px;
    cursor: pointer;
    color: #94a3b8;
    transition: transform 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.expand-toggle.is-expanded {
    transform: rotate(90deg);
}

.route-name-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.route-name {
    font-weight: 700;
    color: #111827;
    font-size: 0.92rem;
}
.route-code {
    font-family: monospace;
    font-size: 0.78rem;
    color: var(--accent, #6366f1);
    font-weight: 600;
    background: rgba(99, 102, 241, 0.08);
    padding: 1px 7px;
    border-radius: 4px;
}
.route-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 3px;
    font-size: 0.75rem;
    color: #94a3b8;
}

.route-header-right {
    flex-shrink: 0;
    min-width: 180px;
}

/* Utilization Bar */
.utilization-section {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.utilization-text {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}
.students-count {
    font-size: 0.8rem;
    color: #64748b;
    font-weight: 500;
}
.utilization-pct {
    font-size: 0.82rem;
    font-weight: 700;
}
.utilization-green { color: #059669; }
.utilization-amber { color: #d97706; }
.utilization-red   { color: #dc2626; }

.utilization-bar-track {
    width: 100%;
    height: 6px;
    background: #f1f5f9;
    border-radius: 3px;
    overflow: hidden;
}
.utilization-bar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
}

/* Expanded Detail */
.route-detail {
    border-top: 1px solid #f1f5f9;
    padding-top: 0;
}

.detail-section {
    margin-bottom: 1.25rem;
}
.detail-section:last-child {
    margin-bottom: 0;
}

.detail-section-title {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    margin-bottom: 0.5rem;
    padding-bottom: 0.35rem;
    border-bottom: 1px solid #f1f5f9;
}

.overflow-x-auto {
    overflow-x: auto;
}

.empty-message {
    text-align: center;
    color: #94a3b8;
    font-size: 0.84rem;
    padding: 1.25rem 0;
}

/* Revenue Section */
.revenue-section {
    background: #f8fafc;
    border-radius: 8px;
    padding: 14px 18px;
}
.revenue-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.revenue-label {
    font-size: 0.84rem;
    font-weight: 600;
    color: #374151;
}
.revenue-value {
    font-size: 1.1rem;
    font-weight: 800;
    color: #4338ca;
    font-family: 'Courier New', monospace;
}

/* Responsive */
@media (max-width: 1024px) {
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 640px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
    .route-card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .route-header-right {
        width: 100%;
        min-width: 0;
    }
}

/* Print */
@media print {
    .btn { display: none !important; }
    .kpi-card { border: 2px solid #e2e8f0; break-inside: avoid; }
    .route-card { break-inside: avoid; }
}
</style>
