<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    stats: Object,
    expiringVehicles: Array,
    routeUtilization: Array,
    todayAttendance: Array,
});

const s = computed(() => props.stats || {});

// -- Computed stat: vehicles with expiring/expired docs
const expiringDocCount = computed(() => (props.expiringVehicles || []).length);

// -- Computed stat: today's pickup attendance (present count)
const todayPickupPresent = computed(() => {
    return (props.todayAttendance || [])
        .filter(a => a.trip_type === 'pickup' && a.status === 'present')
        .reduce((sum, a) => sum + (a.cnt || 0), 0);
});

// -- Flatten expiring vehicles into per-document rows
const expiryAlerts = computed(() => {
    const rows = [];
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    (props.expiringVehicles || []).forEach(v => {
        const docs = [
            { type: 'Insurance', date: v.insurance_expiry },
            { type: 'Fitness', date: v.fitness_expiry },
            { type: 'Pollution (PUC)', date: v.pollution_expiry },
        ];
        docs.forEach(d => {
            if (!d.date) return;
            const expDate = new Date(d.date);
            expDate.setHours(0, 0, 0, 0);
            const diffMs = expDate - today;
            const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
            if (diffDays > 30) return; // only show if within 30 days or expired
            rows.push({
                vehicleId: v.id,
                vehicleNumber: v.vehicle_number,
                vehicleName: v.vehicle_name,
                driver: v.driver,
                route: v.route,
                docType: d.type,
                expiryDate: d.date,
                daysRemaining: diffDays,
            });
        });
    });

    // Sort: expired first (most negative), then soonest
    rows.sort((a, b) => a.daysRemaining - b.daysRemaining);
    return rows;
});

function expiryBadgeClass(days) {
    if (days < 0) return 'badge-red';
    if (days <= 7) return 'badge-amber';
    return 'badge-yellow';
}

function expiryLabel(days) {
    if (days < 0) return 'Expired';
    if (days === 0) return 'Expires today';
    if (days === 1) return '1 day left';
    return days + ' days left';
}

function formatDate(dateStr) {
    if (!dateStr) return '--';
    return new Date(dateStr).toLocaleDateString('en-IN', {
        day: '2-digit', month: 'short', year: 'numeric',
    });
}

function utilizationColor(pct) {
    if (pct >= 90) return 'var(--danger, #ef4444)';
    if (pct >= 70) return 'var(--warning, #f59e0b)';
    return 'var(--success, #10b981)';
}
</script>

<template>
    <SchoolLayout title="Transport Dashboard">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Transport Dashboard</h1>
                <p class="page-header-sub">Fleet overview, alerts, and route utilization</p>
            </div>
        </div>

        <!-- ====== Stats Grid ====== -->
        <div class="stats-grid">
            <div class="stat-card">
                <p class="stat-value">{{ s.total_routes ?? '--' }}</p>
                <p class="stat-label">Total Routes</p>
            </div>
            <div class="stat-card">
                <p class="stat-value">{{ s.active_vehicles ?? '--' }}</p>
                <p class="stat-label">Active Vehicles</p>
            </div>
            <div class="stat-card">
                <p class="stat-value">{{ s.total_students ?? '--' }}</p>
                <p class="stat-label">Students Allocated</p>
            </div>
            <div class="stat-card">
                <p class="stat-value">{{ s.total_stops ?? '--' }}</p>
                <p class="stat-label">Total Stops</p>
            </div>
            <div class="stat-card" :class="{ 'stat-card--alert': expiringDocCount > 0 }">
                <p class="stat-value">{{ expiringDocCount }}</p>
                <p class="stat-label">Expiring Docs</p>
            </div>
            <div class="stat-card">
                <p class="stat-value">{{ todayPickupPresent }}</p>
                <p class="stat-label">Today's Attendance</p>
            </div>
        </div>

        <!-- ====== Document Expiry Alerts ====== -->
        <div class="card section-card" v-if="expiryAlerts.length">
            <div class="card-header">
                <h3 class="card-title">Document Expiry Alerts</h3>
                <span class="badge badge-red">{{ expiryAlerts.length }}</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div style="overflow-x: auto;">
                    <Table>
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Document Type</th>
                                <th>Expiry Date</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center;">Days Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, idx) in expiryAlerts" :key="idx">
                                <td>
                                    <div style="font-weight: 600;">{{ row.vehicleNumber }}</div>
                                    <div v-if="row.vehicleName" style="font-size: 0.75rem; color: var(--text-muted);">{{ row.vehicleName }}</div>
                                </td>
                                <td>{{ row.docType }}</td>
                                <td>{{ formatDate(row.expiryDate) }}</td>
                                <td style="text-align: center;">
                                    <span class="badge" :class="expiryBadgeClass(row.daysRemaining)">
                                        {{ row.daysRemaining < 0 ? 'Expired' : row.daysRemaining <= 7 ? 'Critical' : 'Warning' }}
                                    </span>
                                </td>
                                <td style="text-align: center; font-weight: 600;"
                                    :style="{ color: row.daysRemaining < 0 ? 'var(--danger, #ef4444)' : row.daysRemaining <= 7 ? 'var(--warning-dark, #d97706)' : 'var(--warning, #f59e0b)' }">
                                    {{ expiryLabel(row.daysRemaining) }}
                                </td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
            </div>
        </div>

        <!-- ====== Route Utilization ====== -->
        <div class="card section-card" v-if="routeUtilization && routeUtilization.length">
            <div class="card-header">
                <h3 class="card-title">Route Utilization</h3>
            </div>
            <div class="card-body">
                <div class="route-grid">
                    <div v-for="r in routeUtilization" :key="r.id" class="route-card">
                        <div class="route-card-header">
                            <div>
                                <div class="route-card-name">{{ r.route_name }}</div>
                                <div class="route-card-code">{{ r.route_code }}</div>
                            </div>
                            <span class="badge" :class="r.utilization_pct >= 90 ? 'badge-red' : r.utilization_pct >= 70 ? 'badge-amber' : 'badge-green'">
                                {{ r.utilization_pct }}%
                            </span>
                        </div>

                        <div class="route-progress-wrap">
                            <div class="route-progress-bar">
                                <div class="route-progress-fill"
                                     :style="{ width: Math.min(r.utilization_pct, 100) + '%', background: utilizationColor(r.utilization_pct) }">
                                </div>
                            </div>
                        </div>

                        <div class="route-card-stats">
                            <div class="route-stat">
                                <span class="route-stat-value">{{ r.active_students }}</span>
                                <span class="route-stat-sep">/</span>
                                <span class="route-stat-cap">{{ r.total_capacity }}</span>
                                <span class="route-stat-label">students</span>
                            </div>
                            <div class="route-stat">
                                <span class="route-stat-value">{{ r.vehicle_count }}</span>
                                <span class="route-stat-label">{{ r.vehicle_count === 1 ? 'vehicle' : 'vehicles' }}</span>
                            </div>
                        </div>

                        <div class="route-card-footer">
                            <Link :href="`/school/transport/routes`" class="route-link">
                                View Route Details
                            </Link>
                        </div>
                    </div>
                </div>

                <div v-if="!routeUtilization.length" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    No route data available.
                </div>
            </div>
        </div>

        <!-- ====== Quick Actions ====== -->
        <div class="card section-card">
            <div class="card-header">
                <h3 class="card-title">Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions-grid">
                    <Link href="/school/transport/attendance" class="qa-card">
                        <div class="qa-icon qa-icon--blue">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                        </div>
                        <span class="qa-label">Bus Roll Call</span>
                    </Link>

                    <Link href="/school/transport/routes" class="qa-card">
                        <div class="qa-icon qa-icon--emerald">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
                        </div>
                        <span class="qa-label">Route Report</span>
                    </Link>

                    <Link href="/school/transport/reports/fee-defaulters" class="qa-card">
                        <div class="qa-icon qa-icon--red">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        </div>
                        <span class="qa-label">Fee Defaulters</span>
                    </Link>

                    <Link href="/school/transport/routes" class="qa-card">
                        <div class="qa-icon qa-icon--indigo">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </div>
                        <span class="qa-label">Routes &amp; Stops</span>
                    </Link>

                    <Link href="/school/transport/vehicles" class="qa-card">
                        <div class="qa-icon qa-icon--amber">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        </div>
                        <span class="qa-label">Vehicles</span>
                    </Link>

                    <Link href="/school/transport/allocations" class="qa-card">
                        <div class="qa-icon qa-icon--violet">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                        </div>
                        <span class="qa-label">Allocations</span>
                    </Link>

                    <Link href="/school/transport/live" class="qa-card">
                        <div class="qa-icon qa-icon--teal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                        </div>
                        <span class="qa-label">Live Tracking</span>
                    </Link>
                </div>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* ── Stats Grid ─────────────────────────────────────────── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 640px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1rem;
    text-align: center;
}
.stat-card--alert {
    border-color: var(--danger, #ef4444);
    background: rgba(239, 68, 68, 0.04);
}
.stat-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0;
}
.stat-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

/* ── Section Cards ──────────────────────────────────────── */
.section-card {
    margin-bottom: 1.5rem;
}

/* ── Badge helpers ──────────────────────────────────────── */
.badge-yellow {
    background: rgba(234, 179, 8, 0.12);
    color: #a16207;
}

/* ── Route Utilization Grid ─────────────────────────────── */
.route-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}

.route-card {
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1rem;
    background: var(--bg, var(--surface));
}

.route-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.route-card-name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.route-card-code {
    font-size: 0.7rem;
    color: var(--text-muted);
    font-family: monospace;
    margin-top: 0.125rem;
}

.route-progress-wrap {
    margin-bottom: 0.75rem;
}

.route-progress-bar {
    width: 100%;
    height: 6px;
    background: var(--border);
    border-radius: 3px;
    overflow: hidden;
}

.route-progress-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.4s ease;
}

.route-card-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.route-stat {
    display: flex;
    align-items: baseline;
    gap: 0.2rem;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.route-stat-value {
    font-weight: 700;
    color: var(--text-primary);
}

.route-stat-sep {
    color: var(--text-muted);
}

.route-stat-cap {
    color: var(--text-muted);
}

.route-stat-label {
    font-size: 0.7rem;
    margin-left: 0.15rem;
}

.route-card-footer {
    padding-top: 0.5rem;
    border-top: 1px solid var(--border);
}

.route-link {
    font-size: 0.75rem;
    color: var(--accent);
    font-weight: 500;
    text-decoration: none;
}
.route-link:hover {
    text-decoration: underline;
}

/* ── Quick Actions ──────────────────────────────────────── */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 0.75rem;
}

.qa-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 0.75rem;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    text-decoration: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    cursor: pointer;
}
.qa-card:hover {
    border-color: var(--accent);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.qa-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.qa-icon svg {
    width: 18px;
    height: 18px;
}

.qa-icon--blue      { background: rgba(59, 130, 246, 0.1);  color: #3b82f6; }
.qa-icon--emerald   { background: rgba(16, 185, 129, 0.1);  color: #10b981; }
.qa-icon--red       { background: rgba(239, 68, 68, 0.1);   color: #ef4444; }
.qa-icon--indigo    { background: rgba(99, 102, 241, 0.1);  color: #6366f1; }
.qa-icon--amber     { background: rgba(245, 158, 11, 0.1);  color: #f59e0b; }
.qa-icon--violet    { background: rgba(139, 92, 246, 0.1);  color: #8b5cf6; }
.qa-icon--teal      { background: rgba(20, 184, 166, 0.1);  color: #14b8a6; }

.qa-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-primary);
    text-align: center;
    line-height: 1.3;
}
</style>
