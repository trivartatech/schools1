<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Table from '@/Components/ui/Table.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import ExportDropdown from '@/Components/ExportDropdown.vue';

const props = defineProps({
    defaulters: Array,
    classes: Array,
    filters: Object,
});

const filterForm = ref({
    class_id:   props.filters.class_id   || '',
    section_id: props.filters.section_id || '',
    status:     props.filters.status     || 'all',
    fee_types:  Array.isArray(props.filters.fee_types) ? [...props.filters.fee_types] : [],
});

const sections = ref([]);
const searchQuery = ref('');
const sortKey = ref('total_balance');
const sortDir = ref('desc');
const currentPage = ref(1);
const perPage = ref(25);

const numericKeys = new Set([
    'total_fee', 'paid_fee', 'fee_due',
    'transport_fee', 'transport_paid', 'transport_due',
    'hostel_fee', 'hostel_paid', 'hostel_due',
    'stationary_fee', 'stationary_paid', 'stationary_due',
    'total_balance',
]);

const FEE_TYPE_OPTIONS = [
    { value: 'regular',    label: 'Normal Fee' },
    { value: 'transport',  label: 'Transport Fee' },
    { value: 'hostel',     label: 'Hostel Fee' },
    { value: 'stationary', label: 'Stationary Fee' },
];

function toggleFeeType(value) {
    const arr = filterForm.value.fee_types;
    const idx = arr.indexOf(value);
    if (idx >= 0) arr.splice(idx, 1);
    else arr.push(value);
    fetchReport();
}

const fetchSections = () => {
    if (!filterForm.value.class_id) {
        sections.value = [];
        filterForm.value.section_id = '';
        fetchReport();
        return;
    }
    axios.get(route('school.classes.sections', filterForm.value.class_id))
        .then(res => {
            sections.value = res.data;
            if (!sections.value.find(s => s.id == filterForm.value.section_id)) {
                filterForm.value.section_id = '';
            }
            fetchReport();
        });
};

const fetchReport = () => {
    router.get(route('school.finance.due-report'), filterForm.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilter = () => {
    filterForm.value.class_id = '';
    filterForm.value.section_id = '';
    filterForm.value.status = 'all';
    filterForm.value.fee_types = [];
    sections.value = [];
    searchQuery.value = '';
    fetchReport();
};

onMounted(() => {
    if (filterForm.value.class_id) {
        axios.get(route('school.classes.sections', filterForm.value.class_id))
            .then(res => { sections.value = res.data; });
    }
});

const setSort = (key) => {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = numericKeys.has(key) ? 'desc' : 'asc';
    }
    currentPage.value = 1;
};

const sortIndicator = (key) => {
    if (sortKey.value !== key) return 'inactive';
    return sortDir.value === 'asc' ? 'asc' : 'desc';
};

watch(searchQuery, () => { currentPage.value = 1; });
watch([() => filterForm.value.status, perPage], () => { currentPage.value = 1; });

const filteredRows = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.defaulters;
    return props.defaulters.filter(r =>
        String(r.name).toLowerCase().includes(q) ||
        String(r.father_contact).toLowerCase().includes(q) ||
        String(r.mother_contact).toLowerCase().includes(q)
    );
});

const sortedRows = computed(() => {
    const rows = [...filteredRows.value];
    const k = sortKey.value;
    const dir = sortDir.value === 'asc' ? 1 : -1;
    const collator = new Intl.Collator('en', { sensitivity: 'base' });
    rows.sort((a, b) => numericKeys.has(k)
        ? ((Number(a[k]) - Number(b[k])) * dir)
        : (collator.compare(String(a[k] ?? ''), String(b[k] ?? '')) * dir)
    );
    return rows;
});

const totalPages = computed(() => Math.max(1, Math.ceil(sortedRows.value.length / perPage.value)));

const pagedRows = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return sortedRows.value.slice(start, start + perPage.value);
});

const visiblePages = computed(() => {
    const total = totalPages.value;
    const cur = currentPage.value;
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    if (cur <= 4) return [1, 2, 3, 4, 5, '…', total];
    if (cur >= total - 3) return [1, '…', total - 4, total - 3, total - 2, total - 1, total];
    return [1, '…', cur - 1, cur, cur + 1, '…', total];
});

const goToPage = (p) => {
    if (typeof p !== 'number') return;
    currentPage.value = Math.min(Math.max(1, p), totalPages.value);
};

const stats = computed(() => {
    const d = props.defaulters || [];
    const defaulters = d.filter(r => r.is_defaulter).length;
    const outstanding = d.reduce((s, r) => s + Number(r.total_balance || 0), 0);
    const collected = d.reduce((s, r) =>
        s + Number(r.paid_fee || 0) + Number(r.transport_paid || 0) + Number(r.hostel_paid || 0) + Number(r.stationary_paid || 0), 0);
    return { total: d.length, defaulters, outstanding, collected };
});

const filteredTotals = computed(() => {
    const acc = {
        total_fee: 0, paid_fee: 0, fee_due: 0,
        transport_fee: 0, transport_paid: 0, transport_due: 0,
        hostel_fee: 0, hostel_paid: 0, hostel_due: 0,
        stationary_fee: 0, stationary_paid: 0, stationary_due: 0,
        total_balance: 0,
    };
    for (const r of sortedRows.value) {
        for (const k of Object.keys(acc)) acc[k] += Number(r[k] || 0);
    }
    return acc;
});

const fromIndex = computed(() => sortedRows.value.length === 0 ? 0 : (currentPage.value - 1) * perPage.value + 1);
const toIndex   = computed(() => Math.min(currentPage.value * perPage.value, sortedRows.value.length));

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(Number(amount) || 0);

// ── Reminder dispatch ────────────────────────────────────────────────────────
const sendingFor  = ref(null);   // student_id while a single send is in flight
const sendingBulk = ref(false);

async function sendReminder(studentIds, label) {
    try {
        const { data } = await axios.post(
            route('school.finance.due-report.send-reminder'),
            { student_ids: studentIds }
        );
        alert(data.message ?? `Reminders sent to ${label}.`);
    } catch (e) {
        alert(e.response?.data?.message ?? 'Could not send reminders.');
    }
}

async function sendOne(row) {
    sendingFor.value = row.student_id;
    await sendReminder([row.student_id], row.name);
    sendingFor.value = null;
}

async function sendAll() {
    const targets = (props.defaulters || []).filter(d => Number(d.total_balance || 0) > 0);
    if (targets.length === 0) return;
    if (!confirm(`Send fee due reminders to ${targets.length} parent(s)? This will trigger SMS / WhatsApp / Voice based on your active templates.`)) return;
    sendingBulk.value = true;
    await sendReminder(targets.map(d => d.student_id), `${targets.length} parent(s)`);
    sendingBulk.value = false;
}

// ── Defaulter flag controls ──────────────────────────────────────────────────
const flaggingFor  = ref(null);   // student_id while a single flag toggle is in flight
const flaggingBulk = ref(false);

function toggleRowDefaulter(row) {
    const next = !row.is_defaulter;
    flaggingFor.value = row.student_id;
    router.patch(`/school/students/${row.student_id}/defaulter`,
        { is_defaulter: next },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { row.is_defaulter = next; },   // optimistic update on the in-memory row
            onFinish:  () => { flaggingFor.value = null; },
        }
    );
}

function bulkFlagListed(flag) {
    // Targets students currently visible (after search + fee-type filter) with balance > 0
    // OR for "unflag", any visible student with is_defaulter === true.
    const targets = sortedRows.value.filter(r =>
        flag ? Number(r.total_balance || 0) > 0 && !r.is_defaulter
             : r.is_defaulter
    );
    if (!targets.length) {
        alert(flag
            ? 'No matching students to flag — everyone with dues is already flagged.'
            : 'No flagged students in the current view to unflag.');
        return;
    }
    const verb = flag ? 'flag as defaulter' : 'unflag';
    if (!confirm(`${verb.charAt(0).toUpperCase() + verb.slice(1)} ${targets.length} student(s) currently shown? This sets the defaulter pill on their profile.`)) return;
    flaggingBulk.value = true;
    router.post(route('school.students.defaulter.bulk'),
        { student_ids: targets.map(t => t.student_id), is_defaulter: flag },
        {
            preserveScroll: true,
            preserveState: false,   // refresh the list so flags re-render server-side
            onFinish: () => { flaggingBulk.value = false; },
        }
    );
}
</script>

<template>
    <SchoolLayout>
        <!-- Page header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Due Report &amp; Defaulter List</h1>
                <p class="page-header-sub">All students for the current academic year, with regular, transport, hostel, and stationary fee balances.</p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <Button
                    variant="secondary"
                    :disabled="flaggingBulk || sortedRows.length === 0"
                    title="Mark every visible student with outstanding dues as a defaulter"
                    @click="bulkFlagListed(true)"
                >
                    {{ flaggingBulk ? '…' : '🚩 Flag Listed' }}
                </Button>
                <Button
                    variant="secondary"
                    :disabled="flaggingBulk || sortedRows.filter(r => r.is_defaulter).length === 0"
                    title="Remove the defaulter flag from every visible flagged student"
                    @click="bulkFlagListed(false)"
                >
                    {{ flaggingBulk ? '…' : '✓ Unflag Listed' }}
                </Button>
                <Button
                    variant="primary"
                    :disabled="sendingBulk || defaulters.length === 0"
                    @click="sendAll"
                >
                    {{ sendingBulk ? 'Sending…' : `📣 Send All Reminders${defaulters.length ? ` (${defaulters.length})` : ''}` }}
                </Button>
                <ExportDropdown
                    :base-url="`/school/export/due-report`"
                    :params="{
                        class_id:   filterForm.class_id,
                        section_id: filterForm.section_id,
                        status:     filterForm.status,
                        fee_types:  filterForm.fee_types.join(','),
                        search:     searchQuery,
                        sort_key:   sortKey,
                        sort_dir:   sortDir,
                    }"
                    :formats="['excel', 'pdf']"
                />
            </div>
        </div>

        <!-- Summary cards -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-label">Total Students</div>
                <div class="stat-value">{{ stats.total }}</div>
            </div>
            <div class="stat-card stat-card--warn">
                <div class="stat-label">Defaulters</div>
                <div class="stat-value">{{ stats.defaulters }}</div>
            </div>
            <div class="stat-card stat-card--danger">
                <div class="stat-label">Total Outstanding</div>
                <div class="stat-value">{{ formatCurrency(stats.outstanding) }}</div>
            </div>
            <div class="stat-card stat-card--success">
                <div class="stat-label">Total Collected</div>
                <div class="stat-value">{{ formatCurrency(stats.collected) }}</div>
            </div>
        </div>

        <!-- Filters -->
        <FilterBar
            :active="!!(filterForm.class_id || filterForm.section_id || (filterForm.status && filterForm.status !== 'all') || filterForm.fee_types.length || searchQuery)"
            @clear="resetFilter"
        >
            <div class="form-field">
                <label>Class</label>
                <select v-model="filterForm.class_id" @change="fetchSections" style="width:180px;">
                    <option value="">All Classes</option>
                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>

            <div class="form-field" v-if="filterForm.class_id && sections.length > 0">
                <label>Section</label>
                <select v-model="filterForm.section_id" @change="fetchReport" style="width:180px;">
                    <option value="">All Sections</option>
                    <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>

            <div class="form-field">
                <label>Defaulter Flag</label>
                <select v-model="filterForm.status" @change="fetchReport" style="width:190px;">
                    <option value="all">All Students</option>
                    <option value="defaulter">Defaulters Only</option>
                    <option value="not_defaulter">Non-Defaulters Only</option>
                </select>
            </div>

            <div class="form-field fee-type-field">
                <label>Show Students With Dues In</label>
                <div class="fee-type-chips">
                    <label v-for="opt in FEE_TYPE_OPTIONS" :key="opt.value" class="fee-chip"
                           :class="{ active: filterForm.fee_types.includes(opt.value) }">
                        <input type="checkbox"
                               :checked="filterForm.fee_types.includes(opt.value)"
                               @change="toggleFeeType(opt.value)" />
                        <span>{{ opt.label }}</span>
                    </label>
                </div>
            </div>

            <div class="fb-search">
                <svg class="fb-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input v-model="searchQuery" type="text" placeholder="Search name, father, mother…" />
            </div>
        </FilterBar>

        <!-- Table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <Table class="due-table">
                    <thead>
                        <tr>
                            <th class="sortable" @click="setSort('name')">
                                Student Name <span class="sort-arrow" :class="sortIndicator('name')"></span>
                            </th>
                            <th class="sortable" @click="setSort('class')">
                                Class &amp; Section <span class="sort-arrow" :class="sortIndicator('class')"></span>
                            </th>
                            <th class="sortable" @click="setSort('father_contact')">
                                Father Contact <span class="sort-arrow" :class="sortIndicator('father_contact')"></span>
                            </th>
                            <th class="sortable" @click="setSort('mother_contact')">
                                Mother Contact <span class="sort-arrow" :class="sortIndicator('mother_contact')"></span>
                            </th>
                            <th class="sortable text-right" @click="setSort('total_fee')">
                                Total Fee <span class="sort-arrow" :class="sortIndicator('total_fee')"></span>
                            </th>
                            <th class="sortable text-right" @click="setSort('paid_fee')">
                                Paid Fee <span class="sort-arrow" :class="sortIndicator('paid_fee')"></span>
                            </th>
                            <th class="sortable text-right" @click="setSort('fee_due')">
                                Fee Due <span class="sort-arrow" :class="sortIndicator('fee_due')"></span>
                            </th>
                            <th class="sortable text-right" @click="setSort('transport_fee')">
                                Transport Fee <span class="sort-arrow" :class="sortIndicator('transport_fee')"></span>
                            </th>
                            <th class="sortable text-right" @click="setSort('transport_paid')">
                                Transport Paid <span class="sort-arrow" :class="sortIndicator('transport_paid')"></span>
                            </th>
                            <th class="sortable text-right" @click="setSort('transport_due')">
                                Transport Due <span class="sort-arrow" :class="sortIndicator('transport_due')"></span>
                            </th>
                            <th class="sortable text-right" @click="setSort('hostel_fee')">
                                Hostel Fee <span class="sort-arrow" :class="sortIndicator('hostel_fee')"></span>
                            </th>
                            <th class="sortable text-right" @click="setSort('hostel_paid')">
                                Hostel Paid <span class="sort-arrow" :class="sortIndicator('hostel_paid')"></span>
                            </th>
                            <th class="sortable text-right" @click="setSort('hostel_due')">
                                Hostel Due <span class="sort-arrow" :class="sortIndicator('hostel_due')"></span>
                            </th>
                            <th class="sortable text-right" @click="setSort('stationary_fee')">
                                Stationary Fee <span class="sort-arrow" :class="sortIndicator('stationary_fee')"></span>
                            </th>
                            <th class="sortable text-right" @click="setSort('stationary_paid')">
                                Stationary Paid <span class="sort-arrow" :class="sortIndicator('stationary_paid')"></span>
                            </th>
                            <th class="sortable text-right" @click="setSort('stationary_due')">
                                Stationary Due <span class="sort-arrow" :class="sortIndicator('stationary_due')"></span>
                            </th>
                            <th class="sortable text-right balance-col" @click="setSort('total_balance')">
                                Total Balance <span class="sort-arrow" :class="sortIndicator('total_balance')"></span>
                            </th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in pagedRows" :key="row.student_id">
                            <td class="font-medium">
                                {{ row.name }}
                                <span v-if="row.is_defaulter" class="defaulter-pill" title="Manually flagged as defaulter">Defaulter</span>
                            </td>
                            <td class="text-sm">{{ row.class }}</td>
                            <td class="text-sm font-mono">{{ row.father_contact }}</td>
                            <td class="text-sm font-mono">{{ row.mother_contact }}</td>
                            <td class="text-right text-sm">{{ formatCurrency(row.total_fee) }}</td>
                            <td class="text-right text-sm" style="color: var(--success)">{{ formatCurrency(row.paid_fee) }}</td>
                            <td class="text-right text-sm">{{ formatCurrency(row.fee_due) }}</td>
                            <td class="text-right text-sm">{{ formatCurrency(row.transport_fee) }}</td>
                            <td class="text-right text-sm" style="color: var(--success)">{{ formatCurrency(row.transport_paid) }}</td>
                            <td class="text-right text-sm">{{ formatCurrency(row.transport_due) }}</td>
                            <td class="text-right text-sm">{{ formatCurrency(row.hostel_fee) }}</td>
                            <td class="text-right text-sm" style="color: var(--success)">{{ formatCurrency(row.hostel_paid) }}</td>
                            <td class="text-right text-sm">{{ formatCurrency(row.hostel_due) }}</td>
                            <td class="text-right text-sm">{{ formatCurrency(row.stationary_fee) }}</td>
                            <td class="text-right text-sm" style="color: var(--success)">{{ formatCurrency(row.stationary_paid) }}</td>
                            <td class="text-right text-sm">{{ formatCurrency(row.stationary_due) }}</td>
                            <td class="text-right font-bold balance-col"
                                :class="{ 'balance-zero': Number(row.total_balance) === 0 }">
                                {{ formatCurrency(row.total_balance) }}
                            </td>
                            <td class="text-center print:hidden">
                                <div class="flex items-center justify-center gap-2 flex-wrap">
                                    <Button
                                        v-if="Number(row.total_balance) > 0"
                                        size="xs"
                                        as="a"
                                        :href="`/school/fee/collect?student_id=${row.student_id}`"
                                    >Pay Now</Button>
                                    <span v-else class="paid-badge">PAID</span>
                                    <Button
                                        v-if="Number(row.total_balance) > 0"
                                        size="xs"
                                        variant="secondary"
                                        :disabled="sendingFor === row.student_id"
                                        @click="sendOne(row)"
                                    >
                                        {{ sendingFor === row.student_id ? '…' : '📣 Remind' }}
                                    </Button>
                                    <Button
                                        size="xs"
                                        variant="secondary"
                                        :disabled="flaggingFor === row.student_id"
                                        :title="row.is_defaulter ? 'Click to unflag this student' : 'Mark this student as a fee defaulter'"
                                        @click="toggleRowDefaulter(row)"
                                    >
                                        {{ flaggingFor === row.student_id ? '…' : (row.is_defaulter ? '✓ Unflag' : '🚩 Flag') }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="sortedRows.length === 0">
                            <td colspan="15" class="empty-row">
                                No students found matching the current filters.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="sortedRows.length > 0">
                        <tr class="grand-total-row">
                            <td colspan="4" class="text-right">Grand Total ({{ sortedRows.length }} students):</td>
                            <td class="text-right">{{ formatCurrency(filteredTotals.total_fee) }}</td>
                            <td class="text-right" style="color: var(--success)">{{ formatCurrency(filteredTotals.paid_fee) }}</td>
                            <td class="text-right">{{ formatCurrency(filteredTotals.fee_due) }}</td>
                            <td class="text-right">{{ formatCurrency(filteredTotals.transport_fee) }}</td>
                            <td class="text-right" style="color: var(--success)">{{ formatCurrency(filteredTotals.transport_paid) }}</td>
                            <td class="text-right">{{ formatCurrency(filteredTotals.transport_due) }}</td>
                            <td class="text-right">{{ formatCurrency(filteredTotals.hostel_fee) }}</td>
                            <td class="text-right" style="color: var(--success)">{{ formatCurrency(filteredTotals.hostel_paid) }}</td>
                            <td class="text-right">{{ formatCurrency(filteredTotals.hostel_due) }}</td>
                            <td class="text-right">{{ formatCurrency(filteredTotals.stationary_fee) }}</td>
                            <td class="text-right" style="color: var(--success)">{{ formatCurrency(filteredTotals.stationary_paid) }}</td>
                            <td class="text-right">{{ formatCurrency(filteredTotals.stationary_due) }}</td>
                            <td class="text-right balance-col">{{ formatCurrency(filteredTotals.total_balance) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </Table>
            </div>

            <!-- Pagination strip -->
            <div v-if="sortedRows.length > 0" class="pagination-bar">
                <div class="pagination-info">
                    Showing <strong>{{ fromIndex }}</strong>–<strong>{{ toIndex }}</strong> of <strong>{{ sortedRows.length }}</strong>
                </div>
                <div class="pagination-controls">
                    <button class="page-btn" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">‹ Prev</button>
                    <button
                        v-for="(p, idx) in visiblePages"
                        :key="idx"
                        class="page-btn"
                        :class="{ active: p === currentPage, ellipsis: p === '…' }"
                        :disabled="p === '…'"
                        @click="goToPage(p)"
                    >{{ p }}</button>
                    <button class="page-btn" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">Next ›</button>
                </div>
                <div class="pagination-perpage">
                    <label>Rows:</label>
                    <select v-model.number="perPage">
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
/* ── Summary cards ─────────────────────────────────────────── */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}
.stat-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #6366f1;
    border-radius: 12px;
    padding: 14px 16px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.stat-card--warn    { border-left-color: #f59e0b; }
.stat-card--danger  { border-left-color: #ef4444; }
.stat-card--success { border-left-color: #10b981; }
.stat-label {
    font-size: 0.7rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.stat-value {
    margin-top: 4px;
    font-size: 1.4rem;
    font-weight: 700;
    color: #0f172a;
}

/* ── Search input inside FilterBar ─────────────────────────── */
.fb-search {
    position: relative;
    display: flex;
    align-items: center;
    margin-left: auto;
}
.fb-search input {
    padding-left: 34px;
    width: 260px;
}
.fb-search-icon {
    position: absolute;
    left: 10px;
    width: 15px;
    height: 15px;
    color: #94a3b8;
    pointer-events: none;
}

/* ── Table styling ─────────────────────────────────────────── */
.due-table :deep(thead th) {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f8fafc;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #475569;
    padding: 10px 12px;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}
.due-table :deep(tbody td) {
    padding: 9px 12px;
    border-bottom: 1px solid #f1f5f9;
}
.due-table :deep(tbody tr:nth-child(even) td) { background: #fafbfc; }
.due-table :deep(tbody tr:hover td)            { background: #eef2ff; }

.sortable {
    cursor: pointer;
    user-select: none;
}
.sortable:hover { color: #1e293b; }

.sort-arrow {
    display: inline-block;
    width: 0;
    height: 0;
    margin-left: 6px;
    vertical-align: middle;
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
    opacity: 0.25;
}
.sort-arrow.inactive { border-bottom: 5px solid #94a3b8; }
.sort-arrow.asc      { border-bottom: 5px solid #1169cd; opacity: 1; }
.sort-arrow.desc     { border-top: 5px solid #1169cd; opacity: 1; }

.balance-col {
    background: #fef2f2 !important;
    color: #991b1b;
}
.balance-zero {
    background: #f0fdf4 !important;
    color: #166534;
}

.paid-badge {
    display: inline-block;
    font-size: 0.7rem;
    font-weight: 700;
    color: #166534;
    background: #dcfce7;
    border-radius: 999px;
    padding: 2px 10px;
}

.defaulter-pill {
    display: inline-block;
    margin-left: 6px;
    font-size: 0.65rem;
    font-weight: 700;
    color: #991b1b;
    background: #fee2e2;
    border: 1px solid #fecaca;
    border-radius: 999px;
    padding: 1px 8px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    vertical-align: middle;
}

.empty-row {
    padding: 32px 16px;
    text-align: center;
    color: #64748b;
    font-weight: 500;
}

.grand-total-row td {
    background: #f1f5f9 !important;
    border-top: 2px solid #cbd5e1;
    padding: 12px;
    font-weight: 700;
    color: #0f172a;
}

/* ── Pagination ────────────────────────────────────────────── */
.pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 12px 16px;
    border-top: 1px solid #e2e8f0;
    background: #fff;
    font-size: 0.8125rem;
}
.pagination-info { color: #475569; }
.pagination-info strong { color: #0f172a; }

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}
.page-btn {
    min-width: 32px;
    height: 32px;
    padding: 0 10px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    color: #475569;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.12s;
}
.page-btn:hover:not(:disabled):not(.ellipsis) {
    border-color: #1169cd;
    color: #1169cd;
}
.page-btn.active {
    background: #1169cd;
    border-color: #1169cd;
    color: #fff;
}
.page-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.page-btn.ellipsis {
    border: none;
    background: transparent;
    cursor: default;
}

.pagination-perpage {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #64748b;
}
.pagination-perpage label { font-weight: 600; }
.pagination-perpage select {
    height: 32px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    padding: 0 8px;
    font-size: 0.8125rem;
}

/* form-field labels inside the FilterBar */
.form-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.form-field label {
    font-size: 0.7rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    white-space: nowrap;
}

/* Fee-type checkbox chips */
.fee-type-field {
    min-width: 220px;
}
.fee-type-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
    height: 36px;
}
.fee-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #fff;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.12s;
    user-select: none;
    white-space: nowrap;
}
.fee-chip:hover {
    border-color: #1169cd;
    color: #1169cd;
}
.fee-chip input[type="checkbox"] {
    width: 14px;
    height: 14px;
    accent-color: #1169cd;
    cursor: pointer;
}
.fee-chip.active {
    background: #eff6ff;
    border-color: #1169cd;
    color: #1169cd;
}
</style>
