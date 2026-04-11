<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    types: Array,
});

// ── Modal state ──────────────────────────────────────────────
const showModal  = ref(false);
const isEditing  = ref(false);
const editingId  = ref(null);

const form = useForm({
    name        : '',
    nature      : 'debit',
    description : '',
});

function openCreate() {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.nature = 'debit';
    showModal.value = true;
}

function openEdit(type) {
    isEditing.value = true;
    editingId.value = type.id;
    form.name        = type.name;
    form.nature      = type.nature;
    form.description = type.description ?? '';
    showModal.value  = true;
}

function save() {
    if (isEditing.value) {
        form.put(route('school.finance.ledger-types.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    } else {
        form.post(route('school.finance.ledger-types.store'), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    }
}

function deleteType(type) {
    if (!confirm(`Delete "${type.name}"? This cannot be undone.`)) return;
    router.delete(route('school.finance.ledger-types.destroy', type.id), { preserveScroll: true });
}

const natureLabel = (n) => n === 'debit' ? 'Debit Normal' : 'Credit Normal';
const natureBadge = (n) => n === 'debit' ? 'badge-blue' : 'badge-green';
</script>

<template>
    <SchoolLayout>
        <!-- Page header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Ledger Types</h1>
                <p class="page-header-sub">Define account categories (Asset, Liability, Income, Expense, Capital)</p>
            </div>
            <Button @click="openCreate">+ New Type</Button>
        </div>

        <!-- Cards grid -->
        <div class="lt-grid">
            <div
                v-for="t in types"
                :key="t.id"
                class="lt-card"
                :class="t.nature === 'debit' ? 'lt-card-debit' : 'lt-card-credit'"
            >
                <div class="lt-card-top">
                    <div class="lt-card-icon" :class="t.nature === 'debit' ? 'lt-icon-debit' : 'lt-icon-credit'">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                    <div class="lt-card-badges">
                        <span class="lt-badge" :class="natureBadge(t.nature)">{{ natureLabel(t.nature) }}</span>
                        <span v-if="t.is_system" class="lt-badge lt-badge-sys">System</span>
                    </div>
                </div>
                <div class="lt-card-name">{{ t.name }}</div>
                <div class="lt-card-desc">{{ t.description || '—' }}</div>
                <div class="lt-card-footer">
                    <span class="lt-ledger-count">{{ t.ledgers_count }} ledger{{ t.ledgers_count !== 1 ? 's' : '' }}</span>
                    <div class="lt-card-actions">
                        <button class="lt-act-btn" @click="openEdit(t)" title="Edit">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                        <button v-if="!t.is_system" class="lt-act-btn lt-act-del" @click="deleteType(t)" title="Delete">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                                <path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
                <div class="modal-box">
                    <div class="modal-header">
                        <h3>{{ isEditing ? 'Edit Ledger Type' : 'New Ledger Type' }}</h3>
                        <button class="modal-close" @click="showModal = false">×</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Name <span class="text-red-500">*</span></label>
                            <input v-model="form.name" type="text" class="form-input" placeholder="e.g. Asset" />
                            <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nature <span class="text-red-500">*</span></label>
                            <select v-model="form.nature" class="form-input">
                                <option value="debit">Debit Normal (Asset / Expense)</option>
                                <option value="credit">Credit Normal (Liability / Capital / Income)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea v-model="form.description" class="form-input" rows="2" placeholder="Optional description"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <Button variant="secondary" @click="showModal = false">Cancel</Button>
                        <Button @click="save" :loading="form.processing">
                            {{ (isEditing ? 'Update' : 'Create') }}
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>
    </SchoolLayout>
</template>

<style scoped>
.lt-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 18px;
    margin-top: 8px;
}
.lt-card {
    background: #fff;
    border-radius: 14px;
    padding: 20px;
    border: 1.5px solid #e2e8f0;
    box-shadow: 0 1px 6px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    gap: 8px;
    transition: box-shadow 0.2s;
}
.lt-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,0.1); }
.lt-card-debit  { border-left: 4px solid #6366f1; }
.lt-card-credit { border-left: 4px solid #10b981; }

.lt-card-top { display: flex; align-items: center; justify-content: space-between; }
.lt-card-icon {
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
}
.lt-icon-debit  { background: #ede9fe; color: #6366f1; }
.lt-icon-credit { background: #d1fae5; color: #059669; }

.lt-card-badges { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.lt-badge {
    font-size: 0.68rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
}
.badge-blue    { background: #ede9fe; color: #6366f1; }
.badge-green   { background: #d1fae5; color: #059669; }
.lt-badge-sys  { background: #fef3c7; color: #d97706; }

.lt-card-name  { font-size: 1.05rem; font-weight: 700; color: #1e293b; }
.lt-card-desc  { font-size: 0.8rem; color: #64748b; flex: 1; }
.lt-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 4px;
    padding-top: 10px;
    border-top: 1px solid #f1f5f9;
}
.lt-ledger-count { font-size: 0.75rem; color: #94a3b8; }
.lt-card-actions { display: flex; gap: 6px; }
.lt-act-btn {
    width: 28px; height: 28px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 7px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: #64748b;
    transition: all 0.15s;
}
.lt-act-btn:hover     { background: #ede9fe; border-color: #c4b5fd; color: #6366f1; }
.lt-act-del:hover     { background: #fee2e2; border-color: #fca5a5; color: #dc2626; }

/* Modal */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.4);
    display: flex; align-items: center; justify-content: center;
    z-index: 9000;
}
.modal-box {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 440px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    overflow: hidden;
}
.modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 22px;
    border-bottom: 1px solid #f1f5f9;
}
.modal-header h3 { font-weight: 700; font-size: 1rem; color: #1e293b; }
.modal-close {
    background: none; border: none; cursor: pointer;
    font-size: 1.4rem; color: #94a3b8; line-height: 1;
}
.modal-close:hover { color: #1e293b; }
.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 14px; }
.modal-footer {
    display: flex; justify-content: flex-end; gap: 10px;
    padding: 14px 22px;
    border-top: 1px solid #f1f5f9;
    background: #f8fafc;
}
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 0.8rem; font-weight: 600; color: #374151; }
.form-input {
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.85rem;
    outline: none;
    font-family: inherit;
    color: #1e293b;
    transition: border-color 0.15s;
    width: 100%;
}
.form-input:focus { border-color: #6366f1; }
.form-error { font-size: 0.75rem; color: #dc2626; }
</style>
