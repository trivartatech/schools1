<script setup>
import Button from '@/Components/ui/Button.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import ExportDropdown from '@/Components/ExportDropdown.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    transactions : Object,   // paginated
    ledgers      : Array,
    filters      : Object,
    perPage      : Number,
});

const filterType   = ref(props.filters.type   ?? '');
const filterStatus = ref(props.filters.status ?? '');
const filterFrom   = ref(props.filters.from   ?? '');
const filterTo     = ref(props.filters.to     ?? '');

function applyFilter() {
    router.get(route('school.finance.transactions.index'), {
        type: filterType.value, status: filterStatus.value,
        from: filterFrom.value, to: filterTo.value,
    }, { preserveState: true, replace: true });
}

function resetFilter() {
    filterType.value = ''; filterStatus.value = ''; filterFrom.value = ''; filterTo.value = '';
    applyFilter();
}

function deleteTransaction(id) {
    if (!confirm('Delete this transaction? This cannot be undone.')) return;
    router.delete(route('school.finance.transactions.destroy', id), { preserveScroll: true });
}

const txnList     = computed(() => props.transactions?.data ?? []);
const totalAmount = computed(() => txnList.value.reduce((s, t) => s + t.total_amount, 0));

function exportCsv() {
    const params = new URLSearchParams({
        export: 'csv',
        ...(filterType.value   && { type:   filterType.value }),
        ...(filterStatus.value && { status: filterStatus.value }),
        ...(filterFrom.value   && { from:   filterFrom.value }),
        ...(filterTo.value     && { to:     filterTo.value }),
    });
    window.location.href = route('school.finance.transactions.index') + '?' + params.toString();
}

const fmt    = (n) => new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2 }).format(n);
const fmtCur = (n) => '₹' + fmt(n);

const typeColors = {
    journal : 'pill-journal',
    receipt : 'pill-receipt',
    payment : 'pill-payment',
    contra  : 'pill-contra',
};

const statusClass = {
    draft  : 'status-draft',
    posted : 'status-posted',
    void   : 'status-void',
};
</script>

<template>
    <SchoolLayout>
        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Transactions</h1>
                <p class="page-header-sub">Journal entries &amp; vouchers for all accounting transactions</p>
            </div>
            <div style="display:flex;gap:10px;">
                <ExportDropdown
                    base-url="/school/export/transactions"
                    :params="{ type: filterType, status: filterStatus, from: filterFrom, to: filterTo }"
                />
                <Button as="link" :href="route('school.finance.transactions.create')">
                    + New Transaction
                </Button>
            </div>
        </div>

        <!-- Stats bar -->
        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-label">This Page</span>
                <span class="stat-value">{{ txnList.length }}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Total Records</span>
                <span class="stat-value">{{ transactions.total ?? txnList.length }}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Page Amount</span>
                <span class="stat-value">{{ fmtCur(totalAmount) }}</span>
            </div>
            <div class="stat-item" v-for="type in ['journal','receipt','payment','contra']" :key="type">
                <span class="stat-label">{{ type.charAt(0).toUpperCase() + type.slice(1) }}s</span>
                <span class="stat-value">{{ txnList.filter(t=>t.type===type).length }}</span>
            </div>
        </div>

        <!-- Filters -->
        <FilterBar :active="!!(filterType || filterStatus || filterFrom || filterTo)" @clear="resetFilter">
            <select v-model="filterType" @change="applyFilter" style="width:140px;">
                <option value="">All Types</option>
                <option value="journal">Journal</option>
                <option value="receipt">Receipt</option>
                <option value="payment">Payment</option>
                <option value="contra">Contra</option>
            </select>
            <select v-model="filterStatus" @change="applyFilter" style="width:130px;">
                <option value="">All Status</option>
                <option value="draft">Draft</option>
                <option value="posted">Posted</option>
                <option value="void">Void</option>
            </select>
            <input type="date" v-model="filterFrom" @change="applyFilter" style="width:150px;">
            <input type="date" v-model="filterTo" @change="applyFilter" style="width:150px;">
        </FilterBar>

        <!-- Table -->
        <div class="card overflow-hidden" style="margin-bottom:12px;">
            <Table class="data-table" :empty="txnList.length === 0">
                <thead>
                        <tr>
                            <th>Txn #</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Narration</th>
                            <th>Ref #</th>
                            <th class="text-right">Amount</th>
                            <th>Lines</th>
                            <th>By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in txnList" :key="t.id" :class="{ 'row-void': t.status === 'void', 'row-draft': t.status === 'draft' }">
                            <td>
                                <Link :href="route('school.finance.transactions.show', t.id)" class="txn-no">
                                    {{ t.transaction_no }}
                                </Link>
                            </td>
                            <td class="mono date-col">{{ t.date }}</td>
                            <td>
                                <span class="type-pill" :class="typeColors[t.type]">{{ t.type }}</span>
                            </td>
                            <td>
                                <span class="status-pill" :class="statusClass[t.status]">{{ t.status }}</span>
                            </td>
                            <td class="narration">{{ t.narration || '—' }}</td>
                            <td class="mono text-muted">{{ t.reference_no || '—' }}</td>
                            <td class="text-right mono">{{ fmtCur(t.total_amount) }}</td>
                            <td class="text-center">
                                <span class="lines-badge">{{ t.lines_count }}</span>
                            </td>
                            <td class="text-muted">{{ t.created_by ?? '—' }}</td>
                            <td>
                                <div class="row-actions">
                                    <Button as="link" variant="icon" size="xs" :href="route('school.finance.transactions.show', t.id)" class="act-view" title="View">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </Button>
                                    <Button v-if="t.status !== 'void'" as="link" variant="icon" size="xs" :href="route('school.finance.transactions.edit', t.id)" title="Edit">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </Button>
                                    <Button v-if="t.status !== 'void'" variant="icon" size="xs" class="act-del" @click="deleteTransaction(t.id)" title="Delete">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                                        </svg>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                <template #empty>
                    <p class="empty-cell">No transactions found. <Link :href="route('school.finance.transactions.create')" class="link">Create one</Link></p>
                </template>
            </Table>
        </div>
        <!-- Pagination -->
        <div v-if="transactions.last_page > 1" class="pagination-bar">
            <span class="page-info">
                Showing {{ transactions.from }}–{{ transactions.to }} of {{ transactions.total }} entries
            </span>
            <div class="page-btns">
                <Link
                    v-for="link in transactions.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    :class="['page-btn', { 'page-active': link.active, 'page-disabled': !link.url }]"
                    preserve-scroll
                    v-html="link.label"
                />
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.stats-bar {
    display: flex; flex-wrap: wrap; gap: 12px;
    background: #fff; border: 1.5px solid #e2e8f0; border-radius: 12px;
    padding: 14px 20px; margin-bottom: 16px;
}
.stat-item  { display: flex; flex-direction: column; gap: 2px; padding: 0 16px; border-right: 1px solid #f1f5f9; }
.stat-item:first-child { padding-left: 0; }
.stat-item:last-child  { border-right: none; }
.stat-label { font-size: 0.72rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.stat-value { font-size: 1.1rem; font-weight: 800; color: #1e293b; }

.filter-label { display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 4px; }
.filter-input {
    border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 7px 12px;
    font-size: 0.84rem; outline: none; font-family: inherit; color: #1e293b;
}
.filter-input:focus { border-color: #6366f1; }

.txn-no { color: #6366f1; font-weight: 700; text-decoration: none; font-family: monospace; }
.txn-no:hover { text-decoration: underline; }

.mono     { font-family: 'Courier New', monospace; font-size: 0.82rem; }
.date-col { color: #475569; }
.text-right  { text-align: right; }
.text-center { text-align: center; }
.text-muted  { color: #94a3b8; font-size: 0.8rem; }
.narration   { color: #475569; font-size: 0.82rem; max-width: 200px; }

.type-pill { font-size: 0.68rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; text-transform: capitalize; }
.pill-journal { background: #ede9fe; color: #6366f1; }
.pill-receipt { background: #d1fae5; color: #059669; }
.pill-payment { background: #fee2e2; color: #dc2626; }
.pill-contra  { background: #fef3c7; color: #d97706; }

.status-pill { font-size: 0.65rem; font-weight: 700; padding: 2px 7px; border-radius: 20px; text-transform: capitalize; }
.status-draft  { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
.status-posted { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
.status-void   { background: #f1f5f9; color: #94a3b8; border: 1px solid #cbd5e1; text-decoration: line-through; }

.row-void td  { opacity: 0.55; }
.row-draft td { background: #fffbeb; }

.pagination-bar {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px; padding: 10px 4px;
}
.page-info { font-size: 0.8rem; color: #64748b; }
.page-btns { display: flex; flex-wrap: wrap; gap: 4px; }
.page-btn {
    min-width: 32px; height: 32px; padding: 0 10px;
    border: 1.5px solid #e2e8f0; background: #fff; border-radius: 7px;
    font-size: 0.8rem; color: #374151; text-decoration: none;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s;
}
.page-btn:hover    { background: #ede9fe; border-color: #c4b5fd; color: #6366f1; }
.page-active       { background: #6366f1 !important; border-color: #6366f1 !important; color: #fff !important; font-weight: 700; }
.page-disabled     { opacity: 0.4; pointer-events: none; }

.lines-badge {
    background: #f1f5f9; color: #475569;
    font-size: 0.72rem; font-weight: 700;
    padding: 2px 7px; border-radius: 20px;
}
.row-actions { display: flex; gap: 6px; align-items: center; }
.act-view:hover     { background: #dbeafe; border-color: #93c5fd; color: #1d4ed8; }
.act-del:hover      { background: #fee2e2; border-color: #fca5a5; color: #dc2626; }
.empty-cell { text-align: center; color: #94a3b8; padding: 40px; }
.link { color: #6366f1; font-weight: 600; text-decoration: none; }
.link:hover { text-decoration: underline; }
</style>
