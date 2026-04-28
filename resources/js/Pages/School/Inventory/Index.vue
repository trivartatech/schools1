<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Table from '@/Components/ui/Table.vue';
import ExportDropdown from '@/Components/ExportDropdown.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { useConfirm } from '@/Composables/useConfirm';

const confirm = useConfirm();

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
const deleteCategory = async (c) => {
    const ok = await confirm({
        title: 'Delete category?',
        message: `"${c.name}" will be permanently removed. This cannot be undone.`,
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
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
const returnAsset = async (id) => {
    const ok = await confirm({
        title: 'Return asset?',
        message: 'Mark this asset as returned to inventory? Any active assignment will be closed.',
        confirmLabel: 'Return',
    });
    if (!ok) return;
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

// Stat cards data — replaces the old `.stats-row` 4-up custom layout.
const statCards = computed(() => [
    {
        label: 'Available',
        value: props.stats?.available?.count ?? 0,
        sub: fmtCost(props.stats?.available?.total_cost),
        color: 'success',
        icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>',
    },
    {
        label: 'Assigned',
        value: props.stats?.assigned?.count ?? 0,
        sub: fmtCost(props.stats?.assigned?.total_cost),
        color: 'info',
        icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/></svg>',
    },
    {
        label: 'In Maintenance',
        value: props.openMaintenance,
        sub: 'open tickets',
        color: 'warning',
        icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    },
    {
        label: 'Disposed',
        value: props.stats?.disposed?.count ?? 0,
        sub: 'written off',
        color: 'gray',
        icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>',
    },
]);
</script>

<template>
    <SchoolLayout title="Inventory">

        <PageHeader title="Inventory & Assets" subtitle="Track school assets, assignments and maintenance records.">
            <template #actions>
                <Button as="link" variant="secondary" size="sm" href="/school/inventory-suppliers">Suppliers</Button>
                <Button as="link" variant="secondary" size="sm" href="/school/inventory-stores">Stores</Button>
                <Button as="link" variant="secondary" size="sm" href="/school/inventory/reports">Reports</Button>
                <ExportDropdown base-url="/school/inventory/export" :params="{ status: statusF, category_id: categoryF }" />
                <Button variant="secondary" size="sm" @click="showImport = true">Import</Button>
                <Button variant="secondary" size="sm" @click="showManageCats = true">Categories</Button>
                <Button size="sm" @click="showAdd = true">+ Add Asset</Button>
            </template>
        </PageHeader>

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
        <StatsRow :cols="4" :stats="statCards" />

        <!-- Filters -->
        <FilterBar
            :active="!!(search || categoryF || statusF)"
            @clear="search = ''; categoryF = ''; statusF = '';"
        >
            <div class="fb-search">
                <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                <input v-model="search" type="search" placeholder="Search name, code, serial…" />
            </div>
            <select v-model="categoryF" style="width:200px;">
                <option value="">All Categories</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }} ({{ c.assets_count }})</option>
            </select>
            <select v-model="statusF" style="width:170px;">
                <option value="">All Statuses</option>
                <option value="available">Available</option>
                <option value="assigned">Assigned</option>
                <option value="under_maintenance">Under Maintenance</option>
                <option value="disposed">Disposed</option>
            </select>
        </FilterBar>

        <!-- Table -->
        <div class="card" style="overflow:hidden;">
            <Table :empty="!assets.data?.length">
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
                </tbody>
                <template #empty>
                    <EmptyState
                        title="No assets found"
                        description="Add your first asset, or adjust filters to see existing ones."
                        action-label="+ Add Asset"
                        @action="showAdd = true"
                    />
                </template>
            </Table>

            <div v-if="assets.last_page > 1" class="pagination">
                <a v-for="p in assets.links" :key="p.label" :href="p.url ?? '#'" v-html="p.label"
                   :class="['page-link', { active: p.active, disabled: !p.url }]"></a>
            </div>
        </div>

        <!-- ── Add Asset Modal ─────────────────────────────────────────────── -->
        <Modal v-model:open="showAdd" title="Add New Asset" size="lg">
            <form @submit.prevent="submitAdd" id="add-asset-form">
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
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showAdd = false">Cancel</Button>
                <Button type="submit" form="add-asset-form" :loading="addForm.processing">Add Asset</Button>
            </template>
        </Modal>

        <!-- ── Edit Asset Modal ────────────────────────────────────────────── -->
        <Modal v-model:open="showEdit" :title="editTarget ? `Edit Asset — ${editTarget.name}` : 'Edit Asset'" size="lg">
            <form @submit.prevent="submitEdit" id="edit-asset-form">
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
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showEdit = false">Cancel</Button>
                <Button type="submit" form="edit-asset-form" :loading="editForm.processing">Save Changes</Button>
            </template>
        </Modal>

        <!-- ── Manage Categories Modal ─────────────────────────────────────── -->
        <Modal v-model:open="showManageCats" :title="`Manage Categories (${categories.length})`" size="md">
            <div style="display:flex;flex-direction:column;gap:8px;">
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
            </div>
            <template #footer>
                <Button variant="secondary" @click="showManageCats = false">Close</Button>
                <Button @click="showManageCats = false; showCat = true">+ Add New Category</Button>
            </template>
        </Modal>

        <!-- ── Edit Category Modal ─────────────────────────────────────────── -->
        <Modal v-model:open="showEditCat" :title="editCatTarget ? `Edit Category — ${editCatTarget.name}` : 'Edit Category'" size="sm">
            <form @submit.prevent="submitEditCat" id="edit-cat-form">
                <div class="field full"><label class="field-label">Name <span class="req">*</span></label><input v-model="editCatForm.name" class="field-input" required /></div>
                <div class="field full"><label class="field-label">Description</label><input v-model="editCatForm.description" class="field-input" /></div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showEditCat = false">Cancel</Button>
                <Button type="submit" form="edit-cat-form" :loading="editCatForm.processing">Save</Button>
            </template>
        </Modal>

        <!-- ── Add Category Modal ──────────────────────────────────────────── -->
        <Modal v-model:open="showCat" title="New Category" size="sm">
            <form @submit.prevent="submitCat" id="add-cat-form">
                <div class="field full"><label class="field-label">Category Name <span class="req">*</span></label><input v-model="catForm.name" class="field-input" required /></div>
                <div class="field full"><label class="field-label">Description</label><input v-model="catForm.description" class="field-input" /></div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showCat = false">Cancel</Button>
                <Button type="submit" form="add-cat-form" :loading="catForm.processing">Create</Button>
            </template>
        </Modal>

        <!-- ── CSV Import Modal ────────────────────────────────────────────── -->
        <Modal v-model:open="showImport" title="Import Assets from CSV" size="md">
            <form @submit.prevent="submitImport" enctype="multipart/form-data" id="import-form">
                <div class="import-hint">
                    Columns: Code, Name*, Category*, Brand, Serial No, Purchase Date (YYYY-MM-DD), Cost, Useful Life (yrs), Depreciation Method, Condition, Notes
                </div>
                <a href="/school/inventory/import-template" class="template-link" style="margin-top:10px;display:inline-flex;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download template CSV
                </a>
                <div class="field full" style="margin-top:14px;">
                    <label class="field-label">CSV File <span class="req">*</span></label>
                    <input type="file" accept=".csv,.txt" class="field-input" @change="onImportFile" required />
                    <p v-if="importForm.errors.file" class="field-error">{{ importForm.errors.file }}</p>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showImport = false">Cancel</Button>
                <Button type="submit" form="import-form" :loading="importForm.processing">Import</Button>
            </template>
        </Modal>

        <!-- ── Assign Modal ────────────────────────────────────────────────── -->
        <Modal v-model:open="showAssign" :title="assignTarget ? `Assign — ${assignTarget.name}` : 'Assign Asset'" size="md">
            <form @submit.prevent="submitAssign" id="assign-form">
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
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showAssign = false">Cancel</Button>
                <Button type="submit" form="assign-form" :loading="assignForm.processing">Assign</Button>
            </template>
        </Modal>

        <!-- ── Maintenance Modal ───────────────────────────────────────────── -->
        <Modal v-model:open="showMaint" :title="maintTarget ? `Log Issue — ${maintTarget.name}` : 'Log Issue'" size="sm">
            <form @submit.prevent="submitMaint" id="maint-form">
                <div class="field full"><label class="field-label">Issue Description <span class="req">*</span></label><textarea v-model="maintForm.issue_description" class="field-input" rows="3" required></textarea></div>
                <div class="field-row">
                    <div class="field"><label class="field-label">Type <span class="req">*</span></label><select v-model="maintForm.type" class="field-input" required><option value="corrective">Corrective</option><option value="preventive">Preventive</option><option value="inspection">Inspection</option></select></div>
                    <div class="field"><label class="field-label">Reported On <span class="req">*</span></label><input v-model="maintForm.reported_on" class="field-input" type="date" required /></div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showMaint = false">Cancel</Button>
                <Button type="submit" form="maint-form" :loading="maintForm.processing">Log Issue</Button>
            </template>
        </Modal>

        <!-- ── Resolve Modal ───────────────────────────────────────────────── -->
        <Modal v-model:open="showResolve" :title="resolveTarget ? `Resolve — ${resolveTarget.issue_description}` : 'Resolve Maintenance'" size="sm">
            <form @submit.prevent="submitResolve" id="resolve-form">
                <div class="field full"><label class="field-label">Resolution Notes</label><textarea v-model="resolveForm.resolution_notes" class="field-input" rows="3" placeholder="Describe what was done…"></textarea></div>
                <div class="field-row">
                    <div class="field"><label class="field-label">Repair Cost (₹)</label><input v-model="resolveForm.cost" class="field-input" type="number" min="0" step="0.01" /></div>
                    <div class="field"><label class="field-label">Vendor</label><input v-model="resolveForm.vendor" class="field-input" /></div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showResolve = false">Cancel</Button>
                <Button type="submit" form="resolve-form" :loading="resolveForm.processing">Mark Resolved</Button>
            </template>
        </Modal>

        <!-- ── Dispose Modal ──────────────────────────────────────────────── -->
        <Modal v-model:open="showDispose" :title="disposeTarget ? `Dispose — ${disposeTarget.name}` : 'Dispose Asset'" size="sm">
            <form @submit.prevent="submitDispose" id="dispose-form">
                <div class="dispose-warning">This will permanently mark the asset as disposed, close any open assignments, and scrap open maintenance tickets.</div>
                <div class="field full" style="margin-top:14px;"><label class="field-label">Disposal Date <span class="req">*</span></label><input v-model="disposeForm.disposed_on" class="field-input" type="date" required /></div>
                <div class="field full"><label class="field-label">Reason / Notes</label><textarea v-model="disposeForm.disposal_reason" class="field-input" rows="2" placeholder="e.g. Beyond repair, end of life…"></textarea></div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showDispose = false">Cancel</Button>
                <Button variant="danger" type="submit" form="dispose-form" :loading="disposeForm.processing">Confirm Disposal</Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
.warranty-banner { display:flex;align-items:center;gap:10px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:10px 16px;margin-bottom:16px;font-size:.85rem;color:#92400e; }

.card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05); }

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

/* Table-row action chips — local visual language for row-scoped actions
   (different from page-level <Button>; intentional). */
.act-btn { font-size:.72rem;font-weight:600;padding:4px 10px;border-radius:6px;border:none;cursor:pointer;transition:opacity .15s;white-space:nowrap; }
.act-btn:hover { opacity:.8; }
.act-btn:disabled { opacity:.4;cursor:not-allowed; }
.act-blue   { background:#dbeafe;color:#2563eb; }
.act-gray   { background:#f1f5f9;color:#475569; }
.act-amber  { background:#fef3c7;color:#d97706; }
.act-green  { background:#dcfce7;color:#16a34a; }
.act-red    { background:#fee2e2;color:#dc2626; }
.act-purple { background:#ede9fe;color:#7c3aed; }

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

/* Modal form fields — Tailwind preflight workaround. Scoped to this page;
   data-v travels with teleported slot content so styles still reach inputs
   inside <Modal>. */
.field-row { display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px; }
.field-row:first-child { margin-top:0; }
.field { display:flex;flex-direction:column; }
.field.full { grid-column:span 2;margin-top:14px; }
.field.full:first-child { margin-top:0; }
.field-label { display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:5px; }
.req { color:#ef4444; }
.field-input { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.875rem;color:#1e293b;background:#fff;outline:none;transition:border-color .15s,box-shadow .15s;box-sizing:border-box;font-family:inherit; }
.field-input:focus { border-color:#3b82f6;box-shadow:0 0 0 3px #3b82f620; }
textarea.field-input { resize:vertical; }
.field-error { font-size:.75rem;color:#ef4444;margin-top:4px; }

.dispose-warning { background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:10px 14px;font-size:.8rem;color:#92400e;line-height:1.5; }

.cat-row { display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px; }

.import-hint { background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 14px;font-size:.78rem;color:#0369a1;line-height:1.6; }
.template-link { display:inline-flex;align-items:center;gap:6px;font-size:.8rem;color:#3b82f6;text-decoration:none;font-weight:500; }
.template-link:hover { text-decoration:underline; }
</style>
