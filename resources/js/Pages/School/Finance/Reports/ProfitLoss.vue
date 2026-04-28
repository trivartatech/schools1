<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import PrintButton from '@/Components/ui/PrintButton.vue';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import StatementsTabNav from '@/Components/finance/StatementsTabNav.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';

const props = defineProps({
    income        : Array,
    expenses      : Array,
    totalIncome   : Number,
    totalExpenses : Number,
    netSurplus    : Number,
    from          : String,
    to            : String,
});

const from = ref(props.from);
const to   = ref(props.to);

function apply() {
    router.get(route('school.finance.statements.profit-loss'), { from: from.value, to: to.value }, {
        preserveState: true, replace: true,
    });
}

function exportCsv() {
    window.location.href = route('school.finance.statements.profit-loss') +
        '?from=' + from.value + '&to=' + to.value + '&export=csv';
}
function exportPdf() {
    window.location.href = route('school.finance.statements.profit-loss') +
        '?from=' + from.value + '&to=' + to.value + '&export=pdf';
}

const fmt    = (n) => new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2 }).format(Math.abs(n));
const fmtCur = (n) => '₹' + fmt(n);
</script>

<template>
    <SchoolLayout>
        <StatementsTabNav current="profit-loss" />
        <PageHeader title="Profit &amp; Loss Statement" subtitle="Income earned vs expenses incurred in the selected period">
            <template #actions>
                <Button variant="secondary" @click="exportCsv">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:5px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    CSV
                </Button>
                <Button variant="secondary" @click="exportPdf">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:5px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    PDF
                </Button>
                <PrintButton />

            </template>
        </PageHeader>

        <!-- Date filter -->
        <FilterBar :active="false">
            <div class="form-field">
                <label>From</label>
                <input type="date" v-model="from" style="width:160px;" />
            </div>
            <div class="form-field">
                <label>To</label>
                <input type="date" v-model="to" style="width:160px;" />
            </div>
            <Button size="sm" @click="apply">Apply</Button>
        </FilterBar>

        <!-- Net result banner -->
        <div class="net-banner" :class="netSurplus >= 0 ? 'surplus' : 'deficit'">
            <div class="net-label">{{ netSurplus >= 0 ? 'Net Surplus' : 'Net Deficit' }}</div>
            <div class="net-amount">{{ fmtCur(netSurplus) }}</div>
        </div>

        <div class="pl-layout">
            <!-- Income -->
            <div class="card pl-section">
                <div class="pl-section-header income-header">
                    <span>Income</span>
                    <span class="section-total">{{ fmtCur(totalIncome) }}</span>
                </div>
                <Table class="pl-table" :empty="income.length === 0" empty-text="No income ledgers found.">
                    <tbody>
                        <tr v-for="row in income" :key="row.ledger_id">
                            <td class="pl-name">{{ row.ledger_name }}</td>
                            <td class="pl-amt text-credit">{{ row.amount > 0 ? fmtCur(row.amount) : '—' }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="totals-row">
                            <td>Total Income</td>
                            <td class="pl-amt text-credit font-bold">{{ fmtCur(totalIncome) }}</td>
                        </tr>
                    </tfoot>
                </Table>
            </div>

            <!-- Expenses -->
            <div class="card pl-section">
                <div class="pl-section-header expense-header">
                    <span>Expenses</span>
                    <span class="section-total">{{ fmtCur(totalExpenses) }}</span>
                </div>
                <Table class="pl-table" :empty="expenses.length === 0" empty-text="No expense ledgers found.">
                    <tbody>
                        <tr v-for="row in expenses" :key="row.ledger_id">
                            <td class="pl-name">{{ row.ledger_name }}</td>
                            <td class="pl-amt text-debit">{{ row.amount > 0 ? fmtCur(row.amount) : '—' }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="totals-row">
                            <td>Total Expenses</td>
                            <td class="pl-amt text-debit font-bold">{{ fmtCur(totalExpenses) }}</td>
                        </tr>
                    </tfoot>
                </Table>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.net-banner {
    display:flex;justify-content:space-between;align-items:center;
    padding:18px 24px;border-radius:14px;margin-bottom:20px;
}
.surplus { background:linear-gradient(135deg,#059669,#10b981); color:#fff; }
.deficit { background:linear-gradient(135deg,#dc2626,#f87171); color:#fff; }
.net-label  { font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;opacity:0.85; }
.net-amount { font-size:1.8rem;font-weight:900;font-family:'Courier New',monospace; }

.pl-layout { display:grid;grid-template-columns:1fr 1fr;gap:20px; }

.pl-section-header {
    display:flex;justify-content:space-between;align-items:center;
    padding:14px 18px;font-size:0.88rem;font-weight:700;
    border-bottom:1px solid #f1f5f9;
}
.income-header  { color:#059669;background:#f0fdf4; }
.expense-header { color:#dc2626;background:#fef2f2; }
.section-total  { font-family:'Courier New',monospace;font-size:1rem; }

.pl-name { color:#374151; }
.pl-amt  { text-align:right;font-family:'Courier New',monospace;font-size:0.82rem; }

.totals-row td { background:#f8fafc;font-weight:700;border-top:1.5px solid #e2e8f0;padding:12px 18px; }
.font-bold   { font-weight:700; }
.text-debit  { color:#4338ca; }
.text-credit { color:#059669; }
.empty-cell  { text-align:center;color:#94a3b8;padding:30px; }

@media (max-width:768px) { .pl-layout { grid-template-columns:1fr; } }
@media print { .btn,.fb { display:none; } }
</style>
