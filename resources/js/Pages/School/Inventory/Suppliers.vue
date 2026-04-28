<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Table from '@/Components/ui/Table.vue';
import SortableTh from '@/Components/ui/SortableTh.vue';
import { useTableSort } from '@/Composables/useTableSort';

const props = defineProps({
    suppliers: Array,
});

const pageErrors   = usePage().props.errors ?? {};
const showModal    = ref(false);
const editing      = ref(null);
const deleteTarget = ref(null);

const blankForm = {
    name: '', contact_person: '', phone: '', email: '',
    gstin: '', address: '', city: '', state: '', website: '', notes: '',
};
const form       = useForm({ ...blankForm });
const deleteForm = useForm({});

function openAdd() {
    editing.value = null;
    form.reset();
    showModal.value = true;
}
function openEdit(s) {
    editing.value           = s;
    form.name           = s.name           || '';
    form.contact_person = s.contact_person || '';
    form.phone          = s.phone          || '';
    form.email          = s.email          || '';
    form.gstin          = s.gstin          || '';
    form.address        = s.address        || '';
    form.city           = s.city           || '';
    form.state          = s.state          || '';
    form.website        = s.website        || '';
    form.notes          = s.notes          || '';
    showModal.value = true;
}
function submitForm() {
    const opts = { preserveScroll: true, onSuccess: () => { showModal.value = false; } };
    editing.value
        ? form.put(`/school/inventory-suppliers/${editing.value.id}`, opts)
        : form.post('/school/inventory-suppliers', opts);
}
function doDelete() {
    deleteForm.delete(`/school/inventory-suppliers/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { deleteTarget.value = null; },
    });
}

const linkedCount = () => (props.suppliers ?? []).filter(s => s.assets_count > 0).length;

const { sortKey, sortDir, toggleSort, sortRows } = useTableSort('name', 'asc');
const sortedSuppliers = computed(() => sortRows(props.suppliers || [], {
    getValue: (row, key) => {
        if (key === 'location') return [row.city, row.state].filter(Boolean).join(', ');
        return row[key];
    },
}));
</script>

<template>
    <SchoolLayout title="Suppliers">

        <PageHeader
            title="Item Suppliers"
            subtitle="Manage your vendors and procurement contacts."
            :breadcrumbs="[
                { label: 'Inventory', href: '/school/inventory' },
                { label: 'Suppliers' },
            ]"
        >
            <template #actions>
                <Button as="a" variant="secondary" href="/school/inventory">
                    <template #icon>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 10V7"/></svg>
                    </template>
                    Assets
                </Button>
                <Button as="a" variant="secondary" href="/school/inventory-stores">
                    <template #icon>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"/></svg>
                    </template>
                    Stores
                </Button>
                <Button @click="openAdd">
                    <template #icon>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </template>
                    Add Supplier
                </Button>
            </template>
        </PageHeader>

        <!-- Flash -->
        <div v-if="$page.props.flash?.success" class="flash-success">{{ $page.props.flash.success }}</div>
        <div v-if="pageErrors.supplier" class="flash-error">{{ pageErrors.supplier }}</div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card stat-blue">
                <div class="stat-icon"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
                <div>
                    <div class="stat-label">Total Suppliers</div>
                    <div class="stat-value" style="color:#3b82f6;">{{ suppliers.length }}</div>
                    <div class="stat-sub">registered</div>
                </div>
            </div>
            <div class="stat-card stat-green">
                <div class="stat-icon"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                <div>
                    <div class="stat-label">Linked to Assets</div>
                    <div class="stat-value" style="color:#10b981;">{{ linkedCount() }}</div>
                    <div class="stat-sub">active suppliers</div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card" style="overflow:hidden;">
            <div v-if="!suppliers.length" style="padding:8px;">
                <EmptyState
                    title="No suppliers yet"
                    description="Add your first supplier to get started."
                    action-label="Add Supplier"
                    @action="openAdd"
                />
            </div>
            <Table v-else :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
                <thead>
                    <tr>
                        <SortableTh sort-key="name">Supplier Name</SortableTh>
                        <SortableTh sort-key="contact_person">Contact Person</SortableTh>
                        <SortableTh sort-key="phone">Phone / Email</SortableTh>
                        <SortableTh sort-key="location">City / State</SortableTh>
                        <SortableTh sort-key="gstin">GSTIN</SortableTh>
                        <SortableTh sort-key="assets_count" align="center">Assets</SortableTh>
                        <SortableTh sort-key="store_items_count" align="center">Items</SortableTh>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in sortedSuppliers" :key="s.id">
                        <td>
                            <div style="font-weight:600;font-size:.875rem;color:#1e293b;">{{ s.name }}</div>
                            <div v-if="s.website" style="font-size:.72rem;color:#94a3b8;">{{ s.website }}</div>
                        </td>
                        <td>{{ s.contact_person || '—' }}</td>
                        <td>
                            <div v-if="s.phone" style="font-size:.82rem;color:#374151;">{{ s.phone }}</div>
                            <div v-if="s.email" style="font-size:.72rem;color:#64748b;">{{ s.email }}</div>
                            <span v-if="!s.phone && !s.email" style="color:#cbd5e1;font-size:.8rem;">—</span>
                        </td>
                        <td>
                            {{ [s.city, s.state].filter(Boolean).join(', ') || '—' }}
                        </td>
                        <td>
                            <span v-if="s.gstin" style="font-family:monospace;font-size:.78rem;color:#374151;background:#f1f5f9;padding:2px 7px;border-radius:4px;">{{ s.gstin }}</span>
                            <span v-else style="color:#cbd5e1;font-size:.8rem;">—</span>
                        </td>
                        <td style="text-align:center;">
                            <span class="count-pill count-blue">{{ s.assets_count }}</span>
                        </td>
                        <td style="text-align:center;">
                            <span class="count-pill count-purple">{{ s.store_items_count }}</span>
                        </td>
                        <td>
                            <div style="display:flex;gap:5px;justify-content:flex-end;">
                                <button class="act-btn act-amber" @click="openEdit(s)">Edit</button>
                                <button class="act-btn act-red" :disabled="s.assets_count > 0"
                                    :title="s.assets_count > 0 ? 'Linked to assets — cannot delete' : 'Delete'"
                                    @click="deleteTarget = s">Delete</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </Table>
        </div>

        <!-- Add / Edit Modal -->
        <Modal
            v-model:open="showModal"
            :title="editing ? 'Edit Supplier' : 'Add Supplier'"
            size="lg"
        >
            <form id="supplier-form" @submit.prevent="submitForm">
                <div class="modal-body-inner">
                    <div class="field full">
                        <label class="field-label">Supplier Name <span class="req">*</span></label>
                        <input v-model="form.name" class="field-input" required placeholder="e.g. Tech Solutions Pvt Ltd" />
                        <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label class="field-label">Contact Person</label>
                            <input v-model="form.contact_person" class="field-input" placeholder="Name of your point of contact" />
                        </div>
                        <div class="field">
                            <label class="field-label">Phone</label>
                            <input v-model="form.phone" class="field-input" maxlength="20" placeholder="+91 9876543210" />
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label class="field-label">Email</label>
                            <input v-model="form.email" type="email" class="field-input" placeholder="supplier@example.com" />
                            <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
                        </div>
                        <div class="field">
                            <label class="field-label">GSTIN</label>
                            <input v-model="form.gstin" class="field-input" maxlength="20" placeholder="29ABCDE1234F1Z5" />
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label class="field-label">City</label>
                            <input v-model="form.city" class="field-input" />
                        </div>
                        <div class="field">
                            <label class="field-label">State</label>
                            <input v-model="form.state" class="field-input" />
                        </div>
                    </div>
                    <div class="field full">
                        <label class="field-label">Website</label>
                        <input v-model="form.website" class="field-input" placeholder="https://" />
                        <p v-if="form.errors.website" class="field-error">{{ form.errors.website }}</p>
                    </div>
                    <div class="field full">
                        <label class="field-label">Address</label>
                        <textarea v-model="form.address" class="field-input" rows="2" placeholder="Street, area…"></textarea>
                    </div>
                    <div class="field full">
                        <label class="field-label">Notes</label>
                        <textarea v-model="form.notes" class="field-input" rows="2"></textarea>
                    </div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" @click="showModal = false">Cancel</Button>
                <Button type="submit" form="supplier-form" :loading="form.processing">
                    {{ editing ? 'Update Supplier' : 'Add Supplier' }}
                </Button>
            </template>
        </Modal>

        <!-- Delete confirm -->
        <Modal
            :open="!!deleteTarget"
            title="Delete Supplier"
            size="sm"
            @update:open="(v) => { if (!v) deleteTarget = null; }"
        >
            <div v-if="deleteTarget" class="dispose-warning">
                This will permanently remove <strong>{{ deleteTarget.name }}</strong> from your supplier list. This cannot be undone.
            </div>
            <template #footer>
                <Button variant="secondary" @click="deleteTarget = null">Cancel</Button>
                <Button variant="danger" :loading="deleteForm.processing" @click="doDelete">
                    Delete Supplier
                </Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
.flash-success { background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;border-radius:10px;padding:10px 16px;font-size:.85rem;margin-bottom:16px; }
.flash-error   { background:#fef2f2;border:1px solid #fecaca;color:#dc2626;border-radius:10px;padding:10px 16px;font-size:.85rem;margin-bottom:16px; }

.stats-row { display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px; }
@media (max-width:900px) { .stats-row { grid-template-columns:repeat(2,1fr); } }
.stat-card { display:flex;align-items:flex-start;gap:14px;background:#fff;border-radius:12px;padding:18px 20px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,.05); }
.stat-icon  { width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.stat-green .stat-icon { background:#dcfce7;color:#16a34a; }
.stat-blue  .stat-icon { background:#dbeafe;color:#2563eb; }
.stat-label { font-size:.7rem;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em; }
.stat-value { font-size:1.75rem;font-weight:800;line-height:1.1;margin-top:2px; }
.stat-sub   { font-size:.72rem;color:#94a3b8;margin-top:2px; }

.card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05); }

.act-btn { font-size:.72rem;font-weight:600;padding:4px 10px;border-radius:6px;border:none;cursor:pointer;transition:opacity .15s;white-space:nowrap; }
.act-btn:hover { opacity:.8; }
.act-btn:disabled { opacity:.4;cursor:not-allowed; }
.act-amber { background:#fef3c7;color:#d97706; }
.act-red   { background:#fee2e2;color:#dc2626; }

.count-pill   { display:inline-block;font-size:.72rem;font-weight:700;padding:2px 9px;border-radius:20px; }
.count-blue   { background:#dbeafe;color:#2563eb; }
.count-purple { background:#ede9fe;color:#7c3aed; }

/* Form fields inside <Modal> — Tailwind preflight strips browser defaults
   from <input>/<select>, so explicit styles are needed. */
.modal-body-inner { display:flex;flex-direction:column;gap:14px; }
.field-row  { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
.field.full { grid-column:span 2; }
.field-label { display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:5px; }
.req         { color:#ef4444; }
.field-input { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.875rem;color:#1e293b;background:#fff;outline:none;transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
.field-input:focus { border-color:#3b82f6;box-shadow:0 0 0 3px #3b82f620; }
textarea.field-input { resize:vertical; }
.field-error { font-size:.75rem;color:#ef4444;margin-top:4px; }
.dispose-warning { background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:10px 14px;font-size:.85rem;color:#92400e;line-height:1.6; }
</style>
