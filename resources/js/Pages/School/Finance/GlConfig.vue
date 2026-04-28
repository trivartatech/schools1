<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useToast } from '@/Composables/useToast';
import Table from '@/Components/ui/Table.vue';

const toast = useToast();

const props = defineProps({
    ledgers    : Array,
    settings   : Object,
    categories : Array,
});

// ── Global GL settings form ────────────────────────────────────────────────
const form = useForm({
    gl_cash_ledger_id                  : props.settings.gl_cash_ledger_id                  ?? '',
    gl_fee_income_ledger_id            : props.settings.gl_fee_income_ledger_id            ?? '',
    gl_transport_fee_income_ledger_id  : props.settings.gl_transport_fee_income_ledger_id  ?? '',
    gl_hostel_fee_income_ledger_id     : props.settings.gl_hostel_fee_income_ledger_id     ?? '',
    gl_stationary_fee_income_ledger_id : props.settings.gl_stationary_fee_income_ledger_id ?? '',
    gl_expense_ledger_id               : props.settings.gl_expense_ledger_id               ?? '',
    gl_payroll_ledger_id               : props.settings.gl_payroll_ledger_id               ?? '',
});

function save() {
    form.post(route('school.finance.gl-config.update'));
}

// ── Per-category ledger mapping ────────────────────────────────────────────
// Build a reactive map: category_id → ledger_id (string for select binding)
const catMappings = ref(
    Object.fromEntries(
        (props.categories ?? []).map(c => [c.id, c.ledger_id ? String(c.ledger_id) : ''])
    )
);
const catSaving = ref(false);

function saveCategoryMappings() {
    catSaving.value = true;

    const mappings = (props.categories ?? []).map(c => ({
        id        : c.id,
        ledger_id : catMappings.value[c.id] ? Number(catMappings.value[c.id]) : null,
    }));

    router.post(route('school.finance.gl-config.category-mapping'), { mappings }, {
        preserveScroll : true,
        onSuccess() {
            toast.success('Category mapping saved');
        },
        onFinish() { catSaving.value = false; },
    });
}

// ── Ledger helpers ─────────────────────────────────────────────────────────
const ledgerGroups = computed(() => {
    const groups = {};
    for (const l of props.ledgers) {
        const key = l.ledger_type?.name ?? 'Other';
        if (!groups[key]) groups[key] = [];
        groups[key].push(l);
    }
    return groups;
});

function ledgerName(id) {
    const l = props.ledgers.find(x => x.id == id);
    return l ? l.name : '—';
}

const mappings = [
    {
        key   : 'gl_cash_ledger_id',
        label : 'Cash / Bank Account',
        desc  : 'Debited on fee receipts; credited on expense payments.',
        color : '#6366f1',
        bg    : '#ede9fe',
    },
    {
        key   : 'gl_fee_income_ledger_id',
        label : 'Fee Income Account',
        desc  : 'Credited when a tuition / regular fee payment is received.',
        color : '#059669',
        bg    : '#d1fae5',
    },
    {
        key   : 'gl_transport_fee_income_ledger_id',
        label : 'Transport Fee Income Account',
        desc  : 'Credited when a transport fee receipt is collected. Falls back to Fee Income if blank.',
        color : '#0891b2',
        bg    : '#cffafe',
    },
    {
        key   : 'gl_hostel_fee_income_ledger_id',
        label : 'Hostel Fee Income Account',
        desc  : 'Credited when a hostel fee receipt is collected. Falls back to Fee Income if blank.',
        color : '#7c3aed',
        bg    : '#ede9fe',
    },
    {
        key   : 'gl_stationary_fee_income_ledger_id',
        label : 'Stationary Fee Income Account',
        desc  : 'Credited when a stationary fee receipt is collected. Falls back to Fee Income if blank.',
        color : '#b45309',
        bg    : '#fef3c7',
    },
    {
        key   : 'gl_expense_ledger_id',
        label : 'Default Expense Account',
        desc  : 'Debited for expenses that have no per-category override.',
        color : '#dc2626',
        bg    : '#fee2e2',
    },
    {
        key   : 'gl_payroll_ledger_id',
        label : 'Payroll Expense Account',
        desc  : 'Debited when salary payments are made.',
        color : '#d97706',
        bg    : '#fef3c7',
    },
];
</script>

<template>
    <SchoolLayout>
        <PageHeader title="GL Auto-Posting Settings" subtitle="Map operational modules to ledger accounts for automatic journal entries" />

        <!-- Info banner -->
        <div class="info-banner">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="flex-shrink:0">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span>
                When ledger accounts are mapped below, fee payments, expenses, and payroll will <strong>automatically create journal entries</strong> in the GL.
                Leave fields blank to disable auto-posting for that module.
            </span>
        </div>

        <!-- Global mapping cards -->
        <div class="mappings-grid">
            <div v-for="m in mappings" :key="m.key" class="mapping-card card">
                <div class="mc-badge" :style="{ background: m.bg, color: m.color }">
                    {{ m.label }}
                </div>
                <p class="mc-desc">{{ m.desc }}</p>

                <div class="mc-select-wrap">
                    <label class="form-label">Linked Ledger Account</label>
                    <select v-model="form[m.key]" class="form-input">
                        <option value="">— None (disabled) —</option>
                        <optgroup v-for="(group, typeName) in ledgerGroups" :key="typeName" :label="typeName">
                            <option v-for="l in group" :key="l.id" :value="l.id">
                                {{ l.code ? `[${l.code}] ` : '' }}{{ l.name }}
                            </option>
                        </optgroup>
                    </select>
                </div>

                <div v-if="form[m.key]" class="mc-current">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Currently: <strong>{{ ledgerName(form[m.key]) }}</strong>
                </div>
            </div>
        </div>

        <!-- Save global settings button -->
        <div style="margin-top:20px;display:flex;gap:10px;align-items:center;">
            <Button @click="save" :loading="form.processing">
                Save GL Settings
            </Button>
            <Button variant="secondary" as="link" :href="route('school.finance.transactions.index')">
                View Transactions
            </Button>
        </div>

        <!-- How it works -->
        <div class="card" style="margin-top:24px;">
            <div class="card-body">
                <h3 class="section-title">How Auto-Posting Works</h3>
                <div class="flow-grid">
                    <div class="flow-item">
                        <div class="flow-icon fee">₹</div>
                        <div class="flow-text">
                            <strong>Fee Payment Collected</strong><br/>
                            Dr Cash/Bank &nbsp;→&nbsp; Cr Fee Income
                        </div>
                    </div>
                    <div class="flow-item">
                        <div class="flow-icon transport">🚌</div>
                        <div class="flow-text">
                            <strong>Transport Fee Collected</strong><br/>
                            Dr Cash/Bank &nbsp;→&nbsp; Cr Transport Fee Income
                        </div>
                    </div>
                    <div class="flow-item">
                        <div class="flow-icon hostel">🏠</div>
                        <div class="flow-text">
                            <strong>Hostel Fee Collected</strong><br/>
                            Dr Cash/Bank &nbsp;→&nbsp; Cr Hostel Fee Income
                        </div>
                    </div>
                    <div class="flow-item">
                        <div class="flow-icon stationary">📚</div>
                        <div class="flow-text">
                            <strong>Stationary Fee Collected</strong><br/>
                            Dr Cash/Bank &nbsp;→&nbsp; Cr Stationary Fee Income
                        </div>
                    </div>
                    <div class="flow-item">
                        <div class="flow-icon exp">📋</div>
                        <div class="flow-text">
                            <strong>Expense Recorded</strong><br/>
                            Dr Category Ledger (or Default) &nbsp;→&nbsp; Cr Cash/Bank
                        </div>
                    </div>
                    <div class="flow-item">
                        <div class="flow-icon pay">👤</div>
                        <div class="flow-text">
                            <strong>Payroll Paid</strong><br/>
                            Dr Payroll Expense &nbsp;→&nbsp; Cr Cash/Bank
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Per-Category Expense Ledger Override ─────────────────────── -->
        <div class="card" style="margin-top:24px;" v-if="categories && categories.length">
            <div class="card-body">
                <div class="cat-header">
                    <div>
                        <h3 class="section-title" style="margin-bottom:4px;">Per-Category Expense Ledger Override</h3>
                        <p class="cat-sub">
                            Override the default expense account for specific categories.
                            If a category has no override, the <strong>Default Expense Account</strong> above is used.
                        </p>
                    </div>
                    <div class="cat-actions">
                        <Button size="sm" @click="saveCategoryMappings" :loading="catSaving">
                            Save Category Mappings
                        </Button>
                    </div>
                </div>

                <Table class="cat-table" size="sm">
                    <thead>
                        <tr>
                            <th>Expense Category</th>
                            <th>Ledger Account Override</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="cat in categories" :key="cat.id">
                            <td class="cat-name">{{ cat.name }}</td>
                            <td>
                                <select v-model="catMappings[cat.id]" class="form-input form-input-sm">
                                    <option value="">— Use default expense account —</option>
                                    <optgroup v-for="(group, typeName) in ledgerGroups" :key="typeName" :label="typeName">
                                        <option v-for="l in group" :key="l.id" :value="String(l.id)">
                                            {{ l.code ? `[${l.code}] ` : '' }}{{ l.name }}
                                        </option>
                                    </optgroup>
                                </select>
                            </td>
                            <td>
                                <span v-if="catMappings[cat.id]" class="badge badge-green">
                                    Override: {{ ledgerName(catMappings[cat.id]) }}
                                </span>
                                <span v-else class="badge badge-gray">Using default</span>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <div v-else-if="categories && categories.length === 0" class="empty-state" style="margin-top:24px;">
            <p>No expense categories found. <Link :href="route('school.expense-categories.index')" style="color:#6366f1;">Add expense categories</Link> to enable per-category GL mapping.</p>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.info-banner {
    display:flex;align-items:flex-start;gap:12px;
    background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;
    padding:14px 18px;font-size:0.85rem;color:#1e40af;margin-bottom:20px;
    line-height:1.5;
}

.mappings-grid {
    display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;
}
.mapping-card { padding:20px; }
.mc-badge {
    display:inline-block;padding:5px 14px;border-radius:20px;
    font-size:0.8rem;font-weight:700;margin-bottom:10px;
}
.mc-desc { font-size:0.82rem;color:#64748b;margin-bottom:14px;line-height:1.5; }
.mc-select-wrap { margin-bottom:8px; }
.mc-current {
    display:flex;align-items:center;gap:6px;
    font-size:0.78rem;color:#059669;
}

.form-label { display:block;font-size:0.78rem;font-weight:600;color:#374151;margin-bottom:5px; }
.form-input {
    border:1.5px solid #e2e8f0;border-radius:8px;padding:8px 12px;
    font-size:0.84rem;outline:none;font-family:inherit;color:#1e293b;
    transition:border-color 0.15s;width:100%;
}
.form-input:focus { border-color:#6366f1; }
.form-input-sm { padding:6px 10px;font-size:0.82rem; }

.section-title { font-size:0.88rem;font-weight:700;color:#1e293b;margin-bottom:16px; }
.flow-grid { display:flex;gap:20px;flex-wrap:wrap; }
.flow-item { display:flex;align-items:center;gap:12px;flex:1;min-width:200px; }
.flow-icon {
    width:42px;height:42px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;
}
.flow-icon.fee       { background:#d1fae5;color:#059669; }
.flow-icon.transport { background:#cffafe;color:#0891b2; }
.flow-icon.hostel    { background:#ede9fe;color:#7c3aed; }
.flow-icon.stationary { background:#fef3c7;color:#b45309; }
.flow-icon.exp       { background:#fee2e2;color:#dc2626; }
.flow-icon.pay       { background:#fef3c7;color:#d97706; }
.flow-text { font-size:0.82rem;color:#374151;line-height:1.6; }

/* Per-category section */
.cat-header {
    display:flex;justify-content:space-between;align-items:flex-start;
    gap:12px;flex-wrap:wrap;margin-bottom:16px;
}
.cat-sub { font-size:0.82rem;color:#64748b;line-height:1.5;max-width:500px; }
.cat-actions { display:flex;align-items:center;gap:12px;flex-shrink:0; }
.cat-name { font-weight:600;color:#1e293b;white-space:nowrap; }

.badge {
    display:inline-flex;align-items:center;padding:3px 10px;
    border-radius:12px;font-size:0.75rem;font-weight:600;white-space:nowrap;
}
.badge-green { background:#d1fae5;color:#059669; }
.badge-gray  { background:#f1f5f9;color:#94a3b8; }

.empty-state {
    background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;
    padding:20px;text-align:center;font-size:0.84rem;color:#64748b;
}
</style>
