<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    methods: Array,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
    code: '',
    label: '',
    is_active: true,
    sort_order: 0,
});

function openCreate() {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.is_active = true;
    form.sort_order = (props.methods?.length ?? 0) + 1;
    showModal.value = true;
}

function openEdit(m) {
    isEditing.value = true;
    editingId.value = m.id;
    form.code = m.code;
    form.label = m.label;
    form.is_active = !!m.is_active;
    form.sort_order = m.sort_order ?? 0;
    showModal.value = true;
}

function save() {
    if (isEditing.value) {
        form.put(route('school.finance.payment-methods.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; },
        });
    } else {
        form.post(route('school.finance.payment-methods.store'), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    }
}

function toggleActive(m) {
    router.patch(route('school.finance.payment-methods.toggle', m.id), {}, {
        preserveScroll: true,
    });
}

function deleteMethod(m) {
    if (!confirm(`Delete "${m.label}"? Existing transactions tagged with this method will keep the code on record but it won't appear in dropdowns anymore.`)) return;
    router.delete(route('school.finance.payment-methods.destroy', m.id), {
        preserveScroll: true,
    });
}

function autoCode() {
    if (!isEditing.value && form.label && !form.code) {
        form.code = form.label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    }
}
</script>

<template>
    <SchoolLayout>
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Payment Methods</h1>
                <p class="page-header-sub">
                    Manage the list of payment modes shown on every transaction screen
                    (Fee Collection, Expenses, Payroll, Hostel, Transport, Stationary).
                </p>
            </div>
            <Button @click="openCreate">+ New Payment Method</Button>
        </div>

        <div class="card">
            <Table :empty="methods.length === 0" empty-text="No payment methods yet. Click '+ New Payment Method' to add one.">
                <thead>
                    <tr>
                        <th style="width:60px;">#</th>
                        <th>Label</th>
                        <th>Code</th>
                        <th style="width:120px;">Status</th>
                        <th style="width:160px;" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="m in methods" :key="m.id" :class="{ 'pm-row-inactive': !m.is_active }">
                        <td class="text-muted mono">{{ m.sort_order }}</td>
                        <td class="font-medium">{{ m.label }}</td>
                        <td><span class="pm-code">{{ m.code }}</span></td>
                        <td>
                            <button @click="toggleActive(m)" class="pm-status" :class="m.is_active ? 'pm-status--active' : 'pm-status--inactive'">
                                {{ m.is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </td>
                        <td class="text-right">
                            <Button variant="secondary" size="xs" @click="openEdit(m)" class="mr-1">Edit</Button>
                            <Button variant="danger" size="xs" @click="deleteMethod(m)">Delete</Button>
                        </td>
                    </tr>
                </tbody>
            </Table>
        </div>

        <!-- Add / Edit Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
                <div class="modal-box">
                    <div class="modal-header">
                        <h3>{{ isEditing ? 'Edit Payment Method' : 'New Payment Method' }}</h3>
                        <button class="modal-close" @click="showModal = false">×</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Display Label <span class="req">*</span></label>
                            <input
                                v-model="form.label"
                                type="text"
                                class="form-input"
                                placeholder="e.g. Paytm"
                                @blur="autoCode"
                            />
                            <p v-if="form.errors.label" class="form-error">{{ form.errors.label }}</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Code <span class="req">*</span></label>
                            <input
                                v-model="form.code"
                                type="text"
                                class="form-input"
                                :disabled="isEditing"
                                placeholder="e.g. paytm"
                            />
                            <p class="form-hint">
                                Lowercase, no spaces (letters/digits/underscore only). Stored on every transaction record.
                                {{ isEditing ? 'Cannot be changed once set.' : '' }}
                            </p>
                            <p v-if="form.errors.code" class="form-error">{{ form.errors.code }}</p>
                        </div>

                        <div class="form-group" style="flex-direction:row; align-items:center; gap:10px;">
                            <input type="checkbox" v-model="form.is_active" id="pm_active_chk" />
                            <label for="pm_active_chk" class="form-label" style="margin:0; cursor:pointer;">
                                Active (visible in transaction dropdowns)
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Sort Order</label>
                            <input v-model.number="form.sort_order" type="number" min="0" class="form-input" />
                            <p class="form-hint">Lower numbers appear first in dropdowns.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <Button variant="secondary" @click="showModal = false">Cancel</Button>
                        <Button @click="save" :loading="form.processing">
                            {{ isEditing ? 'Update' : 'Create' }}
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>
    </SchoolLayout>
</template>

<style scoped>
.text-right  { text-align: right; }
.text-muted  { color: #94a3b8; font-size: 0.8rem; }
.mono        { font-family: 'Courier New', monospace; font-size: 0.82rem; }
.font-medium { font-weight: 600; color: #1e293b; }

.pm-code {
    background: #f1f5f9; color: #475569;
    padding: 2px 8px; border-radius: 5px;
    font-size: 0.75rem; font-family: monospace;
}
.pm-row-inactive td { opacity: 0.55; }

.pm-status {
    font-size: 0.72rem; font-weight: 600;
    padding: 3px 10px; border-radius: 20px;
    border: none; cursor: pointer; transition: opacity 0.15s;
}
.pm-status:hover { opacity: 0.8; }
.pm-status--active   { background: #d1fae5; color: #065f46; }
.pm-status--inactive { background: #fee2e2; color: #991b1b; }

/* Modal */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.4);
    display: flex; align-items: center; justify-content: center;
    z-index: 9000; padding: 20px;
}
.modal-box {
    background: #fff; border-radius: 16px;
    width: 100%; max-width: 480px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    overflow: hidden; max-height: 90vh; overflow-y: auto;
}
.modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 22px; border-bottom: 1px solid #f1f5f9;
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
    border-top: 1px solid #f1f5f9; background: #f8fafc;
}
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 0.8rem; font-weight: 600; color: #374151; }
.req { color: #ef4444; }
.form-input {
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    padding: 8px 12px; font-size: 0.85rem; outline: none;
    font-family: inherit; color: #1e293b;
    transition: border-color 0.15s; width: 100%;
}
.form-input:focus { border-color: #6366f1; }
.form-input:disabled { background: #f8fafc; color: #94a3b8; }
.form-hint  { font-size: 0.72rem; color: #94a3b8; }
.form-error { font-size: 0.75rem; color: #dc2626; }
</style>
