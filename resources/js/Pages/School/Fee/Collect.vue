<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, reactive, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useDelete } from '@/Composables/useDelete';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    student:      Object,
    structures:   Array,
    payments:     Array,
    students:     Array,   // search results
    classes:      Array,   // filter options
    concessions:  { type: Array, default: () => [] }, // active concessions for student
});

const searchQuery = ref(new URLSearchParams(window.location.search).get('search') || '');
const selectedClassId = ref(Number(new URLSearchParams(window.location.search).get('class_id')) || '');
const selectedSectionId = ref(Number(new URLSearchParams(window.location.search).get('section_id')) || '');

// Import router-based form helper (moved to top level)
const editingReceiptId  = ref(null);
const editReceiptValue  = ref('');
const receiptEditError  = ref('');
const startEditReceipt  = (p) => { editingReceiptId.value = p.id; editReceiptValue.value = p.receipt_no; receiptEditError.value = ''; };
const saveReceiptNo     = (p) => {
    receiptEditError.value = '';
    router.patch(`/school/fee/collect/${p.id}/receipt-no`, { receipt_no: editReceiptValue.value }, {
        preserveScroll: true,
        onSuccess: () => { editingReceiptId.value = null; },
        onError: (errors) => { receiptEditError.value = errors.receipt_no ?? 'Error saving.'; },
    });
};

const postFeeGl = (paymentId) => {
    router.post(`/school/fee/collect/${paymentId}/post-gl`, {}, { preserveScroll: true });
};

const batchPostingGl = ref(false);
const batchPostGl = () => {
    if (!confirm('Post all unsynced fee payments to the General Ledger?')) return;
    batchPostingGl.value = true;
    router.post('/school/fee/collect/batch-post-gl', {}, {
        preserveScroll: true,
        onFinish: () => { batchPostingGl.value = false; },
    });
};

const search = () => {
    router.get('/school/fee/collect', { 
        search: searchQuery.value,
        class_id: selectedClassId.value,
        section_id: selectedSectionId.value,
    }, { preserveState: true, replace: true });
};

const sections = computed(() => {
    if (!selectedClassId.value) return [];
    return props.classes?.find(c => c.id === selectedClassId.value)?.sections || [];
});

const selectStudent = (id) => {
    router.get('/school/fee/collect', { student_id: id }, { preserveState: true, replace: true });
};

// Calculate totals
const getInclusiveAmount = (s) => {
    let amt = parseFloat(s.amount || 0);
    if (s.fee_head?.is_taxable && s.fee_head?.gst_percent > 0) {
        amt = amt + (amt * (parseFloat(s.fee_head.gst_percent) / 100));
    }
    return amt;
};
const totalDue      = computed(() => (props.structures || []).reduce((sum, s) => sum + getInclusiveAmount(s), 0));
const totalPaid     = computed(() => (props.payments  || []).reduce((s, p) => s + parseFloat(p.amount_paid || 0), 0));
const totalDiscount = computed(() => (props.payments  || []).reduce((s, p) => s + parseFloat(p.discount || 0), 0));
const totalBal      = computed(() => Math.max(0, totalDue.value - totalPaid.value - totalDiscount.value));

// Payment form
const feeForm = reactive({
    student_id:           props.student?.id || '',
    fee_head_id:          '',
    term:                 'annual',
    amount_due:           '',
    amount_paid:          '',
    discount:             0,
    fine:                 0,
    payment_mode:         'cash',
    payment_date:         school.today(),
    transaction_ref:      '',
    remarks:              '',
    receipt_no:           '',
    concession_id:        '',
    existing_payment_id:  null,  // set when paying an existing transport/ad-hoc due entry
});

// ── Concession helpers ─────────────────────────────────────────────────────
const selectedConcession = computed(() =>
    props.concessions?.find(c => c.id == feeForm.concession_id) ?? null
);
const concessionPreview = computed(() => {
    const c = selectedConcession.value;
    if (!c || !feeForm.amount_due) return null;
    const amt = parseFloat(feeForm.amount_due) || 0;
    const disc = c.type === 'percentage'
        ? Math.round((amt * parseFloat(c.value) / 100) * 100) / 100
        : Math.min(parseFloat(c.value), amt);
    return disc;
});
const applyConcession = () => {
    if (concessionPreview.value !== null) {
        feeForm.discount = concessionPreview.value;
    } else {
        feeForm.discount = 0;
    }
};
watch(() => feeForm.concession_id, applyConcession);
watch(() => feeForm.amount_due,   applyConcession);

// Also watch for concessions being deleted/deactivated externally
watch(() => props.concessions, (newConcessions) => {
    if (feeForm.concession_id && !newConcessions?.find(c => c.id === feeForm.concession_id)) {
        feeForm.concession_id = '';
        feeForm.discount = 0;
    }
}, { deep: true });

const prefill = (s) => {
    feeForm.fee_head_id = s.fee_head_id;
    feeForm.term        = s.term;

    if (s.source === 'payment') {
        // Transport / ad-hoc fee: balance pre-computed on server.
        // Always create a NEW receipt (do NOT update the master due record).
        feeForm.amount_due          = Number(s.balance || s.amount || 0).toFixed(2);
        feeForm.existing_payment_id = null;  // ← always create new receipt
    } else {
        feeForm.existing_payment_id = null;
        // Normal fee structure: calculate remaining from payment history
        const totalAmount = getInclusiveAmount(s);
        const relatedPayments = (props.payments || []).filter(p => p.fee_head_id === s.fee_head_id && p.term === s.term);
        const paidSoFar     = relatedPayments.reduce((sum, p) => sum + parseFloat(p.amount_paid || 0), 0);
        const discountSoFar = relatedPayments.reduce((sum, p) => sum + parseFloat(p.discount   || 0), 0);
        const fineSoFar     = relatedPayments.reduce((sum, p) => sum + parseFloat(p.fine       || 0), 0);
        feeForm.amount_due  = Math.max(0, totalAmount - paidSoFar - discountSoFar + fineSoFar).toFixed(2);
    }
    feeForm.amount_paid = '';
};

const handleDropdownChange = (e) => {
    const val = e.target.value;
    if (val === '') {
        feeForm.fee_head_id = '';
        feeForm.term = 'annual';
        feeForm.amount_due = '';
        feeForm.amount_paid = '';
        return;
    }
    const s = props.structures[val];
    if (s) prefill(s);
};

const { del } = useDelete();

// Quick Concession Actions
const toggleConcession = (c) => {
    router.patch(`/school/fee/concessions/${c.id}/toggle`, {}, { preserveScroll: true });
};
const removeConcession = (c) => {
    del(`/school/fee/concessions/${c.id}`, `Remove concession "${c.name}" from this student?`);
};

const isEditing = ref(false);
const editPaymentId = ref(null);

const editPayment = (p) => {
    isEditing.value = true;
    editPaymentId.value = p.id;
    feeForm.fee_head_id = p.fee_head_id;
    feeForm.term = p.term;
    feeForm.amount_due = p.amount_due;
    feeForm.amount_paid = p.amount_paid;
    feeForm.discount = p.discount;
    feeForm.fine = p.fine;
    feeForm.payment_mode = p.payment_mode;
    feeForm.payment_date = p.payment_date;
    feeForm.transaction_ref = p.transaction_ref || '';
    feeForm.remarks = p.remarks || '';
    feeForm.receipt_no = p.receipt_no || '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const cancelEdit = () => {
    isEditing.value = false;
    editPaymentId.value = null;
    feeForm.fee_head_id = '';
    feeForm.term = 'annual';
    feeForm.amount_due = '';
    feeForm.amount_paid = '';
    feeForm.discount = 0;
    feeForm.fine = 0;
    feeForm.payment_mode = 'cash';
    feeForm.transaction_ref = '';
    feeForm.remarks = '';
    feeForm.receipt_no = '';
    feeForm.concession_id = '';
    feeForm.existing_payment_id = null;
};

const submit = () => {
    if (isEditing.value && editPaymentId.value) {
        router.put(`/school/fee/collect/${editPaymentId.value}`, feeForm, {
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
        });
    } else {
        router.post('/school/fee/collect', { ...feeForm, student_id: props.student?.id }, {
            preserveScroll: true,
            onSuccess: () => {
                feeForm.fee_head_id          = '';
                feeForm.term                 = 'annual';
                feeForm.amount_due           = '';
                feeForm.amount_paid          = '';
                feeForm.discount             = 0;
                feeForm.fine                 = 0;
                feeForm.transaction_ref      = '';
                feeForm.remarks              = '';
                feeForm.concession_id        = '';
                feeForm.existing_payment_id  = null;
            },
        });
    }
};

const removePayment = (id) => {
    del(`/school/fee/collect/${id}`, 'Delete this payment record? This action cannot be undone.');
};

const statusBadge = (status) => {
    const map = { paid: 'bg-green-100 text-green-700', partial: 'bg-yellow-100 text-yellow-700', due: 'bg-red-100 text-red-600', waived: 'bg-gray-100 text-gray-500' };
    return map[status] || 'bg-gray-100 text-gray-500';
};
</script>

<template>
    <SchoolLayout title="Fee Collection">
        <div class="max-w-6xl mx-auto space-y-5">

            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-header-title">Fee Collection</h1>
                    <p class="page-header-sub">Search for a student and record payment</p>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <Button variant="secondary" @click="batchPostGl" :loading="batchPostingGl" title="Post all unsynced fee payments to General Ledger">
                        <svg v-if="batchPostingGl" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                        Sync All to GL
                    </Button>
                    <Button variant="secondary" as="a" href="/school/fee/groups">Fee Setup</Button>
                </div>
            </div>

            <!-- Student search -->
            <div class="card">
                <div class="card-body">
                    <div class="flex gap-3 flex-wrap">
                        <select v-model="selectedClassId" @change="selectedSectionId=''" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 w-36">
                            <option value="">All Classes</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <select v-model="selectedSectionId" :disabled="!selectedClassId" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 w-36 disabled:bg-gray-100 disabled:text-gray-400">
                            <option value="">All Sections</option>
                            <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <input v-model="searchQuery" @keyup.enter="search" type="text"
                               placeholder="Search by name or admission number..."
                               class="flex-1 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 min-w-[200px]">
                        <Button variant="success" @click="search">Search</Button>
                    </div>
                    <div v-if="students?.length" class="mt-3 divide-y rounded-lg overflow-hidden" style="border: 1px solid var(--border)">
                        <button v-for="s in students" :key="s.id" @click="selectStudent(s.id)"
                                class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-green-50 text-left transition">
                            <span class="text-sm font-medium" style="color: var(--text-primary)">{{ s.first_name }} {{ s.last_name }}</span>
                            <span class="text-xs" style="color: var(--text-muted)">{{ s.admission_no }} · Roll {{ s.roll_no }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Student panel -->
            <div v-if="student" class="grid grid-cols-1 md:grid-cols-5 gap-5">

                <!-- Left: Fee status -->
                <div class="md:col-span-2 space-y-4">

                    <!-- Student summary card -->
                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full bg-indigo-100 font-bold text-lg flex items-center justify-center" style="color: var(--accent)">
                                    {{ student.first_name?.charAt(0) }}
                                </div>
                                <div>
                                    <h3 class="font-bold" style="color: var(--text-primary)">{{ student.first_name }} {{ student.last_name }}</h3>
                                    <p class="text-xs" style="color: var(--text-muted)">{{ student.admission_no }}</p>
                                </div>
                            </div>
                            <!-- Summary chips -->
                            <div class="grid grid-cols-4 gap-2 mt-4">
                                <div class="text-center bg-blue-50 rounded-lg p-2">
                                    <p class="text-xs text-blue-500 mb-0.5">Total Due</p>
                                    <p class="font-bold text-blue-800 text-sm">{{ $page.props.school.currency }}{{ totalDue.toLocaleString('en-IN') }}</p>
                                </div>
                                <div class="text-center bg-green-50 rounded-lg p-2">
                                    <p class="text-xs text-green-500 mb-0.5">Paid</p>
                                    <p class="font-bold text-green-800 text-sm">{{ $page.props.school.currency }}{{ totalPaid.toLocaleString('en-IN') }}</p>
                                </div>
                                <div class="text-center bg-purple-50 rounded-lg p-2">
                                    <p class="text-xs text-purple-500 mb-0.5">Discounts</p>
                                    <p class="font-bold text-purple-800 text-sm">{{ $page.props.school.currency }}{{ totalDiscount.toLocaleString('en-IN') }}</p>
                                </div>
                                <div class="text-center rounded-lg p-2" :class="totalBal > 0 ? 'bg-red-50' : 'bg-gray-50'">
                                    <p class="text-xs mb-0.5" :class="totalBal > 0 ? 'text-red-500' : 'text-gray-400'">Balance</p>
                                    <p class="font-bold text-sm" :class="totalBal > 0 ? 'text-red-800' : 'text-gray-500'">{{ $page.props.school.currency }}{{ totalBal.toLocaleString('en-IN') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Assigned Concessions -->
                    <div v-if="concessions?.length" class="card overflow-hidden" style="border-color: #e0e7ff;">
                        <div class="card-header flex items-center gap-2" style="background: #eef2ff; border-color: #e0e7ff;">
                            <span>🎟️</span>
                            <span class="text-xs font-semibold uppercase" style="color: #3730a3;">Assigned Concessions</span>
                        </div>
                        <div class="divide-y" style="border-color: #e0e7ff;">
                            <div v-for="c in concessions" :key="c.id" class="px-4 py-3 flex items-start justify-between text-sm">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="font-semibold" style="color: var(--text-primary)">{{ c.name }}</p>
                                        <span v-if="!c.is_active" class="badge badge-red text-[10px]">Used</span>
                                    </div>
                                    <p class="text-xs mt-0.5" style="color: var(--text-muted)" v-if="c.created_by?.name">Assigned by: {{ c.created_by.name }}</p>
                                </div>
                                <div class="text-right flex flex-col items-end">
                                    <p class="font-bold px-2 py-0.5 rounded text-xs border mb-1" style="color: var(--accent); background: #eef2ff; border-color: #c7d2fe;">
                                        {{ c.type === 'percentage' ? c.value + '%' : $page.props.school.currency + parseFloat(c.value).toLocaleString() }}
                                    </p>
                                    <button @click="removeConcession(c)" class="text-[10px] font-semibold underline" style="color: var(--danger)">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fee Schedule -->
                    <div class="card overflow-hidden">
                        <div class="card-header">
                            <span class="text-xs font-semibold uppercase" style="color: var(--text-muted)">Fee Schedule</span>
                        </div>
                        <div v-for="s in structures" :key="s.id"
                             class="flex items-center justify-between px-4 py-3 border-b last:border-0 transition"
                             style="border-color: var(--border)"
                             :class="s.status === 'paid' ? 'opacity-60 cursor-not-allowed' : 'hover:bg-green-50 cursor-pointer group'"
                             @click="s.status !== 'paid' && prefill(s)">
                            <div>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <p class="text-sm font-medium" style="color: var(--text-primary)">{{ s.fee_head?.name }}</p>
                                    <span v-if="s.source === 'payment' && s.status === 'paid'" class="badge badge-green text-[10px]">✓ paid</span>
                                    <span v-else-if="s.source === 'payment' && s.fee_head?.is_hostel_fee" class="badge badge-purple text-[10px]">Hostel</span>
                                    <span v-if="s.is_optional" class="badge badge-purple text-[10px]">Optional</span>
                                    <span v-if="s.fee_head?.is_taxable" class="badge badge-red text-[10px]" title="Includes GST">GST {{ s.fee_head?.gst_percent }}%</span>
                                </div>
                                <p class="text-xs mt-0.5" style="color: var(--text-muted)">
                                    {{ s.term.startsWith('Installment') ? s.term : s.term.charAt(0).toUpperCase() + s.term.slice(1).replace('_', ' ') }}
                                    · {{ s.fee_head?.fee_group?.name ?? (s.fee_head?.is_hostel_fee ? 'Hostel' : 'Fee') }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-semibold"
                                      :class="s.status === 'paid' ? 'text-green-600' : (s.source === 'payment' ? 'text-red-600' : '')">
                                    {{ $page.props.school.currency }}{{ Number(s.source === 'payment' ? (s.balance ?? s.amount) : getInclusiveAmount(s)).toLocaleString('en-IN') }}
                                </span>
                                <p v-if="s.source === 'payment' && s.status !== 'paid'" class="text-[10px] text-red-400">Balance due</p>
                                <p v-if="s.status === 'paid'" class="text-[10px] text-green-500">Fully paid ✓</p>
                                <p v-else class="text-xs text-green-600 opacity-0 group-hover:opacity-100 transition">Click to fill →</p>
                            </div>
                        </div>
                        <p v-if="!structures?.length" class="px-4 py-4 text-sm text-center" style="color: var(--text-muted)">No fee structure for this class.</p>
                    </div>
                </div>

                <!-- Right: Record payment + History -->
                <div class="md:col-span-3 space-y-4">

                    <!-- Payment form -->
                    <div class="card">
                        <div class="card-header flex items-center justify-between">
                            <h3 class="card-title flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                {{ isEditing ? 'Edit Payment Record' : 'Record Payment' }}
                            </h3>
                            <button v-if="isEditing" @click="cancelEdit" class="text-xs underline" style="color: var(--text-muted)">Cancel Edit</button>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label class="block text-xs mb-1" style="color: var(--text-muted)">Fee Head *</label>
                                    <select @change="handleDropdownChange" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="" :selected="!feeForm.fee_head_id">Select Fee Head</option>
                                        <option v-for="(s, i) in structures" :key="s.id || i" :value="i" :selected="feeForm.fee_head_id === s.fee_head_id && feeForm.term === s.term">{{ s.fee_head?.name }} ({{ s.term.startsWith('Installment') ? s.term : s.term.charAt(0).toUpperCase() + s.term.slice(1).replace('_', ' ') }})</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs mb-1" style="color: var(--text-muted)">Amount Due ({{ $page.props.school.currency }})</label>
                                    <input v-model="feeForm.amount_due" type="number" step="0.01" placeholder="0.00"
                                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs mb-1" style="color: var(--text-muted)">Amount Paid ({{ $page.props.school.currency }}) *</label>
                                    <input v-model="feeForm.amount_paid" type="number" step="0.01" placeholder="0.00"
                                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <!-- Concession Selector -->
                                <div v-if="concessions && concessions.filter(c => c.is_active && c.payments_count === 0 && (!c.applicable_fee_heads?.length || c.applicable_fee_heads.includes(feeForm.fee_head_id))).length && !isEditing" class="col-span-2">
                                    <label class="block text-xs mb-1" style="color: var(--text-muted)">🎟️ Apply Concession / Scholarship</label>
                                    <select v-model="feeForm.concession_id"
                                            class="w-full rounded-md border-indigo-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-indigo-50 font-medium">
                                        <option value="">— No Concession —</option>
                                        <option v-for="c in concessions.filter(c => c.is_active && c.payments_count === 0 && (!c.applicable_fee_heads?.length || c.applicable_fee_heads.includes(feeForm.fee_head_id)))" :key="c.id" :value="c.id">
                                            {{ c.name }}
                                            ({{ c.type === 'percentage' ? c.value + '%' : $page.props.school.currency + parseFloat(c.value).toLocaleString() }})
                                            {{ c.description ? '— ' + c.description : '' }}
                                        </option>
                                    </select>
                                    <!-- Live discount preview -->
                                    <div v-if="selectedConcession && concessionPreview !== null"
                                         class="mt-1.5 flex items-center gap-2 text-xs font-medium bg-amber-50 text-amber-800 border-amber-200"
                                         style="border-width: 1px; border-radius: 0.5rem; padding: 0.375rem 0.75rem;">
                                        <span class="text-amber-600">⚠️</span>
                                        Discount of <strong>{{ $page.props.school.currency }}{{ concessionPreview.toLocaleString('en-IN') }}</strong> will be applied.
                                        <span class="ml-1 font-bold">(Expires after use)</span>
                                        <span class="ml-auto" style="color: var(--text-muted)">({{ selectedConcession.name }})</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs mb-1" style="color: var(--text-muted)">
                                        Discount ({{ $page.props.school.currency }})
                                        <span v-if="feeForm.concession_id" class="ml-1" style="color: var(--accent)">— set by concession</span>
                                    </label>
                                    <input v-model="feeForm.discount" type="number" step="0.01" placeholder="0"
                                           :readonly="!!feeForm.concession_id && !isEditing"
                                           :class="feeForm.concession_id && !isEditing
                                               ? 'bg-green-50 border-green-300 text-green-800 font-semibold cursor-not-allowed'
                                               : 'border-gray-300'"
                                           class="w-full rounded-md text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs mb-1" style="color: var(--text-muted)">Fine ({{ $page.props.school.currency }})</label>
                                    <input v-model="feeForm.fine" type="number" step="0.01" placeholder="0"
                                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs mb-1" style="color: var(--text-muted)">Payment Mode *</label>
                                    <select v-model="feeForm.payment_mode" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="cash">Cash</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="online">Online</option>
                                        <option value="upi">UPI</option>
                                        <option value="dd">Demand Draft</option>
                                        <option value="card">Card</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs mb-1" style="color: var(--text-muted)">Payment Date *</label>
                                    <input v-model="feeForm.payment_date" type="date"
                                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs mb-1" style="color: var(--text-muted)">Cheque/UTR/Reference No.</label>
                                    <input v-model="feeForm.transaction_ref" type="text" placeholder=""
                                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs mb-1" style="color: var(--text-muted)">Remarks</label>
                                    <input v-model="feeForm.remarks" type="text" placeholder="Optional..."
                                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <!-- Receipt No (editable when editing) -->
                                <div v-if="isEditing" class="col-span-2">
                                    <div class="p-3 rounded-lg flex items-center gap-3" style="background: #fffbeb; border: 1px solid #fcd34d;">
                                        <span class="font-bold text-xs whitespace-nowrap" style="color: var(--warning)">🧾 Receipt No:</span>
                                        <input v-model="feeForm.receipt_no" type="text"
                                               class="flex-1 rounded-md border-amber-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 font-mono font-bold"
                                               placeholder="e.g. FEE-00042" />
                                        <span class="text-xs text-amber-500">Must be unique</span>
                                    </div>
                                    <p v-if="feeForm.errors?.receipt_no" class="form-error mt-1">{{ feeForm.errors.receipt_no }}</p>
                                </div>
                            </div>

                            <Button variant="success" @click="submit" class="mt-4 w-full justify-center">
                                {{ isEditing ? '📝 Update Payment' : '💰 Save & Generate Receipt' }}
                            </Button>
                        </div>
                    </div>

                    <!-- Payment history -->
                    <div class="card overflow-hidden">
                        <div class="card-header">
                            <span class="text-xs font-semibold uppercase" style="color: var(--text-muted)">Payment History</span>
                        </div>
                        <div v-if="payments?.length">
                            <div v-for="p in payments" :key="p.id"
                                 class="flex items-start justify-between px-4 py-3 border-b last:border-0 group hover:bg-gray-50 transition"
                                 style="border-color: var(--border)">
                                <div>
                                    <!-- Receipt No - inline editable -->
                                    <div class="flex items-center gap-1 mb-0.5">
                                        <template v-if="editingReceiptId !== p.id">
                                            <p class="text-xs font-mono" style="color: var(--text-muted)">{{ p.receipt_no }}</p>
                                            <button @click="startEditReceipt(p)"
                                                    class="text-gray-300 hover:text-indigo-500 transition" title="Edit Receipt No">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </button>
                                        </template>
                                        <template v-else>
                                            <input v-model="editReceiptValue" type="text"
                                                   class="border border-indigo-300 rounded px-1.5 py-0.5 text-xs font-mono focus:outline-none focus:ring-1 focus:ring-indigo-400 w-32"
                                                   @keyup.enter="saveReceiptNo(p)" @keyup.esc="editingReceiptId = null" />
                                            <Button size="xs" @click="saveReceiptNo(p)">Save</Button>
                                            <Button variant="secondary" size="xs" @click="editingReceiptId = null">✕</Button>
                                        </template>
                                    </div>
                                    <p v-if="receiptEditError && editingReceiptId === p.id" class="form-error">⚠ {{ receiptEditError }}</p>
                                    <p class="text-sm font-medium" style="color: var(--text-primary)">{{ p.fee_head?.name }}</p>
                                    <p class="text-xs" style="color: var(--text-muted)">
                                        {{ school.fmtDate(p.payment_date) }} · {{ p.payment_mode?.toUpperCase() }}
                                        <span v-if="p.collected_by">· Collected by: {{ p.collected_by.name }}</span>
                                    </p>
                                </div>
                                <div class="text-right flex flex-col items-end">
                                    <p class="text-sm font-bold" style="color: var(--text-primary)">{{ $page.props.school.currency }}{{ Number(p.amount_paid).toLocaleString('en-IN') }}</p>
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="statusBadge(p.status)">{{ p.status }}</span>
                                    <p v-if="p.balance > 0" class="text-xs mt-0.5" style="color: var(--danger)">Bal: {{ $page.props.school.currency }}{{ Number(p.balance).toLocaleString('en-IN') }}</p>
                                    <!-- GL Status -->
                                    <div class="mt-1">
                                        <span v-if="p.gl_transaction" class="fee-gl-posted" :title="p.gl_transaction.transaction_no">
                                            ✓ GL: {{ p.gl_transaction.transaction_no }}
                                        </span>
                                        <button v-else @click="postFeeGl(p.id)" class="fee-gl-pending" title="Post to General Ledger">
                                            Post to GL
                                        </button>
                                    </div>
                                    <!-- Actions -->
                                    <div class="flex items-center gap-2 mt-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <a :href="`/school/fee/collect/${p.id}/receipt`" target="_blank"
                                           class="p-1 hover:bg-gray-100 rounded transition" style="color: var(--text-muted)" title="Print Receipt">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                            </svg>
                                        </a>
                                        <Button variant="secondary" @click="editPayment(p)" style="color: var(--text-muted)" title="Edit Payment">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        </Button>
                                        <button @click="removePayment(p.id)" class="p-1 hover:bg-red-50 rounded transition" style="color: var(--text-muted)" title="Delete Payment">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="px-4 py-6 text-sm text-center" style="color: var(--text-muted)">No payments recorded yet.</p>
                    </div>
                </div>
            </div>

            <!-- No student selected -->
            <div v-else class="card text-center py-16" style="color: var(--text-muted)">
                <svg class="w-12 h-12 mx-auto mb-3" style="color: var(--border)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <p class="text-sm">Search for a student above to collect their fee.</p>
            </div>

        </div>
    </SchoolLayout>
</template>

<style scoped>
.fee-gl-posted {
    display: inline-flex; align-items: center;
    font-size: 0.68rem; font-weight: 600; font-family: monospace;
    color: #059669; background: #d1fae5; border-radius: 8px;
    padding: 2px 7px; white-space: nowrap;
}
.fee-gl-pending {
    display: inline-flex; align-items: center;
    font-size: 0.68rem; font-weight: 600;
    color: #92400e; background: #fef3c7;
    border: 1px solid #fde68a; border-radius: 8px;
    padding: 2px 7px; white-space: nowrap;
    cursor: pointer; transition: background 0.15s;
}
.fee-gl-pending:hover { background: #fde68a; }
</style>
