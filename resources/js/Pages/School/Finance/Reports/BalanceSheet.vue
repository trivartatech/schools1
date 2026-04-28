<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import StatementsTabNav from '@/Components/finance/StatementsTabNav.vue';

const props = defineProps({
    assets           : Array,
    liabilities      : Array,
    capital          : Array,
    totalAssets      : Number,
    totalLiabilities : Number,
    totalCapital     : Number,
    totalLCE         : Number,
    isBalanced       : Boolean,
    asOf             : String,
});

const asOf = ref(props.asOf);

function apply() {
    router.get(route('school.finance.statements.balance-sheet'), { as_of: asOf.value }, {
        preserveState: true, replace: true,
    });
}

function exportCsv() {
    window.location.href = route('school.finance.statements.balance-sheet') +
        '?as_of=' + asOf.value + '&export=csv';
}
function exportPdf() {
    window.location.href = route('school.finance.statements.balance-sheet') +
        '?as_of=' + asOf.value + '&export=pdf';
}

const fmt    = (n) => new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2 }).format(Math.abs(n));
const fmtCur = (n) => '₹' + fmt(n);
</script>

<template>
    <SchoolLayout>
        <StatementsTabNav current="balance-sheet" />
        <PageHeader title="Balance Sheet" subtitle="Statement of financial position — Assets = Liabilities + Capital">
            <template #actions>
                <Button variant="secondary" @click="exportCsv">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:5px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    CSV
                </Button>
                <Button variant="secondary" @click="exportPdf">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:5px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    PDF
                </Button>
                <Button variant="secondary" @click="window.print()">Print</Button>

            </template>
        </PageHeader>

        <!-- Filter -->
        <div class="card" style="margin-bottom:16px;">
            <div class="card-body" style="display:flex;gap:12px;align-items:flex-end;">
                <div>
                    <label class="filter-label">As of Date</label>
                    <input type="date" v-model="asOf" class="filter-input" />
                </div>
                <Button size="sm" @click="apply">Apply</Button>
            </div>
        </div>

        <!-- Balance check banner -->
        <div class="bal-banner" :class="isBalanced ? 'bal-ok' : 'bal-err'">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <template v-if="isBalanced"><polyline points="20 6 9 17 4 12"/></template>
                <template v-else><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></template>
            </svg>
            <span v-if="isBalanced">Balance Sheet is <strong>balanced</strong> &mdash; Assets {{ fmtCur(totalAssets) }} = Liabilities + Capital {{ fmtCur(totalLCE) }}</span>
            <span v-else>Balance Sheet is <strong>NOT balanced</strong> &mdash; Difference: {{ fmtCur(Math.abs(totalAssets - totalLCE)) }}</span>
        </div>

        <div class="bs-layout">
            <!-- Left: Assets -->
            <div class="bs-col">
                <div class="card">
                    <div class="bs-col-header assets-header">Assets</div>
                    <Table class="bs-table" :empty="assets.length === 0" empty-text="No asset ledgers.">
                        <tbody>
                            <tr v-for="row in assets" :key="row.ledger_id">
                                <td class="bs-name">{{ row.ledger_name }}</td>
                                <td class="bs-amt text-debit">{{ fmtCur(row.amount) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="totals-row">
                                <td>Total Assets</td>
                                <td class="bs-amt text-debit font-bold">{{ fmtCur(totalAssets) }}</td>
                            </tr>
                        </tfoot>
                    </Table>
                </div>
            </div>

            <!-- Right: Liabilities + Capital -->
            <div class="bs-col">
                <div class="card" style="margin-bottom:16px;">
                    <div class="bs-col-header liabilities-header">Liabilities</div>
                    <Table class="bs-table" :empty="liabilities.length === 0" empty-text="No liability ledgers.">
                        <tbody>
                            <tr v-for="row in liabilities" :key="row.ledger_id">
                                <td class="bs-name">{{ row.ledger_name }}</td>
                                <td class="bs-amt text-credit">{{ fmtCur(row.amount) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="totals-row">
                                <td>Total Liabilities</td>
                                <td class="bs-amt text-credit font-bold">{{ fmtCur(totalLiabilities) }}</td>
                            </tr>
                        </tfoot>
                    </Table>
                </div>

                <div class="card">
                    <div class="bs-col-header capital-header">Capital &amp; Reserves</div>
                    <Table class="bs-table" :empty="capital.length === 0" empty-text="No capital ledgers.">
                        <tbody>
                            <tr v-for="row in capital" :key="row.ledger_id">
                                <td class="bs-name">{{ row.ledger_name }}</td>
                                <td class="bs-amt text-capital">{{ fmtCur(row.amount) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="totals-row">
                                <td>Total Capital</td>
                                <td class="bs-amt text-capital font-bold">{{ fmtCur(totalCapital) }}</td>
                            </tr>
                            <tr class="grand-total-row">
                                <td>Total Liabilities + Capital</td>
                                <td class="bs-amt font-bold">{{ fmtCur(totalLCE) }}</td>
                            </tr>
                        </tfoot>
                    </Table>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.filter-label { display:block;font-size:0.78rem;font-weight:600;color:#374151;margin-bottom:4px; }
.filter-input {
    border:1.5px solid #e2e8f0;border-radius:8px;padding:7px 12px;
    font-size:0.84rem;outline:none;font-family:inherit;color:#1e293b;
}
.filter-input:focus { border-color:#6366f1; }

.bal-banner {
    display:flex;align-items:center;gap:10px;
    padding:13px 18px;border-radius:12px;font-size:0.88rem;margin-bottom:16px;
}
.bal-ok  { background:#d1fae5;color:#065f46; }
.bal-err { background:#fee2e2;color:#991b1b; }

.bs-layout { display:grid;grid-template-columns:1fr 1fr;gap:20px; }
.bs-col { display:flex;flex-direction:column; }

.bs-col-header {
    padding:13px 18px;font-size:0.88rem;font-weight:800;letter-spacing:0.02em;
    border-bottom:1px solid #f1f5f9;
}
.assets-header      { color:#4338ca;background:#ede9fe; }
.liabilities-header { color:#dc2626;background:#fee2e2; }
.capital-header     { color:#d97706;background:#fef3c7; }

.bs-name { color:#374151; }
.bs-amt  { text-align:right;font-family:'Courier New',monospace;font-size:0.82rem; }

.totals-row td { background:#f8fafc;font-weight:700;border-top:1.5px solid #e2e8f0; }
.grand-total-row td { background:#1e293b;color:#fff;font-weight:800; }
.font-bold   { font-weight:700; }
.text-debit  { color:#4338ca; }
.text-credit { color:#dc2626; }
.text-capital{ color:#d97706; }
.empty-cell  { text-align:center;color:#94a3b8;padding:30px; }

@media (max-width:768px) { .bs-layout { grid-template-columns:1fr; } }
@media print { .btn,.filter-input,.card:first-of-type { display:none; } }
</style>
