<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    asset:    Object,
    auditLog: Array,
});

const activeTab = ref('overview');

// ── Resolve maintenance from detail page ──────────────────────────────────
const showResolve   = ref(false);
const resolveTarget = ref(null);
const resolveForm   = useForm({ resolution_notes: '', cost: '', vendor: '' });
const openResolve   = (log) => { resolveTarget.value = log; showResolve.value = true; };
const submitResolve = () => resolveForm.patch(`/school/inventory/maintenance/${resolveTarget.value.id}/resolve`, {
    preserveScroll: true,
    onSuccess: () => { showResolve.value = false; resolveForm.reset(); },
});

const markInProgress = (logId) => {
    useForm({}).patch(`/school/inventory/maintenance/${logId}/progress`, { preserveScroll: true });
};

// ── Helpers ────────────────────────────────────────────────────────────────
const statusBadge  = { available: '#10b981', assigned: '#3b82f6', under_maintenance: '#f59e0b', disposed: '#94a3b8' };
const statusLabel  = { available: 'Available', assigned: 'Assigned', under_maintenance: 'Under Maintenance', disposed: 'Disposed' };
const conditionDot = { excellent: '#10b981', good: '#3b82f6', fair: '#f59e0b', poor: '#ef4444', condemned: '#6b7280' };
const maintStatusColor = { open: '#ef4444', in_progress: '#f59e0b', resolved: '#10b981', scrapped: '#94a3b8' };
const maintStatusLabel = { open: 'Open', in_progress: 'In Progress', resolved: 'Resolved', scrapped: 'Scrapped' };

import { useFormat } from '@/Composables/useFormat';
const { formatDate: fmt } = useFormat();
const fmtCost = (n) => n != null && n !== '' ? '₹' + Number(n).toLocaleString('en-IN') : '—';
const pct     = (a, b) => (b && b > 0) ? Math.round((a / b) * 100) : 0;

const warrantyStatus = computed(() => {
    if (!props.asset.warranty_until) return null;
    const d    = new Date(props.asset.warranty_until);
    const now  = new Date();
    const in30 = new Date(); in30.setDate(in30.getDate() + 30);
    if (d < now)   return 'expired';
    if (d <= in30) return 'expiring';
    return 'ok';
});

const totalMaintCost = computed(() =>
    (props.asset.maintenance_logs ?? []).reduce((s, l) => s + parseFloat(l.cost || 0), 0)
);

const deprPct = computed(() => pct(
    props.asset.current_value,
    parseFloat(props.asset.purchase_cost || 0)
));

const auditEventColor = { created: '#10b981', updated: '#3b82f6', deleted: '#dc2626' };

const formatChanges = (changes) => {
    if (!changes?.attributes) return [];
    const old = changes.old ?? {};
    const cur = changes.attributes;
    return Object.keys(cur)
        .filter(k => !['updated_at', 'created_at'].includes(k) && old[k] !== cur[k])
        .map(k => ({ field: k.replace(/_/g, ' '), from: old[k] ?? '—', to: cur[k] ?? '—' }));
};
</script>

<template>
    <SchoolLayout :title="asset.name">

        <!-- Breadcrumb -->
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;font-size:.82rem;color:#94a3b8;">
            <a href="/school/inventory" style="color:#64748b;text-decoration:none;font-weight:500;">Inventory</a>
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span style="color:#1e293b;">{{ asset.name }}</span>
        </div>

        <!-- Asset Header card -->
        <div class="header-card">
            <div class="header-left">
                <div class="asset-icon">
                    <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 10V7"/></svg>
                </div>
                <div>
                    <h1 class="asset-title">{{ asset.name }}</h1>
                    <div class="asset-sub-row">
                        <span v-if="asset.asset_code" class="meta-chip">{{ asset.asset_code }}</span>
                        <span v-if="asset.brand" class="meta-chip">{{ asset.brand }}</span>
                        <span v-if="asset.serial_no" class="meta-chip">S/N {{ asset.serial_no }}</span>
                        <span class="cat-badge">{{ asset.category?.name }}</span>
                    </div>
                </div>
            </div>
            <div class="header-right">
                <span class="status-pill" :style="{ background: statusBadge[asset.status] + '1a', color: statusBadge[asset.status], border: '1px solid ' + statusBadge[asset.status] + '40' }">
                    {{ statusLabel[asset.status] ?? asset.status }}
                </span>
                <span style="display:inline-flex;align-items:center;gap:5px;font-size:.82rem;color:#475569;text-transform:capitalize;">
                    <span class="condition-dot" :style="{ background: conditionDot[asset.condition] }"></span>
                    {{ asset.condition }}
                </span>
                <a :href="`/school/inventory?status=${asset.status}`" class="btn-outline" style="font-size:.8rem;padding:6px 12px;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
            </div>
        </div>

        <!-- Key metrics row -->
        <div class="metrics-row">
            <div class="metric-card">
                <div class="metric-label">Purchase Cost</div>
                <div class="metric-value">{{ fmtCost(asset.purchase_cost) }}</div>
                <div class="metric-sub">{{ fmt(asset.purchase_date) }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Current Book Value</div>
                <div class="metric-value" style="color:#3b82f6;">{{ fmtCost(asset.current_value) }}</div>
                <div class="metric-sub">
                    <span style="text-transform:capitalize;">{{ (asset.depreciation_method ?? 'straight_line').replace('_', ' ') }}</span>
                    · {{ deprPct }}% remaining
                </div>
                <div v-if="asset.purchase_cost > 0" class="value-bar-wrap">
                    <div class="value-bar" :style="{ width: deprPct + '%' }"></div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Total Maintenance Cost</div>
                <div class="metric-value" :style="{ color: totalMaintCost > 0 ? '#dc2626' : '#94a3b8' }">
                    {{ totalMaintCost > 0 ? fmtCost(totalMaintCost) : '—' }}
                </div>
                <div class="metric-sub">{{ asset.maintenance_logs?.length ?? 0 }} maintenance record(s)</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Warranty</div>
                <div class="metric-value" :style="{ color: warrantyStatus === 'expired' ? '#dc2626' : warrantyStatus === 'expiring' ? '#d97706' : '#10b981', fontSize: '1rem' }">
                    {{ asset.warranty_until ? fmt(asset.warranty_until) : '—' }}
                </div>
                <div class="metric-sub">
                    <span v-if="warrantyStatus === 'expired'" style="color:#dc2626;">Expired</span>
                    <span v-else-if="warrantyStatus === 'expiring'" style="color:#d97706;">Expiring soon</span>
                    <span v-else-if="warrantyStatus === 'ok'" style="color:#10b981;">Active</span>
                    <span v-else>No warranty</span>
                </div>
            </div>
        </div>

        <!-- Current assignment banner -->
        <div v-if="asset.active_assignment" class="assign-banner">
            <svg width="16" height="16" fill="none" stroke="#2563eb" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span>Currently assigned to <strong>{{ asset.active_assignment.location }}</strong>
                <span v-if="asset.active_assignment.assignee_name"> · {{ asset.active_assignment.assignee_name }}</span>
                since {{ fmt(asset.active_assignment.assigned_on) }}
            </span>
        </div>

        <!-- Disposal info -->
        <div v-if="asset.status === 'disposed'" class="dispose-banner">
            <svg width="16" height="16" fill="none" stroke="#dc2626" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            <span>Disposed on <strong>{{ fmt(asset.disposed_on) }}</strong>
                <span v-if="asset.disposal_reason"> · {{ asset.disposal_reason }}</span>
            </span>
        </div>

        <!-- Tabs -->
        <div class="tabs-bar">
            <button class="tab-btn" :class="{ active: activeTab === 'overview' }"     @click="activeTab = 'overview'">Overview</button>
            <button class="tab-btn" :class="{ active: activeTab === 'assignments' }"  @click="activeTab = 'assignments'">
                Assignments <span class="tab-count">{{ asset.assignments?.length ?? 0 }}</span>
            </button>
            <button class="tab-btn" :class="{ active: activeTab === 'maintenance' }"  @click="activeTab = 'maintenance'">
                Maintenance <span class="tab-count" :style="{ background: (asset.maintenance_logs?.filter(m => ['open','in_progress'].includes(m.status)).length ?? 0) > 0 ? '#fee2e2' : '', color: (asset.maintenance_logs?.filter(m => ['open','in_progress'].includes(m.status)).length ?? 0) > 0 ? '#dc2626' : '' }">{{ asset.maintenance_logs?.length ?? 0 }}</span>
            </button>
            <button class="tab-btn" :class="{ active: activeTab === 'audit' }"        @click="activeTab = 'audit'">
                Audit Log <span class="tab-count">{{ auditLog?.length ?? 0 }}</span>
            </button>
        </div>

        <!-- Tab: Overview -->
        <div v-if="activeTab === 'overview'" class="card">
            <div class="detail-grid">
                <div class="detail-row"><span class="detail-label">Asset Name</span><span class="detail-val">{{ asset.name }}</span></div>
                <div class="detail-row"><span class="detail-label">Asset Code</span><span class="detail-val">{{ asset.asset_code || '—' }}</span></div>
                <div class="detail-row"><span class="detail-label">Category</span><span class="detail-val"><span class="cat-badge">{{ asset.category?.name ?? '—' }}</span></span></div>
                <div class="detail-row"><span class="detail-label">Brand</span><span class="detail-val">{{ asset.brand || '—' }}</span></div>
                <div class="detail-row"><span class="detail-label">Model</span><span class="detail-val">{{ asset.model_no || '—' }}</span></div>
                <div class="detail-row"><span class="detail-label">Serial No</span><span class="detail-val">{{ asset.serial_no || '—' }}</span></div>
                <div class="detail-row"><span class="detail-label">Supplier</span><span class="detail-val">{{ asset.supplier || '—' }}</span></div>
                <div class="detail-row"><span class="detail-label">Purchase Date</span><span class="detail-val">{{ fmt(asset.purchase_date) }}</span></div>
                <div class="detail-row"><span class="detail-label">Purchase Cost</span><span class="detail-val">{{ fmtCost(asset.purchase_cost) }}</span></div>
                <div class="detail-row"><span class="detail-label">Useful Life</span><span class="detail-val">{{ asset.useful_life_years }} years</span></div>
                <div class="detail-row"><span class="detail-label">Depreciation</span><span class="detail-val" style="text-transform:capitalize;">{{ (asset.depreciation_method ?? 'straight_line').replace('_', ' ') }}</span></div>
                <div class="detail-row"><span class="detail-label">Current Book Value</span><span class="detail-val" style="color:#3b82f6;font-weight:700;">{{ fmtCost(asset.current_value) }}</span></div>
                <div class="detail-row"><span class="detail-label">Condition</span>
                    <span class="detail-val" style="display:inline-flex;align-items:center;gap:5px;text-transform:capitalize;">
                        <span class="condition-dot" :style="{ background: conditionDot[asset.condition] }"></span>{{ asset.condition }}
                    </span>
                </div>
                <div class="detail-row"><span class="detail-label">Status</span>
                    <span class="detail-val">
                        <span class="status-pill" :style="{ background: statusBadge[asset.status] + '1a', color: statusBadge[asset.status], border: '1px solid ' + statusBadge[asset.status] + '40' }">{{ statusLabel[asset.status] }}</span>
                    </span>
                </div>
                <div v-if="asset.notes" class="detail-row full"><span class="detail-label">Notes</span><span class="detail-val">{{ asset.notes }}</span></div>
            </div>
        </div>

        <!-- Tab: Assignments -->
        <div v-if="activeTab === 'assignments'" class="card" style="overflow:hidden;">
            <div style="overflow-x:auto;">
                <table class="det-table">
                    <thead>
                        <tr>
                            <th>Assigned On</th><th>Assign Type</th><th>Assignee</th>
                            <th>Location</th><th>Returned</th><th>By</th><th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="asgn in asset.assignments" :key="asgn.id">
                            <td style="white-space:nowrap;">{{ fmt(asgn.assigned_on) }}</td>
                            <td style="text-transform:capitalize;">{{ asgn.assignee_type || 'general' }}</td>
                            <td>{{ asgn.assignee_name || '—' }}</td>
                            <td>{{ asgn.location }}</td>
                            <td>
                                <span v-if="asgn.returned_on" style="color:#10b981;">{{ fmt(asgn.returned_on) }}</span>
                                <span v-else class="status-pill" style="background:#dbeafe1a;color:#2563eb;border:1px solid #2563eb40;">Active</span>
                            </td>
                            <td style="font-size:.78rem;color:#64748b;">{{ asgn.assigned_by?.name ?? '—' }}</td>
                            <td style="font-size:.78rem;color:#64748b;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ asgn.notes || '—' }}</td>
                        </tr>
                        <tr v-if="!asset.assignments?.length">
                            <td colspan="7" class="empty-cell">No assignment history.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Maintenance -->
        <div v-if="activeTab === 'maintenance'" class="card" style="overflow:hidden;">
            <div style="overflow-x:auto;">
                <table class="det-table">
                    <thead>
                        <tr>
                            <th>Reported</th><th>Type</th><th>Description</th>
                            <th>Status</th><th>Cost</th><th>Vendor</th><th>Resolved</th><th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in asset.maintenance_logs" :key="log.id">
                            <td style="white-space:nowrap;">{{ fmt(log.reported_on) }}</td>
                            <td style="text-transform:capitalize;">{{ log.type }}</td>
                            <td>{{ log.issue_description }}</td>
                            <td>
                                <span class="maint-status-pill"
                                    :style="{ background: maintStatusColor[log.status] + '1a', color: maintStatusColor[log.status], border: '1px solid ' + maintStatusColor[log.status] + '40' }">
                                    {{ maintStatusLabel[log.status] }}
                                </span>
                            </td>
                            <td>{{ log.cost > 0 ? fmtCost(log.cost) : '—' }}</td>
                            <td style="font-size:.78rem;color:#64748b;">{{ log.vendor || '—' }}</td>
                            <td style="font-size:.78rem;color:#64748b;">
                                {{ fmt(log.resolved_on) }}
                                <div v-if="log.resolution_notes" style="font-style:italic;color:#94a3b8;font-size:.72rem;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" :title="log.resolution_notes">
                                    {{ log.resolution_notes }}
                                </div>
                            </td>
                            <td>
                                <div style="display:flex;gap:5px;justify-content:flex-end;">
                                    <button v-if="log.status === 'open'" class="act-btn act-amber" @click="markInProgress(log.id)">In Progress</button>
                                    <button v-if="['open','in_progress'].includes(log.status)" class="act-btn act-green" @click="openResolve(log)">Resolve</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!asset.maintenance_logs?.length">
                            <td colspan="8" class="empty-cell">No maintenance records.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Audit Log -->
        <div v-if="activeTab === 'audit'" class="card" style="padding:0;overflow:hidden;">
            <div v-if="!auditLog?.length" class="empty-cell" style="padding:32px;text-align:center;">
                No audit entries yet. Changes will appear here once the asset is modified.
            </div>
            <div v-else class="audit-list">
                <div v-for="entry in auditLog" :key="entry.id" class="audit-entry">
                    <div class="audit-dot" :style="{ background: auditEventColor[entry.event] ?? '#94a3b8' }"></div>
                    <div class="audit-body">
                        <div class="audit-meta">
                            <span class="audit-event" :style="{ color: auditEventColor[entry.event] ?? '#64748b' }">{{ entry.event }}</span>
                            <span class="audit-by">by {{ entry.causer_name }}</span>
                            <span class="audit-time">{{ entry.created_at }}</span>
                        </div>
                        <div v-if="formatChanges(entry.changes).length" class="audit-changes">
                            <div v-for="ch in formatChanges(entry.changes)" :key="ch.field" class="audit-change-row">
                                <span class="change-field">{{ ch.field }}</span>
                                <span class="change-from">{{ ch.from }}</span>
                                <svg width="12" height="12" fill="none" stroke="#94a3b8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                <span class="change-to">{{ ch.to }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Resolve Maintenance Modal ──────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showResolve" class="backdrop" @click.self="showResolve = false">
                <div class="modal-box" style="max-width:420px;">
                    <div class="modal-head">
                        <span class="modal-icon" style="background:#dcfce7;color:#16a34a;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                        <div><h3 class="modal-title">Resolve Maintenance</h3><p class="modal-sub">{{ resolveTarget?.issue_description }}</p></div>
                        <button class="modal-close" @click="showResolve = false"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                    <form @submit.prevent="submitResolve">
                        <div class="modal-body">
                            <div class="field full"><label class="field-label">Resolution Notes</label><textarea v-model="resolveForm.resolution_notes" class="field-input" rows="3" placeholder="Describe what was done…"></textarea></div>
                            <div class="field-row">
                                <div class="field"><label class="field-label">Cost (₹)</label><input v-model="resolveForm.cost" class="field-input" type="number" min="0" step="0.01" /></div>
                                <div class="field"><label class="field-label">Vendor</label><input v-model="resolveForm.vendor" class="field-input" /></div>
                            </div>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn-outline" @click="showResolve = false">Cancel</button>
                            <button type="submit" class="btn-primary" :disabled="resolveForm.processing">Mark Resolved</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

    </SchoolLayout>
</template>

<style scoped>
.btn-outline { display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#fff;color:#374151;border:1px solid #d1d5db;border-radius:8px;font-size:.875rem;font-weight:500;cursor:pointer;transition:background .15s;text-decoration:none; }
.btn-outline:hover { background:#f9fafb; }
.btn-primary { display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#3b82f6;color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer;transition:background .15s; }
.btn-primary:hover:not(:disabled) { background:#2563eb; }
.btn-primary:disabled { opacity:.6;cursor:not-allowed; }

/* ── Header card ────────────────────────────────────────────────────────── */
.header-card { display:flex;align-items:center;justify-content:space-between;gap:16px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;margin-bottom:16px;flex-wrap:wrap; }
.header-left { display:flex;align-items:center;gap:16px; }
.asset-icon { width:52px;height:52px;background:#ede9fe;border-radius:14px;display:flex;align-items:center;justify-content:center;color:#7c3aed;flex-shrink:0; }
.asset-title { font-size:1.25rem;font-weight:800;color:#0f172a;margin:0 0 4px; }
.asset-sub-row { display:flex;gap:6px;flex-wrap:wrap;align-items:center; }
.header-right { display:flex;align-items:center;gap:10px;flex-wrap:wrap; }

/* ── Metrics row ────────────────────────────────────────────────────────── */
.metrics-row { display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px; }
@media (max-width:900px) { .metrics-row { grid-template-columns:repeat(2,1fr); } }
.metric-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;box-shadow:0 1px 3px rgba(0,0,0,.04); }
.metric-label { font-size:.7rem;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em; }
.metric-value { font-size:1.35rem;font-weight:800;color:#1e293b;line-height:1.2;margin:4px 0 2px; }
.metric-sub   { font-size:.72rem;color:#94a3b8; }
.value-bar-wrap { width:100%;height:4px;background:#f1f5f9;border-radius:2px;margin-top:8px;overflow:hidden; }
.value-bar { height:100%;background:#3b82f6;border-radius:2px;transition:width .3s; }

/* ── Banners ────────────────────────────────────────────────────────────── */
.assign-banner { display:flex;align-items:center;gap:10px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 16px;margin-bottom:12px;font-size:.85rem;color:#1d4ed8; }
.dispose-banner { display:flex;align-items:center;gap:10px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 16px;margin-bottom:12px;font-size:.85rem;color:#991b1b; }

/* ── Tabs ───────────────────────────────────────────────────────────────── */
.tabs-bar { display:flex;gap:2px;margin-bottom:12px;background:#f1f5f9;border-radius:10px;padding:4px; }
.tab-btn { display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:none;border-radius:7px;font-size:.82rem;font-weight:600;color:#64748b;background:transparent;cursor:pointer;transition:background .15s,color .15s; }
.tab-btn:hover { color:#374151; }
.tab-btn.active { background:#fff;color:#1e293b;box-shadow:0 1px 3px rgba(0,0,0,.08); }
.tab-count { font-size:.68rem;font-weight:700;background:#e2e8f0;color:#64748b;padding:1px 6px;border-radius:10px; }

/* ── Card ───────────────────────────────────────────────────────────────── */
.card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;margin-bottom:0; }

/* ── Overview grid ──────────────────────────────────────────────────────── */
.detail-grid { display:grid;grid-template-columns:1fr 1fr;gap:0; }
.detail-row { display:flex;flex-direction:column;gap:3px;padding:14px 20px;border-bottom:1px solid #f1f5f9; }
.detail-row.full { grid-column:span 2; }
.detail-row:last-child { border-bottom:none; }
.detail-label { font-size:.72rem;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em; }
.detail-val   { font-size:.875rem;color:#1e293b;font-weight:500; }

/* ── Tables ─────────────────────────────────────────────────────────────── */
.det-table { width:100%;border-collapse:collapse; }
.det-table th { padding:10px 16px;text-align:left;font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;background:#f8fafc;border-bottom:1px solid #e2e8f0;white-space:nowrap; }
.det-table td { padding:11px 16px;border-bottom:1px solid #f1f5f9;font-size:.85rem;vertical-align:middle;color:#374151; }
.det-table tr:last-child td { border-bottom:none; }
.det-table tr:hover td { background:#fafbff; }

/* ── Audit log ──────────────────────────────────────────────────────────── */
.audit-list { display:flex;flex-direction:column; }
.audit-entry { display:flex;gap:14px;padding:14px 20px;border-bottom:1px solid #f1f5f9; }
.audit-entry:last-child { border-bottom:none; }
.audit-dot { width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:5px; }
.audit-body { flex:1; }
.audit-meta { display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px; }
.audit-event { font-size:.8rem;font-weight:700;text-transform:capitalize; }
.audit-by    { font-size:.8rem;color:#475569; }
.audit-time  { font-size:.75rem;color:#94a3b8;margin-left:auto; }
.audit-changes { display:flex;flex-direction:column;gap:4px; }
.audit-change-row { display:inline-flex;align-items:center;gap:6px;font-size:.78rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:3px 10px; }
.change-field { font-weight:600;color:#475569;text-transform:capitalize; }
.change-from  { color:#94a3b8;text-decoration:line-through; }
.change-to    { color:#1e293b;font-weight:600; }

/* ── Shared chips ───────────────────────────────────────────────────────── */
.meta-chip { font-size:.68rem;color:#64748b;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;padding:1px 6px; }
.cat-badge { font-size:.75rem;font-weight:500;color:#6d28d9;background:#ede9fe;padding:2px 8px;border-radius:20px; }
.status-pill { display:inline-block;font-size:.72rem;font-weight:600;padding:3px 10px;border-radius:20px;white-space:nowrap; }
.condition-dot { display:inline-block;width:7px;height:7px;border-radius:50%; }
.maint-status-pill { font-size:.7rem;font-weight:600;padding:2px 8px;border-radius:20px;white-space:nowrap; }
.act-btn { font-size:.72rem;font-weight:600;padding:4px 10px;border-radius:6px;border:none;cursor:pointer;transition:opacity .15s; }
.act-btn:hover { opacity:.8; }
.act-amber { background:#fef3c7;color:#d97706; }
.act-green  { background:#dcfce7;color:#16a34a; }
.empty-cell { text-align:center;padding:32px;color:#94a3b8;font-size:.875rem; }

/* ── Modal ──────────────────────────────────────────────────────────────── */
.backdrop { position:fixed;inset:0;background:rgba(15,23,42,.5);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;z-index:1000;padding:16px; }
.modal-box { background:#fff;border-radius:16px;width:100%;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);max-height:92vh;overflow-y:auto; }
.modal-head { display:flex;align-items:flex-start;gap:14px;padding:20px 20px 16px;border-bottom:1px solid #f1f5f9;position:sticky;top:0;background:#fff;z-index:1;border-radius:16px 16px 0 0; }
.modal-icon { width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.modal-title { font-size:1rem;font-weight:700;color:#0f172a;margin:0; }
.modal-sub { font-size:.8rem;color:#64748b;margin:2px 0 0; }
.modal-close { margin-left:auto;background:#f1f5f9;border:none;border-radius:8px;width:34px;height:34px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b;transition:background .15s;flex-shrink:0; }
.modal-close:hover { background:#e2e8f0;color:#0f172a; }
.modal-body { padding:20px;display:flex;flex-direction:column;gap:14px; }
.modal-foot { padding:16px 20px;border-top:1px solid #f1f5f9;background:#f8fafc;display:flex;justify-content:flex-end;gap:10px;border-radius:0 0 16px 16px;position:sticky;bottom:0; }
.field-row { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
.field.full { grid-column:span 2; }
.field-label { display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:5px; }
.field-input { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.875rem;color:#1e293b;background:#fff;outline:none;transition:border-color .15s;box-sizing:border-box; }
.field-input:focus { border-color:#3b82f6;box-shadow:0 0 0 3px #3b82f620; }
textarea.field-input { resize:vertical; }
</style>
