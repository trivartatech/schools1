<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    staffList: Array,
    payrollMap: Object,
    month: Number,
    year: Number,
    summary: Object,
});

// ── Month Navigation ──────────────────────────────────────────────────────────
const curMonth = ref(props.month);
const curYear  = ref(props.year);

const monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];

const navigate = (delta) => {
    let m = curMonth.value + delta;
    let y = curYear.value;
    if (m > 12) { m = 1; y++; }
    if (m < 1)  { m = 12; y--; }
    curMonth.value = m;
    curYear.value = y;
    router.get('/school/payroll', { month: m, year: y }, { preserveScroll: true, replace: true });
};

// ── Generate Payroll ─────────────────────────────────────────────────────────
const genForm = useForm({ month: props.month, year: props.year });

const generate = () => {
    genForm.month = curMonth.value;
    genForm.year  = curYear.value;
    genForm.post('/school/payroll/generate', { preserveScroll: true });
};

// ── Mark Paid ─────────────────────────────────────────────────────────────────
const markPaidId  = ref(null);
const showMarkPaid = ref(false);
const markPaidForm = useForm({ payment_date: new Date().toISOString().split('T')[0], payment_mode: 'bank_transfer' });

const openMarkPaid = (payrollId) => { markPaidId.value = payrollId; showMarkPaid.value = true; };
const closeMarkPaid = () => { markPaidId.value = null; showMarkPaid.value = false; };

const submitMarkPaid = () => {
    markPaidForm.patch(`/school/payroll/${markPaidId.value}/mark-paid`, {
        preserveScroll: true,
        onSuccess: () => closeMarkPaid()
    });
};

// ── Helpers ───────────────────────────────────────────────────────────────────
const getPayroll = (staffId) => props.payrollMap?.[staffId] ?? null;

const postGl = (payrollId) => {
    router.post(route('school.payroll.post-gl', payrollId), {}, { preserveScroll: true });
};

const payrollList = computed(() => Object.values(props.payrollMap || {}));
const paidCount  = computed(() => payrollList.value.filter(p => p.status === 'paid').length);
const totalPayout = computed(() => payrollList.value.reduce((s, p) => s + parseFloat(p.net_salary || 0), 0));

const fmt = (n) => '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2 });

const statCards = computed(() => [
    { label: 'Active Staff',       value: props.summary.total_staff, color: 'accent' },
    { label: 'Payrolls Generated', value: props.summary.generated,   color: 'warning' },
    { label: 'Paid',               value: paidCount.value,           color: 'success' },
    { label: 'Total Payout',       value: fmt(totalPayout.value),    color: 'purple' },
]);
</script>

<template>
    <SchoolLayout title="Payroll">

        <PageHeader :title="`${monthNames[curMonth]} ${curYear}`" subtitle="Manage and process monthly staff payroll.">
            <template #actions>
                <Button variant="icon" size="sm" aria-label="Previous month" @click="navigate(-1)">&#9664;</Button>
                <Button variant="icon" size="sm" aria-label="Next month" @click="navigate(1)">&#9654;</Button>
                <Button variant="secondary" as="a" :href="`/school/payroll/export?month=${curMonth}&year=${curYear}`" target="_blank">
                    Export Excel
                </Button>
                <Button @click="generate" :loading="genForm.processing">
                    Generate Payroll
                </Button>
            </template>
        </PageHeader>

        <!-- Summary Cards -->
        <StatsRow :cols="4" :stats="statCards" />

        <!-- Payroll Table -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Payroll — {{ monthNames[curMonth] }} {{ curYear }}</span>
            </div>

            <Table :empty="staffList.length === 0">
                <thead>
                    <tr>
                        <th>Staff Member</th>
                        <th>Designation</th>
                        <th style="text-align:right;">Basic</th>
                        <th style="text-align:right;">Allowances</th>
                        <th style="text-align:right;">Deductions</th>
                        <th style="text-align:right;font-weight:700;">Net Pay</th>
                        <th>Status</th>
                        <th>GL</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in staffList" :key="s.id">
                        <td>
                            <div style="font-weight:500;">{{ s.user?.name }}</div>
                            <div style="font-size:.75rem;color:#94a3b8;">{{ s.employee_id }}</div>
                        </td>
                        <td style="font-size:.8125rem;color:#475569;">{{ s.designation?.name || '—' }}</td>

                        <template v-if="getPayroll(s.id)">
                            <td style="text-align:right;color:#475569;">{{ fmt(getPayroll(s.id).basic_pay) }}</td>
                            <td style="text-align:right;color:#16a34a;">+{{ fmt(getPayroll(s.id).allowances) }}</td>
                            <td style="text-align:right;color:#dc2626;">
                                -{{ fmt(parseFloat(getPayroll(s.id).deductions) + parseFloat(getPayroll(s.id).unpaid_leave_deduction || 0)) }}
                                <div v-if="getPayroll(s.id).unpaid_leave_days > 0" style="font-size:.65rem;color:#f87171;margin-top:2px;">
                                    (inc. {{ getPayroll(s.id).unpaid_leave_days }}d LWP)
                                </div>
                            </td>
                            <td style="text-align:right;font-weight:700;">{{ fmt(getPayroll(s.id).net_salary) }}</td>
                            <td>
                                <span class="badge" :class="getPayroll(s.id).status === 'paid' ? 'badge-green' : 'badge-amber'">
                                    {{ getPayroll(s.id).status }}
                                </span>
                            </td>
                            <td>
                                <span v-if="getPayroll(s.id).gl_transaction" class="gl-badge gl-posted" :title="getPayroll(s.id).gl_transaction.transaction_no">
                                    ✓ {{ getPayroll(s.id).gl_transaction.transaction_no }}
                                </span>
                                <button v-else-if="getPayroll(s.id).status === 'paid'"
                                    @click="postGl(getPayroll(s.id).id)"
                                    class="gl-badge gl-pending" title="Post to General Ledger">
                                    Post GL
                                </button>
                                <span v-else class="gl-badge gl-na">—</span>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;align-items:center;">
                                    <Button variant="success" size="xs" v-if="getPayroll(s.id).status !== 'paid'" @click="openMarkPaid(getPayroll(s.id).id)">
                                        Mark Paid
                                    </Button>
                                    <Button variant="secondary" size="xs" as="a" :href="`/school/payroll/${getPayroll(s.id).id}/payslip`" target="_blank">
                                        Payslip
                                    </Button>
                                </div>
                            </td>
                        </template>
                        <template v-else>
                            <td colspan="4" style="text-align:center;color:#94a3b8;font-size:.8125rem;">— Not generated —</td>
                            <td><span class="badge badge-gray">Pending</span></td>
                            <td>—</td>
                            <td style="color:#94a3b8;font-size:.8125rem;">—</td>
                        </template>
                    </tr>
                </tbody>
                <template #empty>
                    <EmptyState
                        title="No active staff found"
                        description="Add staff members to start managing payroll."
                    />
                </template>
            </Table>
        </div>

        <!-- Mark Paid Modal -->
        <Modal v-model:open="showMarkPaid" title="Mark as Paid" size="sm">
            <form @submit.prevent="submitMarkPaid" id="mark-paid-form">
                <div style="display:flex;flex-direction:column;gap:16px;">
                    <div class="form-field">
                        <label>Payment Date *</label>
                        <input v-model="markPaidForm.payment_date" type="date" required />
                    </div>
                    <div class="form-field">
                        <label>Payment Mode *</label>
                        <select v-model="markPaidForm.payment_mode" required>
                            <option v-for="m in $page.props.payment_methods" :key="m.code" :value="m.code">{{ m.label }}</option>
                        </select>
                    </div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="closeMarkPaid">Cancel</Button>
                <Button variant="success" type="submit" form="mark-paid-form" :loading="markPaidForm.processing">
                    Confirm Payment
                </Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
.gl-badge {
    display: inline-flex; align-items: center; padding: 3px 8px;
    border-radius: 10px; font-size: 0.72rem; font-weight: 600;
    white-space: nowrap; cursor: default; border: none;
}
.gl-posted { background: #d1fae5; color: #059669; font-family: monospace; }
.gl-pending {
    background: #fef3c7; color: #92400e;
    border: 1px solid #fde68a; cursor: pointer; transition: background 0.15s;
}
.gl-pending:hover { background: #fde68a; }
.gl-na { background: #f1f5f9; color: #94a3b8; }

/* Form fields — Tailwind preflight workaround */
.form-field { display: flex; flex-direction: column; gap: 5px; }
.form-field label { font-size: 0.78rem; font-weight: 600; color: #374151; }
.form-field input,
.form-field select {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    background: #fff;
    color: #111827;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.form-field input:focus,
.form-field select:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
</style>
