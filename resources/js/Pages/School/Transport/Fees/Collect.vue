<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

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
    form.post(`/school/transport/fees/${props.allocation.id}/collect`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('amount_paid', 'discount', 'fine', 'transaction_ref', 'remarks');
            form.payment_mode = 'cash';
            form.payment_date = today;
        },
    });
}

function printReceipt(paymentId) {
    window.open(`/school/transport/fees/receipts/${paymentId}/receipt`, '_blank');
}

function voidReceipt(paymentId) {
    if (!confirm('Void this receipt? The allocation balance will be recalculated.')) return;
    router.delete(`/school/transport/fees/receipts/${paymentId}`, { preserveScroll: true });
}

const STATUS_COLOURS = {
    paid:    'bg-green-100 text-green-700',
    partial: 'bg-amber-100 text-amber-700',
    unpaid:  'bg-rose-100 text-rose-700',
    waived:  'bg-gray-200 text-gray-600',
};
</script>

<template>
    <Head :title="`Transport Fee — ${studentName}`" />
    <SchoolLayout title="Transport Fee Collection">

        <!-- Breadcrumb -->
        <div class="mb-3 text-sm">
            <Link href="/school/transport/fees" class="text-indigo-600 hover:underline">← Back to Transport Fees</Link>
        </div>

        <!-- Student / Allocation summary -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-4 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <p class="text-[11px] uppercase tracking-wider text-gray-500">Student</p>
                <p class="font-bold text-gray-900 mt-1">{{ studentName }}</p>
                <p class="text-xs text-gray-500">Adm: {{ allocation.student?.admission_no ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[11px] uppercase tracking-wider text-gray-500">Route / Stop</p>
                <p class="font-semibold text-gray-800 mt-1">{{ allocation.route?.route_name ?? '—' }}</p>
                <p class="text-xs text-gray-500">{{ allocation.stop?.stop_name ?? '' }}</p>
            </div>
            <div>
                <p class="text-[11px] uppercase tracking-wider text-gray-500">Vehicle</p>
                <p class="font-semibold text-gray-800 mt-1">{{ allocation.vehicle?.vehicle_number ?? '—' }}</p>
                <p class="text-xs text-gray-500">{{ allocation.vehicle?.vehicle_name ?? '' }}</p>
            </div>
            <div>
                <p class="text-[11px] uppercase tracking-wider text-gray-500">Status</p>
                <span class="inline-block mt-1 px-2 py-0.5 rounded text-xs font-semibold"
                      :class="STATUS_COLOURS[allocation.payment_status] || 'bg-gray-100 text-gray-600'">
                    {{ allocation.payment_status }}
                </span>
            </div>
        </div>

        <!-- Balance card -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
            <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
                <p class="text-[11px] uppercase tracking-wider text-indigo-600">Transport Fee</p>
                <p class="text-lg font-bold text-indigo-700 mt-1">{{ fmt(allocation.transport_fee) }}</p>
            </div>
            <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                <p class="text-[11px] uppercase tracking-wider text-green-600">Paid So Far</p>
                <p class="text-lg font-bold text-green-700 mt-1">{{ fmt(allocation.amount_paid) }}</p>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                <p class="text-[11px] uppercase tracking-wider text-amber-600">Discount</p>
                <p class="text-lg font-bold text-amber-700 mt-1">{{ fmt(allocation.discount) }}</p>
            </div>
            <div :class="Number(allocation.balance) > 0 ? 'bg-rose-50 border-rose-200' : 'bg-gray-50 border-gray-200'"
                 class="border rounded-xl p-4">
                <p class="text-[11px] uppercase tracking-wider" :class="Number(allocation.balance) > 0 ? 'text-rose-600' : 'text-gray-600'">Outstanding</p>
                <p class="text-lg font-bold mt-1" :class="Number(allocation.balance) > 0 ? 'text-rose-700' : 'text-gray-700'">
                    {{ fmt(allocation.balance) }}
                </p>
            </div>
        </div>

        <!-- Collection form -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-5" v-if="can('collect_transport_fee') && Number(allocation.balance) > 0">
            <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="w-8 h-8 bg-indigo-100 text-indigo-700 rounded-lg flex items-center justify-center">💰</span>
                Record Payment
            </h3>

            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount Paid *</label>
                    <input v-model="form.amount_paid" type="number" step="0.01" min="0.01" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">
                    <p v-if="form.errors.amount_paid" class="text-xs text-red-500 mt-1">{{ form.errors.amount_paid }}</p>
                </div>
                <div v-if="concessions.length">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Concession (optional)</label>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Discount</label>
                    <input v-model="form.discount" type="number" step="0.01" min="0"
                           :disabled="!!form.concession_id"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                           :class="form.concession_id ? 'bg-gray-50' : ''">
                    <p v-if="form.concession_id" class="text-xs text-indigo-500 mt-1">Auto-filled from concession.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fine / Late Fee</label>
                    <input v-model="form.fine" type="number" step="0.01" min="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date *</label>
                    <input v-model="form.payment_date" type="date" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Mode *</label>
                    <select v-model="form.payment_mode" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                        <option v-for="m in paymentModes" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Ref.</label>
                    <input v-model="form.transaction_ref" type="text" placeholder="UTR / cheque no. / UPI ref"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>

                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                    <input v-model="form.remarks" type="text" placeholder="Optional note"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>

                <!-- Net effect preview -->
                <div class="md:col-span-3 bg-gray-50 border border-gray-200 rounded-lg p-3 text-sm flex flex-wrap gap-x-6 gap-y-1">
                    <span>Current balance: <strong>{{ fmt(netEffect.balance) }}</strong></span>
                    <span>+ Fine: <strong class="text-rose-600">{{ fmt(netEffect.fine) }}</strong></span>
                    <span>− Discount: <strong class="text-amber-600">{{ fmt(netEffect.discount) }}</strong></span>
                    <span>− Paying now: <strong class="text-green-600">{{ fmt(netEffect.paid) }}</strong></span>
                    <span class="ml-auto">New balance: <strong :class="netEffect.newBalance > 0 ? 'text-rose-700' : 'text-green-700'">{{ fmt(netEffect.newBalance) }}</strong></span>
                </div>

                <div class="md:col-span-3 flex justify-end">
                    <Button type="submit" variant="primary" :disabled="disabledSubmit || form.processing" :loading="form.processing">
                        Record Payment &amp; Print Receipt
                    </Button>
                </div>
            </form>
        </div>

        <div v-else-if="Number(allocation.balance) <= 0"
             class="bg-green-50 border border-green-200 rounded-xl p-5 mb-5 text-sm text-green-800">
            ✅ No outstanding balance — all transport fees for this allocation are settled.
        </div>

        <!-- Receipt history -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base font-bold text-gray-800">📜 Receipts ({{ allocation.payments?.length ?? 0 }})</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Receipt No.</th>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-right">Amount</th>
                            <th class="px-4 py-2 text-right">Discount</th>
                            <th class="px-4 py-2 text-right">Fine</th>
                            <th class="px-4 py-2 text-left">Mode</th>
                            <th class="px-4 py-2 text-left">Collected By</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!allocation.payments?.length">
                            <td colspan="8" class="text-center py-8 text-gray-400">No receipts recorded yet.</td>
                        </tr>
                        <tr v-for="p in allocation.payments" :key="p.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono text-indigo-700">{{ p.receipt_no }}</td>
                            <td class="px-4 py-2">{{ fmtDate(p.payment_date) }}</td>
                            <td class="px-4 py-2 text-right font-mono text-green-700">{{ fmt(p.amount_paid) }}</td>
                            <td class="px-4 py-2 text-right font-mono text-amber-700">{{ fmt(p.discount) }}</td>
                            <td class="px-4 py-2 text-right font-mono text-rose-700">{{ fmt(p.fine) }}</td>
                            <td class="px-4 py-2 uppercase text-xs">{{ p.payment_mode }}</td>
                            <td class="px-4 py-2 text-xs">{{ p.collected_by?.name ?? '—' }}</td>
                            <td class="px-4 py-2 text-right space-x-1">
                                <button @click="printReceipt(p.id)" class="text-indigo-600 hover:underline text-xs">Print</button>
                                <button v-if="can('collect_transport_fee')" @click="voidReceipt(p.id)" class="text-rose-600 hover:underline text-xs">Void</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </SchoolLayout>
</template>
