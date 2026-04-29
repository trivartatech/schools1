<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, reactive, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useConfirm } from '@/Composables/useConfirm';
import { useSchoolStore } from '@/stores/useSchoolStore';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    allocation: Object,
});

const { can } = usePermissions();
const confirm = useConfirm();
const school = useSchoolStore();

function fmt(n) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 }).format(n ?? 0);
}

function studentName(s) {
    return s?.user?.name || [s?.first_name, s?.last_name].filter(Boolean).join(' ') || '—';
}

const statusBadge = (s) => ({
    paid: 'badge-green', partial: 'badge-amber', unpaid: 'badge-red', waived: 'badge-gray',
    complete: 'badge-green', none: 'badge-red',
})[s] || 'badge-gray';

// ── Issue Modal ─────────────────────────────────────────────────────────
const showIssue   = ref(false);
const issuing     = ref(false);
const issueErrors = ref({});
const issueForm   = reactive({ lines: [], remarks: '' });

function openIssueModal() {
    issueForm.lines = props.allocation.line_items
        .filter(l => l.qty_entitled - l.qty_collected > 0)
        .map(l => ({ allocation_item_id: l.id, qty_issued: 0, item_name: l.item?.name, remaining: l.qty_entitled - l.qty_collected }));
    issueForm.remarks = '';
    issueErrors.value = {};
    showIssue.value = true;
}

function submitIssue() {
    issuing.value = true;
    issueErrors.value = {};
    const lines = issueForm.lines
        .filter(l => l.qty_issued > 0)
        .map(l => ({ allocation_item_id: l.allocation_item_id, qty_issued: l.qty_issued }));

    if (!lines.length) {
        issuing.value = false;
        issueErrors.value = { lines: 'Enter at least one positive qty.' };
        return;
    }

    router.post(`/school/stationary/allocations/${props.allocation.id}/issuances`,
        { lines, remarks: issueForm.remarks }, {
        preserveScroll: true,
        onSuccess: () => { showIssue.value = false; },
        onError:   (e) => { issueErrors.value = e; },
        onFinish:  () => { issuing.value = false; },
    });
}

async function voidIssuance(issuance) {
    const ok = await confirm({
        title: 'Void issuance?',
        message: `Void this issuance from ${school.fmtDate(issuance.issued_at)}? Stock and qty_collected will be restored.`,
        confirmLabel: 'Void',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/stationary/issuances/${issuance.id}`, { preserveScroll: true });
}

// ── Return Modal ────────────────────────────────────────────────────────
const showReturn   = ref(false);
const returning    = ref(false);
const returnErrors = ref({});
const returnForm   = reactive({ lines: [], refund_amount: 0, refund_mode: 'none', remarks: '' });

function openReturnModal() {
    returnForm.lines = props.allocation.line_items
        .filter(l => l.qty_collected > 0)
        .map(l => ({
            allocation_item_id: l.id,
            qty_returned: 0,
            condition: 'good',
            restock: true,
            item_name: l.item?.name,
            unit_price: parseFloat(l.unit_price),
            qty_collected: l.qty_collected,
        }));
    returnForm.refund_amount = 0;
    returnForm.refund_mode = 'none';
    returnForm.remarks = '';
    returnErrors.value = {};
    showReturn.value = true;
}

const totalLineRefund = computed(() => {
    return returnForm.lines.reduce((s, l) => s + (l.qty_returned * l.unit_price), 0);
});

function submitReturn() {
    returning.value = true;
    returnErrors.value = {};
    const lines = returnForm.lines
        .filter(l => l.qty_returned > 0)
        .map(l => ({
            allocation_item_id: l.allocation_item_id,
            qty_returned: l.qty_returned,
            condition: l.condition,
            restock: l.restock,
        }));

    if (!lines.length) {
        returning.value = false;
        returnErrors.value = { lines: 'Enter at least one positive qty to return.' };
        return;
    }

    router.post(`/school/stationary/allocations/${props.allocation.id}/returns`,
        { lines, refund_amount: returnForm.refund_amount, refund_mode: returnForm.refund_mode, remarks: returnForm.remarks }, {
        preserveScroll: true,
        onSuccess: () => { showReturn.value = false; },
        onError:   (e) => { returnErrors.value = e; },
        onFinish:  () => { returning.value = false; },
    });
}

async function voidReturn(ret) {
    const ok = await confirm({
        title: 'Void return?',
        message: `Void this return from ${school.fmtDate(ret.returned_at)}? Refund will be reversed.`,
        confirmLabel: 'Void',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/stationary/returns/${ret.id}`, { preserveScroll: true });
}
</script>

<template>
    <SchoolLayout title="Stationary Allocation Detail">
        <PageHeader
            :title="`Allocation #${allocation.id}`"
            back-href="/school/stationary/allocations"
            back-label="← All allocations">
            <template #actions>
                <Link :href="`/school/stationary/fees/${allocation.id}`" class="btn-link" style="background:#eef2ff;padding:8px 14px;border-radius:8px;">💰 Collect Fee</Link>
                <Button v-if="can('issue_stationary_items')" @click="openIssueModal">📦 Issue Items</Button>
                <Button v-if="can('accept_stationary_returns')" variant="secondary" @click="openReturnModal">↩ Accept Return</Button>
            </template>
        </PageHeader>

        <!-- Header card -->
        <div class="card" style="margin-bottom: 16px;">
            <div class="card-body" style="padding: 18px 22px;">
                <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:18px;">
                    <div>
                        <p class="lab">Student</p>
                        <p style="font-size:1rem;font-weight:600;color:#1e293b;">{{ studentName(allocation.student) }}</p>
                        <p style="font-size:0.78rem;color:#94a3b8;">Adm No. {{ allocation.student?.admission_no }} · AY {{ allocation.academic_year?.name }}</p>
                    </div>
                    <div>
                        <p class="lab">Total / Paid</p>
                        <p class="val">{{ fmt(allocation.total_amount) }} / {{ fmt(allocation.amount_paid) }}</p>
                    </div>
                    <div>
                        <p class="lab">Balance</p>
                        <p class="val" :style="parseFloat(allocation.balance) > 0 ? 'color:#dc2626' : 'color:#059669'">{{ fmt(allocation.balance) }}</p>
                    </div>
                    <div>
                        <p class="lab">Status</p>
                        <span :class="['badge', statusBadge(allocation.payment_status)]">Pay: {{ allocation.payment_status }}</span>
                        <span :class="['badge', statusBadge(allocation.collection_status)]" style="margin-left:6px;">Coll: {{ allocation.collection_status }}</span>
                    </div>
                </div>
                <div v-if="allocation.remarks" style="margin-top:10px;color:#475569;font-size:0.84rem;border-top:1px solid #e2e8f0;padding-top:10px;">
                    <strong>Remarks:</strong> {{ allocation.remarks }}
                </div>
            </div>
        </div>

        <!-- Kit line items -->
        <div class="card" style="margin-bottom: 16px;">
            <div class="card-body" style="padding: 0;">
                <h3 style="padding:14px 18px;border-bottom:1px solid #e2e8f0;font-size:0.92rem;font-weight:700;color:#1e293b;margin:0;">Kit Composition</h3>
                <Table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Entitled</th>
                            <th class="text-right">Collected</th>
                            <th class="text-right">Remaining</th>
                            <th class="text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="l in allocation.line_items" :key="l.id">
                            <td>{{ l.item?.name }} <small style="color:#94a3b8;font-family:monospace;">{{ l.item?.code }}</small></td>
                            <td class="text-right">{{ fmt(l.unit_price) }}</td>
                            <td class="text-right">{{ l.qty_entitled }}</td>
                            <td class="text-right">{{ l.qty_collected }}</td>
                            <td class="text-right" :style="(l.qty_entitled - l.qty_collected) > 0 ? 'color:#b45309;font-weight:600' : 'color:#94a3b8'">
                                {{ l.qty_entitled - l.qty_collected }}
                            </td>
                            <td class="text-right">{{ fmt(l.line_total) }}</td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <!-- Issuance log + Return log -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="card">
                <div class="card-body" style="padding:0;">
                    <h3 style="padding:14px 18px;border-bottom:1px solid #e2e8f0;font-size:0.92rem;font-weight:700;color:#1e293b;margin:0;">📦 Issuance Log</h3>
                    <div v-if="!allocation.issuances?.length" style="padding:20px;text-align:center;color:#94a3b8;font-size:0.84rem;">No items issued yet.</div>
                    <div v-for="iss in allocation.issuances" :key="iss.id" style="padding:14px 18px;border-bottom:1px solid #f1f5f9;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                            <div>
                                <p style="font-size:0.84rem;font-weight:600;color:#1e293b;">{{ new Date(iss.issued_at).toLocaleString() }}</p>
                                <p style="font-size:0.78rem;color:#94a3b8;">By {{ iss.issued_by?.name || '—' }}</p>
                            </div>
                            <button v-if="can('issue_stationary_items')" @click="voidIssuance(iss)" class="btn-void">Void</button>
                        </div>
                        <ul style="margin-top:6px;font-size:0.82rem;color:#475569;list-style:disc;padding-left:18px;">
                            <li v-for="line in iss.items" :key="line.id">{{ line.item?.name }} × {{ line.qty_issued }}</li>
                        </ul>
                        <p v-if="iss.remarks" style="font-size:0.78rem;color:#94a3b8;margin-top:4px;">"{{ iss.remarks }}"</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body" style="padding:0;">
                    <h3 style="padding:14px 18px;border-bottom:1px solid #e2e8f0;font-size:0.92rem;font-weight:700;color:#1e293b;margin:0;">↩ Return Log</h3>
                    <div v-if="!allocation.returns?.length" style="padding:20px;text-align:center;color:#94a3b8;font-size:0.84rem;">No returns recorded.</div>
                    <div v-for="ret in allocation.returns" :key="ret.id" style="padding:14px 18px;border-bottom:1px solid #f1f5f9;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                            <div>
                                <p style="font-size:0.84rem;font-weight:600;color:#1e293b;">{{ new Date(ret.returned_at).toLocaleString() }}</p>
                                <p style="font-size:0.78rem;color:#94a3b8;">By {{ ret.accepted_by?.name || '—' }}</p>
                            </div>
                            <button v-if="can('accept_stationary_returns')" @click="voidReturn(ret)" class="btn-void">Void</button>
                        </div>
                        <ul style="margin-top:6px;font-size:0.82rem;color:#475569;list-style:disc;padding-left:18px;">
                            <li v-for="line in ret.items" :key="line.id">
                                {{ line.item?.name }} × {{ line.qty_returned }}
                                <small style="color:#94a3b8;">[{{ line.condition }}{{ line.restock ? ', restocked' : ', written off' }}]</small>
                            </li>
                        </ul>
                        <p v-if="parseFloat(ret.refund_amount) > 0" style="font-size:0.84rem;color:#dc2626;font-weight:600;margin-top:4px;">
                            Refund: {{ fmt(ret.refund_amount) }} ({{ ret.refund_mode }})
                        </p>
                        <p v-if="ret.remarks" style="font-size:0.78rem;color:#94a3b8;margin-top:4px;">"{{ ret.remarks }}"</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment history -->
        <div class="card" style="margin-top:16px;">
            <div class="card-body" style="padding:0;">
                <h3 style="padding:14px 18px;border-bottom:1px solid #e2e8f0;font-size:0.92rem;font-weight:700;color:#1e293b;margin:0;">💰 Payment Receipts</h3>
                <Table v-if="allocation.payments?.length">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Date</th>
                            <th>Mode</th>
                            <th class="text-right">Amount</th>
                            <th class="text-right">Discount</th>
                            <th class="text-right">Fine</th>
                            <th>Collected by</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in allocation.payments" :key="p.id">
                            <td><span style="font-family:monospace;font-weight:600;">{{ p.receipt_no }}</span></td>
                            <td>{{ p.payment_date }}</td>
                            <td>{{ p.payment_mode }}</td>
                            <td class="text-right">{{ fmt(p.amount_paid) }}</td>
                            <td class="text-right">{{ fmt(p.discount) }}</td>
                            <td class="text-right">{{ fmt(p.fine) }}</td>
                            <td>{{ p.collected_by?.name || '—' }}</td>
                            <td>
                                <a :href="`/school/stationary/fees/receipts/${p.id}/receipt`" target="_blank" class="btn-link">📄 PDF</a>
                            </td>
                        </tr>
                    </tbody>
                </Table>
                <div v-else style="padding:20px;text-align:center;color:#94a3b8;font-size:0.84rem;">No receipts yet.</div>
            </div>
        </div>

        <!-- Issue Modal -->
        <Modal v-model:open="showIssue" title="Issue Items to Student" size="lg">
            <p v-if="!issueForm.lines.length" style="padding:20px;text-align:center;color:#94a3b8;">All entitled items have been issued. Nothing to do.</p>
            <div v-for="(line, i) in issueForm.lines" :key="i" style="display:grid;grid-template-columns:1fr 80px 80px;gap:10px;align-items:center;padding:8px;border-bottom:1px solid #f1f5f9;">
                <div>
                    <p style="font-size:0.86rem;font-weight:600;color:#1e293b;">{{ line.item_name }}</p>
                    <p style="font-size:0.74rem;color:#94a3b8;">{{ line.remaining }} remaining</p>
                </div>
                <input v-model.number="line.qty_issued" type="number" :min="0" :max="line.remaining" class="form-input" placeholder="Qty" />
                <span style="font-size:0.78rem;color:#94a3b8;">of {{ line.remaining }}</span>
            </div>
            <p v-if="issueErrors.lines" class="form-err">{{ issueErrors.lines }}</p>
            <div class="form-row" style="margin-top:14px;">
                <label>Remarks (optional)</label>
                <textarea v-model="issueForm.remarks" rows="2" class="form-input"></textarea>
            </div>
            <template #footer>
                <Button variant="secondary" @click="showIssue = false">Cancel</Button>
                <Button :loading="issuing" @click="submitIssue" :disabled="!issueForm.lines.length">Issue Items</Button>
            </template>
        </Modal>

        <!-- Return Modal -->
        <Modal v-model:open="showReturn" title="Accept Return" size="lg">
            <p v-if="!returnForm.lines.length" style="padding:20px;text-align:center;color:#94a3b8;">No items have been issued — nothing to return.</p>
            <div v-for="(line, i) in returnForm.lines" :key="i" style="display:grid;grid-template-columns:1.6fr 70px 100px 60px;gap:8px;align-items:center;padding:8px;border-bottom:1px solid #f1f5f9;">
                <div>
                    <p style="font-size:0.86rem;font-weight:600;color:#1e293b;">{{ line.item_name }}</p>
                    <p style="font-size:0.74rem;color:#94a3b8;">{{ line.qty_collected }} issued · {{ fmt(line.unit_price) }}/each</p>
                </div>
                <input v-model.number="line.qty_returned" type="number" :min="0" :max="line.qty_collected" class="form-input" placeholder="Qty" />
                <select v-model="line.condition" class="form-input">
                    <option value="good">Good</option>
                    <option value="damaged">Damaged</option>
                </select>
                <label style="font-size:0.74rem;display:flex;align-items:center;gap:4px;">
                    <input type="checkbox" v-model="line.restock" /> Restock
                </label>
            </div>
            <p v-if="returnErrors.lines" class="form-err">{{ returnErrors.lines }}</p>

            <div style="background:#f8fafc;padding:12px 16px;border-radius:8px;margin-top:14px;">
                <p style="font-size:0.84rem;color:#475569;display:flex;justify-content:space-between;">
                    Sum of line refunds: <strong>{{ fmt(totalLineRefund) }}</strong>
                </p>
            </div>

            <div class="form-row-2" style="margin-top:14px;">
                <div>
                    <label>Refund Amount (₹)</label>
                    <input v-model.number="returnForm.refund_amount" type="number" min="0" step="0.01" class="form-input" />
                    <p style="font-size:0.72rem;color:#94a3b8;">Set to 0 if no refund.</p>
                </div>
                <div>
                    <label>Refund Mode</label>
                    <select v-model="returnForm.refund_mode" class="form-input">
                        <option value="none">No refund</option>
                        <option v-for="m in $page.props.payment_methods" :key="m.code" :value="m.code">{{ m.label }}</option>
                        <option value="adjust">Adjust against balance (no GL)</option>
                    </select>
                </div>
            </div>
            <p v-if="returnErrors.refund_amount" class="form-err">{{ returnErrors.refund_amount }}</p>
            <p v-if="returnErrors.refund_mode" class="form-err">{{ returnErrors.refund_mode }}</p>

            <div class="form-row" style="margin-top:14px;">
                <label>Remarks</label>
                <textarea v-model="returnForm.remarks" rows="2" class="form-input"></textarea>
            </div>
            <template #footer>
                <Button variant="secondary" @click="showReturn = false">Cancel</Button>
                <Button :loading="returning" @click="submitReturn" :disabled="!returnForm.lines.length">Accept Return</Button>
            </template>
        </Modal>
    </SchoolLayout>
</template>

<style scoped>
.text-right { text-align: right; }
.lab { font-size: 0.74rem; color: #94a3b8; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
.val { font-size: 1rem; font-weight: 600; color: #1e293b; }
.badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
.badge-green { background: #d1fae5; color: #059669; }
.badge-amber { background: #fef3c7; color: #b45309; }
.badge-red   { background: #fee2e2; color: #dc2626; }
.badge-gray  { background: #f1f5f9; color: #94a3b8; }
.btn-link { color: #6366f1; font-size: 0.84rem; text-decoration: none; }
.btn-void { background: #fef2f2; color: #dc2626; border: 0; padding: 4px 10px; border-radius: 6px; font-size: 0.74rem; cursor: pointer; }
.btn-void:hover { background: #fee2e2; }

.form-row { display: flex; flex-direction: column; gap: 4px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-row label, .form-row-2 label { font-size: 0.78rem; font-weight: 600; color: #475569; }
.form-input { border: 1px solid #cbd5e1; border-radius: 8px; padding: 7px 10px; font-size: 0.86rem; outline: none; width: 100%; }
.form-input:focus { border-color: #6366f1; }
.form-err { font-size: 0.74rem; color: #dc2626; }
</style>
