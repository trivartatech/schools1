<script setup>
import { Link, Head } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    stats:         Object,
    lowStockItems: Array,
});

function fmt(n) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 }).format(Number(n || 0));
}
</script>

<template>
    <Head title="Stationary Dashboard" />
    <SchoolLayout title="Stationary Dashboard">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">📚 Stationary Module</h1>
                <p class="page-header-sub">Items, allocations, issuances, returns, and fee collection at a glance.</p>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Today's Collection</p>
                <p class="stat-value" style="color:#059669;">{{ fmt(stats.today_collection) }}</p>
                <p v-if="stats.today_refunds > 0" class="stat-meta" style="color:#dc2626;">− Refunds {{ fmt(stats.today_refunds) }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-label">This Month</p>
                <p class="stat-value" style="color:#059669;">{{ fmt(stats.month_collection) }}</p>
                <p v-if="stats.month_refunds > 0" class="stat-meta" style="color:#dc2626;">− Refunds {{ fmt(stats.month_refunds) }}</p>
            </div>
            <div class="stat-card" :class="{ 'stat-card--alert': parseFloat(stats.total_outstanding) > 0 }">
                <p class="stat-label">Total Outstanding</p>
                <p class="stat-value" style="color:#dc2626;">{{ fmt(stats.total_outstanding) }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-label">Active Items</p>
                <p class="stat-value">{{ stats.active_items }} <span style="font-size:0.86rem;color:#94a3b8;">/ {{ stats.item_count }}</span></p>
            </div>
        </div>

        <!-- Quick links -->
        <div class="quick-grid">
            <Link href="/school/stationary/items" class="quick-card">
                <div class="quick-card-icon" style="background:#fef3c7;color:#b45309;">📦</div>
                <div>
                    <div class="quick-card-title">Items</div>
                    <div class="quick-card-meta">{{ stats.item_count }} total</div>
                </div>
            </Link>
            <Link href="/school/stationary/allocations" class="quick-card">
                <div class="quick-card-icon" style="background:#e0e7ff;color:#4338ca;">📋</div>
                <div>
                    <div class="quick-card-title">Allocations</div>
                    <div class="quick-card-meta">{{ stats.allocations }} kits</div>
                </div>
            </Link>
            <Link href="/school/stationary/fees" class="quick-card">
                <div class="quick-card-icon" style="background:#d1fae5;color:#059669;">💰</div>
                <div>
                    <div class="quick-card-title">Fee Collection</div>
                    <div class="quick-card-meta">{{ stats.unpaid_count + stats.partial_count }} pending</div>
                </div>
            </Link>
            <Link href="/school/stationary/reports/collection-pending" class="quick-card">
                <div class="quick-card-icon" style="background:#fef3c7;color:#b45309;">📦</div>
                <div>
                    <div class="quick-card-title">Collection Pending</div>
                    <div class="quick-card-meta">{{ stats.collection_pending }} students</div>
                </div>
            </Link>
        </div>

        <!-- Low stock + Quick reports -->
        <div class="dashboard-grid">
            <div class="card section-card">
                <div class="card-header">
                    <span class="card-title">⚠️ Low Stock Alerts</span>
                    <Link href="/school/stationary/items" style="font-size:0.78rem;color:var(--accent, #6366f1);text-decoration:none;">All items →</Link>
                </div>
                <div v-if="!lowStockItems?.length" style="padding: 2rem; text-align: center; color: #9ca3af; font-size: 0.85rem;">
                    No items below minimum stock — all good. 🎉
                </div>
                <div v-else>
                    <div v-for="i in lowStockItems" :key="i.id"
                         style="padding:0.625rem 1rem;border-bottom:1px solid var(--border, #f1f5f9);display:flex;justify-content:space-between;align-items:center;font-size:0.84rem;">
                        <div>
                            <span style="font-weight:500;color:#111827;">{{ i.name }}</span>
                            <span v-if="i.code" style="margin-left:0.375rem;font-size:0.74rem;color:#94a3b8;font-family:monospace;">{{ i.code }}</span>
                        </div>
                        <div>
                            <span style="font-weight:700;color:#dc2626;">{{ i.current_stock }}</span>
                            <span style="color:#94a3b8;font-size:0.78rem;"> / min {{ i.min_stock }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card section-card">
                <div class="card-header">
                    <span class="card-title">Quick Reports</span>
                </div>
                <Link href="/school/stationary/reports/fee-defaulters" class="quick-link">
                    <span>💰 Fee Defaulters</span>
                    <span :class="['badge', (stats.unpaid_count + stats.partial_count) > 0 ? 'badge-red' : 'badge-gray']">
                        {{ stats.unpaid_count + stats.partial_count }}
                    </span>
                </Link>
                <Link href="/school/stationary/reports/collection-pending" class="quick-link">
                    <span>📦 Collection Pending</span>
                    <span :class="['badge', stats.collection_pending > 0 ? 'badge-amber' : 'badge-gray']">
                        {{ stats.collection_pending }}
                    </span>
                </Link>
                <Link href="/school/stationary/reports/returns" class="quick-link">
                    <span>↩ Returns Report</span>
                    <span class="badge badge-gray">View</span>
                </Link>
                <Link href="/school/stationary/allocations" class="quick-link">
                    <span>🆕 Create New Allocation</span>
                    <span class="badge badge-gray">→</span>
                </Link>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.875rem;
    margin-bottom: 1.25rem;
}
@media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }

.stat-card {
    background: var(--surface, #fff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 0.625rem;
    padding: 0.875rem 1rem;
}
.stat-card--alert {
    border-color: rgba(239, 68, 68, 0.4);
    background: rgba(239, 68, 68, 0.04);
}
.stat-label {
    font-size: 0.7rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 600;
    margin: 0 0 0.375rem 0;
}
.stat-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #111827;
    margin: 0;
    line-height: 1.1;
}
.stat-meta {
    font-size: 0.74rem;
    margin: 0.25rem 0 0 0;
}

.quick-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}
@media (max-width: 1024px) { .quick-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) { .quick-grid { grid-template-columns: 1fr; } }

.quick-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    background: var(--surface, #fff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 0.625rem;
    text-decoration: none;
    transition: border-color 0.15s, transform 0.15s;
}
.quick-card:hover {
    border-color: var(--accent, #6366f1);
    transform: translateY(-1px);
}
.quick-card-icon {
    width: 38px;
    height: 38px;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.quick-card-title {
    font-size: 0.86rem;
    font-weight: 600;
    color: #111827;
}
.quick-card-meta {
    font-size: 0.74rem;
    color: #94a3b8;
    margin-top: 0.125rem;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
@media (max-width: 768px) { .dashboard-grid { grid-template-columns: 1fr; } }

.section-card {
    overflow: hidden;
}

.quick-link {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border, #f1f5f9);
    text-decoration: none;
    color: #111827;
    font-size: 0.86rem;
    transition: background 0.15s;
}
.quick-link:last-child { border-bottom: 0; }
.quick-link:hover { background: #f8fafc; }

.badge {
    display: inline-block;
    padding: 0.2rem 0.625rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    min-width: 1.5rem;
    text-align: center;
}
.badge-red   { background: #fee2e2; color: #dc2626; }
.badge-amber { background: #fef3c7; color: #b45309; }
.badge-gray  { background: #f1f5f9; color: #64748b; }
</style>
