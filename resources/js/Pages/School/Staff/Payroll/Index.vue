<script setup>
import Button from '@/Components/ui/Button.vue';
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
const markPaidForm = useForm({ payment_date: new Date().toISOString().split('T')[0], payment_mode: 'bank_transfer' });

const openMarkPaid = (payrollId) => { markPaidId.value = payrollId; };
const closeMarkPaid = () => { markPaidId.value = null; };

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
</script>

<template>
    <SchoolLayout title="Payroll">

        <!-- Header + Month Navigation -->
        <div class="page-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <Button variant="icon" size="sm" aria-label="Previous month" @click="navigate(-1)">&#9664;</Button>
                <h1 class="page-header-title" style="width:200px;text-align:center;margin:0;">{{ monthNames[curMonth] }} {{ curYear }}</h1>
                <Button variant="icon" size="sm" aria-label="Next month" @click="navigate(1)">&#9654;</Button>
            </div>
            <Button @click="generate" :loading="genForm.processing">
                Generate Payroll
            </Button>
        </div>

        <!-- Summary Cards -->
        <div class="payroll-stats">
            <div class="card stat-card-mini">
                <div class="card-body">
                    <div class="stat-value" style="color:#1d4ed8;">{{ summary.total_staff }}</div>
                    <div class="stat-label">Active Staff</div>
                </div>
            </div>
            <div class="card stat-card-mini">
                <div class="card-body">
                    <div class="stat-value" style="color:#d97706;">{{ summary.generated }}</div>
                    <div class="stat-label">Payrolls Generated</div>
                </div>
            </div>
            <div class="card stat-card-mini">
                <div class="card-body">
                    <div class="stat-value" style="color:var(--success);">{{ paidCount }}</div>
                    <div class="stat-label">Paid</div>
                </div>
            </div>
            <div class="card stat-card-mini">
                <div class="card-body">
                    <div class="stat-value" style="color:#7c3aed;font-size:1.25rem;">{{ fmt(totalPayout) }}</div>
                    <div class="stat-label">Total Payout</div>
                </div>
            </div>
        </div>

        <!-- Payroll Table -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Payroll — {{ monthNames[curMonth] }} {{ curYear }}</span>
            </div>

            <div v-if="staffList.length === 0" class="card-body" style="text-align:center;padding:40px;color:#94a3b8;">
                No active staff found.
            </div>

            <div v-else style="overflow-x:auto;">
                <Table>
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
                                        <Button variant="secondary" size="xs" as="link" :href="`/school/payroll/${getPayroll(s.id).id}/payslip`" target="_blank">
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
                </Table>
            </div>
        </div>

        <!-- Mark Paid Modal -->
        <Teleport to="body">
            <div v-if="markPaidId" class="modal-backdrop" @click.self="closeMarkPaid">
                <div class="modal" style="width:100%;max-width:400px;">
                    <div class="modal-header">
                        <h3 class="modal-title">Mark as Paid</h3>
                        <button @click="closeMarkPaid" class="modal-close">&times;</button>
                    </div>
                    <form @submit.prevent="submitMarkPaid">
                        <div class="modal-body" style="display:flex;flex-direction:column;gap:16px;">
                            <div class="form-field">
                                <label>Payment Date *</label>
                                <input v-model="markPaidForm.payment_date" type="date" required />
                            </div>
                            <div class="form-field">
                                <label>Payment Mode *</label>
                                <select v-model="markPaidForm.payment_mode" required>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="cash">Cash</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="upi">UPI</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <Button variant="secondary" type="button" @click="closeMarkPaid">Cancel</Button>
                            <Button variant="success" type="submit" :loading="markPaidForm.processing">
                                Confirm Payment
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

    </SchoolLayout>
</template>

<style scoped>
.payroll-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px; }
.stat-card-mini { text-align: center; }
.stat-card-mini .card-body { padding: 16px; }
.stat-value { font-size: 1.5rem; font-weight: 700; }
.stat-label { font-size: .75rem; color: var(--text-muted); margin-top: 4px; }

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

.modal-backdrop {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15,23,42,.5); backdrop-filter: blur(2px);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.modal {
    background: #fff; border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,.1), 0 10px 10px -5px rgba(0,0,0,.04);
}
.modal-header {
    padding: 16px 20px; border-bottom: 1px solid #e2e8f0;
    display: flex; justify-content: space-between; align-items: center;
}
.modal-title { font-size: 1rem; font-weight: 700; color: #1e293b; }
.modal-close {
    background: none; border: none; font-size: 1.5rem; line-height: 1;
    color: #94a3b8; cursor: pointer; padding: 0 4px;
}
.modal-close:hover { color: #0f172a; }
.modal-body { padding: 20px; }
.modal-footer {
    padding: 16px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc;
    border-radius: 0 0 12px 12px; display: flex; justify-content: flex-end; gap: 10px;
}
</style>
