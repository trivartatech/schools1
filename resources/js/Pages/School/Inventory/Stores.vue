<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';

defineProps({
    stores:    Array,
    suppliers: Array,
});

const showModal    = ref(false);
const editing      = ref(null);
const deleteTarget = ref(null);

const form       = useForm({ name: '', location: '', description: '' });
const deleteForm = useForm({});

function openAdd() {
    editing.value = null;
    form.reset();
    showModal.value = true;
}
function openEdit(s) {
    editing.value    = s;
    form.name        = s.name        || '';
    form.location    = s.location    || '';
    form.description = s.description || '';
    showModal.value  = true;
}
function submitForm() {
    const opts = { preserveScroll: true, onSuccess: () => { showModal.value = false; } };
    editing.value
        ? form.put(`/school/inventory-stores/${editing.value.id}`, opts)
        : form.post('/school/inventory-stores', opts);
}
function doDelete() {
    deleteForm.delete(`/school/inventory-stores/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { deleteTarget.value = null; },
    });
}
</script>

<template>
    <SchoolLayout title="Item Stores">

        <PageHeader
            title="Item Stores"
            subtitle="Manage storerooms and track consumable stock levels."
            :breadcrumbs="[
                { label: 'Inventory', href: '/school/inventory' },
                { label: 'Stores' },
            ]"
        >
            <template #actions>
                <Button as="a" variant="secondary" href="/school/inventory">
                    <template #icon>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 10V7"/></svg>
                    </template>
                    Assets
                </Button>
                <Button as="a" variant="secondary" href="/school/inventory-suppliers">
                    <template #icon>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </template>
                    Suppliers
                </Button>
                <Button @click="openAdd">
                    <template #icon>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </template>
                    Add Store
                </Button>
            </template>
        </PageHeader>

        <!-- Flash -->
        <div v-if="$page.props.flash?.success" class="flash-success">{{ $page.props.flash.success }}</div>
        <div v-if="$page.props.errors?.store" class="flash-error">{{ $page.props.errors.store }}</div>

        <!-- Empty state -->
        <div v-if="!stores.length" class="card" style="padding:8px;">
            <EmptyState
                title="No stores yet"
                description="Create your first storeroom to start tracking stock."
                action-label="Create First Store"
                @action="openAdd"
            />
        </div>

        <!-- Store cards grid -->
        <div v-else class="stores-grid">
            <div v-for="s in stores" :key="s.id" class="store-card">
                <div class="store-card-top">
                    <div class="store-icon-wrap">
                        <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7h18M3 12h18M3 17h18"/></svg>
                    </div>
                    <div class="store-info">
                        <h3 class="store-name">{{ s.name }}</h3>
                        <div v-if="s.location" class="store-location">
                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ s.location }}
                        </div>
                    </div>
                </div>
                <div v-if="s.description" class="store-desc">{{ s.description }}</div>
                <div class="store-footer">
                    <span class="count-pill count-blue">
                        {{ s.items_count }} item type{{ s.items_count !== 1 ? 's' : '' }}
                    </span>
                    <div style="display:flex;gap:6px;margin-left:auto;">
                        <Button as="a" :href="`/school/inventory-stores/${s.id}`" size="sm">
                            Manage Items
                        </Button>
                        <button class="act-btn act-amber" @click="openEdit(s)">Edit</button>
                        <button class="act-btn act-red"
                            :disabled="s.items_count > 0"
                            :title="s.items_count > 0 ? 'Store has items — remove them first' : 'Delete'"
                            @click="deleteTarget = s">Del</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add / Edit Modal -->
        <Modal
            v-model:open="showModal"
            :title="editing ? 'Edit Store' : 'Create Store'"
            size="md"
        >
            <form id="store-form" @submit.prevent="submitForm">
                <div class="modal-body-inner">
                    <div class="field full">
                        <label class="field-label">Store Name <span class="req">*</span></label>
                        <input v-model="form.name" class="field-input" required placeholder="e.g. Science Lab Store, Stationery Room" />
                        <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                    </div>
                    <div class="field full">
                        <label class="field-label">Location / Room</label>
                        <input v-model="form.location" class="field-input" placeholder="e.g. Block A, Room 12" />
                    </div>
                    <div class="field full">
                        <label class="field-label">Description</label>
                        <textarea v-model="form.description" class="field-input" rows="2" placeholder="What kind of items are stored here?"></textarea>
                    </div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" @click="showModal = false">Cancel</Button>
                <Button type="submit" form="store-form" :loading="form.processing">
                    {{ editing ? 'Update Store' : 'Create Store' }}
                </Button>
            </template>
        </Modal>

        <!-- Delete confirm -->
        <Modal
            :open="!!deleteTarget"
            title="Delete Store"
            size="sm"
            @update:open="(v) => { if (!v) deleteTarget = null; }"
        >
            <div v-if="deleteTarget" class="dispose-warning">
                Delete <strong>{{ deleteTarget.name }}</strong>? All items and transaction history will be permanently removed.
            </div>
            <template #footer>
                <Button variant="secondary" @click="deleteTarget = null">Cancel</Button>
                <Button variant="danger" :loading="deleteForm.processing" @click="doDelete">
                    Delete Store
                </Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
.flash-success { background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;border-radius:10px;padding:10px 16px;font-size:.85rem;margin-bottom:16px; }
.flash-error   { background:#fef2f2;border:1px solid #fecaca;color:#dc2626;border-radius:10px;padding:10px 16px;font-size:.85rem;margin-bottom:16px; }

.card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05); }

.stores-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px; }

.store-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;display:flex;flex-direction:column;gap:12px;box-shadow:0 1px 3px rgba(0,0,0,.05);transition:box-shadow .15s; }
.store-card:hover { box-shadow:0 4px 12px rgba(0,0,0,.08); }

.store-card-top { display:flex;gap:12px;align-items:flex-start; }
.store-icon-wrap { width:42px;height:42px;border-radius:10px;background:#eff6ff;color:#3b82f6;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.store-name { font-size:.95rem;font-weight:700;color:#0f172a;margin:0 0 3px; }
.store-location { display:flex;align-items:center;gap:4px;font-size:.75rem;color:#64748b; }
.store-desc { font-size:.8rem;color:#64748b;line-height:1.5;padding:8px 10px;background:#f8fafc;border-radius:6px; }

.store-footer { display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding-top:4px;border-top:1px solid #f1f5f9;margin-top:4px; }

.count-pill   { display:inline-block;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:20px; }
.count-blue   { background:#dbeafe;color:#2563eb; }

.act-btn { font-size:.72rem;font-weight:600;padding:4px 10px;border-radius:6px;border:none;cursor:pointer;transition:opacity .15s;white-space:nowrap; }
.act-btn:hover { opacity:.8; }
.act-btn:disabled { opacity:.4;cursor:not-allowed; }
.act-amber { background:#fef3c7;color:#d97706; }
.act-red   { background:#fee2e2;color:#dc2626; }

/* Form fields inside <Modal> — Tailwind preflight strips browser defaults
   from <input>/<select>, so explicit styles are needed. */
.modal-body-inner { display:flex;flex-direction:column;gap:14px; }
.field.full  { grid-column:span 2; }
.field-label { display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:5px; }
.req         { color:#ef4444; }
.field-input { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.875rem;color:#1e293b;background:#fff;outline:none;transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
.field-input:focus { border-color:#3b82f6;box-shadow:0 0 0 3px #3b82f620; }
textarea.field-input { resize:vertical; }
.field-error { font-size:.75rem;color:#ef4444;margin-top:4px; }
.dispose-warning { background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:10px 14px;font-size:.85rem;color:#92400e;line-height:1.6; }
</style>
