<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import PrintButton from '@/Components/ui/PrintButton.vue';
import { computed } from 'vue';
import { router, Link, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';
import { useConfirm } from '@/Composables/useConfirm';

const confirm = useConfirm();

const props = defineProps({
    transaction : Object,
});

const school = useSchoolStore();

const debitLines  = computed(() => props.transaction.lines.filter(l => l.type === 'debit'));
const creditLines = computed(() => props.transaction.lines.filter(l => l.type === 'credit'));
const totalDebit  = computed(() => debitLines.value.reduce((s, l)  => s + parseFloat(l.amount), 0));
const totalCredit = computed(() => creditLines.value.reduce((s, l) => s + parseFloat(l.amount), 0));

async function deleteTransaction() {
    const ok = await confirm({
        title: 'Delete transaction?',
        message: 'Delete this transaction? This cannot be undone.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(route('school.finance.transactions.destroy', props.transaction.id), {
        onSuccess: () => router.visit(route('school.finance.transactions.index')),
    });
}

async function reverseTransaction() {
    const ok = await confirm({
        title: 'Reverse transaction?',
        message: 'Create a reversal entry for this transaction? The original will be voided.',
        confirmLabel: 'Reverse',
        danger: true,
    });
    if (!ok) return;
    useForm({}).post(route('school.finance.transactions.reverse', props.transaction.id));
}

function print() { window.print(); }

const statusColors = {
    draft  : { bg: '#fef9c3', color: '#854d0e' },
    posted : { bg: '#d1fae5', color: '#065f46' },
    void   : { bg: '#f1f5f9', color: '#94a3b8' },
};

const fmt    = (n) => new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2 }).format(n);
const fmtCur = (n) => '₹' + fmt(n);

const typeColors = {
    journal : '#6366f1',
    receipt : '#059669',
    payment : '#dc2626',
    contra  : '#d97706',
};
const typeBg = {
    journal : '#ede9fe',
    receipt : '#d1fae5',
    payment : '#fee2e2',
    contra  : '#fef3c7',
};
</script>

<template>
    <SchoolLayout>
        <!-- Header -->
        <PageHeader>
            <template #title>
                <div style="display:flex; align-items:center; gap:12px;">
                    <Link :href="route('school.finance.transactions.index')" class="back-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                        </svg>
                    </Link>
                    <h1 class="page-header-title">{{ transaction.transaction_no }}</h1>
                </div>
            </template>
            <template #subtitle>
                <p class="page-header-sub">{{ school.fmtDate(transaction.date) }} &bull; {{ transaction.academic_year?.name }}</p>
            </template>
            <template #actions>
                <!-- Status badge -->
                <span class="status-badge" :style="{ background: statusColors[transaction.status]?.bg, color: statusColors[transaction.status]?.color }">
                    {{ transaction.status?.toUpperCase() }}
                </span>
                <Button variant="secondary" as="link" v-if="transaction.status !== 'void'" :href="route('school.finance.transactions.edit', transaction.id)">Edit</Button>
                <Button variant="warning" v-if="transaction.status === 'posted' && !transaction.is_reversed" @click="reverseTransaction">
                    ↩ Reverse
                </Button>
                <PrintButton />
                <Button variant="danger" v-if="transaction.status !== 'void'" @click="deleteTransaction">Delete</Button>
            </template>
        </PageHeader>

        <div class="show-layout">
            <!-- Voucher card -->
            <div class="voucher-card">
                <!-- Voucher header -->
                <div class="voucher-header">
                    <div class="voucher-type-badge" :style="{ background: typeBg[transaction.type], color: typeColors[transaction.type] }">
                        {{ transaction.type?.toUpperCase() }} VOUCHER
                    </div>
                    <div class="voucher-no">{{ transaction.transaction_no }}</div>
                </div>

                <!-- Void / Reversal banners -->
                <div v-if="transaction.status === 'void'" class="reversal-banner reversal-banner-void">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    This transaction has been <strong>voided</strong> and reversed.
                </div>
                <div v-if="transaction.reversal_of" class="reversal-banner reversal-banner-info">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.49"/></svg>
                    Reversal of:
                    <Link :href="route('school.finance.transactions.show', transaction.reversal_of)" class="reversal-link">
                        {{ transaction.reversal_txn ?? '#' + transaction.reversal_of }}
                    </Link>
                </div>

                <!-- Meta info -->
                <div class="voucher-meta">
                    <div class="meta-item">
                        <span class="meta-label">Date</span>
                        <span class="meta-value">{{ school.fmtDate(transaction.date) }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Reference</span>
                        <span class="meta-value">{{ transaction.reference_no || '—' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Narration</span>
                        <span class="meta-value">{{ transaction.narration || '—' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Created By</span>
                        <span class="meta-value">{{ transaction.created_by?.name ?? '—' }}</span>
                    </div>
                </div>

                <!-- Lines table -->
                <Table class="lines-table">
                    <thead>
                            <tr>
                                <th>Account</th>
                                <th>Type</th>
                                <th class="text-right">Debit (Dr)</th>
                                <th class="text-right">Credit (Cr)</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in transaction.lines" :key="line.id" :class="line.type === 'debit' ? 'line-dr' : 'line-cr'">
                                <td>
                                    <div class="acct-name">{{ line.ledger?.name ?? '—' }}</div>
                                    <div class="acct-type">{{ line.ledger?.ledger_type?.name ?? '' }}</div>
                                </td>
                                <td>
                                    <span class="dr-cr-badge" :class="line.type === 'debit' ? 'badge-dr' : 'badge-cr'">
                                        {{ line.type === 'debit' ? 'Dr' : 'Cr' }}
                                    </span>
                                </td>
                                <td class="text-right mono text-debit">{{ line.type === 'debit' ? fmtCur(line.amount) : '' }}</td>
                                <td class="text-right mono text-credit">{{ line.type === 'credit' ? fmtCur(line.amount) : '' }}</td>
                                <td class="text-muted">{{ line.description || '—' }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="totals-row">
                                <td colspan="2" style="font-weight:700; text-align:right; padding-right:20px;">Total</td>
                                <td class="text-right mono text-debit font-bold">{{ fmtCur(totalDebit) }}</td>
                                <td class="text-right mono text-credit font-bold">{{ fmtCur(totalCredit) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                </Table>

                <!-- Balance status -->
                <div class="balance-footer" :class="transaction.is_balanced ? 'bal-ok' : 'bal-err'">
                    <template v-if="transaction.is_balanced">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                        Transaction is balanced &mdash; Debit {{ fmtCur(totalDebit) }} = Credit {{ fmtCur(totalCredit) }}
                    </template>
                    <template v-else>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        Transaction is NOT balanced
                    </template>
                </div>
            </div>

            <!-- Sidebar: summary -->
            <div class="show-sidebar">
                <div class="card">
                    <div class="card-body">
                        <h4 class="section-title">Summary</h4>
                        <div class="sum-row">
                            <span>Debit Lines</span>
                            <span>{{ debitLines.length }}</span>
                        </div>
                        <div class="sum-row">
                            <span>Credit Lines</span>
                            <span>{{ creditLines.length }}</span>
                        </div>
                        <div class="sum-divider"></div>
                        <div class="sum-row sum-total">
                            <span>Total Amount</span>
                            <span>{{ fmtCur(totalDebit) }}</span>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-top:14px;">
                    <div class="card-body">
                        <h4 class="section-title">Affected Accounts</h4>
                        <div v-for="line in transaction.lines" :key="line.id" class="affected-row">
                            <span class="dr-cr-badge" :class="line.type === 'debit' ? 'badge-dr' : 'badge-cr'">
                                {{ line.type === 'debit' ? 'Dr' : 'Cr' }}
                            </span>
                            <span class="aff-name">{{ line.ledger?.name }}</span>
                            <span class="aff-amt">{{ fmtCur(line.amount) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.back-btn {
    width: 36px; height: 36px; border: 1.5px solid #e2e8f0; background: #fff;
    border-radius: 10px; display: flex; align-items: center; justify-content: center;
    color: #64748b; cursor: pointer; text-decoration: none; transition: all 0.15s;
}
.back-btn:hover { background: #f1f5f9; }

.status-badge {
    font-size: 0.72rem; font-weight: 800; padding: 4px 12px; border-radius: 20px;
    letter-spacing: 0.06em;
}

.reversal-banner {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 22px; font-size: 0.84rem; font-weight: 500;
}
.reversal-banner-void { background: #fee2e2; color: #991b1b; }
.reversal-banner-info { background: #eff6ff; color: #1e40af; }
.reversal-link { color: #1d4ed8; font-weight: 700; text-decoration: underline; }

.show-layout { display: flex; gap: 20px; align-items: flex-start; }
.voucher-card { flex: 1; min-width: 0; background: #fff; border: 1.5px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
.show-sidebar { width: 260px; flex-shrink: 0; }

.voucher-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 22px; border-bottom: 1px solid #f1f5f9;
    background: #f8fafc;
}
.voucher-type-badge {
    font-size: 0.75rem; font-weight: 800; padding: 5px 14px; border-radius: 20px;
    letter-spacing: 0.08em;
}
.voucher-no { font-family: monospace; font-size: 1.1rem; font-weight: 700; color: #1e293b; }

.voucher-meta {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 14px; padding: 18px 22px; border-bottom: 1px solid #f1f5f9;
}
.meta-item   { display: flex; flex-direction: column; gap: 3px; }
.meta-label  { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em; }
.meta-value  { font-size: 0.9rem; color: #1e293b; font-weight: 500; }

.line-dr td { border-left: 3px solid #6366f1; }
.line-cr td { border-left: 3px solid #10b981; }

.acct-name { font-weight: 600; color: #1e293b; font-size: 0.86rem; }
.acct-type { font-size: 0.73rem; color: #94a3b8; margin-top: 2px; }

.dr-cr-badge { font-size: 0.7rem; font-weight: 800; padding: 3px 8px; border-radius: 5px; }
.badge-dr { background: #ede9fe; color: #6366f1; }
.badge-cr { background: #d1fae5; color: #059669; }

.text-right  { text-align: right; }
.mono        { font-family: 'Courier New', monospace; font-size: 0.84rem; }
.text-debit  { color: #4338ca; }
.text-credit { color: #059669; }
.text-muted  { color: #94a3b8; font-size: 0.8rem; }
.font-bold   { font-weight: 700; }

.totals-row td {
    background: #f8fafc; font-weight: 700; border-top: 2px solid #e2e8f0;
    padding: 12px 16px;
}

.balance-footer {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 22px; font-size: 0.84rem; font-weight: 600;
}
.bal-ok  { background: #d1fae5; color: #065f46; }
.bal-err { background: #fee2e2; color: #991b1b; }

/* Sidebar */
.section-title { font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 12px; }
.sum-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 0.84rem; color: #374151; }
.sum-total { font-weight: 700; color: #1e293b; }
.sum-divider { border-top: 1px dashed #e2e8f0; margin: 6px 0; }

.affected-row { display: flex; align-items: center; gap: 8px; padding: 5px 0; font-size: 0.82rem; }
.aff-name { flex: 1; color: #374151; font-weight: 500; }
.aff-amt  { font-family: monospace; color: #1e293b; font-weight: 700; }

@media (max-width: 768px) {
    .show-layout { flex-direction: column; }
    .show-sidebar { width: 100%; }
}
</style>
