<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    rows       : Array,
    totals     : Object,
    isBalanced : Boolean,
    asOf       : String,
});

const asOf = ref(props.asOf);

function apply() {
    router.get(route('school.finance.statements.trial-balance'), { as_of: asOf.value }, {
        preserveState: true, replace: true,
    });
}

function exportCsv() {
    window.location.href = route('school.finance.statements.trial-balance') +
        '?as_of=' + asOf.value + '&export=csv';
}
function exportPdf() {
    window.location.href = route('school.finance.statements.trial-balance') +
        '?as_of=' + asOf.value + '&export=pdf';
}

const fmt    = (n) => new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2 }).format(n);
const fmtCur = (n) => '₹' + fmt(n);
</script>

<template>
    <SchoolLayout>
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Trial Balance</h1>
                <p class="page-header-sub">Closing balances of all ledger accounts — debits must equal credits</p>
            </div>
            <div style="display:flex;gap:10px;">
                <Button variant="secondary" @click="exportCsv">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:5px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    CSV
                </Button>
                <Button variant="secondary" @click="exportPdf">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:5px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    PDF
                </Button>
                <Button variant="secondary" @click="window.print()">Print</Button>
            </div>
        </div>

        <!-- Filter -->
        <div class="card" style="margin-bottom:16px;">
            <div class="card-body" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                <div>
                    <label class="filter-label">As of Date</label>
                    <input type="date" v-model="asOf" class="filter-input" />
                </div>
                <Button size="sm" @click="apply">Apply</Button>
            </div>
        </div>

        <!-- Balance status banner -->
        <div class="balance-banner" :class="isBalanced ? 'banner-ok' : 'banner-err'">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <template v-if="isBalanced"><polyline points="20 6 9 17 4 12"/></template>
                <template v-else><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></template>
            </svg>
            <span v-if="isBalanced">Trial Balance is <strong>balanced</strong> &mdash; Total Dr = Total Cr = {{ fmtCur(totals.debit) }}</span>
            <span v-else>Trial Balance is <strong>NOT balanced</strong> &mdash; Difference: {{ fmtCur(Math.abs(totals.debit - totals.credit)) }}</span>
        </div>

        <!-- Table -->
        <div class="card overflow-hidden">
            <Table class="tb-table" :empty="rows.length === 0" empty-text="No ledger balances found for this date.">
                <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Ledger Account</th>
                            <th style="width:140px;">Type</th>
                            <th class="text-right" style="width:160px;">Debit (Dr) ₹</th>
                            <th class="text-right" style="width:160px;">Credit (Cr) ₹</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in rows" :key="row.ledger_id">
                            <td class="text-muted mono">{{ i + 1 }}</td>
                            <td class="font-medium">{{ row.ledger_name }}</td>
                            <td><span class="type-badge">{{ row.type_name }}</span></td>
                            <td class="text-right mono text-debit">{{ row.debit > 0 ? fmtCur(row.debit) : '' }}</td>
                            <td class="text-right mono text-credit">{{ row.credit > 0 ? fmtCur(row.credit) : '' }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="totals-row">
                            <td colspan="3" style="text-align:right;padding-right:16px;font-weight:700;">Total</td>
                            <td class="text-right mono text-debit font-bold">{{ fmtCur(totals.debit) }}</td>
                            <td class="text-right mono text-credit font-bold">{{ fmtCur(totals.credit) }}</td>
                        </tr>
                    </tfoot>
            </Table>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.balance-banner {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 18px; border-radius: 12px; font-size: 0.88rem; margin-bottom: 16px;
}
.banner-ok  { background: #d1fae5; color: #065f46; }
.banner-err { background: #fee2e2; color: #991b1b; }

.filter-label { display:block;font-size:0.78rem;font-weight:600;color:#374151;margin-bottom:4px; }
.filter-input {
    border:1.5px solid #e2e8f0;border-radius:8px;padding:7px 12px;
    font-size:0.84rem;outline:none;font-family:inherit;color:#1e293b;
    transition:border-color 0.15s;
}
.filter-input:focus { border-color:#6366f1; }

.totals-row td { background:#f1f5f9;font-weight:700;border-top:2px solid #e2e8f0; }
.font-bold  { font-weight:700; }
.font-medium { font-weight:500; }
.text-right  { text-align:right; }
.text-muted  { color:#94a3b8;font-size:0.8rem; }
.mono        { font-family:'Courier New',monospace;font-size:0.82rem; }
.text-debit  { color:#4338ca; }
.text-credit { color:#059669; }
.empty-cell  { text-align:center;color:#94a3b8;padding:40px; }

.type-badge {
    font-size:0.7rem;font-weight:600;padding:2px 8px;border-radius:20px;
    background:#f1f5f9;color:#475569;
}

@media print {
    .btn, .filter-input, .card:first-of-type { display:none; }
}
</style>
