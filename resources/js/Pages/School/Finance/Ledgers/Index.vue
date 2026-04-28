<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import ExportDropdown from '@/Components/ExportDropdown.vue';
import { useToast } from '@/Composables/useToast';
import Table from '@/Components/ui/Table.vue';

const toast = useToast();

const props = defineProps({
    ledgers : Array,
    types   : Array,
});

// ── Filters ──────────────────────────────────────────────────
const filterType   = ref('');
const filterSearch = ref('');

const filtered = computed(() => {
    return props.ledgers.filter(l => {
        const matchType   = !filterType.value   || l.ledger_type_id == filterType.value;
        const matchSearch = !filterSearch.value || l.name.toLowerCase().includes(filterSearch.value.toLowerCase());
        return matchType && matchSearch;
    });
});

// Totals by type nature
const totalDebitBalance  = computed(() => filtered.value.filter(l => l.balance_type === 'debit') .reduce((s, l) => s + l.balance, 0));
const totalCreditBalance = computed(() => filtered.value.filter(l => l.balance_type === 'credit').reduce((s, l) => s + l.balance, 0));

// ── Modal ────────────────────────────────────────────────────
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
    ledger_type_id       : '',
    name                 : '',
    code                 : '',
    opening_balance      : '0',
    opening_balance_type : 'debit',
    description          : '',
    is_active            : true,
});

function openCreate() {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.opening_balance      = '0';
    form.opening_balance_type = 'debit';
    form.is_active            = true;
    showModal.value = true;
}

function openEdit(l) {
    isEditing.value = true;
    editingId.value = l.id;
    form.ledger_type_id       = l.ledger_type_id;
    form.name                 = l.name;
    form.code                 = l.code ?? '';
    form.opening_balance      = l.opening_balance;
    form.opening_balance_type = l.opening_balance_type;
    form.description          = l.description ?? '';
    form.is_active            = l.is_active;
    showModal.value = true;
}

function save() {
    if (isEditing.value) {
        form.put(route('school.finance.ledgers.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; },
        });
    } else {
        form.post(route('school.finance.ledgers.store'), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    }
}

function deleteLedger(l) {
    if (!confirm(`Delete ledger "${l.name}"?`)) return;
    router.delete(route('school.finance.ledgers.destroy', l.id), {
        preserveScroll: true,
        onError: (errors) => {
            toast.error(errors.error || 'Failed to delete ledger.');
        },
    });
}

function toggleActive(l) {
    router.put(route('school.finance.ledgers.update', l.id), {
        ...l,
        is_active: !l.is_active,
    }, { preserveScroll: true });
}

const fmt = (n) => new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2 }).format(n);
const fmtCur = (n) => '₹' + fmt(n);

// ── Manage Ledger Types modal ────────────────────────────────
const showTypesModal = ref(false);
const editingTypeId = ref(null);
const typeForm = useForm({
    name: '',
    nature: 'debit',
    description: '',
});

function openTypesModal() {
    showTypesModal.value = true;
    cancelTypeEdit();
}

function cancelTypeEdit() {
    editingTypeId.value = null;
    typeForm.reset();
    typeForm.nature = 'debit';
    typeForm.clearErrors();
}

function editType(t) {
    editingTypeId.value = t.id;
    typeForm.name = t.name;
    typeForm.nature = t.nature;
    typeForm.description = t.description ?? '';
}

function saveType() {
    const onDone = () => {
        cancelTypeEdit();
        router.reload({ only: ['types'], preserveScroll: true });
    };
    if (editingTypeId.value) {
        typeForm.put(route('school.finance.ledger-types.update', editingTypeId.value), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    } else {
        typeForm.post(route('school.finance.ledger-types.store'), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    }
}

function deleteType(t) {
    if (!confirm(`Delete "${t.name}"? This cannot be undone.`)) return;
    router.delete(route('school.finance.ledger-types.destroy', t.id), {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['types'], preserveScroll: true }),
    });
}
</script>

<template>
    <SchoolLayout>
        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Chart of Accounts</h1>
                <p class="page-header-sub">Manage all ledger accounts used in accounting transactions</p>
            </div>
            <div style="display:flex;gap:8px;">
                <ExportDropdown base-url="/school/export/ledgers" />
                <Button variant="secondary" @click="openTypesModal">Manage Types</Button>
                <Button @click="openCreate">+ New Ledger</Button>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="ledger-summary">
            <div class="summary-card summary-debit">
                <div class="summary-label">Total Debit Balances</div>
                <div class="summary-amount">{{ fmtCur(totalDebitBalance) }}</div>
                <div class="summary-sub">{{ filtered.filter(l=>l.balance_type==='debit').length }} accounts</div>
            </div>
            <div class="summary-card summary-credit">
                <div class="summary-label">Total Credit Balances</div>
                <div class="summary-amount">{{ fmtCur(totalCreditBalance) }}</div>
                <div class="summary-sub">{{ filtered.filter(l=>l.balance_type==='credit').length }} accounts</div>
            </div>
            <div class="summary-card summary-total">
                <div class="summary-label">Total Accounts</div>
                <div class="summary-amount">{{ filtered.length }}</div>
                <div class="summary-sub">{{ filtered.filter(l=>l.is_active).length }} active</div>
            </div>
        </div>

        <!-- Filters + Table -->
        <div class="card">
            <div class="card-body" style="border-bottom:1px solid #f1f5f9; display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                <input v-model="filterSearch" type="text" placeholder="Search ledger name…" class="filter-input" />
                <select v-model="filterType" class="filter-input" style="width:200px;">
                    <option value="">All Types</option>
                    <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <Button variant="secondary" size="sm" v-if="filterType || filterSearch" @click="filterType=''; filterSearch='';">Clear</Button>
            </div>

            <Table class="data-table" :empty="filtered.length === 0" empty-text="No ledgers found.">
                <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Opening Balance</th>
                            <th>Current Balance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="l in filtered" :key="l.id" :class="{ 'row-inactive': !l.is_active }">
                            <td><span class="code-chip">{{ l.code || '—' }}</span></td>
                            <td>
                                <div class="ledger-name">{{ l.name }}</div>
                                <div v-if="l.description" class="ledger-desc">{{ l.description }}</div>
                            </td>
                            <td>
                                <span class="type-badge" :class="l.ledger_type?.nature === 'debit' ? 'type-debit' : 'type-credit'">
                                    {{ l.ledger_type?.name ?? '—' }}
                                </span>
                            </td>
                            <td class="text-right mono">
                                {{ fmtCur(l.opening_balance) }}
                                <span class="dr-cr">{{ l.opening_balance_type === 'debit' ? 'Dr' : 'Cr' }}</span>
                            </td>
                            <td class="text-right mono">
                                <span :class="l.balance_type === 'debit' ? 'bal-debit' : 'bal-credit'">
                                    {{ fmtCur(l.balance) }}
                                    <span class="dr-cr">{{ l.balance_type === 'debit' ? 'Dr' : 'Cr' }}</span>
                                </span>
                            </td>
                            <td>
                                <button @click="toggleActive(l)" class="status-toggle" :class="l.is_active ? 'status-active' : 'status-inactive'">
                                    {{ l.is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <Button as="link" variant="icon" size="xs" :href="route('school.finance.ledgers.show', l.id)" class="act-view" title="Ledger Book">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </Button>
                                    <Button variant="icon" size="xs" @click="openEdit(l)" title="Edit">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </Button>
                                    <Button v-if="!l.is_system" variant="icon" size="xs" class="act-del" @click="deleteLedger(l)" title="Delete">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                                            <path d="M10 11v6"/><path d="M14 11v6"/>
                                        </svg>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
            </Table>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
                <div class="modal-box">
                    <div class="modal-header">
                        <h3>{{ isEditing ? 'Edit Ledger' : 'New Ledger Account' }}</h3>
                        <button class="modal-close" @click="showModal = false">×</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group flex-2">
                                <label class="form-label">Ledger Name <span class="req">*</span></label>
                                <input v-model="form.name" type="text" class="form-input" placeholder="e.g. Cash Account" />
                                <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Account Code</label>
                                <input v-model="form.code" type="text" class="form-input" placeholder="e.g. 1001" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Type <span class="req">*</span></label>
                            <select v-model="form.ledger_type_id" class="form-input">
                                <option value="">Select type…</option>
                                <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }} ({{ t.nature }})</option>
                            </select>
                            <p v-if="form.errors.ledger_type_id" class="form-error">{{ form.errors.ledger_type_id }}</p>
                        </div>
                        <div class="form-row">
                            <div class="form-group flex-2">
                                <label class="form-label">Opening Balance</label>
                                <input v-model="form.opening_balance" type="number" min="0" step="0.01" class="form-input" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">Balance Type</label>
                                <select v-model="form.opening_balance_type" class="form-input">
                                    <option value="debit">Debit (Dr)</option>
                                    <option value="credit">Credit (Cr)</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea v-model="form.description" class="form-input" rows="2" placeholder="Optional notes about this account"></textarea>
                        </div>
                        <div v-if="isEditing" class="form-group" style="flex-direction:row; align-items:center; gap:10px;">
                            <input type="checkbox" v-model="form.is_active" id="is_active_chk" />
                            <label for="is_active_chk" class="form-label" style="margin:0; cursor:pointer;">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <Button variant="secondary" @click="showModal = false">Cancel</Button>
                        <Button @click="save" :loading="form.processing">
                            {{ (isEditing ? 'Update' : 'Create') }}
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Manage Ledger Types Modal -->
        <Teleport to="body">
            <div v-if="showTypesModal" class="modal-overlay" @click.self="showTypesModal = false">
                <div class="modal-box" style="max-width:560px;">
                    <div class="modal-header">
                        <h3>Manage Ledger Types</h3>
                        <button class="modal-close" @click="showTypesModal = false">×</button>
                    </div>
                    <div class="modal-body">
                        <!-- Add / Edit form -->
                        <form @submit.prevent="saveType" class="lt-form">
                            <div class="form-group">
                                <label class="form-label">{{ editingTypeId ? 'Edit Type Name' : 'New Type Name' }} <span class="req">*</span></label>
                                <input v-model="typeForm.name" type="text" class="form-input" placeholder="e.g. Asset" required />
                                <p v-if="typeForm.errors.name" class="form-error">{{ typeForm.errors.name }}</p>
                            </div>
                            <div class="form-row">
                                <div class="form-group flex-2">
                                    <label class="form-label">Nature <span class="req">*</span></label>
                                    <select v-model="typeForm.nature" class="form-input">
                                        <option value="debit">Debit Normal (Asset / Expense)</option>
                                        <option value="credit">Credit Normal (Liability / Capital / Income)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <input v-model="typeForm.description" type="text" class="form-input" placeholder="Optional" />
                            </div>
                            <div style="display:flex;gap:8px;">
                                <Button type="submit" size="sm" :loading="typeForm.processing">
                                    {{ editingTypeId ? 'Update Type' : 'Add Type' }}
                                </Button>
                                <Button v-if="editingTypeId" type="button" variant="secondary" size="sm" @click="cancelTypeEdit">
                                    Cancel Edit
                                </Button>
                            </div>
                        </form>

                        <!-- Existing types list -->
                        <div class="lt-list-header">Existing Types ({{ types.length }})</div>
                        <div v-if="types.length === 0" class="lt-empty">No types yet.</div>
                        <div v-else class="lt-list">
                            <div v-for="t in types" :key="t.id" class="lt-row" :class="{ 'lt-row--editing': editingTypeId === t.id }">
                                <div class="lt-row-text">
                                    <div class="lt-row-name">
                                        {{ t.name }}
                                        <span class="lt-row-nature" :class="t.nature === 'debit' ? 'nature-dr' : 'nature-cr'">
                                            {{ t.nature === 'debit' ? 'Dr' : 'Cr' }}
                                        </span>
                                        <span v-if="t.is_system" class="lt-row-sys">System</span>
                                    </div>
                                    <div v-if="t.description" class="lt-row-desc">{{ t.description }}</div>
                                    <div class="lt-row-count">{{ t.ledgers_count ?? 0 }} ledger{{ (t.ledgers_count ?? 0) !== 1 ? 's' : '' }}</div>
                                </div>
                                <div class="lt-row-actions">
                                    <button class="lt-row-btn" @click="editType(t)">Edit</button>
                                    <button v-if="!t.is_system" class="lt-row-btn lt-row-del" @click="deleteType(t)">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <Button variant="secondary" @click="showTypesModal = false">Close</Button>
                    </div>
                </div>
            </div>
        </Teleport>
    </SchoolLayout>
</template>

<style scoped>
.ledger-summary {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.summary-card {
    background: #fff;
    border-radius: 12px;
    padding: 18px 20px;
    border: 1.5px solid #e2e8f0;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.summary-debit  { border-left: 4px solid #6366f1; }
.summary-credit { border-left: 4px solid #10b981; }
.summary-total  { border-left: 4px solid #f59e0b; }
.summary-label  { font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
.summary-amount { font-size: 1.35rem; font-weight: 800; color: #1e293b; margin: 6px 0 2px; }
.summary-sub    { font-size: 0.75rem; color: #94a3b8; }

.filter-input {
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: 7px 12px;
    font-size: 0.84rem;
    outline: none;
    font-family: inherit;
    color: #1e293b;
    transition: border-color 0.15s;
    flex: 1;
    min-width: 160px;
}
.filter-input:focus { border-color: #6366f1; }

.row-inactive td { opacity: 0.55; }

.text-right { text-align: right; }
.mono { font-family: 'Courier New', monospace; font-size: 0.82rem; }
.dr-cr { font-size: 0.68rem; font-weight: 700; margin-left: 3px; color: #94a3b8; }

.ledger-name { font-weight: 600; color: #1e293b; }
.ledger-desc { font-size: 0.73rem; color: #94a3b8; margin-top: 2px; }

.code-chip {
    background: #f1f5f9;
    padding: 2px 7px;
    border-radius: 5px;
    font-size: 0.75rem;
    font-family: monospace;
    color: #475569;
}

.type-badge {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 20px;
}
.type-debit  { background: #ede9fe; color: #6366f1; }
.type-credit { background: #d1fae5; color: #059669; }

.bal-debit  { color: #4338ca; }
.bal-credit { color: #059669; }

.status-toggle {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
    border: none;
    cursor: pointer;
    transition: opacity 0.15s;
}
.status-toggle:hover { opacity: 0.8; }
.status-active   { background: #d1fae5; color: #065f46; }
.status-inactive { background: #fee2e2; color: #991b1b; }

.row-actions { display: flex; gap: 6px; align-items: center; }
.act-view:hover     { background: #dbeafe; border-color: #93c5fd; color: #1d4ed8; }
.act-del:hover      { background: #fee2e2; border-color: #fca5a5; color: #dc2626; }
.empty-cell { text-align: center; color: #94a3b8; padding: 40px; }

/* Modal */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.4);
    display: flex; align-items: center; justify-content: center;
    z-index: 9000;
    padding: 20px;
}
.modal-box {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 520px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    overflow: hidden;
    max-height: 90vh;
    overflow-y: auto;
}
.modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 22px;
    border-bottom: 1px solid #f1f5f9;
    position: sticky; top: 0; background: #fff; z-index: 1;
}
.modal-header h3 { font-weight: 700; font-size: 1rem; color: #1e293b; }
.modal-close { background: none; border: none; cursor: pointer; font-size: 1.4rem; color: #94a3b8; line-height: 1; }
.modal-close:hover { color: #1e293b; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 14px; }
.modal-footer {
    display: flex; justify-content: flex-end; gap: 10px;
    padding: 14px 22px;
    border-top: 1px solid #f1f5f9;
    background: #f8fafc;
    position: sticky; bottom: 0;
}
.form-row { display: flex; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; flex: 1; }
.flex-2 { flex: 2; }
.form-label { font-size: 0.8rem; font-weight: 600; color: #374151; }
.req { color: #ef4444; }
.form-input {
    border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 8px 12px;
    font-size: 0.85rem; outline: none; font-family: inherit; color: #1e293b;
    transition: border-color 0.15s; width: 100%;
}
.form-input:focus { border-color: #6366f1; }
.form-error { font-size: 0.75rem; color: #dc2626; }

/* Manage Ledger Types modal */
.lt-form { padding-bottom: 14px; border-bottom: 1px solid #e2e8f0; margin-bottom: 14px; display: flex; flex-direction: column; gap: 12px; }
.lt-list-header { font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 8px; }
.lt-empty { font-size: 0.85rem; color: #94a3b8; padding: 14px; text-align: center; background: #f8fafc; border-radius: 8px; }
.lt-list { display: flex; flex-direction: column; gap: 6px; }
.lt-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px;
    background: #fff; transition: all 0.15s; gap: 10px;
}
.lt-row--editing { border-color: #6366f1; background: #eef2ff; }
.lt-row-text { flex: 1; min-width: 0; }
.lt-row-name { font-weight: 600; color: #1e293b; font-size: 0.88rem; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.lt-row-nature { font-size: 0.66rem; font-weight: 700; padding: 2px 7px; border-radius: 12px; }
.nature-dr { background: #ede9fe; color: #6366f1; }
.nature-cr { background: #d1fae5; color: #059669; }
.lt-row-sys { font-size: 0.66rem; font-weight: 700; padding: 2px 7px; border-radius: 12px; background: #fef3c7; color: #d97706; }
.lt-row-desc { font-size: 0.74rem; color: #94a3b8; margin-top: 2px; }
.lt-row-count { font-size: 0.7rem; color: #94a3b8; margin-top: 3px; }
.lt-row-actions { display: flex; gap: 6px; flex-shrink: 0; }
.lt-row-btn {
    border: 1px solid #e2e8f0; background: #f8fafc;
    border-radius: 6px; padding: 4px 10px;
    font-size: 0.74rem; font-weight: 600; color: #475569;
    cursor: pointer; transition: all 0.15s;
}
.lt-row-btn:hover { background: #eef2ff; border-color: #c4b5fd; color: #6366f1; }
.lt-row-del:hover { background: #fee2e2; border-color: #fca5a5; color: #dc2626; }
</style>
