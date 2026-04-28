<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import PrintButton from '@/Components/ui/PrintButton.vue';
import Table from '@/Components/ui/Table.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useConfirm } from '@/Composables/useConfirm';

const { can } = usePermissions();
const confirm = useConfirm();

const props = defineProps({
    allocation:   Object,
    paymentModes: Array,
    concessions:  { type: Array, default: () => [] },
});

function fmt(n) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 }).format(Number(n || 0));
}
function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

const studentName = computed(() =>
    props.allocation?.student?.user?.name
    || [props.allocation?.student?.first_name, props.allocation?.student?.last_name].filter(Boolean).join(' ')
    || '—'
);

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    amount_paid:     props.allocation.balance > 0 ? Number(props.allocation.balance) : '',
    discount:        0,
    concession_id:   '',
    fine:            0,
    payment_date:    today,
    payment_mode:    'cash',
    transaction_ref: '',
    remarks:         '',
});

function applyConcession() {
    if (!form.concession_id) {
        form.discount = 0;
        return;
    }
    const c = props.concessions.find(x => x.id === Number(form.concession_id) || x.id === form.concession_id);
    if (!c) return;
    const balance = Number(props.allocation.balance || 0);
    const discount = c.type === 'percentage'
        ? Math.round(balance * Number(c.value) / 100)
        : Math.min(Number(c.value), balance);
    form.discount = discount;
}

const netEffect = computed(() => {
    const paid     = Number(form.amount_paid || 0);
    const discount = Number(form.discount || 0);
    const fine     = Number(form.fine || 0);
    const balance  = Number(props.allocation.balance || 0);
    const newBalance = Math.max(0, balance + fine - discount - paid);
    return { paid, discount, fine, balance, newBalance };
});

const disabledSubmit = computed(() => {
    return !form.amount_paid || Number(form.amount_paid) <= 0 || Number(props.allocation.balance) <= 0;
});

function submit() {
    form.post(`/school/stationary/fees/${props.allocation.id}/collect`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('amount_paid', 'discount', 'fine', 'transaction_ref', 'remarks');
            form.payment_mode = 'cash';
            form.payment_date = today;
        },
    });
}

function printReceipt(paymentId) {
    window.open(`/school/stationary/fees/receipts/${paymentId}/receipt`, '_blank');
}

async function voidReceipt(paymentId) {
    const ok = await confirm({
        title: 'Void receipt?',
        message: 'The allocation balance will be recalculated. This cannot be undone.',
        confirmLabel: 'Void Receipt',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/stationary/fees/receipts/${paymentId}`, { preserveScroll: true });
}

const STATUS_COLOURS = {
    paid:    'bg-green-100 text-green-700',
    partial: 'bg-amber-100 text-amber-700',
    unpaid:  'bg-rose-100 text-rose-700',
    waived:  'bg-gray-200 text-gray-600',
};
</script>

<template>
    <Head :title="`Stationary Fee — ${studentName}`" />
    <SchoolLayout title="Stationary Fee Collection">
        <div class="mb-3 text-sm">
            <Link href="/school/stationary/fees" class="text-indigo-600 hover:underline">← Back to Stationary Fees</Link>
            <span class="mx-2 text-gray-300">|</span>
            <Link :href="`/school/stationary/allocations/${allocation.id}`" class="text-indigo-600 hover:underline">View Allocation Detail</Link>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-xs uppercase text-gray-500 font-semibold">Student</p>
                    <p class="font-bold text-base">{{ studentName }}</p>
                    <p class="text-xs text-gray-400 font-mono">Adm: {{ allocation.student?.admission_no }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-gray-500 font-semibold">Total / Paid</p>
                    <p>{{ fmt(allocation.total_amount) }} / {{ fmt(allocation.amount_paid) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-gray-500 font-semibold">Balance</p>
                    <p class="text-lg font-bold" :class="parseFloat(allocation.balance) > 0 ? 'text-rose-600' : 'text-green-600'">
                        {{ fmt(allocation.balance) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase text-gray-500 font-semibold">Status</p>
                    <span :class="['inline-block px-2 py-1 rounded-full text-xs font-medium', STATUS_COLOURS[allocation.payment_status]]">
                        {{ allocation.payment_status }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Kit composition (read-only on this page) -->
        <div v-if="allocation.line_items?.length" class="bg-white rounded-xl border border-gray-200 mb-4">
            <h3 class="px-4 py-3 border-b text-sm font-bold">Kit Items</h3>
            <Table>
                <thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Unit</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    <tr v-for="l in allocation.line_items" :key="l.id">
                        <td>{{ l.item?.name }} <small class="text-gray-400 font-mono">{{ l.item?.code }}</small></td>
                        <td class="text-right">{{ l.qty_entitled }}</td>
                        <td class="text-right">{{ fmt(l.unit_price) }}</td>
                        <td class="text-right">{{ fmt(l.line_total) }}</td>
                    </tr>
                </tbody>
            </Table>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Form -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 lg:col-span-2">
                <h3 class="text-base font-bold mb-4">Record Payment</h3>
                <form @submit.prevent="submit" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Amount Paid (₹) *</label>
                            <input v-model.number="form.amount_paid" type="number" step="0.01" min="0.01" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                            <p v-if="form.errors.amount_paid" class="text-xs text-red-500 mt-1">{{ form.errors.amount_paid }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Payment Date *</label>
                            <input v-model="form.payment_date" type="date" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                        <div v-if="concessions.length">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Concession (optional)</label>
                            <select v-model="form.concession_id" @change="applyConcession"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                <option value="">— No concession —</option>
                                <option v-for="c in concessions" :key="c.id" :value="c.id">
                                    {{ c.name }} ({{ c.type === 'percentage' ? c.value + '%' : '₹' + c.value }})
                                </option>
                            </select>
                            <p v-if="form.errors.concession_id" class="text-xs text-red-500 mt-1">{{ form.errors.concession_id }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Discount (₹)</label>
                            <input v-model.number="form.discount" type="number" step="0.01" min="0"
                                   :disabled="!!form.concession_id"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                                   :class="form.concession_id ? 'bg-gray-50' : ''" />
                            <p v-if="form.concession_id" class="text-xs text-indigo-500 mt-1">Auto-filled from concession.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Fine / Late Fee (₹)</label>
                            <input v-model.number="form.fine" type="number" step="0.01" min="0"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Payment Mode *</label>
                            <select v-model="form.payment_mode" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option v-for="m in paymentModes" :key="m.value" :value="m.value">{{ m.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Transaction Ref</label>
                            <input v-model="form.transaction_ref" type="text" placeholder="UPI / cheque no."
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Remarks</label>
                        <textarea v-model="form.remarks" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                    </div>

                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Current balance</span><span>{{ fmt(netEffect.balance) }}</span></div>
                        <div v-if="netEffect.fine > 0" class="flex justify-between"><span class="text-gray-500">+ Fine</span><span>+ {{ fmt(netEffect.fine) }}</span></div>
                        <div v-if="netEffect.discount > 0" class="flex justify-between"><span class="text-gray-500">− Discount</span><span>− {{ fmt(netEffect.discount) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">− Payment</span><span>− {{ fmt(netEffect.paid) }}</span></div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 mt-2 font-bold">
                            <span>New balance</span>
                            <span :class="netEffect.newBalance === 0 ? 'text-green-600' : 'text-rose-600'">{{ fmt(netEffect.newBalance) }}</span>
                        </div>
                    </div>

                    <Button type="submit" :loading="form.processing" :disabled="disabledSubmit" class="w-full">
                        Record Payment
                    </Button>
                </form>
            </div>

            <!-- Receipts history -->
            <div class="bg-white rounded-xl border border-gray-200">
                <h3 class="px-4 py-3 border-b text-sm font-bold">Receipts</h3>
                <div v-if="!allocation.payments?.length" class="p-6 text-center text-gray-400 text-sm">No receipts yet.</div>
                <div v-for="p in allocation.payments" :key="p.id" class="px-4 py-3 border-b last:border-b-0">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-mono font-semibold text-sm">{{ p.receipt_no }}</p>
                            <p class="text-xs text-gray-500">{{ fmtDate(p.payment_date) }} · {{ p.payment_mode }}</p>
                            <p class="text-xs text-gray-400">By {{ p.collected_by?.name || '—' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-green-600">{{ fmt(p.amount_paid) }}</p>
                            <div class="flex gap-2 justify-end mt-1">
                                <PrintButton :href="`/school/stationary/fees/receipts/${p.id}/receipt`" label="PDF" size="xs" />
                                <button v-if="can('collect_stationary_fee')" @click="voidReceipt(p.id)" class="text-xs text-rose-600">Void</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>
