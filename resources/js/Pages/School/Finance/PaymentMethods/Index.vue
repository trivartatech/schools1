<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Table from '@/Components/ui/Table.vue';
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useConfirm } from '@/Composables/useConfirm';

const confirm = useConfirm();

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

async function deleteMethod(m) {
    const ok = await confirm({
        title: 'Delete payment method?',
        message: `"${m.label}" will no longer appear in transaction dropdowns. Existing transactions tagged with this method will keep the code on record.`,
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
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
        <PageHeader
            title="Payment Methods"
            subtitle="Manage the list of payment modes shown on every transaction screen (Fee Collection, Expenses, Payroll, Hostel, Transport, Stationary)."
        >
            <template #actions>
                <Button @click="openCreate">+ New Payment Method</Button>
            </template>
        </PageHeader>

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
        <Modal v-model:open="showModal" :title="isEditing ? 'Edit Payment Method' : 'New Payment Method'" size="md">
            <form @submit.prevent="save" id="pm-form">
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
            </form>
            <template #footer>
                <Button variant="secondary" @click="showModal = false">Cancel</Button>
                <Button type="submit" form="pm-form" :loading="form.processing">
                    {{ isEditing ? 'Update' : 'Create' }}
                </Button>
            </template>
        </Modal>
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

/* Modal form fields — Tailwind preflight workaround. Scoped here so styles
   only affect this page's <Modal> contents (data-v travels with teleport). */
.form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
.form-group:last-child { margin-bottom: 0; }
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
