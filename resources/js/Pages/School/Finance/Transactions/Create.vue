<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import LedgerCombobox from '@/Components/LedgerCombobox.vue';

const props = defineProps({
    ledgers     : Array,
    editMode    : Boolean,
    transaction : Object,
    today       : String,
});

// ── Form ──────────────────────────────────────────────────────
const form = useForm({
    date         : props.transaction?.date ?? props.today,
    type         : props.transaction?.type ?? 'journal',
    status       : props.transaction?.status ?? 'posted',
    narration    : props.transaction?.narration ?? '',
    reference_no : props.transaction?.reference_no ?? '',
    lines        : props.transaction?.lines?.map(l => ({
        ledger_id   : l.ledger_id,
        type        : l.type,
        amount      : l.amount,
        description : l.description ?? '',
    })) ?? [
        { ledger_id: '', type: 'debit',  amount: '', description: '' },
        { ledger_id: '', type: 'credit', amount: '', description: '' },
    ],
});

// ── Lines helpers ─────────────────────────────────────────────
function addLine(type = 'debit') {
    form.lines.push({ ledger_id: '', type, amount: '', description: '' });
}

function removeLine(index) {
    if (form.lines.length <= 2) return;
    form.lines.splice(index, 1);
}

const totalDebit  = computed(() => form.lines.filter(l => l.type === 'debit') .reduce((s, l) => s + (parseFloat(l.amount) || 0), 0));
const totalCredit = computed(() => form.lines.filter(l => l.type === 'credit').reduce((s, l) => s + (parseFloat(l.amount) || 0), 0));
const isBalanced  = computed(() => Math.abs(totalDebit.value - totalCredit.value) < 0.01 && totalDebit.value > 0);
const difference  = computed(() => Math.abs(totalDebit.value - totalCredit.value));

// ── Ledger lookup ─────────────────────────────────────────────
const ledgerMap = computed(() => Object.fromEntries(props.ledgers.map(l => [l.id, l])));
function ledgerName(id) { return ledgerMap.value[id]?.name ?? ''; }

// ── Quick fill from type ──────────────────────────────────────
function applyType(type) {
    form.type = type;
    if (type === 'receipt' && form.lines.length === 2) {
        form.lines[0].type = 'debit';
        form.lines[1].type = 'credit';
    } else if (type === 'payment' && form.lines.length === 2) {
        form.lines[0].type = 'debit';
        form.lines[1].type = 'credit';
    }
}

// ── Submit ────────────────────────────────────────────────────
function submit(postStatus = 'posted') {
    form.status = postStatus;
    if (props.editMode) {
        form.put(route('school.finance.transactions.update', props.transaction.id), {
            onSuccess: () => router.visit(route('school.finance.transactions.index')),
        });
    } else {
        form.post(route('school.finance.transactions.store'));
    }
}

const fmt    = (n) => new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2 }).format(n);
const fmtCur = (n) => '₹' + fmt(n);

const typeDescriptions = {
    journal : 'General journal entry for any accounting adjustment.',
    receipt : 'Money received — debit cash/bank, credit income account.',
    payment : 'Money paid out — debit expense, credit cash/bank.',
    contra  : 'Transfer between cash/bank accounts.',
};
</script>

<template>
    <SchoolLayout>
        <!-- Header -->
        <div class="page-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <Link :href="route('school.finance.transactions.index')" class="back-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                    </svg>
                </Link>
                <div>
                    <h1 class="page-header-title">{{ editMode ? 'Edit Transaction' : 'New Transaction' }}</h1>
                    <p class="page-header-sub">{{ editMode ? 'Update journal entry' : 'Create a double-entry accounting record' }}</p>
                </div>
            </div>
        </div>

        <div class="create-layout">
            <!-- Left: Form -->
            <div class="create-main">
                <!-- Transaction details card -->
                <div class="card">
                    <div class="card-body">
                        <h3 class="section-title">Transaction Details</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Date <span class="req">*</span></label>
                                <input v-model="form.date" type="date" class="form-input" />
                                <p v-if="form.errors.date" class="form-error">{{ form.errors.date }}</p>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Transaction Type <span class="req">*</span></label>
                                <div class="type-btns">
                                    <button v-for="t in ['journal','receipt','payment','contra']" :key="t"
                                        type="button" class="type-btn" :class="{ 'type-btn-active': form.type === t }"
                                        @click="applyType(t)">{{ t }}</button>
                                </div>
                                <p class="type-hint">{{ typeDescriptions[form.type] }}</p>
                            </div>
                        </div>
                        <div class="form-grid" style="margin-top:14px;">
                            <div class="form-group">
                                <label class="form-label">Narration / Description</label>
                                <textarea v-model="form.narration" class="form-input" rows="2" placeholder="Describe this transaction…"></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Reference No.</label>
                                <input v-model="form.reference_no" type="text" class="form-input" placeholder="e.g. Invoice/Cheque/Bill no." />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Journal Lines card -->
                <div class="card" style="margin-top:16px;">
                    <div class="card-body">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                            <h3 class="section-title" style="margin:0;">Journal Lines</h3>
                            <div style="display:flex; gap:8px;">
                                <button type="button" class="add-line-btn add-debit"  @click="addLine('debit')">+ Debit</button>
                                <button type="button" class="add-line-btn add-credit" @click="addLine('credit')">+ Credit</button>
                            </div>
                        </div>

                        <p v-if="form.errors.lines" class="form-error" style="margin-bottom:10px;">{{ form.errors.lines }}</p>

                        <!-- Lines header -->
                        <div class="lines-header">
                            <span style="flex:3;">Ledger Account</span>
                            <span style="width:90px;">Dr / Cr</span>
                            <span style="width:140px; text-align:right;">Amount (₹)</span>
                            <span style="flex:1.5;">Description</span>
                            <span style="width:32px;"></span>
                        </div>

                        <!-- Line rows -->
                        <div v-for="(line, i) in form.lines" :key="i" class="line-row" :class="line.type === 'debit' ? 'line-debit' : 'line-credit'">
                            <div class="line-type-indicator" :class="line.type === 'debit' ? 'ind-debit' : 'ind-credit'">
                                {{ line.type === 'debit' ? 'Dr' : 'Cr' }}
                            </div>
                            <!-- Searchable ledger combobox -->
                            <div style="flex:3; min-width:0;">
                                <LedgerCombobox
                                    v-model="line.ledger_id"
                                    :ledgers="ledgers"
                                    placeholder="Search account…"
                                    input-class="line-combo-input"
                                />
                            </div>
                            <select v-model="line.type" class="line-input line-drcrsel" :class="line.type === 'debit' ? 'sel-debit' : 'sel-credit'">
                                <option value="debit">Debit (Dr)</option>
                                <option value="credit">Credit (Cr)</option>
                            </select>
                            <input v-model="line.amount" type="number" min="0" step="0.01" class="line-input line-amount" placeholder="0.00" />
                            <input v-model="line.description" type="text" class="line-input line-desc" placeholder="Optional note" />
                            <button type="button" class="line-del" @click="removeLine(i)" :disabled="form.lines.length <= 2" title="Remove line">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Summary & Submit -->
            <div class="create-sidebar">
                <div class="card sidebar-card">
                    <div class="card-body">
                        <h3 class="section-title">Balance Check</h3>

                        <div class="balance-row">
                            <span>Total Debits</span>
                            <span class="bal-debit">{{ fmtCur(totalDebit) }}</span>
                        </div>
                        <div class="balance-row">
                            <span>Total Credits</span>
                            <span class="bal-credit">{{ fmtCur(totalCredit) }}</span>
                        </div>
                        <div class="balance-divider"></div>
                        <div class="balance-row balance-diff" :class="isBalanced ? 'diff-ok' : 'diff-err'">
                            <span>Difference</span>
                            <span>{{ isBalanced ? '✓ Balanced' : fmtCur(difference) }}</span>
                        </div>

                        <div class="balance-status" :class="isBalanced ? 'status-ok' : 'status-err'">
                            <template v-if="isBalanced">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                                Transaction is balanced
                            </template>
                            <template v-else>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                                </svg>
                                Debit ≠ Credit
                            </template>
                        </div>

                        <!-- Status + Submit -->
                        <div style="margin-top:20px; display:flex; flex-direction:column; gap:8px;">
                            <Button type="button" @click="submit('posted')" :loading="form.processing" :disabled="!isBalanced" block>
                                {{ form.processing ? 'Saving…' : (editMode ? 'Update & Post' : 'Post Transaction') }}
                            </Button>
                            <Button variant="secondary" v-if="!editMode" type="button" @click="submit('draft')" :loading="form.processing" :disabled="!isBalanced" block>
                                Save as Draft
                            </Button>
                            <Button variant="secondary" as="link" :href="route('school.finance.transactions.index')" block>
                                Cancel
                            </Button>
                        </div>

                        <!-- Lines preview -->
                        <div class="line-summary" style="margin-top:20px;">
                            <div class="ls-title">Lines Preview</div>
                            <div v-for="(line, i) in form.lines" :key="i" class="ls-row" :class="line.type === 'debit' ? 'ls-dr' : 'ls-cr'">
                                <span class="ls-type">{{ line.type === 'debit' ? 'Dr' : 'Cr' }}</span>
                                <span class="ls-name">{{ ledgerName(line.ledger_id) || 'Select account' }}</span>
                                <span class="ls-amt">{{ line.amount ? fmtCur(parseFloat(line.amount) || 0) : '—' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.back-btn {
    width:36px;height:36px;border:1.5px solid #e2e8f0;background:#fff;
    border-radius:10px;display:flex;align-items:center;justify-content:center;
    color:#64748b;cursor:pointer;text-decoration:none;transition:all 0.15s;
}
.back-btn:hover { background:#f1f5f9;color:#1e293b; }

.create-layout { display:flex;gap:20px;align-items:flex-start; }
.create-main    { flex:1;min-width:0;display:flex;flex-direction:column;gap:16px; }
.create-sidebar { width:280px;flex-shrink:0;position:sticky;top:20px; }

.sidebar-card .card-body { padding:18px 20px; }
.section-title { font-size:0.88rem;font-weight:700;color:#1e293b;margin-bottom:14px; }

.form-grid { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
.form-group { display:flex;flex-direction:column;gap:5px; }
.form-label { font-size:0.8rem;font-weight:600;color:#374151; }
.req { color:#ef4444; }
.form-input {
    border:1.5px solid #e2e8f0;border-radius:8px;padding:8px 12px;
    font-size:0.85rem;outline:none;font-family:inherit;color:#1e293b;
    transition:border-color 0.15s;width:100%;
}
.form-input:focus { border-color:#6366f1; }
.form-error { font-size:0.75rem;color:#dc2626; }

.type-btns { display:flex;gap:6px;flex-wrap:wrap; }
.type-btn {
    padding:6px 14px;border-radius:8px;font-size:0.8rem;font-weight:600;
    border:1.5px solid #e2e8f0;background:#f8fafc;cursor:pointer;
    text-transform:capitalize;transition:all 0.15s;color:#64748b;
}
.type-btn:hover  { border-color:#c4b5fd;background:#ede9fe;color:#6366f1; }
.type-btn-active { border-color:#6366f1;background:#6366f1;color:#fff; }
.type-hint { font-size:0.72rem;color:#94a3b8;margin-top:4px; }

/* Lines */
.lines-header {
    display:flex;gap:8px;align-items:center;padding:6px 8px;
    font-size:0.72rem;font-weight:700;text-transform:uppercase;
    color:#94a3b8;letter-spacing:0.04em;
    border-bottom:1px solid #f1f5f9;margin-bottom:6px;
}
.line-row {
    display:flex;gap:8px;align-items:center;
    padding:6px 8px 6px 0;border-radius:8px;
    border-left:3px solid transparent;margin-bottom:4px;
}
.line-debit  { border-left-color:#6366f1;background:rgba(99,102,241,0.03); }
.line-credit { border-left-color:#10b981;background:rgba(16,185,129,0.03); }
.line-type-indicator {
    width:30px;height:24px;border-radius:5px;font-size:0.68rem;font-weight:800;
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.ind-debit  { background:#ede9fe;color:#6366f1; }
.ind-credit { background:#d1fae5;color:#059669; }

/* Override LedgerCombobox input inside line */
:deep(.line-combo-input) {
    border:1.5px solid #e2e8f0;border-radius:7px;padding:7px 42px 7px 10px;
    font-size:0.82rem;transition:border-color 0.15s;
}
:deep(.line-combo-input:focus) { border-color:#6366f1; }

.line-input {
    border:1.5px solid #e2e8f0;border-radius:7px;padding:7px 10px;
    font-size:0.82rem;outline:none;font-family:inherit;color:#1e293b;
    transition:border-color 0.15s;
}
.line-input:focus { border-color:#6366f1; }
.line-drcrsel { width:105px; }
.sel-debit  { color:#6366f1;font-weight:600; }
.sel-credit { color:#059669;font-weight:600; }
.line-amount { width:130px;text-align:right; }
.line-desc { flex:1.5; }
.line-del {
    width:28px;height:28px;border:1px solid #fee2e2;background:#fff5f5;
    border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;
    color:#f87171;flex-shrink:0;transition:all 0.15s;
}
.line-del:hover:not(:disabled) { background:#fee2e2;color:#dc2626; }
.line-del:disabled { opacity:0.3;cursor:not-allowed; }

.add-line-btn {
    padding:5px 12px;border-radius:7px;font-size:0.78rem;font-weight:600;
    border:1.5px solid;cursor:pointer;transition:all 0.15s;
}
.add-debit  { border-color:#c4b5fd;color:#6366f1;background:#f5f3ff; }
.add-debit:hover  { background:#ede9fe; }
.add-credit { border-color:#6ee7b7;color:#059669;background:#f0fdf4; }
.add-credit:hover { background:#d1fae5; }

/* Balance sidebar */
.balance-row { display:flex;justify-content:space-between;align-items:center;padding:7px 0;font-size:0.84rem;color:#374151; }
.bal-debit  { color:#4338ca;font-weight:700;font-family:monospace; }
.bal-credit { color:#059669;font-weight:700;font-family:monospace; }
.balance-divider { border-top:1.5px dashed #e2e8f0;margin:4px 0; }
.balance-diff { font-weight:700; }
.diff-ok span { color:#059669;font-family:monospace; }
.diff-err span { color:#dc2626;font-family:monospace; }
.balance-status {
    display:flex;align-items:center;gap:6px;
    padding:10px 12px;border-radius:8px;font-size:0.82rem;font-weight:600;margin-top:10px;
}
.status-ok  { background:#d1fae5;color:#065f46; }
.status-err { background:#fee2e2;color:#991b1b; }

.line-summary { border-top:1px solid #f1f5f9;padding-top:14px; }
.ls-title { font-size:0.72rem;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:8px;letter-spacing:0.04em; }
.ls-row { display:flex;align-items:center;gap:6px;padding:4px 0;font-size:0.78rem; }
.ls-type { width:22px;height:16px;border-radius:3px;font-size:0.65rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.ls-dr .ls-type { background:#ede9fe;color:#6366f1; }
.ls-cr .ls-type { background:#d1fae5;color:#059669; }
.ls-name { flex:1;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.ls-amt  { font-family:monospace;color:#1e293b;font-size:0.78rem;flex-shrink:0; }

@media (max-width:768px) {
    .create-layout { flex-direction:column; }
    .create-sidebar { width:100%;position:static; }
    .form-grid { grid-template-columns:1fr; }
    .lines-header { display:none; }
    .line-row { flex-wrap:wrap; }
}
</style>
