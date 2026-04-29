<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Table from '@/Components/ui/Table.vue';
import SortableTh from '@/Components/ui/SortableTh.vue';
import { useTableSort } from '@/Composables/useTableSort';
import { useConfirm } from '@/Composables/useConfirm';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();
const confirm = useConfirm();

const props = defineProps({
    store:     Object,
    suppliers: Array,
});

const fmt    = (n) => school.fmtMoney(n, { fixed: true });
const fmtQty = (n) => Number(n).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
const isLow  = (item) => Number(item.min_quantity) > 0 && Number(item.quantity) <= Number(item.min_quantity);

const lowStockItems = computed(() => (props.store.items || []).filter(isLow));
const totalValue    = computed(() =>
    (props.store.items || []).reduce((s, i) => s + Number(i.quantity) * Number(i.unit_price), 0)
);

// ── Table sort ────────────────────────────────────────────────────────────
const { sortKey, sortDir, toggleSort, sortRows } = useTableSort('name', 'asc');
const sortedItems = computed(() => sortRows(props.store.items || [], {
    getValue: (row, key) => {
        if (key === 'supplier_name') return row.supplier?.name ?? '';
        if (key === 'quantity') return Number(row.quantity);
        if (key === 'min_quantity') return Number(row.min_quantity);
        if (key === 'unit_price') return Number(row.unit_price);
        if (key === 'stock_value') return Number(row.quantity) * Number(row.unit_price);
        return row[key];
    },
}));

// ── Item CRUD ─────────────────────────────────────────────────────────────────
const showItemModal = ref(false);
const editingItem   = ref(null);
const itemForm = useForm({ name: '', unit: 'pcs', supplier_id: '', quantity: '', min_quantity: '', unit_price: '', notes: '' });

function openAddItem() {
    editingItem.value = null;
    itemForm.reset();
    itemForm.unit = 'pcs';
    showItemModal.value = true;
}
function openEditItem(item) {
    editingItem.value    = item;
    itemForm.name        = item.name        || '';
    itemForm.unit        = item.unit        || 'pcs';
    itemForm.supplier_id = item.supplier_id || '';
    itemForm.min_quantity= item.min_quantity|| '';
    itemForm.unit_price  = item.unit_price  || '';
    itemForm.notes       = item.notes       || '';
    showItemModal.value  = true;
}
function submitItemForm() {
    const opts = { preserveScroll: true, onSuccess: () => { showItemModal.value = false; } };
    editingItem.value
        ? itemForm.put(`/school/inventory-stores/items/${editingItem.value.id}`, opts)
        : itemForm.post(`/school/inventory-stores/${props.store.id}/items`, opts);
}

// ── Delete item ───────────────────────────────────────────────────────────────
const deleteItemForm   = useForm({});
async function deleteItem(item) {
    const ok = await confirm({
        title: 'Delete item?',
        message: `Delete ${item.name}? All transaction history for this item will also be permanently removed.`,
        confirmLabel: 'Delete Item',
        danger: true,
    });
    if (!ok) return;
    deleteItemForm.delete(`/school/inventory-stores/items/${item.id}`, {
        preserveScroll: true,
    });
}

// ── Transactions ──────────────────────────────────────────────────────────────
const txnTarget = ref(null);
const txnForm   = useForm({ type: 'in', quantity: '', transaction_date: '', reference: '', notes: '' });

function openTxn(item, type) {
    txnTarget.value          = item;
    txnForm.type             = type;
    txnForm.quantity         = '';
    txnForm.transaction_date = new Date().toISOString().slice(0, 10);
    txnForm.reference        = '';
    txnForm.notes            = '';
}
function submitTxn() {
    txnForm.post(`/school/inventory-stores/items/${txnTarget.value.id}/transaction`, {
        preserveScroll: true,
        onSuccess: () => { txnTarget.value = null; },
    });
}

const showTxnModal = computed({
    get: () => txnTarget.value !== null,
    set: (v) => { if (!v) txnTarget.value = null; },
});
</script>

<template>
    <SchoolLayout :title="store.name">

        <PageHeader :title="store.name" :breadcrumbs="[
            { label: 'Inventory', href: '/school/inventory' },
            { label: 'Stores', href: '/school/inventory-stores' },
            { label: store.name },
        ]">
            <template #subtitle>
                <p v-if="store.location" style="color:#64748b;font-size:.875rem;margin-top:2px;">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:3px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ store.location }}
                </p>
            </template>
            <template #actions>
                <Button @click="openAddItem">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Item
                </Button>
            </template>
        </PageHeader>

        <!-- Flash -->
        <div v-if="$page.props.flash?.success" class="flash-success">{{ $page.props.flash.success }}</div>
        <div v-if="$page.props.errors?.quantity" class="flash-error">{{ $page.props.errors.quantity }}</div>

        <!-- Low stock banner -->
        <div v-if="lowStockItems.length" class="low-stock-banner">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>
                <strong>{{ lowStockItems.length }} item{{ lowStockItems.length > 1 ? 's' : '' }}</strong> below minimum stock:
                {{ lowStockItems.map(i => i.name).join(', ') }}
            </span>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card stat-blue">
                <div class="stat-icon"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 10V7"/></svg></div>
                <div>
                    <div class="stat-label">Item Types</div>
                    <div class="stat-value" style="color:#3b82f6;">{{ store.items?.length ?? 0 }}</div>
                    <div class="stat-sub">in this store</div>
                </div>
            </div>
            <div class="stat-card stat-green">
                <div class="stat-icon"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                <div>
                    <div class="stat-label">Total Stock Value</div>
                    <div class="stat-value" style="color:#10b981;">{{ fmt(totalValue) }}</div>
                    <div class="stat-sub">at current prices</div>
                </div>
            </div>
            <div class="stat-card stat-amber">
                <div class="stat-icon"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></div>
                <div>
                    <div class="stat-label">Low Stock Alerts</div>
                    <div class="stat-value" style="color:#f59e0b;">{{ lowStockItems.length }}</div>
                    <div class="stat-sub">items need restocking</div>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="card" style="overflow:hidden;">
            <Table :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
                <thead>
                    <tr>
                        <SortableTh sort-key="name">Item Name</SortableTh>
                        <SortableTh sort-key="supplier_name">Supplier</SortableTh>
                        <SortableTh sort-key="quantity" align="right">Qty in Stock</SortableTh>
                        <SortableTh sort-key="unit">Unit</SortableTh>
                        <SortableTh sort-key="min_quantity" align="right">Min Stock</SortableTh>
                        <SortableTh sort-key="unit_price" align="right">Unit Price</SortableTh>
                        <SortableTh sort-key="stock_value" align="right">Stock Value</SortableTh>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in sortedItems" :key="item.id" :class="{ 'row-low': isLow(item) }">
                        <td>
                            <div style="font-weight:600;font-size:.875rem;color:#1e293b;">{{ item.name }}</div>
                            <span v-if="isLow(item)" style="font-size:.68rem;font-weight:700;background:#fee2e2;color:#dc2626;padding:1px 7px;border-radius:10px;">Low Stock</span>
                        </td>
                        <td>{{ item.supplier?.name || '—' }}</td>
                        <td style="text-align:right;">
                            <span :style="{ fontWeight: 700, fontSize: '.9rem', color: isLow(item) ? '#dc2626' : '#1e293b' }">{{ fmtQty(item.quantity) }}</span>
                        </td>
                        <td>{{ item.unit }}</td>
                        <td style="text-align:right;">
                            {{ item.min_quantity > 0 ? fmtQty(item.min_quantity) : '—' }}
                        </td>
                        <td style="text-align:right;">
                            {{ item.unit_price > 0 ? fmt(item.unit_price) : '—' }}
                        </td>
                        <td style="text-align:right;font-weight:600;color:#1e293b;">
                            {{ item.unit_price > 0 ? fmt(Number(item.quantity) * Number(item.unit_price)) : '—' }}
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;justify-content:flex-end;flex-wrap:wrap;">
                                <button class="act-btn act-green" @click="openTxn(item, 'in')" title="Stock In">+ In</button>
                                <button class="act-btn act-orange" @click="openTxn(item, 'out')" title="Stock Out">− Out</button>
                                <button class="act-btn act-blue" @click="openEditItem(item)">Edit</button>
                                <button class="act-btn act-red" @click="deleteItem(item)">Del</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!sortedItems.length">
                        <td colspan="8" class="empty-row">
                            <svg width="40" height="40" fill="none" stroke="#cbd5e1" viewBox="0 0 24 24" style="margin-bottom:8px;display:block;margin-inline:auto;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 10V7"/></svg>
                            No items yet. Add your first item to start tracking stock.
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="sortedItems.length">
                    <tr>
                        <td colspan="6" style="text-align:right;font-weight:700;">Total Stock Value</td>
                        <td style="text-align:right;font-weight:800;color:#1e293b;">{{ fmt(totalValue) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </Table>
        </div>

        <!-- Add / Edit Item Modal -->
        <Modal v-model:open="showItemModal" :title="editingItem ? 'Edit Item' : 'Add Item'" size="md">
            <form @submit.prevent="submitItemForm" id="store-item-form">
                <div class="field full">
                    <label class="field-label">Item Name <span class="req">*</span></label>
                    <input v-model="itemForm.name" class="field-input" required placeholder="e.g. A4 Paper, Marker, Chalk Box" />
                </div>
                <div class="field-row" style="margin-top:14px;">
                    <div class="field">
                        <label class="field-label">Unit <span class="req">*</span></label>
                        <input v-model="itemForm.unit" class="field-input" placeholder="pcs / kg / L / box" required />
                    </div>
                    <div class="field">
                        <label class="field-label">Supplier</label>
                        <select v-model="itemForm.supplier_id" class="field-input">
                            <option value="">— None —</option>
                            <option v-for="sup in suppliers" :key="sup.id" :value="sup.id">{{ sup.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="field-row" style="margin-top:14px;">
                    <div v-if="!editingItem" class="field">
                        <label class="field-label">Opening Qty</label>
                        <input v-model="itemForm.quantity" type="number" min="0" step="0.01" class="field-input" placeholder="0" />
                    </div>
                    <div class="field">
                        <label class="field-label">Min Stock Alert</label>
                        <input v-model="itemForm.min_quantity" type="number" min="0" step="0.01" class="field-input" placeholder="0" />
                    </div>
                    <div class="field">
                        <label class="field-label">Unit Price (₹)</label>
                        <input v-model="itemForm.unit_price" type="number" min="0" step="0.01" class="field-input" placeholder="0.00" />
                    </div>
                </div>
                <div class="field full" style="margin-top:14px;">
                    <label class="field-label">Notes</label>
                    <textarea v-model="itemForm.notes" class="field-input" rows="2"></textarea>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showItemModal = false">Cancel</Button>
                <Button type="submit" form="store-item-form" :loading="itemForm.processing">
                    {{ editingItem ? 'Update Item' : 'Add Item' }}
                </Button>
            </template>
        </Modal>

        <!-- Stock In / Out Modal -->
        <Modal v-model:open="showTxnModal" :title="`Stock ${txnForm.type === 'in' ? 'In' : 'Out'}`" size="sm">
            <p v-if="txnTarget" style="font-size:.82rem;color:#64748b;margin:0 0 14px;">{{ txnTarget.name }}</p>
            <form v-if="txnTarget" @submit.prevent="submitTxn" id="store-txn-form">
                <div class="txn-current">
                    Current stock: <strong>{{ fmtQty(txnTarget.quantity) }} {{ txnTarget.unit }}</strong>
                    <span v-if="isLow(txnTarget)" style="margin-left:8px;font-size:.72rem;font-weight:700;background:#fee2e2;color:#dc2626;padding:1px 6px;border-radius:10px;">Low</span>
                </div>
                <div class="field-row" style="margin-top:14px;">
                    <div class="field">
                        <label class="field-label">Quantity <span class="req">*</span></label>
                        <input v-model="txnForm.quantity" type="number" min="0.01" step="0.01" class="field-input" required placeholder="0.00" />
                    </div>
                    <div class="field">
                        <label class="field-label">Date <span class="req">*</span></label>
                        <input v-model="txnForm.transaction_date" type="date" class="field-input" required />
                    </div>
                </div>
                <div class="field full" style="margin-top:14px;">
                    <label class="field-label">Reference</label>
                    <input v-model="txnForm.reference" class="field-input" placeholder="PO no., invoice, dept request…" />
                </div>
                <div class="field full" style="margin-top:14px;">
                    <label class="field-label">Notes</label>
                    <textarea v-model="txnForm.notes" class="field-input" rows="2"></textarea>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="txnTarget = null">Cancel</Button>
                <Button type="submit" form="store-txn-form" :loading="txnForm.processing">
                    {{ txnForm.type === 'in' ? 'Confirm Stock In' : 'Confirm Stock Out' }}
                </Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
.flash-success { background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;border-radius:10px;padding:10px 16px;font-size:.85rem;margin-bottom:16px; }
.flash-error   { background:#fef2f2;border:1px solid #fecaca;color:#dc2626;border-radius:10px;padding:10px 16px;font-size:.85rem;margin-bottom:16px; }

.low-stock-banner { display:flex;align-items:center;gap:10px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:10px 16px;margin-bottom:16px;font-size:.85rem;color:#92400e; }

.stats-row { display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px; }
@media (max-width:900px) { .stats-row { grid-template-columns:1fr 1fr; } }
.stat-card { display:flex;align-items:flex-start;gap:14px;background:#fff;border-radius:12px;padding:18px 20px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,.05); }
.stat-icon  { width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.stat-green .stat-icon { background:#dcfce7;color:#16a34a; }
.stat-blue  .stat-icon { background:#dbeafe;color:#2563eb; }
.stat-amber .stat-icon { background:#fef3c7;color:#d97706; }
.stat-label { font-size:.7rem;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em; }
.stat-value { font-size:1.5rem;font-weight:800;line-height:1.1;margin-top:2px; }
.stat-sub   { font-size:.72rem;color:#94a3b8;margin-top:2px; }

.card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05); }
:deep(tfoot td) { background:#f8fafc;border-top:2px solid #e2e8f0;border-bottom:none !important; }
.row-low :deep(td) { background:rgba(239,68,68,.04); }

.act-btn { font-size:.72rem;font-weight:600;padding:4px 10px;border-radius:6px;border:none;cursor:pointer;transition:opacity .15s;white-space:nowrap; }
.act-btn:hover { opacity:.8; }
.act-btn:disabled { opacity:.4;cursor:not-allowed; }
.act-green  { background:#dcfce7;color:#16a34a; }
.act-orange { background:#fff7ed;color:#c2410c; }
.act-blue   { background:#dbeafe;color:#2563eb; }
.act-red    { background:#fee2e2;color:#dc2626; }

.empty-row { text-align:center;padding:48px 24px;color:#94a3b8;font-size:.9rem; }

.txn-current { background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;font-size:.85rem;color:#374151; }

.field-row  { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
.field.full { grid-column:span 2; }
.field-label { display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:5px; }
.req         { color:#ef4444; }
.field-input { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.875rem;color:#1e293b;background:#fff;outline:none;transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
.field-input:focus { border-color:#3b82f6;box-shadow:0 0 0 3px #3b82f620; }
textarea.field-input { resize:vertical; }
.field-error { font-size:.75rem;color:#ef4444;margin-top:4px; }
</style>
