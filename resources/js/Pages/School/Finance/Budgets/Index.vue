<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import ExportDropdown from '@/Components/ExportDropdown.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useDelete } from '@/Composables/useDelete';

const { canDo } = usePermissions();
const { del } = useDelete();

const props = defineProps({
    budgets    : { type: Array, required: true },
    categories : { type: Array, required: true },
    totals     : { type: Object, required: true },
});

// ── Modal state ───────────────────────────────────────────────
const showModal  = ref(false);
const editTarget = ref(null);

const form = useForm({
    name                : '',
    expense_category_id : '',
    amount              : '',
    notes               : '',
});

function openCreate() {
    editTarget.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(b) {
    editTarget.value = b;
    form.name                = b.name;
    form.expense_category_id = b.expense_category_id ?? '';
    form.amount              = b.amount;
    form.notes               = b.notes ?? '';
    form.clearErrors();
    showModal.value = true;
}

function submit() {
    if (editTarget.value) {
        form.put(route('school.finance.budgets.update', editTarget.value.id), {
            onSuccess: () => (showModal.value = false),
        });
    } else {
        form.post(route('school.finance.budgets.store'), {
            onSuccess: () => (showModal.value = false),
        });
    }
}

function deleteBudget(id) {
    del(route('school.finance.budgets.destroy', id), 'Delete this budget? This cannot be undone.');
}

const fmt    = (n) => new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2 }).format(n);
const fmtCur = (n) => '₹' + fmt(n);

function progressColor(pct) {
    if (pct >= 90) return '#dc2626';
    if (pct >= 70) return '#d97706';
    return '#059669';
}

const overallPct = computed(() =>
    props.totals.budget > 0 ? Math.round(props.totals.spent / props.totals.budget * 100) : 0
);

const statCards = computed(() => [
    { label: 'Total Budget',    value: fmtCur(props.totals.budget),    color: 'accent' },
    { label: 'Total Spent',     value: fmtCur(props.totals.spent),     color: 'danger' },
    { label: 'Remaining',       value: fmtCur(props.totals.remaining), color: 'success' },
    { label: 'Overall %',       value: `${overallPct.value}%`,         color: overallPct.value >= 90 ? 'danger' : overallPct.value >= 70 ? 'warning' : 'success' },
]);
</script>

<template>
    <SchoolLayout>
        <PageHeader
            title="Budget Management"
            subtitle="Track spending against allocated budgets for this academic year"
        >
            <template #actions>
                <ExportDropdown base-url="/school/export/budgets" />
                <Button v-if="canDo('create', 'finance')" @click="openCreate">+ New Budget</Button>
            </template>
        </PageHeader>

        <!-- Summary banner -->
        <StatsRow :cols="4" :stats="statCards" />

        <!-- Budget cards grid -->
        <div class="budget-grid" v-if="budgets.length > 0">
            <div v-for="b in budgets" :key="b.id" class="budget-card card">
                <div class="bc-header">
                    <div>
                        <div class="bc-name">{{ b.name }}</div>
                        <div class="bc-category">{{ b.expense_category?.name ?? 'All Expenses' }}</div>
                    </div>
                    <div v-if="canDo('edit', 'finance') || canDo('delete', 'finance')" class="bc-actions">
                        <Button v-if="canDo('edit', 'finance')" variant="icon" size="xs" @click="openEdit(b)" title="Edit">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </Button>
                        <Button v-if="canDo('delete', 'finance')" variant="icon" size="xs" class="act-del" @click="deleteBudget(b.id)" title="Delete">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                            </svg>
                        </Button>
                    </div>
                </div>

                <!-- Progress bar -->
                <div class="progress-wrap">
                    <div class="progress-bar">
                        <div class="progress-fill" :style="{ width: b.percent + '%', background: progressColor(b.percent) }"></div>
                    </div>
                    <span class="progress-pct" :style="{ color: progressColor(b.percent) }">{{ b.percent }}%</span>
                </div>

                <div class="bc-amounts">
                    <div class="bc-amt-item">
                        <span class="bc-amt-label">Budget</span>
                        <span class="bc-amt-value text-indigo">{{ fmtCur(b.amount) }}</span>
                    </div>
                    <div class="bc-amt-item">
                        <span class="bc-amt-label">Spent</span>
                        <span class="bc-amt-value" :style="{ color: progressColor(b.percent) }">{{ fmtCur(b.spent) }}</span>
                    </div>
                    <div class="bc-amt-item">
                        <span class="bc-amt-label">Remaining</span>
                        <span class="bc-amt-value text-green">{{ fmtCur(b.remaining) }}</span>
                    </div>
                </div>

                <div v-if="b.notes" class="bc-notes">{{ b.notes }}</div>
            </div>
        </div>

        <div v-else class="card">
            <EmptyState
                title="No budgets yet"
                description="Create your first budget to start tracking spending against allocated amounts."
                :action-label="canDo('create', 'finance') ? '+ New Budget' : ''"
                @action="openCreate"
            />
        </div>

        <!-- Create/Edit Modal -->
        <Modal v-model:open="showModal" :title="editTarget ? 'Edit Budget' : 'New Budget'" size="md">
            <form @submit.prevent="submit" id="budget-form">
                <div class="form-group">
                    <label class="form-label">Budget Name <span class="req">*</span></label>
                    <input v-model="form.name" type="text" class="form-input" placeholder="e.g. Annual Salary Budget" />
                    <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Expense Category</label>
                    <select v-model="form.expense_category_id" class="form-input">
                        <option value="">All Expenses (no specific category)</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <p v-if="form.errors.expense_category_id" class="form-error">{{ form.errors.expense_category_id }}</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Budget Amount (₹) <span class="req">*</span></label>
                    <input v-model="form.amount" type="number" min="1" step="0.01" class="form-input" placeholder="0.00" />
                    <p v-if="form.errors.amount" class="form-error">{{ form.errors.amount }}</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea v-model="form.notes" class="form-input" rows="2" placeholder="Optional notes…"></textarea>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" @click="showModal = false">Cancel</Button>
                <Button type="submit" form="budget-form" :loading="form.processing">
                    {{ editTarget ? 'Update' : 'Create' }}
                </Button>
            </template>
        </Modal>
    </SchoolLayout>
</template>

<style scoped>
.text-indigo { color:#6366f1; }
.text-green  { color:#059669; }

.budget-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px; }

.budget-card { padding:20px; }
.bc-header { display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px; }
.bc-name     { font-size:0.92rem;font-weight:700;color:#1e293b; }
.bc-category { font-size:0.74rem;color:#94a3b8;margin-top:3px; }
.bc-actions  { display:flex;gap:6px; }

.progress-wrap { display:flex;align-items:center;gap:8px;margin-bottom:14px; }
.progress-bar  { flex:1;height:8px;background:#f1f5f9;border-radius:10px;overflow:hidden; }
.progress-fill { height:100%;border-radius:10px;transition:width 0.3s; }
.progress-pct  { font-size:0.78rem;font-weight:800;min-width:32px;text-align:right; }

.bc-amounts { display:flex;gap:0; }
.bc-amt-item { flex:1;display:flex;flex-direction:column;gap:2px;padding:0 4px; }
.bc-amt-item:first-child { padding-left:0; }
.bc-amt-label { font-size:0.68rem;font-weight:600;text-transform:uppercase;color:#94a3b8; }
.bc-amt-value { font-size:0.85rem;font-weight:700;font-family:'Courier New',monospace; }

.bc-notes { font-size:0.76rem;color:#94a3b8;margin-top:10px;font-style:italic; }

.act-del:hover  { background:#fee2e2;border-color:#fca5a5;color:#dc2626; }

/* Modal form fields — Tailwind preflight workaround. */
.form-group { display:flex;flex-direction:column;gap:5px;margin-bottom:14px; }
.form-group:last-child { margin-bottom:0; }
.form-label { font-size:0.8rem;font-weight:600;color:#374151; }
.req { color:#ef4444; }
.form-input {
    border:1.5px solid #e2e8f0;border-radius:8px;padding:8px 12px;
    font-size:0.85rem;outline:none;font-family:inherit;color:#1e293b;
    transition:border-color 0.15s;
}
.form-input:focus { border-color:#6366f1; }
.form-error { font-size:0.75rem;color:#dc2626; }
</style>
