<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import ExportDropdown from '@/Components/ExportDropdown.vue';
import debounce from 'lodash/debounce';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    expenses: Array,
    categories: Array,
    filters: Object,
});

const form = useForm({
    expense_category_id: '',
    title: '',
    amount: '',
    expense_date: new Date().toISOString().split('T')[0],
    payment_mode: 'cash',
    transaction_ref: '',
    description: '',
});

const isEditing = ref(false);
const editingId = ref(null);

const activeTab = ref('list'); // 'list' or 'form'

const filterForm = ref({
    category_id: props.filters.category_id || '',
    from_date: props.filters.from_date || '',
    to_date: props.filters.to_date || '',
});

const fetchFiltered = debounce(() => {
    router.get(route('school.expenses.index'), filterForm.value, {
        preserveState: true,
        replace: true
    });
}, 300);

const resetFilters = () => {
    filterForm.value = { category_id: '', from_date: '', to_date: '' };
    fetchFiltered();
};

const saveExpense = () => {
    if (isEditing.value) {
        form.put(route('school.expenses.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => {
                cancelEdit();
                activeTab.value = 'list';
            },
        });
    } else {
        form.post(route('school.expenses.store'), {
            preserveScroll: true,
            onSuccess: () => {
                cancelEdit();
                activeTab.value = 'list';
            },
        });
    }
};

const editExpense = (exp) => {
    isEditing.value = true;
    editingId.value = exp.id;
    form.expense_category_id = exp.expense_category_id;
    form.title = exp.title;
    form.amount = exp.amount;
    form.expense_date = exp.expense_date.split('T')[0];
    form.payment_mode = exp.payment_mode;
    form.transaction_ref = exp.transaction_ref || '';
    form.description = exp.description || '';
    activeTab.value = 'form';
};

const cancelEdit = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    activeTab.value = 'list';
};

const openNewForm = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.expense_date = new Date().toISOString().split('T')[0];
    activeTab.value = 'form';
};

const deleteExpense = (exp) => {
    if (confirm('Are you sure you want to delete this expense record?')) {
        router.delete(route('school.expenses.destroy', exp.id), { preserveScroll: true });
    }
};

const postToGl = (exp) => {
    router.post(route('school.expenses.post-gl', exp.id), {}, { preserveScroll: true });
};

const unpostedCount = computed(() => props.expenses.filter(e => !e.gl_transaction).length);

const postingAll = ref(false);
const postAllUnposted = () => {
    if (!confirm(`Post ${unpostedCount.value} unposted expense(s) to the General Ledger?`)) return;
    postingAll.value = true;
    router.post(route('school.expenses.post-all-unposted'), {}, {
        preserveScroll: true,
        onFinish: () => { postingAll.value = false; },
    });
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);
};
</script>

<template>
    <SchoolLayout>
        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Expenses</h1>
                <p class="page-header-sub">Track school expenditures and cash outflows</p>
            </div>
            <div class="flex gap-2">
                <ExportDropdown
                    base-url="/school/export/expenses"
                    :params="{ category_id: filterForm.category_id, from_date: filterForm.from_date, to_date: filterForm.to_date }"
                />
                <Button
                    v-if="unpostedCount > 0 && activeTab === 'list'"
                    variant="warning"
                    @click="postAllUnposted"
                    :loading="postingAll"
                    :title="`${unpostedCount} expense(s) not yet posted to GL`"
                >
                    Post All to GL ({{ unpostedCount }})
                </Button>
                <Button
                    @click="activeTab = 'list'"
                    :variant="activeTab === 'list' ? 'primary' : 'secondary'"
                >
                    View Ledger
                </Button>
                <Button
                    @click="openNewForm"
                    :variant="activeTab === 'form' ? 'primary' : 'secondary'"
                >
                    + Record Expense
                </Button>
            </div>
        </div>

        <!-- LIST TAB -->
        <div v-if="activeTab === 'list'" class="space-y-6">

            <!-- Filters -->
            <div class="card">
                <div class="card-body flex flex-wrap gap-4 items-end">
                    <div class="w-full sm:w-auto">
                        <label class="block text-xs font-semibold mb-1" style="color: var(--text-secondary)">Category</label>
                        <select v-model="filterForm.category_id" @change="fetchFiltered" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 w-48">
                            <option value="">All Categories</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="w-full sm:w-auto">
                        <label class="block text-xs font-semibold mb-1" style="color: var(--text-secondary)">From Date</label>
                        <input type="date" v-model="filterForm.from_date" @change="fetchFiltered" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <div class="w-full sm:w-auto">
                        <label class="block text-xs font-semibold mb-1" style="color: var(--text-secondary)">To Date</label>
                        <input type="date" v-model="filterForm.to_date" @change="fetchFiltered" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <Button variant="secondary" size="sm" @click="resetFilters" v-if="filterForm.category_id || filterForm.from_date || filterForm.to_date">
                        Clear Filters
                    </Button>
                </div>
            </div>

            <!-- Table -->
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <Table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Particulars (Title)</th>
                                <th>Mode &amp; Ref</th>
                                <th class="text-right">Amount</th>
                                <th>GL Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="exp in expenses" :key="exp.id">
                                <td class="text-sm" style="color: var(--text-secondary)">{{ school.fmtDate(exp.expense_date) }}</td>
                                <td>
                                    <span class="badge badge-gray">{{ exp.category?.name }}</span>
                                </td>
                                <td>
                                    <div class="font-medium text-sm" style="color: var(--text-primary)">{{ exp.title }}</div>
                                    <div v-if="exp.description" class="text-xs mt-0.5" style="color: var(--text-muted)">{{ exp.description }}</div>
                                </td>
                                <td>
                                    <div class="text-sm capitalize" style="color: var(--text-primary)">{{ exp.payment_mode }}</div>
                                    <div v-if="exp.transaction_ref" class="text-xs font-mono" style="color: var(--text-muted)">{{ exp.transaction_ref }}</div>
                                </td>
                                <td class="text-right font-medium" style="color: var(--danger)">
                                    {{ formatCurrency(exp.amount) }}
                                </td>
                                <td>
                                    <span v-if="exp.gl_transaction" class="gl-badge gl-posted" :title="exp.gl_transaction.transaction_no">
                                        ✓ {{ exp.gl_transaction.transaction_no }}
                                    </span>
                                    <button v-else @click="postToGl(exp)" class="gl-badge gl-pending" title="Post to General Ledger">
                                        Post to GL
                                    </button>
                                </td>
                                <td class="text-right">
                                    <Button variant="secondary" size="xs" @click="editExpense(exp)" class="mr-1">Edit</Button>
                                    <Button variant="danger" size="xs" @click="deleteExpense(exp)">Delete</Button>
                                </td>
                            </tr>
                            <tr v-if="expenses.length === 0">
                                <td colspan="7" class="text-center py-8" style="color: var(--text-muted)">
                                    No expenses found for the selected dates.
                                </td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
            </div>
        </div>

        <!-- FORM TAB -->
        <div v-if="activeTab === 'form'" class="max-w-3xl mx-auto">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ isEditing ? 'Edit Expense Record' : 'Record New Expense' }}</h2>
                </div>
                <form @submit.prevent="saveExpense" class="card-body">
                    <div class="form-row-2 mb-4">
                        <div class="form-field mb-0">
                            <label>Expense Category <span style="color: var(--danger)">*</span></label>
                            <select v-model="form.expense_category_id" required class="w-full">
                                <option value="" disabled>Select a category...</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <div v-if="form.errors.expense_category_id" class="form-error">{{ form.errors.expense_category_id }}</div>
                        </div>

                        <div class="form-field mb-0">
                            <label>Expense Date <span style="color: var(--danger)">*</span></label>
                            <input type="date" v-model="form.expense_date" required class="w-full" />
                            <div v-if="form.errors.expense_date" class="form-error">{{ form.errors.expense_date }}</div>
                        </div>
                    </div>

                    <div class="form-field mb-4">
                        <label>Title / Particulars <span style="color: var(--danger)">*</span></label>
                        <input type="text" v-model="form.title" required class="w-full" placeholder="e.g., Bought 20 reams of printer paper" />
                        <div v-if="form.errors.title" class="form-error">{{ form.errors.title }}</div>
                    </div>

                    <div class="form-row-2 mb-4">
                        <div class="form-field mb-0">
                            <label>Amount (₹) <span style="color: var(--danger)">*</span></label>
                            <input type="number" step="0.01" min="0" v-model="form.amount" required class="w-full" placeholder="0.00" />
                            <div v-if="form.errors.amount" class="form-error">{{ form.errors.amount }}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-field mb-0">
                                <label>Mode <span style="color: var(--danger)">*</span></label>
                                <select v-model="form.payment_mode" required class="w-full">
                                    <option value="cash">Cash</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="online">Online/NEFT</option>
                                    <option value="upi">UPI</option>
                                    <option value="card">Card</option>
                                </select>
                            </div>
                            <div class="form-field mb-0">
                                <label>Ref / UTR No.</label>
                                <input type="text" v-model="form.transaction_ref" class="w-full" placeholder="Optional" />
                            </div>
                        </div>
                    </div>

                    <div class="form-field mb-6">
                        <label>Detailed Description</label>
                        <textarea v-model="form.description" class="w-full" rows="3" placeholder="Additional details or supplier name..."></textarea>
                    </div>

                    <div class="flex items-center gap-3 pt-4" style="border-top: 1px solid var(--border)">
                        <Button type="submit" :loading="form.processing">
                            {{ isEditing ? 'Save Changes' : 'Record Expense' }}
                        </Button>
                        <Button variant="secondary" type="button" @click="cancelEdit">
                            Cancel
                        </Button>
                    </div>
                </form>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
.gl-badge {
    display: inline-flex; align-items: center; padding: 3px 8px;
    border-radius: 10px; font-size: 0.72rem; font-weight: 600;
    white-space: nowrap; cursor: default;
}
.gl-posted {
    background: #d1fae5; color: #059669; font-family: monospace;
}
.gl-pending {
    background: #fef3c7; color: #92400e;
    border: 1px solid #fde68a; cursor: pointer;
    transition: background 0.15s;
}
.gl-pending:hover { background: #fde68a; }
</style>
