<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import ExportDropdown from '@/Components/ExportDropdown.vue';
import debounce from 'lodash/debounce';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';
import { useConfirm } from '@/Composables/useConfirm';

const school = useSchoolStore();
const confirm = useConfirm();

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

// ── Manage Categories modal ──────────────────────────────────
const showCategoriesModal = ref(false);
const editingCategoryId = ref(null);
const categoryForm = useForm({
    name: '',
    description: '',
});

const openCategoriesModal = () => {
    showCategoriesModal.value = true;
    cancelCategoryEdit();
};

const cancelCategoryEdit = () => {
    editingCategoryId.value = null;
    categoryForm.reset();
    categoryForm.clearErrors();
};

const editCategory = (cat) => {
    editingCategoryId.value = cat.id;
    categoryForm.name = cat.name;
    categoryForm.description = cat.description || '';
};

const saveCategory = () => {
    const onDone = () => {
        cancelCategoryEdit();
        router.reload({ only: ['categories'], preserveScroll: true });
    };
    if (editingCategoryId.value) {
        categoryForm.put(route('school.expense-categories.update', editingCategoryId.value), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    } else {
        categoryForm.post(route('school.expense-categories.store'), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    }
};

const deleteCategory = async (cat) => {
    const ok = await confirm({
        title: 'Delete category?',
        message: `"${cat.name}" will be removed. Expenses tagged to it may need re-categorization.`,
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(route('school.expense-categories.destroy', cat.id), {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['categories'], preserveScroll: true }),
    });
};

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

const deleteExpense = async (exp) => {
    const ok = await confirm({
        title: 'Delete expense record?',
        message: 'This expense will be permanently removed.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(route('school.expenses.destroy', exp.id), { preserveScroll: true });
};

const postToGl = (exp) => {
    router.post(route('school.expenses.post-gl', exp.id), {}, { preserveScroll: true });
};

const unpostedCount = computed(() => props.expenses.filter(e => !e.gl_transaction).length);

const postingAll = ref(false);
const postAllUnposted = async () => {
    const ok = await confirm({
        title: 'Post to General Ledger?',
        message: `Post ${unpostedCount.value} unposted expense(s) to the General Ledger?`,
        confirmLabel: 'Post All',
    });
    if (!ok) return;
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
        <PageHeader title="Expenses" subtitle="Track school expenditures and cash outflows">
            <template #actions>
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
                <Button variant="secondary" @click="openCategoriesModal">
                    Manage Categories
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

            </template>
        </PageHeader>

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
                                    <div v-if="exp.gl_transaction" class="flex items-center gap-1">
                                        <span class="gl-badge gl-posted" :title="exp.gl_transaction.transaction_no">
                                            ✓ {{ exp.gl_transaction.transaction_no }}
                                        </span>
                                        <button @click="postToGl(exp)" class="gl-repost" title="Repost to GL with current category ledger mapping">↻</button>
                                    </div>
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
                                    <option v-for="m in $page.props.payment_methods" :key="m.code" :value="m.code">{{ m.label }}</option>
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

        <!-- Manage Categories Modal -->
        <Teleport to="body">
            <div v-if="showCategoriesModal" class="cat-modal-overlay" @click.self="showCategoriesModal = false">
                <div class="cat-modal-box">
                    <div class="cat-modal-header">
                        <h3>Manage Expense Categories</h3>
                        <button class="cat-modal-close" @click="showCategoriesModal = false">×</button>
                    </div>
                    <div class="cat-modal-body">
                        <!-- Add / Edit form -->
                        <form @submit.prevent="saveCategory" class="cat-form">
                            <div class="cat-form-row">
                                <div class="cat-form-field cat-form-field--grow">
                                    <label>{{ editingCategoryId ? 'Edit Category Name' : 'New Category Name' }} <span class="cat-req">*</span></label>
                                    <input v-model="categoryForm.name" type="text" required placeholder="e.g. Utilities" />
                                    <p v-if="categoryForm.errors.name" class="cat-err">{{ categoryForm.errors.name }}</p>
                                </div>
                            </div>
                            <div class="cat-form-row">
                                <div class="cat-form-field cat-form-field--grow">
                                    <label>Description</label>
                                    <input v-model="categoryForm.description" type="text" placeholder="Optional" />
                                </div>
                            </div>
                            <div class="cat-form-actions">
                                <Button type="submit" size="sm" :loading="categoryForm.processing">
                                    {{ editingCategoryId ? 'Update' : 'Add Category' }}
                                </Button>
                                <Button v-if="editingCategoryId" type="button" variant="secondary" size="sm" @click="cancelCategoryEdit">
                                    Cancel Edit
                                </Button>
                            </div>
                        </form>

                        <!-- Existing categories list -->
                        <div class="cat-list-header">Existing Categories ({{ categories.length }})</div>
                        <div v-if="categories.length === 0" class="cat-empty">No categories yet. Add your first one above.</div>
                        <div v-else class="cat-list">
                            <div v-for="cat in categories" :key="cat.id" class="cat-row" :class="{ 'cat-row--editing': editingCategoryId === cat.id }">
                                <div class="cat-row-text">
                                    <div class="cat-row-name">{{ cat.name }}</div>
                                    <div v-if="cat.description" class="cat-row-desc">{{ cat.description }}</div>
                                </div>
                                <div class="cat-row-actions">
                                    <button class="cat-act-btn" @click="editCategory(cat)" title="Edit">Edit</button>
                                    <button class="cat-act-btn cat-act-del" @click="deleteCategory(cat)" title="Delete">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="cat-modal-footer">
                        <Button variant="secondary" @click="showCategoriesModal = false">Close</Button>
                    </div>
                </div>
            </div>
        </Teleport>

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
.gl-repost {
    background: none; border: none; cursor: pointer;
    color: #6b7280; font-size: 0.85rem; padding: 1px 3px;
    border-radius: 4px; line-height: 1; transition: color 0.15s;
}
.gl-repost:hover { color: #059669; }

/* Manage Categories modal */
.cat-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.4);
    display: flex; align-items: center; justify-content: center;
    z-index: 9000;
    padding: 20px;
}
.cat-modal-box {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 520px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    overflow: hidden;
    max-height: 88vh;
    display: flex;
    flex-direction: column;
}
.cat-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 22px;
    border-bottom: 1px solid #f1f5f9;
}
.cat-modal-header h3 { font-weight: 700; font-size: 1rem; color: #1e293b; }
.cat-modal-close {
    background: none; border: none; cursor: pointer;
    font-size: 1.4rem; color: #94a3b8; line-height: 1;
}
.cat-modal-close:hover { color: #1e293b; }
.cat-modal-body { padding: 18px 22px; overflow-y: auto; flex: 1; }
.cat-modal-footer {
    display: flex; justify-content: flex-end; gap: 10px;
    padding: 12px 22px;
    border-top: 1px solid #f1f5f9;
    background: #f8fafc;
}

.cat-form { display: flex; flex-direction: column; gap: 10px; padding-bottom: 14px; border-bottom: 1px solid #e2e8f0; margin-bottom: 14px; }
.cat-form-row { display: flex; gap: 10px; }
.cat-form-field { display: flex; flex-direction: column; gap: 4px; }
.cat-form-field--grow { flex: 1; }
.cat-form-field label { font-size: 0.78rem; font-weight: 600; color: #374151; }
.cat-req { color: #ef4444; }
.cat-form-field input {
    border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 7px 11px;
    font-size: 0.85rem; outline: none; font-family: inherit; color: #1e293b;
    transition: border-color 0.15s; width: 100%;
}
.cat-form-field input:focus { border-color: #6366f1; }
.cat-err { font-size: 0.74rem; color: #dc2626; }
.cat-form-actions { display: flex; gap: 8px; }

.cat-list-header { font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 8px; }
.cat-empty { font-size: 0.85rem; color: #94a3b8; padding: 14px; text-align: center; background: #f8fafc; border-radius: 8px; }
.cat-list { display: flex; flex-direction: column; gap: 6px; }
.cat-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 8px;
    background: #fff; transition: all 0.15s;
}
.cat-row--editing { border-color: #6366f1; background: #eef2ff; }
.cat-row-text { flex: 1; min-width: 0; }
.cat-row-name { font-weight: 600; color: #1e293b; font-size: 0.88rem; }
.cat-row-desc { font-size: 0.74rem; color: #94a3b8; margin-top: 2px; }
.cat-row-actions { display: flex; gap: 6px; }
.cat-act-btn {
    border: 1px solid #e2e8f0; background: #f8fafc;
    border-radius: 6px; padding: 4px 10px;
    font-size: 0.74rem; font-weight: 600; color: #475569;
    cursor: pointer; transition: all 0.15s;
}
.cat-act-btn:hover { background: #eef2ff; border-color: #c4b5fd; color: #6366f1; }
.cat-act-del:hover { background: #fee2e2; border-color: #fca5a5; color: #dc2626; }
</style>
