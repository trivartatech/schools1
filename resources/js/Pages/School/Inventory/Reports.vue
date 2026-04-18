<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    deprAssets:       Array,
    maintByCategory:  Array,
    aging:            Array,
    totals:           Object,
});

const deprSort  = ref({ key: 'depreciation', dir: 'desc' });
const deprSearch = ref('');

const sortedAssets = computed(() => {
    let list = props.deprAssets ?? [];
    if (deprSearch.value) {
        const q = deprSearch.value.toLowerCase();
        list = list.filter(a => a.name.toLowerCase().includes(q) || (a.asset_code ?? '').toLowerCase().includes(q) || a.category.toLowerCase().includes(q));
    }
    return [...list].sort((a, b) => {
        const av = a[deprSort.value.key] ?? 0;
        const bv = b[deprSort.value.key] ?? 0;
        return deprSort.value.dir === 'asc' ? (av > bv ? 1 : -1) : (av < bv ? 1 : -1);
    });
});

const setSort = (key) => {
    if (deprSort.value.key === key) deprSort.value.dir = deprSort.value.dir === 'asc' ? 'desc' : 'asc';
    else deprSort.value = { key, dir: 'desc' };
};

const fmt     = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' }) : '—';
const fmtCost = (n) => n != null ? '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) : '—';
const pct     = (a, b) => b ? Math.round((a / b) * 100) : 0;

const statusColor = { available: '#10b981', assigned: '#3b82f6', under_maintenance: '#f59e0b', disposed: '#94a3b8' };
const statusLabel = { available: 'Available', assigned: 'Assigned', under_maintenance: 'Maintenance', disposed: 'Disposed' };
</script>

<template>
    <SchoolLayout title="Asset Reports">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Asset Reports</h1>
                <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Depreciation schedule, maintenance costs and asset lifecycle overview.</p>
            </div>
            <div style="display:flex;gap:8px;">
                <button class="btn-outline" onclick="window.print()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print
                </button>
                <a href="/school/inventory/export" class="btn-outline">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export CSV
                </a>
                <a href="/school/inventory" class="btn-outline">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="summary-grid">
            <div class="sum-card">
                <div class="sum-label">Total Purchase Value</div>
                <div class="sum-value">{{ fmtCost(totals.purchase_cost) }}</div>
            </div>
            <div class="sum-card">
                <div class="sum-label">Current Book Value</div>
                <div class="sum-value" style="color:#3b82f6;">{{ fmtCost(totals.current_value) }}</div>
                <div class="sum-sub">{{ pct(totals.current_value, totals.purchase_cost) }}% of original cost</div>
            </div>
            <div class="sum-card">
                <div class="sum-label">Total Depreciation</div>
                <div class="sum-value" style="color:#f59e0b;">{{ fmtCost(totals.depreciation) }}</div>
                <div class="sum-sub">{{ pct(totals.depreciation, totals.purchase_cost) }}% written down</div>
            </div>
            <div class="sum-card">
                <div class="sum-label">Total Maintenance Spend</div>
                <div class="sum-value" style="color:#dc2626;">{{ fmtCost(totals.maint_cost) }}</div>
            </div>
        </div>

        <!-- Two-column row: aging + maintenance by category -->
        <div class="two-col">

            <!-- Asset Aging -->
            <div class="card">
                <div class="card-head">Asset Age Distribution</div>
                <div style="padding:16px;">
                    <div v-for="bracket in aging" :key="bracket.label" class="aging-row">
                        <div class="aging-label">{{ bracket.label }}</div>
                        <div class="aging-bar-wrap">
                            <div class="aging-bar"
                                :style="{ width: Math.max(4, pct(bracket.count, deprAssets?.length ?? 1)) + '%' }"></div>
                        </div>
                        <div class="aging-count">{{ bracket.count }}</div>
                    </div>
                </div>
            </div>

            <!-- Maintenance by Category -->
            <div class="card">
                <div class="card-head">Maintenance Cost by Category</div>
                <div style="overflow-x:auto;">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th style="text-align:right;">Tickets</th>
                                <th style="text-align:right;">Open</th>
                                <th style="text-align:right;">Total Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in maintByCategory" :key="row.category">
                                <td>{{ row.category }}</td>
                                <td style="text-align:right;color:#475569;">{{ row.ticket_count }}</td>
                                <td style="text-align:right;">
                                    <span v-if="row.open_count > 0" style="color:#dc2626;font-weight:600;">{{ row.open_count }}</span>
                                    <span v-else style="color:#94a3b8;">0</span>
                                </td>
                                <td style="text-align:right;font-weight:600;color:#1e293b;">{{ fmtCost(row.total_cost) }}</td>
                            </tr>
                            <tr v-if="!maintByCategory?.length">
                                <td colspan="4" style="text-align:center;color:#94a3b8;padding:24px;">No maintenance records yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Depreciation Schedule -->
        <div class="card" style="margin-top:0;">
            <div class="card-head" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <span>Depreciation Schedule</span>
                <div class="search-wrap" style="flex:1;max-width:280px;">
                    <svg class="search-icon" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input v-model="deprSearch" class="search-input" placeholder="Filter assets…" />
                </div>
                <span style="font-size:.75rem;color:#94a3b8;margin-left:auto;">{{ sortedAssets.length }} assets</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>
                                <button class="sort-btn" @click="setSort('name')">
                                    Asset
                                    <svg v-if="deprSort.key === 'name'" width="10" height="10" fill="currentColor" viewBox="0 0 24 24">
                                        <path :d="deprSort.dir === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'"/>
                                    </svg>
                                </button>
                            </th>
                            <th>
                                <button class="sort-btn" @click="setSort('category')">Category</button>
                            </th>
                            <th>
                                <button class="sort-btn" @click="setSort('purchase_date')">
                                    Purchase Date
                                    <svg v-if="deprSort.key === 'purchase_date'" width="10" height="10" fill="currentColor" viewBox="0 0 24 24">
                                        <path :d="deprSort.dir === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'"/>
                                    </svg>
                                </button>
                            </th>
                            <th style="text-align:right;">
                                <button class="sort-btn" @click="setSort('purchase_cost')">
                                    Cost
                                    <svg v-if="deprSort.key === 'purchase_cost'" width="10" height="10" fill="currentColor" viewBox="0 0 24 24">
                                        <path :d="deprSort.dir === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'"/>
                                    </svg>
                                </button>
                            </th>
                            <th style="text-align:right;">Life (yrs)</th>
                            <th style="text-align:right;">
                                <button class="sort-btn" @click="setSort('current_value')">
                                    Book Value
                                    <svg v-if="deprSort.key === 'current_value'" width="10" height="10" fill="currentColor" viewBox="0 0 24 24">
                                        <path :d="deprSort.dir === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'"/>
                                    </svg>
                                </button>
                            </th>
                            <th style="text-align:right;">
                                <button class="sort-btn" @click="setSort('depreciation')">
                                    Depreciation
                                    <svg v-if="deprSort.key === 'depreciation'" width="10" height="10" fill="currentColor" viewBox="0 0 24 24">
                                        <path :d="deprSort.dir === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'"/>
                                    </svg>
                                </button>
                            </th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in sortedAssets" :key="a.id">
                            <td>
                                <div style="font-weight:600;font-size:.875rem;color:#1e293b;">{{ a.name }}</div>
                                <div v-if="a.asset_code" style="font-size:.72rem;color:#94a3b8;">{{ a.asset_code }}</div>
                            </td>
                            <td>
                                <span class="cat-badge">{{ a.category }}</span>
                            </td>
                            <td style="white-space:nowrap;color:#475569;">{{ fmt(a.purchase_date) }}</td>
                            <td style="text-align:right;font-weight:600;">{{ fmtCost(a.purchase_cost) }}</td>
                            <td style="text-align:right;color:#64748b;">{{ a.useful_life }}</td>
                            <td style="text-align:right;">
                                <span :style="{ color: a.current_value <= 0 ? '#94a3b8' : '#3b82f6', fontWeight: 600 }">
                                    {{ fmtCost(a.current_value) }}
                                </span>
                                <!-- Remaining value bar -->
                                <div class="value-bar-wrap" v-if="a.purchase_cost > 0">
                                    <div class="value-bar"
                                        :style="{ width: pct(a.current_value, a.purchase_cost) + '%', background: a.current_value <= 0 ? '#e2e8f0' : '#3b82f6' }">
                                    </div>
                                </div>
                            </td>
                            <td style="text-align:right;color:#f59e0b;font-weight:600;">
                                {{ fmtCost(a.depreciation) }}
                                <div v-if="a.purchase_cost > 0" style="font-size:.68rem;color:#94a3b8;font-weight:400;">
                                    {{ pct(a.depreciation, a.purchase_cost) }}% written down
                                </div>
                            </td>
                            <td>
                                <span class="status-pill"
                                    :style="{ background: statusColor[a.status] + '1a', color: statusColor[a.status], border: '1px solid ' + statusColor[a.status] + '40' }">
                                    {{ statusLabel[a.status] ?? a.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!sortedAssets.length">
                            <td colspan="8" style="text-align:center;color:#94a3b8;padding:32px;">No assets found.</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="sortedAssets.length">
                        <tr style="background:#f8fafc;font-weight:700;border-top:2px solid #e2e8f0;">
                            <td colspan="3" style="padding:10px 16px;font-size:.8rem;color:#64748b;">
                                Totals ({{ sortedAssets.length }} assets)
                            </td>
                            <td style="text-align:right;padding:10px 16px;">{{ fmtCost(sortedAssets.reduce((s,a) => s + a.purchase_cost, 0)) }}</td>
                            <td></td>
                            <td style="text-align:right;padding:10px 16px;color:#3b82f6;">{{ fmtCost(sortedAssets.reduce((s,a) => s + a.current_value, 0)) }}</td>
                            <td style="text-align:right;padding:10px 16px;color:#f59e0b;">{{ fmtCost(sortedAssets.reduce((s,a) => s + a.depreciation, 0)) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
.btn-outline {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: #fff; color: #374151;
    border: 1px solid #d1d5db; border-radius: 8px; font-size: .875rem; font-weight: 500;
    cursor: pointer; transition: background .15s; text-decoration: none;
}
.btn-outline:hover { background: #f9fafb; }

/* ── Summary cards ──────────────────────────────────────────────────────── */
.summary-grid {
    display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 20px;
}
@media (max-width:900px) { .summary-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width:500px) { .summary-grid { grid-template-columns: 1fr; } }
.sum-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,.05);
}
.sum-label { font-size: .7rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .06em; }
.sum-value { font-size: 1.6rem; font-weight: 800; color: #1e293b; line-height: 1.2; margin-top: 4px; }
.sum-sub   { font-size: .72rem; color: #94a3b8; margin-top: 2px; }

/* ── Two column ─────────────────────────────────────────────────────────── */
.two-col {
    display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;
}
@media (max-width:768px) { .two-col { grid-template-columns: 1fr; } }

/* ── Card ───────────────────────────────────────────────────────────────── */
.card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.05);
}
.card-head {
    padding: 14px 20px; font-size: .875rem; font-weight: 700; color: #1e293b;
    border-bottom: 1px solid #f1f5f9; background: #f8fafc;
    display: flex; align-items: center;
}

/* ── Aging chart ────────────────────────────────────────────────────────── */
.aging-row {
    display: flex; align-items: center; gap: 12px; margin-bottom: 10px;
}
.aging-label { font-size: .8rem; color: #475569; width: 80px; flex-shrink: 0; }
.aging-bar-wrap { flex: 1; background: #f1f5f9; border-radius: 4px; height: 8px; overflow: hidden; }
.aging-bar { height: 100%; background: #3b82f6; border-radius: 4px; transition: width .3s; min-width: 4px; }
.aging-count { font-size: .8rem; font-weight: 700; color: #1e293b; width: 30px; text-align: right; }

/* ── Report table ───────────────────────────────────────────────────────── */
.rpt-table {
    width: 100%; border-collapse: collapse;
}
.rpt-table th {
    padding: 10px 16px; text-align: left;
    font-size: .7rem; font-weight: 700; color: #64748b;
    text-transform: uppercase; letter-spacing: .05em;
    background: #f8fafc; border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.rpt-table td {
    padding: 11px 16px; border-bottom: 1px solid #f1f5f9;
    font-size: .85rem; vertical-align: middle;
}
.rpt-table tr:last-child td { border-bottom: none; }
.rpt-table tr:hover td { background: #fafbff; }

.cat-badge {
    font-size: .75rem; font-weight: 500; color: #6d28d9;
    background: #ede9fe; padding: 2px 8px; border-radius: 20px;
}
.status-pill {
    font-size: .72rem; font-weight: 600;
    padding: 3px 10px; border-radius: 20px; white-space: nowrap;
}
.sort-btn {
    background: none; border: none; cursor: pointer; display: inline-flex;
    align-items: center; gap: 4px; font-size: .7rem; font-weight: 700;
    color: #64748b; text-transform: uppercase; letter-spacing: .05em;
    padding: 0; transition: color .15s;
}
.sort-btn:hover { color: #3b82f6; }

/* ── Value bar ──────────────────────────────────────────────────────────── */
.value-bar-wrap {
    width: 80px; height: 4px; background: #f1f5f9; border-radius: 2px;
    overflow: hidden; margin-top: 4px; margin-left: auto;
}
.value-bar { height: 100%; border-radius: 2px; transition: width .3s; }

/* ── Search ─────────────────────────────────────────────────────────────── */
.search-wrap { position: relative; }
.search-icon {
    position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
    color: #94a3b8; pointer-events: none;
}
.search-input {
    width: 100%; padding: 7px 10px 7px 30px;
    border: 1px solid #e2e8f0; border-radius: 7px;
    font-size: .8rem; color: #1e293b; background: #fff;
    outline: none; transition: border-color .15s;
    box-sizing: border-box;
}
.search-input:focus { border-color: #3b82f6; }

/* ── Print ──────────────────────────────────────────────────────────────── */
@media print {
    .page-header, .search-wrap, .sort-btn svg, .aging-bar-wrap { display: none !important; }
    .summary-grid { grid-template-columns: repeat(4, 1fr); }
    .two-col { grid-template-columns: 1fr 1fr; page-break-inside: avoid; }
    .card { border: 1px solid #ccc; box-shadow: none; page-break-inside: avoid; }
    .rpt-table thead { background: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .rpt-table tfoot { background: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .value-bar-wrap, .value-bar { display: none; }
    body { font-size: 11px; }
    @page { margin: 18mm; size: A4 landscape; }
}
</style>
