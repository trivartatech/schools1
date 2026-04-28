<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    ledger  : Object,
    rows    : Array,
    filters : Object,
});

const from = ref(props.filters.from);
const to   = ref(props.filters.to);

function applyFilter() {
    router.get(route('school.finance.ledgers.show', props.ledger.id), { from: from.value, to: to.value }, {
        preserveState: true, replace: true,
    });
}

function printLedger() {
    window.print();
}

function exportCsv() {
    window.location.href = route('school.finance.ledgers.show', props.ledger.id) +
        '?from=' + from.value + '&to=' + to.value + '&export=csv';
}

function exportPdf() {
    window.location.href = route('school.finance.ledgers.show', props.ledger.id) +
        '?from=' + from.value + '&to=' + to.value + '&export=pdf';
}

const totalDebit  = computed(() => props.rows.reduce((s, r) => s + (r.debit  ?? 0), 0));
const totalCredit = computed(() => props.rows.reduce((s, r) => s + (r.credit ?? 0), 0));

const fmt    = (n) => n == null ? '—' : new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2 }).format(n);
const fmtCur = (n) => '₹' + fmt(n);
</script>

<template>
    <SchoolLayout>
        <!-- Header -->
        <PageHeader>
            <template #title>
                <div style="display:flex; align-items:center; gap:12px;">
                    <Link :href="route('school.finance.ledgers.index')" class="back-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                        </svg>
                    </Link>
                    <h1 class="page-header-title">{{ ledger.name }}</h1>
                </div>
            </template>
            <template #subtitle>
                <p class="page-header-sub">
                    {{ ledger.ledger_type?.name }} &bull;
                    Code: {{ ledger.code || '—' }}
                </p>
            </template>
            <template #actions>
                <Button variant="secondary" @click="exportCsv">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:5px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    CSV
                </Button>
                <Button variant="secondary" @click="exportPdf">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:5px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    PDF
                </Button>
                <Button variant="secondary" @click="printLedger">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px;">
                        <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
                    </svg>
                    Print
                </Button>
            </template>
        </PageHeader>

        <!-- Summary banner -->
        <div class="ledger-banner">
            <div class="banner-item">
                <div class="banner-label">Opening Balance</div>
                <div class="banner-value">
                    {{ fmtCur(ledger.opening_balance) }}
                    <span class="dr-cr">{{ ledger.opening_balance_type === 'debit' ? 'Dr' : 'Cr' }}</span>
                </div>
            </div>
            <div class="banner-sep"></div>
            <div class="banner-item">
                <div class="banner-label">Total Debits (period)</div>
                <div class="banner-value text-debit">{{ fmtCur(totalDebit) }}</div>
            </div>
            <div class="banner-sep"></div>
            <div class="banner-item">
                <div class="banner-label">Total Credits (period)</div>
                <div class="banner-value text-credit">{{ fmtCur(totalCredit) }}</div>
            </div>
            <div class="banner-sep"></div>
            <div class="banner-item">
                <div class="banner-label">Closing Balance</div>
                <div class="banner-value">
                    {{ fmtCur(ledger.balance) }}
                    <span class="dr-cr">{{ ledger.balance_type === 'debit' ? 'Dr' : 'Cr' }}</span>
                </div>
            </div>
        </div>

        <!-- Date filter -->
        <div class="card" style="margin-bottom:16px;">
            <div class="card-body" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
                <div>
                    <label class="filter-label">From</label>
                    <input type="date" v-model="from" class="filter-input" />
                </div>
                <div>
                    <label class="filter-label">To</label>
                    <input type="date" v-model="to" class="filter-input" />
                </div>
                <Button size="sm" @click="applyFilter">Apply</Button>
            </div>
        </div>

        <!-- Ledger Book table -->
        <div class="card overflow-hidden">
            <Table class="data-table">
                <thead>
                        <tr>
                            <th style="width:110px;">Date</th>
                            <th style="width:140px;">Transaction #</th>
                            <th style="width:100px;">Type</th>
                            <th>Narration</th>
                            <th class="text-right" style="width:130px;">Debit (Dr)</th>
                            <th class="text-right" style="width:130px;">Credit (Cr)</th>
                            <th class="text-right" style="width:150px;">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Opening balance row -->
                        <tr class="opening-row">
                            <td colspan="4" style="font-weight:600; color:#64748b;">Opening Balance</td>
                            <td class="text-right mono">{{ ledger.opening_balance_type === 'debit' ? fmtCur(ledger.opening_balance) : '—' }}</td>
                            <td class="text-right mono">{{ ledger.opening_balance_type === 'credit' ? fmtCur(ledger.opening_balance) : '—' }}</td>
                            <td class="text-right mono">
                                {{ fmtCur(ledger.opening_balance) }}
                                <span class="dr-cr">{{ ledger.opening_balance_type === 'debit' ? 'Dr' : 'Cr' }}</span>
                            </td>
                        </tr>

                        <tr v-for="r in rows" :key="r.id">
                            <td class="mono">{{ school.fmtDate(r.date) }}</td>
                            <td>
                                <!-- FIX #5/#6: use integer transaction_id for route model binding -->
                                <Link :href="route('school.finance.transactions.show', r.transaction_id)" class="txn-link">
                                    {{ r.transaction_no }}
                                </Link>
                            </td>
                            <td>
                                <span class="type-pill" :class="'type-' + r.type_label.toLowerCase()">{{ r.type_label }}</span>
                            </td>
                            <td class="narration">{{ r.narration || '—' }}</td>
                            <td class="text-right mono text-debit">{{ r.debit  != null ? fmtCur(r.debit)  : '' }}</td>
                            <td class="text-right mono text-credit">{{ r.credit != null ? fmtCur(r.credit) : '' }}</td>
                            <td class="text-right mono">
                                {{ fmtCur(r.running_balance) }}
                                <span class="dr-cr">{{ r.running_balance_type }}</span>
                            </td>
                        </tr>

                        <!-- Totals row -->
                        <tr class="totals-row">
                            <td colspan="4" style="font-weight:700; text-align:right; padding-right:20px;">Totals</td>
                            <td class="text-right mono text-debit font-bold">{{ fmtCur(totalDebit) }}</td>
                            <td class="text-right mono text-credit font-bold">{{ fmtCur(totalCredit) }}</td>
                            <td class="text-right mono font-bold">
                                {{ fmtCur(ledger.balance) }}
                                <span class="dr-cr">{{ ledger.balance_type === 'debit' ? 'Dr' : 'Cr' }}</span>
                            </td>
                        </tr>
                    </tbody>
            </Table>
            <div v-if="rows.length === 0" class="empty-cell">No transactions in this period.</div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.back-btn {
    width: 36px; height: 36px;
    border: 1.5px solid #e2e8f0;
    background: #fff;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #64748b;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
}
.back-btn:hover { background: #f1f5f9; color: #1e293b; }

.ledger-banner {
    display: flex;
    flex-wrap: wrap;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 14px;
    padding: 20px 24px;
    margin-bottom: 20px;
    gap: 0;
}
.banner-item  { flex: 1; min-width: 150px; padding: 4px 16px; }
.banner-item:first-child { padding-left: 0; }
.banner-sep   { width: 1px; background: rgba(255,255,255,0.2); margin: 4px 0; }
.banner-label { font-size: 0.72rem; color: rgba(255,255,255,0.7); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
.banner-value { font-size: 1.2rem; font-weight: 800; color: #fff; margin-top: 4px; }
.dr-cr        { font-size: 0.68rem; font-weight: 700; margin-left: 3px; opacity: 0.7; }

.filter-label  { display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 4px; }
.filter-input  {
    border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 7px 12px;
    font-size: 0.84rem; outline: none; font-family: inherit; color: #1e293b;
    transition: border-color 0.15s;
}
.filter-input:focus { border-color: #6366f1; }

.opening-row td { background: #f8fafc; color: #64748b; font-style: italic; }
.totals-row td  { background: #f1f5f9; font-weight: 700; border-top: 2px solid #e2e8f0; }
.font-bold { font-weight: 700; }

.text-right  { text-align: right; }
.mono        { font-family: 'Courier New', monospace; font-size: 0.82rem; }
.text-debit  { color: #4338ca; }
.text-credit { color: #059669; }

.narration { color: #475569; font-size: 0.82rem; }
.txn-link  { color: #6366f1; font-weight: 600; text-decoration: none; font-family: monospace; }
.txn-link:hover { text-decoration: underline; }

.type-pill { font-size: 0.68rem; font-weight: 600; padding: 2px 7px; border-radius: 20px; }
.type-journal { background: #ede9fe; color: #6366f1; }
.type-receipt { background: #d1fae5; color: #059669; }
.type-payment { background: #fee2e2; color: #dc2626; }
.type-contra  { background: #fef3c7; color: #d97706; }

.empty-cell { text-align: center; color: #94a3b8; padding: 40px; }
</style>
