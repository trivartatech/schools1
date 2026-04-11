<script setup>
import Button from '@/Components/ui/Button.vue';
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
    del(route('school.finance.budgets.destroy', id), 'Delete this budget?');
}

const fmt    = (n) => new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2 }).format(n);
const fmtCur = (n) => '₹' + fmt(n);

function progressColor(pct) {
    if (pct >= 90) return '#dc2626';
    if (pct >= 70) return '#d97706';
    return '#059669';
}
</script>

<template>
    <SchoolLayout>
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Budget Management</h1>
                <p class="page-header-sub">Track spending against allocated budgets for this academic year</p>
            </div>
            <div style="display:flex;gap:8px;">
                <ExportDropdown base-url="/school/export/budgets" />
                <Button v-if="canDo('create', 'finance')" @click="openCreate">+ New Budget</Button>
            </div>
        </div>

        <!-- Summary banner -->
        <div class="summary-grid">
            <div class="sum-card">
                <div class="sum-label">Total Budget</div>
                <div class="sum-value text-indigo">{{ fmtCur(totals.budget) }}</div>
            </div>
            <div class="sum-card">
                <div class="sum-label">Total Spent</div>
                <div class="sum-value text-red">{{ fmtCur(totals.spent) }}</div>
            </div>
            <div class="sum-card">
                <div class="sum-label">Remaining</div>
                <div class="sum-value text-green">{{ fmtCur(totals.remaining) }}</div>
            </div>
            <div class="sum-card">
                <div class="sum-label">Overall %</div>
                <div class="sum-value" :style="{ color: progressColor(totals.budget > 0 ? Math.round(totals.spent/totals.budget*100) : 0) }">
                    {{ totals.budget > 0 ? Math.round(totals.spent / totals.budget * 100) : 0 }}%
                </div>
            </div>
        </div>

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

        <div v-else class="card empty-state">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-linecap="round">
                <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
            </svg>
            <p>No budgets created yet. <button v-if="canDo('create', 'finance')" class="link-btn" @click="openCreate">Create your first budget</button></p>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
            <div class="modal-box">
                <div class="modal-header">
                    <h3>{{ editTarget ? 'Edit Budget' : 'New Budget' }}</h3>
                    <button class="modal-close" @click="showModal = false">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
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
                </div>
                <div class="modal-footer">
                    <Button variant="secondary" @click="showModal = false">Cancel</Button>
                    <Button @click="submit" :loading="form.processing">
                        {{ (editTarget ? 'Update' : 'Create') }}
                    </Button>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.summary-grid {
    display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;
}
.sum-card {
    background:#fff;border:1.5px solid #e2e8f0;border-radius:12px;
    padding:16px 20px;
}
.sum-label { font-size:0.72rem;font-weight:700;text-transform:uppercase;color:#94a3b8;letter-spacing:0.04em; }
.sum-value { font-size:1.4rem;font-weight:800;margin-top:4px;font-family:'Courier New',monospace; }
.text-indigo { color:#6366f1; }
.text-red    { color:#dc2626; }
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

.empty-state { display:flex;flex-direction:column;align-items:center;gap:10px;padding:50px;color:#94a3b8; }
.link-btn { background:none;border:none;color:#6366f1;font-weight:600;cursor:pointer;text-decoration:underline; }

/* Modal */
.modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;display:flex;align-items:center;justify-content:center; }
.modal-box { background:#fff;border-radius:16px;width:480px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,0.2); }
.modal-header { display:flex;justify-content:space-between;align-items:center;padding:18px 22px;border-bottom:1px solid #f1f5f9; }
.modal-header h3 { font-size:1rem;font-weight:700;color:#1e293b; }
.modal-close { background:none;border:none;cursor:pointer;color:#94a3b8;padding:4px; }
.modal-close:hover { color:#374151; }
.modal-body { padding:20px 22px;display:flex;flex-direction:column;gap:14px; }
.modal-footer { padding:16px 22px;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;gap:10px; }

.form-group { display:flex;flex-direction:column;gap:5px; }
.form-label { font-size:0.8rem;font-weight:600;color:#374151; }
.req { color:#ef4444; }
.form-input {
    border:1.5px solid #e2e8f0;border-radius:8px;padding:8px 12px;
    font-size:0.85rem;outline:none;font-family:inherit;color:#1e293b;
    transition:border-color 0.15s;
}
.form-input:focus { border-color:#6366f1; }
.form-error { font-size:0.75rem;color:#dc2626; }

@media (max-width:768px) { .summary-grid { grid-template-columns:repeat(2,1fr); } }
</style>
