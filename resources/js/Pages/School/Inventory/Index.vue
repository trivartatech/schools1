<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    assets:          Object,
    categories:      Array,
    stats:           Object,
    openMaintenance: Number,
    staff:           Array,
    sections:        Array,
    departments:     Array,
    suppliers:       Array,
    stores:          Array,
});

const params    = new URLSearchParams(window.location.search);
const search    = ref(params.get('search')      ?? '');
const statusF   = ref(params.get('status')      ?? '');
const categoryF = ref(params.get('category_id') ?? '');

const applyFilters = () => router.get('/school/inventory', {
    search:      search.value      || undefined,
    status:      statusF.value     || undefined,
    category_id: categoryF.value   || undefined,
}, { preserveState: true, preserveScroll: true });

let fTimer;
watch([search, statusF, categoryF], () => { clearTimeout(fTimer); fTimer = setTimeout(applyFilters, 400); });

// ── Warranty alerts ────────────────────────────────────────────────────────
const today       = new Date();
const in30        = new Date(today); in30.setDate(in30.getDate() + 30);
const warrantyExpiring = computed(() =>
    (props.assets?.data ?? []).filter(a => {
        if (!a.warranty_until) return false;
        const d = new Date(a.warranty_until);
        return d >= today && d <= in30;
    }).length
);
const warrantyExpired = computed(() =>
    (props.assets?.data ?? []).filter(a => {
        if (!a.warranty_until) return false;
        return new Date(a.warranty_until) < today;
    }).length
);
const warrantyStatus = (w) => {
    if (!w) return null;
    const d = new Date(w);
    if (d < today)  return 'expired';
    if (d <= in30)  return 'expiring';
    return 'ok';
};

// ── Expand/collapse maintenance rows ──────────────────────────────────────
const expanded = ref({});
const toggleExpand = (id) => { expanded.value = { ...expanded.value, [id]: !expanded.value[id] }; };

// ── Add Asset ──────────────────────────────────────────────────────────────
const showAdd = ref(false);
const addForm = useForm({
    category_id: '', name: '', asset_code: '', brand: '', model_no: '', serial_no: '',
    purchase_date: '', purchase_cost: '', supplier: '', supplier_id: '', store_id: '',
    warranty_until: '', useful_life_years: 5,
    depreciation_method: 'straight_line', condition: 'good', notes: '',
});
const submitAdd = () => addForm.post('/school/inventory', {
    preserveScroll: true,
    onSuccess: () => { showAdd.value = false; addForm.reset(); },
});

// ── Edit Asset ─────────────────────────────────────────────────────────────
const showEdit   = ref(false);
const editTarget = ref(null);
const editForm   = useForm({
    category_id: '', name: '', asset_code: '', brand: '', model_no: '', serial_no: '',
    purchase_date: '', purchase_cost: '', supplier: '', supplier_id: '', store_id: '',
    warranty_until: '', useful_life_years: 5,
    depreciation_method: 'straight_line', condition: 'good', notes: '',
});
const openEdit = (a) => {
    editTarget.value = a;
    editForm.category_id          = a.category_id ?? '';
    editForm.name                 = a.name ?? '';
    editForm.asset_code           = a.asset_code ?? '';
    editForm.brand                = a.brand ?? '';
    editForm.model_no             = a.model_no ?? '';
    editForm.serial_no            = a.serial_no ?? '';
    editForm.purchase_date        = a.purchase_date ?? '';
    editForm.purchase_cost        = a.purchase_cost ?? '';
    editForm.supplier             = a.supplier ?? '';
    editForm.supplier_id          = a.supplier_id ?? '';
    editForm.store_id             = a.store_id ?? '';
    editForm.warranty_until       = a.warranty_until ?? '';
    editForm.useful_life_years    = a.useful_life_years ?? 5;
    editForm.depreciation_method  = a.depreciation_method ?? 'straight_line';
    editForm.condition            = a.condition ?? 'good';
    editForm.notes                = a.notes ?? '';
    showEdit.value = true;
};
const submitEdit = () => editForm.put(`/school/inventory/${editTarget.value.id}`, {
    preserveScroll: true,
    onSuccess: () => { showEdit.value = false; },
});

// ── Category ───────────────────────────────────────────────────────────────
const showCat = ref(false);
const catForm = useForm({ name: '', description: '' });
const submitCat = () => catForm.post('/school/inventory/categories', {
    preserveScroll: true,
    onSuccess: () => { showCat.value = false; catForm.reset(); },
});

const showEditCat  = ref(false);
const editCatTarget = ref(null);
const editCatForm  = useForm({ name: '', description: '' });
const openEditCat  = (c) => {
    editCatTarget.value = c;
    editCatForm.name        = c.name;
    editCatForm.description = c.description ?? '';
    showEditCat.value = true;
};
const submitEditCat = () => editCatForm.put(`/school/inventory/categories/${editCatTarget.value.id}`, {
    preserveScroll: true,
    onSuccess: () => { showEditCat.value = false; },
});
const deleteCategory = (c) => {
    if (!confirm(`Delete category "${c.name}"? This cannot be undone.`)) return;
    useForm({}).delete(`/school/inventory/categories/${c.id}`, { preserveScroll: true });
};

// ── CSV Import ─────────────────────────────────────────────────────────────
const showImport = ref(false);
const importForm = useForm({ file: null });
const onImportFile = (e) => { importForm.file = e.target.files[0]; };
const submitImport = () => importForm.post('/school/inventory/import', {
    preserveScroll: true,
    onSuccess: () => { showImport.value = false; importForm.reset(); },
});

// ── Assign ─────────────────────────────────────────────────────────────────
const showAssign   = ref(false);
const assignTarget = ref(null);
const assignForm   = useForm({
    location: '', assignee_type: 'classroom', assignee_id: '', assignee_name: '',
    assigned_on: new Date().toISOString().slice(0,10), notes: '',
});
const openAssign = (a) => {
    assignTarget.value = a;
    assignForm.reset();
    assignForm.assigned_on   = new Date().toISOString().slice(0,10);
    assignForm.assignee_type = 'classroom';
    showAssign.value = true;
};
const submitAssign = () => assignForm.post(`/school/inventory/${assignTarget.value.id}/assign`, {
    preserveScroll: true,
    onSuccess: () => { showAssign.value = false; assignForm.reset(); },
});
const assigneeOptions = computed(() => {
    if (assignForm.assignee_type === 'staff')      return props.staff       ?? [];
    if (assignForm.assignee_type === 'classroom')  return props.sections    ?? [];
    if (assignForm.assignee_type === 'department') return props.departments ?? [];
    return [];
});

// ── Maintenance (log issue) ────────────────────────────────────────────────
const showMaint   = ref(false);
const maintTarget = ref(null);
const maintForm   = useForm({ issue_description: '', type: 'corrective', reported_on: new Date().toISOString().slice(0,10) });
const openMaint   = (a) => { maintTarget.value = a; showMaint.value = true; };
const submitMaint = () => maintForm.post(`/school/inventory/${maintTarget.value.id}/maintenance`, {
    preserveScroll: true,
    onSuccess: () => { showMaint.value = false; maintForm.reset(); },
});

// ── Resolve maintenance ────────────────────────────────────────────────────
const showResolve   = ref(false);
const resolveTarget = ref(null);
const resolveForm   = useForm({ resolution_notes: '', cost: '', vendor: '' });
const openResolve   = (log) => { resolveTarget.value = log; showResolve.value = true; };
const submitResolve = () => resolveForm.patch(`/school/inventory/maintenance/${resolveTarget.value.id}/resolve`, {
    preserveScroll: true,
    onSuccess: () => { showResolve.value = false; resolveForm.reset(); },
});

// ── Mark In Progress ───────────────────────────────────────────────────────
const markInProgress = (logId) => {
    useForm({}).patch(`/school/inventory/maintenance/${logId}/progress`, { preserveScroll: true });
};

// ── Return asset ───────────────────────────────────────────────────────────
const returnAsset = (id) => {
    if (!confirm('Mark this asset as returned to inventory?')) return;
    useForm({}).patch(`/school/inventory/${id}/return`, { preserveScroll: true });
};

// ── Dispose asset ──────────────────────────────────────────────────────────
const showDispose   = ref(false);
const disposeTarget = ref(null);
const disposeForm   = useForm({ disposed_on: new Date().toISOString().slice(0,10), disposal_reason: '' });
const openDispose   = (a) => { disposeTarget.value = a; showDispose.value = true; };
const submitDispose = () => disposeForm.patch(`/school/inventory/${disposeTarget.value.id}/dispose`, {
    preserveScroll: true,
    onSuccess: () => { showDispose.value = false; disposeForm.reset(); },
});

// ── Manage categories modal ────────────────────────────────────────────────
const showManageCats = ref(false);

// ── Helpers ────────────────────────────────────────────────────────────────
const statusBadge  = { available: '#10b981', assigned: '#3b82f6', under_maintenance: '#f59e0b', disposed: '#94a3b8' };
const statusLabel  = { available: 'Available', assigned: 'Assigned', under_maintenance: 'Maintenance', disposed: 'Disposed' };
const conditionDot = { excellent: '#10b981', good: '#3b82f6', fair: '#f59e0b', poor: '#ef4444', condemned: '#6b7280' };
const maintStatusColor = { open: '#ef4444', in_progress: '#f59e0b', resolved: '#10b981', scrapped: '#94a3b8' };
const maintStatusLabel = { open: 'Open', in_progress: 'In Progress', resolved: 'Resolved', scrapped: 'Scrapped' };
import { useFormat } from '@/Composables/useFormat';
const { formatDate: fmt } = useFormat();
const fmtCost = (n) => n ? '₹' + Number(n).toLocaleString('en-IN') : '—';
const maintTotal = (logs) => logs?.reduce((s, l) => s + parseFloat(l.cost || 0), 0) ?? 0;
</script>

<template>
    <SchoolLayout title="Inventory">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Inventory & Assets</h1>
                <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Track school assets, assignments and maintenance records.</p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="/school/inventory-suppliers" class="btn-outline">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Suppliers
                </a>
                <a href="/school/inventory-stores" class="btn-outline">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"/></svg>
                    Stores
                </a>
                <a href="/school/inventory/reports" class="btn-outline">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Reports
                </a>
                <a :href="`/school/inventory/export?status=${statusF}&category_id=${categoryF}`" class="btn-outline">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                </a>
                <button class="btn-outline" @click="showImport = true">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Import
                </button>
                <button class="btn-outline" @click="showManageCats = true">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    Categories
                </button>
                <button class="btn-primary" @click="showAdd = true">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Asset
                </button>
            </div>
        </div>

        <!-- Warranty alert banner -->
        <div v-if="warrantyExpired > 0 || warrantyExpiring > 0" class="warranty-banner">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>
                <span v-if="warrantyExpired > 0"><strong>{{ warrantyExpired }} asset(s)</strong> have expired warranties.</span>
                <span v-if="warrantyExpired > 0 && warrantyExpiring > 0"> · </span>
                <span v-if="warrantyExpiring > 0"><strong>{{ warrantyExpiring }} asset(s)</strong> have warranties expiring within 30 days.</span>
            </span>
        </div>

        <!-- Stats row -->
        <div class="stats-row">
            <div class="stat-card stat-green">
                <div class="stat-icon"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                <div>
                    <div class="stat-label">Available</div>
                    <div class="stat-value" style="color:#10b981;">{{ stats?.available?.count ?? 0 }}</div>
                    <div class="stat-sub">{{ fmtCost(stats?.available?.total_cost) }}</div>
                </div>
            </div>
            <div class="stat-card stat-blue">
                <div class="stat-icon"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div>
                <div>
                    <div class="stat-label">Assigned</div>
                    <div class="stat-value" style="color:#3b82f6;">{{ stats?.assigned?.count ?? 0 }}</div>
                    <div class="stat-sub">{{ fmtCost(stats?.assigned?.total_cost) }}</div>
                </div>
            </div>
            <div class="stat-card stat-amber">
                <div class="stat-icon"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
                <div>
                    <div class="stat-label">In Maintenance</div>
                    <div class="stat-value" style="color:#f59e0b;">{{ openMaintenance }}</div>
                    <div class="stat-sub">open tickets</div>
                </div>
            </div>
            <div class="stat-card stat-gray">
                <div class="stat-icon"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></div>
                <div>
                    <div class="stat-label">Disposed</div>
                    <div class="stat-value" style="color:#64748b;">{{ stats?.disposed?.count ?? 0 }}</div>
                    <div class="stat-sub">written off</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
            <div class="search-wrap">
                <svg class="search-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input v-model="search" class="search-input" placeholder="Search name, code, serial…" />
            </div>
            <select v-model="categoryF" class="filter-select">
                <option value="">All Categories</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }} ({{ c.assets_count }})</option>
            </select>
            <select v-model="statusF" class="filter-select">
                <option value="">All Statuses</option>
                <option value="available">Available</option>
                <option value="assigned">Assigned</option>
                <option value="under_maintenance">Under Maintenance</option>
                <option value="disposed">Disposed</option>
            </select>
        </div>

        <!-- Table -->
        <div class="card" style="overflow:hidden;">
            <div style="overflow-x:auto;">
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th style="width:32px;"></th>
                            <th>Asset</th>
                            <th>Category</th>
                            <th>Purchase</th>
                            <th>Maint. Cost</th>
                            <th>Condition</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="a in assets.data" :key="a.id">
                            <tr :class="{ 'row-expanded': expanded[a.id] }">
                                <!-- Expand toggle -->
                                <td style="padding:12px 8px 12px 16px;">
                                    <button v-if="a.maintenance_logs?.length" class="expand-btn" @click="toggleExpand(a.id)"
                                        :title="expanded[a.id] ? 'Hide maintenance' : `${a.maintenance_logs.length} log(s)`">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            :style="{ transform: expanded[a.id] ? 'rotate(90deg)' : '', transition: 'transform .15s' }">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                        <span class="maint-badge">{{ a.maintenance_logs.length }}</span>
                                    </button>
                                </td>
                                <td>
                                    <a :href="`/school/inventory/${a.id}`" class="asset-name-link">{{ a.name }}</a>
                                    <div class="asset-meta">
                                        <span v-if="a.asset_code" class="meta-chip">{{ a.asset_code }}</span>
                                        <span v-if="a.brand" class="meta-chip">{{ a.brand }}</span>
                                        <span v-if="a.serial_no" class="meta-chip">S/N: {{ a.serial_no }}</span>
                                        <span v-if="a.supplier_model" class="meta-chip chip-supplier" :title="'Supplier: ' + a.supplier_model.name">🏭 {{ a.supplier_model.name }}</span>
                                        <span v-if="a.store" class="meta-chip chip-store" :title="'Store: ' + a.store.name">🏪 {{ a.store.name }}</span>
                                        <!-- Warranty badge -->
                                        <span v-if="warrantyStatus(a.warranty_until) === 'expired'"
                                            class="meta-chip warranty-expired" title="Warranty expired">
                                            Warranty expired
                                        </span>
                                        <span v-else-if="warrantyStatus(a.warranty_until) === 'expiring'"
                                            class="meta-chip warranty-expiring" title="Warranty expiring soon">
                                            Warranty expiring
                                        </span>
                                    </div>
                                </td>
                                <td><span class="cat-badge">{{ a.category?.name ?? '—' }}</span></td>
                                <td>
                                    <div class="cost-val">{{ fmtCost(a.purchase_cost) }}</div>
                                    <div class="cost-date">{{ fmt(a.purchase_date) }}</div>
                                </td>
                                <td>
                                    <span v-if="maintTotal(a.maintenance_logs) > 0" style="font-size:.82rem;font-weight:600;color:#dc2626;">
                                        {{ fmtCost(maintTotal(a.maintenance_logs)) }}
                                    </span>
                                    <span v-else style="font-size:.78rem;color:#cbd5e1;">—</span>
                                </td>
                                <td>
                                    <span class="condition-dot" :style="{ background: conditionDot[a.condition] }"></span>
                                    <span class="condition-text" style="text-transform:capitalize;">{{ a.condition }}</span>
                                </td>
                                <td>
                                    <span class="status-pill" :style="{ background: statusBadge[a.status] + '1a', color: statusBadge[a.status], border: '1px solid ' + statusBadge[a.status] + '40' }">
                                        {{ statusLabel[a.status] ?? a.status }}
                                    </span>
                                </td>
                                <td>
                                    <span v-if="a.active_assignment" style="font-size:.8rem;color:#475569;">
                                        {{ a.active_assignment.location }}
                                        <span v-if="a.active_assignment.assignee_name" style="color:#94a3b8;"> · {{ a.active_assignment.assignee_name }}</span>
                                    </span>
                                    <span v-else style="font-size:.8rem;color:#cbd5e1;">—</span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:5px;justify-content:flex-end;flex-wrap:wrap;">
                                        <button class="act-btn act-purple" @click="openEdit(a)">Edit</button>
                                        <button v-if="a.status === 'available'"   class="act-btn act-blue"  @click="openAssign(a)">Assign</button>
                                        <button v-if="a.status === 'assigned'"    class="act-btn act-gray"  @click="returnAsset(a.id)">Return</button>
                                        <button v-if="!['under_maintenance','disposed'].includes(a.status)" class="act-btn act-amber" @click="openMaint(a)">Issue</button>
                                        <button v-if="a.status !== 'disposed'"    class="act-btn act-red"   @click="openDispose(a)">Dispose</button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Maintenance history sub-row -->
                            <tr v-if="expanded[a.id] && a.maintenance_logs?.length" class="maint-subrow">
                                <td colspan="9" style="padding:0 16px 12px 48px;">
                                    <div class="maint-panel">
                                        <div class="maint-panel-title">Maintenance History · Total spend: {{ fmtCost(maintTotal(a.maintenance_logs)) }}</div>
                                        <table class="maint-table">
                                            <thead>
                                                <tr>
                                                    <th>Date</th><th>Type</th><th>Description</th>
                                                    <th>Status</th><th>Cost</th><th style="text-align:right;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="log in a.maintenance_logs" :key="log.id">
                                                    <td style="white-space:nowrap;">{{ fmt(log.reported_on) }}</td>
                                                    <td style="text-transform:capitalize;">{{ log.type }}</td>
                                                    <td>{{ log.issue_description }}</td>
                                                    <td>
                                                        <span class="maint-status-pill"
                                                            :style="{ background: maintStatusColor[log.status] + '1a', color: maintStatusColor[log.status], border: '1px solid ' + maintStatusColor[log.status] + '40' }">
                                                            {{ maintStatusLabel[log.status] ?? log.status }}
                                                        </span>
                                                    </td>
                                                    <td>{{ fmtCost(log.cost) }}</td>
                                                    <td>
                                                        <div style="display:flex;gap:5px;justify-content:flex-end;">
                                                            <button v-if="log.status === 'open'" class="act-btn act-amber" @click="markInProgress(log.id)">In Progress</button>
                                                            <button v-if="['open','in_progress'].includes(log.status)" class="act-btn act-green" @click="openResolve(log)">Resolve</button>
                                                            <span v-if="log.status === 'resolved' && log.resolution_notes" class="maint-resolved-note" :title="log.resolution_notes">
                                                                {{ log.resolution_notes }}
                                                            </span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <tr v-if="!assets.data?.length">
                            <td colspan="9" class="empty-row">
                                <svg width="40" height="40" fill="none" stroke="#cbd5e1" viewBox="0 0 24 24" style="margin-bottom:8px;display:block;margin-inline:auto;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 10V7"/></svg>
                                No assets found. Add your first asset to get started.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="assets.last_page > 1" class="pagination">
                <a v-for="p in assets.links" :key="p.label" :href="p.url ?? '#'" v-html="p.label"
                   :class="['page-link', { active: p.active, disabled: !p.url }]"></a>
            </div>
        </div>

        <!-- ── Add Asset Modal ─────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showAdd" class="backdrop" @click.self="showAdd = false">
                <div class="modal-box" style="max-width:600px;">
                    <div class="modal-head">
                        <span class="modal-icon" style="background:#ede9fe;color:#7c3aed;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 10V7"/></svg></span>
                        <div><h3 class="modal-title">Add New Asset</h3><p class="modal-sub">Fill in the asset details below</p></div>
                        <button class="modal-close" @click="showAdd = false"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                    <form @submit.prevent="submitAdd">
                        <div class="modal-body">
                            <div class="field full"><label class="field-label">Asset Name <span class="req">*</span></label><input v-model="addForm.name" class="field-input" required placeholder="e.g. Dell Latitude Laptop" /><p v-if="addForm.errors.name" class="field-error">{{ addForm.errors.name }}</p></div>
                            <div class="field-row">
                                <div class="field"><label class="field-label">Category <span class="req">*</span></label><select v-model="addForm.category_id" class="field-input" required><option value="">Select category…</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                                <div class="field"><label class="field-label">Asset Code</label><input v-model="addForm.asset_code" class="field-input" placeholder="e.g. LAP-001" /></div>
                            </div>
                            <div class="field-row">
                                <div class="field"><label class="field-label">Brand</label><input v-model="addForm.brand" class="field-input" placeholder="Dell, HP…" /></div>
                                <div class="field"><label class="field-label">Serial No</label><input v-model="addForm.serial_no" class="field-input" /></div>
                            </div>
                            <div class="field-row">
                                <div class="field"><label class="field-label">Purchase Date</label><input v-model="addForm.purchase_date" class="field-input" type="date" /></div>
                                <div class="field"><label class="field-label">Purchase Cost (₹)</label><input v-model="addForm.purchase_cost" class="field-input" type="number" min="0" step="0.01" /></div>
                            </div>
                            <div class="field-row">
                                <div class="field"><label class="field-label">Useful Life (yrs)</label><input v-model="addForm.useful_life_years" class="field-input" type="number" min="1" max="50" /></div>
                                <div class="field"><label class="field-label">Depreciation Method</label>
                                    <select v-model="addForm.depreciation_method" class="field-input">
                                        <option value="straight_line">Straight Line</option>
                                        <option value="declining_balance">Declining Balance (2x)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="field-row">
                                <div class="field"><label class="field-label">Condition</label><select v-model="addForm.condition" class="field-input"><option value="excellent">Excellent</option><option value="good">Good</option><option value="fair">Fair</option><option value="poor">Poor</option><option value="condemned">Condemned</option></select></div>
                                <div class="field"><label class="field-label">Warranty Until</label><input v-model="addForm.warranty_until" class="field-input" type="date" /></div>
                            </div>
                            <div class="field-row">
                                <div class="field">
                                    <label class="field-label">Supplier</label>
                                    <select v-model="addForm.supplier_id" class="field-input">
                                        <option value="">— None / Other —</option>
                                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Store / Location</label>
                                    <select v-model="addForm.store_id" class="field-input">
                                        <option value="">— None —</option>
                                        <option v-for="st in stores" :key="st.id" :value="st.id">{{ st.name }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="field full"><label class="field-label">Supplier (manual)</label><input v-model="addForm.supplier" class="field-input" placeholder="Free-text fallback if no supplier selected above" /></div>
                            <div class="field full"><label class="field-label">Notes</label><textarea v-model="addForm.notes" class="field-input" rows="2"></textarea></div>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn-outline" @click="showAdd = false">Cancel</button>
                            <button type="submit" class="btn-primary" :disabled="addForm.processing">{{ addForm.processing ? 'Saving…' : 'Add Asset' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ── Edit Asset Modal ────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showEdit" class="backdrop" @click.self="showEdit = false">
                <div class="modal-box" style="max-width:600px;">
                    <div class="modal-head">
                        <span class="modal-icon" style="background:#fef3c7;color:#d97706;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></span>
                        <div><h3 class="modal-title">Edit Asset</h3><p class="modal-sub">{{ editTarget?.name }}</p></div>
                        <button class="modal-close" @click="showEdit = false"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                    <form @submit.prevent="submitEdit">
                        <div class="modal-body">
                            <div class="field full"><label class="field-label">Asset Name <span class="req">*</span></label><input v-model="editForm.name" class="field-input" required /><p v-if="editForm.errors.name" class="field-error">{{ editForm.errors.name }}</p></div>
                            <div class="field-row">
                                <div class="field"><label class="field-label">Category <span class="req">*</span></label><select v-model="editForm.category_id" class="field-input" required><option value="">Select…</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                                <div class="field"><label class="field-label">Asset Code</label><input v-model="editForm.asset_code" class="field-input" /></div>
                            </div>
                            <div class="field-row">
                                <div class="field"><label class="field-label">Brand</label><input v-model="editForm.brand" class="field-input" /></div>
                                <div class="field"><label class="field-label">Serial No</label><input v-model="editForm.serial_no" class="field-input" /></div>
                            </div>
                            <div class="field-row">
                                <div class="field"><label class="field-label">Purchase Date</label><input v-model="editForm.purchase_date" class="field-input" type="date" /></div>
                                <div class="field"><label class="field-label">Purchase Cost (₹)</label><input v-model="editForm.purchase_cost" class="field-input" type="number" min="0" step="0.01" /></div>
                            </div>
                            <div class="field-row">
                                <div class="field"><label class="field-label">Useful Life (yrs)</label><input v-model="editForm.useful_life_years" class="field-input" type="number" min="1" max="50" /></div>
                                <div class="field"><label class="field-label">Depreciation Method</label>
                                    <select v-model="editForm.depreciation_method" class="field-input">
                                        <option value="straight_line">Straight Line</option>
                                        <option value="declining_balance">Declining Balance (2x)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="field-row">
                                <div class="field"><label class="field-label">Condition</label><select v-model="editForm.condition" class="field-input"><option value="excellent">Excellent</option><option value="good">Good</option><option value="fair">Fair</option><option value="poor">Poor</option><option value="condemned">Condemned</option></select></div>
                                <div class="field"><label class="field-label">Warranty Until</label><input v-model="editForm.warranty_until" class="field-input" type="date" /></div>
                            </div>
                            <div class="field-row">
                                <div class="field">
                                    <label class="field-label">Supplier</label>
                                    <select v-model="editForm.supplier_id" class="field-input">
                                        <option value="">— None / Other —</option>
                                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Store / Location</label>
                                    <select v-model="editForm.store_id" class="field-input">
                                        <option value="">— None —</option>
                                        <option v-for="st in stores" :key="st.id" :value="st.id">{{ st.name }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="field full"><label class="field-label">Supplier (manual)</label><input v-model="editForm.supplier" class="field-input" placeholder="Free-text fallback" /></div>
                            <div class="field full"><label class="field-label">Notes</label><textarea v-model="editForm.notes" class="field-input" rows="2"></textarea></div>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn-outline" @click="showEdit = false">Cancel</button>
                            <button type="submit" class="btn-primary" :disabled="editForm.processing">{{ editForm.processing ? 'Saving…' : 'Save Changes' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ── Manage Categories Modal ─────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showManageCats" class="backdrop" @click.self="showManageCats = false">
                <div class="modal-box" style="max-width:480px;">
                    <div class="modal-head">
                        <span class="modal-icon" style="background:#dcfce7;color:#16a34a;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg></span>
                        <div><h3 class="modal-title">Manage Categories</h3><p class="modal-sub">{{ categories.length }} categories</p></div>
                        <button class="modal-close" @click="showManageCats = false"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                    <div class="modal-body" style="gap:8px;">
                        <div v-for="c in categories" :key="c.id" class="cat-row">
                            <div>
                                <div style="font-weight:600;font-size:.875rem;color:#1e293b;">{{ c.name }}</div>
                                <div style="font-size:.75rem;color:#94a3b8;">{{ c.assets_count }} assets</div>
                            </div>
                            <div style="display:flex;gap:6px;">
                                <button class="act-btn act-amber" @click="openEditCat(c); showManageCats = false">Edit</button>
                                <button class="act-btn act-red" @click="deleteCategory(c)" :disabled="c.assets_count > 0" :title="c.assets_count > 0 ? 'Has assets — cannot delete' : 'Delete'">Delete</button>
                            </div>
                        </div>
                        <div style="border-top:1px solid #f1f5f9;padding-top:12px;">
                            <button class="btn-primary" style="width:100%;" @click="showManageCats = false; showCat = true">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Add New Category
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Edit Category Modal ─────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showEditCat" class="backdrop" @click.self="showEditCat = false">
                <div class="modal-box" style="max-width:400px;">
                    <div class="modal-head">
                        <span class="modal-icon" style="background:#fef3c7;color:#d97706;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></span>
                        <div><h3 class="modal-title">Edit Category</h3><p class="modal-sub">{{ editCatTarget?.name }}</p></div>
                        <button class="modal-close" @click="showEditCat = false"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                    <form @submit.prevent="submitEditCat">
                        <div class="modal-body">
                            <div class="field full"><label class="field-label">Name <span class="req">*</span></label><input v-model="editCatForm.name" class="field-input" required /></div>
                            <div class="field full"><label class="field-label">Description</label><input v-model="editCatForm.description" class="field-input" /></div>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn-outline" @click="showEditCat = false">Cancel</button>
                            <button type="submit" class="btn-primary" :disabled="editCatForm.processing">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ── Add Category Modal ──────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showCat" class="backdrop" @click.self="showCat = false">
                <div class="modal-box" style="max-width:400px;">
                    <div class="modal-head">
                        <span class="modal-icon" style="background:#dcfce7;color:#16a34a;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></span>
                        <div><h3 class="modal-title">New Category</h3><p class="modal-sub">Group your assets by type</p></div>
                        <button class="modal-close" @click="showCat = false"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                    <form @submit.prevent="submitCat">
                        <div class="modal-body">
                            <div class="field full"><label class="field-label">Category Name <span class="req">*</span></label><input v-model="catForm.name" class="field-input" required /></div>
                            <div class="field full"><label class="field-label">Description</label><input v-model="catForm.description" class="field-input" /></div>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn-outline" @click="showCat = false">Cancel</button>
                            <button type="submit" class="btn-primary" :disabled="catForm.processing">Create</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ── CSV Import Modal ────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showImport" class="backdrop" @click.self="showImport = false">
                <div class="modal-box" style="max-width:440px;">
                    <div class="modal-head">
                        <span class="modal-icon" style="background:#dbeafe;color:#2563eb;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg></span>
                        <div><h3 class="modal-title">Import Assets from CSV</h3><p class="modal-sub">Bulk add assets from a spreadsheet</p></div>
                        <button class="modal-close" @click="showImport = false"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                    <form @submit.prevent="submitImport" enctype="multipart/form-data">
                        <div class="modal-body">
                            <div class="import-hint">
                                Columns: Code, Name*, Category*, Brand, Serial No, Purchase Date (YYYY-MM-DD), Cost, Useful Life (yrs), Depreciation Method, Condition, Notes
                            </div>
                            <a href="/school/inventory/import-template" class="template-link">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Download template CSV
                            </a>
                            <div class="field full">
                                <label class="field-label">CSV File <span class="req">*</span></label>
                                <input type="file" accept=".csv,.txt" class="field-input" @change="onImportFile" required />
                                <p v-if="importForm.errors.file" class="field-error">{{ importForm.errors.file }}</p>
                            </div>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn-outline" @click="showImport = false">Cancel</button>
                            <button type="submit" class="btn-primary" :disabled="importForm.processing">{{ importForm.processing ? 'Importing…' : 'Import' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ── Assign Modal ────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showAssign" class="backdrop" @click.self="showAssign = false">
                <div class="modal-box" style="max-width:440px;">
                    <div class="modal-head">
                        <span class="modal-icon" style="background:#dbeafe;color:#2563eb;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></span>
                        <div><h3 class="modal-title">Assign Asset</h3><p class="modal-sub">{{ assignTarget?.name }}</p></div>
                        <button class="modal-close" @click="showAssign = false"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                    <form @submit.prevent="submitAssign">
                        <div class="modal-body">
                            <div class="field-row">
                                <div class="field"><label class="field-label">Assign To</label>
                                    <select v-model="assignForm.assignee_type" class="field-input" @change="assignForm.assignee_id = ''; assignForm.assignee_name = ''">
                                        <option value="classroom">Classroom / Section</option>
                                        <option value="staff">Staff Member</option>
                                        <option value="department">Department</option>
                                        <option value="general">General Use</option>
                                    </select>
                                </div>
                                <div class="field"><label class="field-label">Assigned On <span class="req">*</span></label><input v-model="assignForm.assigned_on" class="field-input" type="date" required /></div>
                            </div>
                            <div v-if="assigneeOptions.length" class="field full">
                                <label class="field-label">{{ assignForm.assignee_type === 'staff' ? 'Staff Member' : assignForm.assignee_type === 'classroom' ? 'Section' : 'Department' }}</label>
                                <select v-model="assignForm.assignee_id" class="field-input" @change="assignForm.assignee_name = assigneeOptions.find(o => o.id == assignForm.assignee_id)?.name ?? ''">
                                    <option value="">— select —</option>
                                    <option v-for="o in assigneeOptions" :key="o.id" :value="o.id">{{ o.name }}</option>
                                </select>
                            </div>
                            <div class="field full"><label class="field-label">Location / Room <span class="req">*</span></label><input v-model="assignForm.location" class="field-input" required placeholder="e.g. Computer Lab, Room 101" /></div>
                            <div class="field full"><label class="field-label">Notes</label><textarea v-model="assignForm.notes" class="field-input" rows="2"></textarea></div>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn-outline" @click="showAssign = false">Cancel</button>
                            <button type="submit" class="btn-primary" :disabled="assignForm.processing">Assign</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ── Maintenance Modal ───────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showMaint" class="backdrop" @click.self="showMaint = false">
                <div class="modal-box" style="max-width:420px;">
                    <div class="modal-head">
                        <span class="modal-icon" style="background:#fef3c7;color:#d97706;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></span>
                        <div><h3 class="modal-title">Log Issue</h3><p class="modal-sub">{{ maintTarget?.name }}</p></div>
                        <button class="modal-close" @click="showMaint = false"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                    <form @submit.prevent="submitMaint">
                        <div class="modal-body">
                            <div class="field full"><label class="field-label">Issue Description <span class="req">*</span></label><textarea v-model="maintForm.issue_description" class="field-input" rows="3" required></textarea></div>
                            <div class="field-row">
                                <div class="field"><label class="field-label">Type <span class="req">*</span></label><select v-model="maintForm.type" class="field-input" required><option value="corrective">Corrective</option><option value="preventive">Preventive</option><option value="inspection">Inspection</option></select></div>
                                <div class="field"><label class="field-label">Reported On <span class="req">*</span></label><input v-model="maintForm.reported_on" class="field-input" type="date" required /></div>
                            </div>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn-outline" @click="showMaint = false">Cancel</button>
                            <button type="submit" class="btn-primary" :disabled="maintForm.processing">Log Issue</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ── Resolve Modal ───────────────────────────────────────────────── -->
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
                                <div class="field"><label class="field-label">Repair Cost (₹)</label><input v-model="resolveForm.cost" class="field-input" type="number" min="0" step="0.01" /></div>
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

        <!-- ── Dispose Modal ──────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showDispose" class="backdrop" @click.self="showDispose = false">
                <div class="modal-box" style="max-width:420px;">
                    <div class="modal-head">
                        <span class="modal-icon" style="background:#fee2e2;color:#dc2626;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></span>
                        <div><h3 class="modal-title">Dispose Asset</h3><p class="modal-sub">{{ disposeTarget?.name }}</p></div>
                        <button class="modal-close" @click="showDispose = false"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                    <form @submit.prevent="submitDispose">
                        <div class="modal-body">
                            <div class="dispose-warning">This will permanently mark the asset as disposed, close any open assignments, and scrap open maintenance tickets.</div>
                            <div class="field full"><label class="field-label">Disposal Date <span class="req">*</span></label><input v-model="disposeForm.disposed_on" class="field-input" type="date" required /></div>
                            <div class="field full"><label class="field-label">Reason / Notes</label><textarea v-model="disposeForm.disposal_reason" class="field-input" rows="2" placeholder="e.g. Beyond repair, end of life…"></textarea></div>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn-outline" @click="showDispose = false">Cancel</button>
                            <button type="submit" class="btn-danger" :disabled="disposeForm.processing">{{ disposeForm.processing ? 'Processing…' : 'Confirm Disposal' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

    </SchoolLayout>
</template>

<style scoped>
.btn-primary { display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#3b82f6;color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer;transition:background .15s;text-decoration:none; }
.btn-primary:hover:not(:disabled) { background:#2563eb; }
.btn-primary:disabled { opacity:.6;cursor:not-allowed; }
.btn-outline { display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#fff;color:#374151;border:1px solid #d1d5db;border-radius:8px;font-size:.875rem;font-weight:500;cursor:pointer;transition:background .15s;text-decoration:none; }
.btn-outline:hover { background:#f9fafb; }
.btn-danger { display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer;transition:background .15s; }
.btn-danger:hover:not(:disabled) { background:#b91c1c; }
.btn-danger:disabled { opacity:.6;cursor:not-allowed; }

.warranty-banner { display:flex;align-items:center;gap:10px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:10px 16px;margin-bottom:16px;font-size:.85rem;color:#92400e; }

.stats-row { display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px; }
@media (max-width:900px) { .stats-row { grid-template-columns:repeat(2,1fr); } }
.stat-card { display:flex;align-items:flex-start;gap:14px;background:#fff;border-radius:12px;padding:18px 20px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,.05); }
.stat-icon { width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.stat-green .stat-icon { background:#dcfce7;color:#16a34a; }
.stat-blue  .stat-icon { background:#dbeafe;color:#2563eb; }
.stat-amber .stat-icon { background:#fef3c7;color:#d97706; }
.stat-gray  .stat-icon { background:#f1f5f9;color:#64748b; }
.stat-label { font-size:.7rem;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em; }
.stat-value { font-size:1.75rem;font-weight:800;line-height:1.1;margin-top:2px; }
.stat-sub   { font-size:.72rem;color:#94a3b8;margin-top:2px; }

.filter-bar { display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap; }
.search-wrap { flex:1;min-width:220px;position:relative; }
.search-icon { position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none; }
.search-input { width:100%;padding:9px 12px 9px 36px;border:1px solid #e2e8f0;border-radius:8px;font-size:.875rem;color:#1e293b;background:#fff;outline:none;transition:border-color .15s;box-sizing:border-box; }
.search-input:focus { border-color:#3b82f6;box-shadow:0 0 0 3px #3b82f620; }
.filter-select { padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:.875rem;color:#374151;background:#fff;outline:none;cursor:pointer;min-width:150px;transition:border-color .15s; }

.card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05); }
.inv-table { width:100%;border-collapse:collapse; }
.inv-table th { padding:11px 16px;text-align:left;font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;background:#f8fafc;border-bottom:1px solid #e2e8f0;white-space:nowrap; }
.inv-table td { padding:12px 16px;border-bottom:1px solid #f1f5f9;vertical-align:middle; }
.inv-table tr:last-child td { border-bottom:none; }
.inv-table tr:hover td { background:#fafbff; }
.inv-table tr.row-expanded td { background:#f8f9ff; }
.maint-subrow td { background:#f8fafc !important;border-bottom:2px solid #e2e8f0; }

.asset-name-link { font-size:.875rem;font-weight:600;color:#1e293b;text-decoration:none; }
.asset-name-link:hover { color:#3b82f6;text-decoration:underline; }
.asset-meta { display:flex;gap:5px;flex-wrap:wrap;margin-top:3px; }
.meta-chip { font-size:.68rem;color:#64748b;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;padding:1px 6px; }
.warranty-expired  { background:#fee2e2 !important;color:#dc2626 !important;border-color:#fca5a5 !important; }
.warranty-expiring { background:#fff7ed !important;color:#d97706 !important;border-color:#fed7aa !important; }
.chip-supplier { background:#f0fdf4 !important;color:#15803d !important;border-color:#bbf7d0 !important; }
.chip-store    { background:#fdf4ff !important;color:#7e22ce !important;border-color:#e9d5ff !important; }

.cat-badge { font-size:.75rem;font-weight:500;color:#6d28d9;background:#ede9fe;padding:2px 8px;border-radius:20px; }
.cost-val { font-size:.85rem;font-weight:600;color:#1e293b; }
.cost-date { font-size:.72rem;color:#94a3b8;margin-top:1px; }
.condition-dot { display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:5px;vertical-align:middle; }
.condition-text { font-size:.8rem;color:#475569; }
.status-pill { display:inline-block;font-size:.72rem;font-weight:600;padding:3px 10px;border-radius:20px;white-space:nowrap; }

.act-btn { font-size:.72rem;font-weight:600;padding:4px 10px;border-radius:6px;border:none;cursor:pointer;transition:opacity .15s;white-space:nowrap; }
.act-btn:hover { opacity:.8; }
.act-btn:disabled { opacity:.4;cursor:not-allowed; }
.act-blue   { background:#dbeafe;color:#2563eb; }
.act-gray   { background:#f1f5f9;color:#475569; }
.act-amber  { background:#fef3c7;color:#d97706; }
.act-green  { background:#dcfce7;color:#16a34a; }
.act-red    { background:#fee2e2;color:#dc2626; }
.act-purple { background:#ede9fe;color:#7c3aed; }

.empty-row { text-align:center;padding:48px 24px;color:#94a3b8;font-size:.9rem; }

.expand-btn { display:inline-flex;align-items:center;gap:3px;background:none;border:none;cursor:pointer;color:#94a3b8;padding:2px 4px;border-radius:4px;transition:color .15s,background .15s; }
.expand-btn:hover { color:#3b82f6;background:#eff6ff; }
.maint-badge { font-size:.65rem;font-weight:700;background:#fee2e2;color:#dc2626;border-radius:10px;padding:1px 5px;line-height:1.4; }

.maint-panel { background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-top:4px; }
.maint-panel-title { font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;padding:8px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0; }
.maint-table { width:100%;border-collapse:collapse;font-size:.8rem; }
.maint-table th { padding:7px 14px;text-align:left;font-size:.68rem;font-weight:700;color:#94a3b8;text-transform:uppercase;background:#f8fafc;border-bottom:1px solid #f1f5f9; }
.maint-table td { padding:8px 14px;border-bottom:1px solid #f8fafc;color:#374151; }
.maint-table tr:last-child td { border-bottom:none; }
.maint-status-pill { font-size:.68rem;font-weight:600;padding:2px 8px;border-radius:20px;white-space:nowrap; }
.maint-resolved-note { font-size:.72rem;color:#64748b;font-style:italic;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }

.pagination { display:flex;justify-content:center;gap:4px;padding:14px;border-top:1px solid #f1f5f9; }
.page-link { padding:5px 11px;border-radius:6px;font-size:.8rem;font-weight:500;text-decoration:none;background:#f1f5f9;color:#475569;transition:background .15s; }
.page-link:hover { background:#e2e8f0; }
.page-link.active { background:#3b82f6;color:#fff; }
.page-link.disabled { opacity:.4;pointer-events:none; }

.backdrop { position:fixed;inset:0;background:rgba(15,23,42,.5);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;z-index:1000;padding:16px; }
.modal-box { background:#fff;border-radius:16px;width:100%;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);max-height:92vh;overflow-y:auto; }
.modal-head { display:flex;align-items:flex-start;gap:14px;padding:20px 20px 16px;border-bottom:1px solid #f1f5f9;position:sticky;top:0;background:#fff;z-index:1;border-radius:16px 16px 0 0; }
.modal-icon { width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.modal-title { font-size:1rem;font-weight:700;color:#0f172a;margin:0; }
.modal-sub   { font-size:.8rem;color:#64748b;margin:2px 0 0; }
.modal-close { margin-left:auto;background:#f1f5f9;border:none;border-radius:8px;width:34px;height:34px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b;transition:background .15s;flex-shrink:0; }
.modal-close:hover { background:#e2e8f0;color:#0f172a; }
.modal-body { padding:20px;display:flex;flex-direction:column;gap:14px; }
.modal-foot { padding:16px 20px;border-top:1px solid #f1f5f9;background:#f8fafc;display:flex;justify-content:flex-end;gap:10px;border-radius:0 0 16px 16px;position:sticky;bottom:0; }

.field-row { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
.field.full { grid-column:span 2; }
.field-label { display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:5px; }
.req { color:#ef4444; }
.field-input { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.875rem;color:#1e293b;background:#fff;outline:none;transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
.field-input:focus { border-color:#3b82f6;box-shadow:0 0 0 3px #3b82f620; }
textarea.field-input { resize:vertical; }
.field-error { font-size:.75rem;color:#ef4444;margin-top:4px; }

.dispose-warning { background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:10px 14px;font-size:.8rem;color:#92400e;line-height:1.5; }

.cat-row { display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px; }

.import-hint { background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 14px;font-size:.78rem;color:#0369a1;line-height:1.6; }
.template-link { display:inline-flex;align-items:center;gap:6px;font-size:.8rem;color:#3b82f6;text-decoration:none;font-weight:500; }
.template-link:hover { text-decoration:underline; }
</style>
