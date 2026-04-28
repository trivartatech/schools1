<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import { ref, reactive, computed } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useConfirm } from '@/Composables/useConfirm';
import Table from '@/Components/ui/Table.vue';

const confirm = useConfirm();

const props = defineProps({
    items:   Object, // paginator
    filters: Object,
    stats:   Object,
});

const { can } = usePermissions();

const search = ref(props.filters?.q ?? '');
const status = ref(props.filters?.status ?? '');

const showModal   = ref(false);
const editingItem = ref(null);
const saving      = ref(false);
const formErrors  = ref({});

const form = reactive({
    name: '', code: '', unit_price: 0, hsn_code: '',
    current_stock: 0, min_stock: 0, status: 'active', description: '',
});

function applyFilters() {
    router.get('/school/stationary/items',
        { q: search.value, status: status.value },
        { preserveState: true, preserveScroll: true, replace: true });
}

function clearFilters() {
    search.value = '';
    status.value = '';
    applyFilters();
}

function openModal(item = null) {
    editingItem.value = item;
    formErrors.value = {};
    if (item) {
        Object.assign(form, {
            name: item.name,
            code: item.code || '',
            unit_price: item.unit_price,
            hsn_code: item.hsn_code || '',
            current_stock: item.current_stock,
            min_stock: item.min_stock,
            status: item.status,
            description: item.description || '',
        });
    } else {
        Object.assign(form, {
            name: '', code: '', unit_price: 0, hsn_code: '',
            current_stock: 0, min_stock: 0, status: 'active', description: '',
        });
    }
    showModal.value = true;
}

function save() {
    saving.value = true;
    formErrors.value = {};
    const url    = editingItem.value ? `/school/stationary/items/${editingItem.value.id}` : '/school/stationary/items';
    const method = editingItem.value ? 'put' : 'post';
    router[method](url, { ...form }, {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false; },
        onError:   (e) => { formErrors.value = e; },
        onFinish:  () => { saving.value = false; },
    });
}

async function destroy(item) {
    const ok = await confirm({
        title: 'Delete item?',
        message: `"${item.name}" will be permanently removed. This cannot be undone.`,
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/stationary/items/${item.id}`, { preserveScroll: true });
}

function fmt(n) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 }).format(n ?? 0);
}

const statCards = computed(() => [
    { label: 'Total Items',       value: props.stats?.total ?? 0,     color: 'accent' },
    { label: 'Active',            value: props.stats?.active ?? 0,    color: 'success' },
    { label: 'Low Stock Alerts',  value: props.stats?.low_stock ?? 0, color: 'warning' },
]);
</script>

<template>
    <Head title="Stationary Items" />
    <SchoolLayout title="Stationary Items">
        <PageHeader title="Stationary Items" subtitle="Master list of items the school sells to students.">
            <template #actions>
                <Button v-if="can('create_stationary_items')" @click="openModal()">+ Add Item</Button>
            </template>
        </PageHeader>

        <!-- Stats grid -->
        <StatsRow :cols="3" :stats="statCards" />

        <!-- Filters -->
        <FilterBar :active="!!(search || status)" @clear="clearFilters">
            <div class="fb-search">
                <svg class="fb-search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <input v-model="search" @keydown.enter="applyFilters" type="text" placeholder="Search by name or code..." />
            </div>
            <select v-model="status" @change="applyFilters">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <Button variant="secondary" size="sm" @click="applyFilters">Apply</Button>
        </FilterBar>

        <!-- Table -->
        <div class="card">
            <Table :empty="!items.data.length">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Stock</th>
                        <th style="text-align:right;">Min</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items.data" :key="item.id">
                        <td>
                            <div style="font-weight: 500; color: #111827;">{{ item.name }}</div>
                            <div v-if="item.description" style="font-size: 0.75rem; color: #94a3b8;">{{ item.description }}</div>
                        </td>
                        <td><span style="font-family: monospace; font-size: 0.82rem; color: #64748b;">{{ item.code || '—' }}</span></td>
                        <td style="text-align: right;">{{ fmt(item.unit_price) }}</td>
                        <td style="text-align: right;">
                            <span :style="item.current_stock <= item.min_stock ? 'color:#b45309;font-weight:700;' : ''">
                                {{ item.current_stock }}
                            </span>
                            <span v-if="item.current_stock <= item.min_stock" title="Low stock" style="margin-left:4px;">⚠️</span>
                        </td>
                        <td style="text-align: right; color: #94a3b8; font-size: 0.82rem;">{{ item.min_stock }}</td>
                        <td>
                            <span :class="['badge', item.status === 'active' ? 'badge-green' : 'badge-gray']">{{ item.status }}</span>
                        </td>
                        <td style="text-align:right;">
                            <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.375rem;">
                                <Button v-if="can('edit_stationary_items')" size="xs" variant="secondary" @click="openModal(item)">Edit</Button>
                                <Button v-if="can('delete_stationary_items')" size="xs" variant="danger" @click="destroy(item)">×</Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <template #empty>
                    <EmptyState
                        title="No stationary items yet"
                        description="Add the first item to start tracking school stationary."
                        :action-label="can('create_stationary_items') ? '+ Add Item' : ''"
                        @action="openModal()"
                    />
                </template>
            </Table>

            <div v-if="items.last_page > 1" style="padding: 0.75rem 1rem; display: flex; gap: 0.375rem; flex-wrap: wrap; border-top: 1px solid var(--border, #e5e7eb);">
                <a v-for="link in items.links" :key="link.label"
                   :href="link.url || '#'" v-html="link.label"
                   :class="link.active ? 'pgn pgn-active' : 'pgn'"
                   :style="!link.url ? 'pointer-events:none;opacity:0.4' : ''"></a>
            </div>
        </div>

        <!-- Add / Edit Modal -->
        <Modal v-model:open="showModal" :title="editingItem ? 'Edit Item' : 'Add Item'" size="md">
            <form @submit.prevent="save" id="item-form">
                <div v-if="Object.keys(formErrors).length" style="background:#fef2f2;border:1px solid #fecaca;border-radius:0.5rem;padding:0.75rem 1rem;margin-bottom:1rem;">
                    <p v-for="(msg, key) in formErrors" :key="key" style="font-size:0.8rem;color:#dc2626;margin:0.125rem 0;">
                        {{ Array.isArray(msg) ? msg[0] : msg }}
                    </p>
                </div>

                <div class="form-field">
                    <label>Item Name *</label>
                    <input v-model="form.name" type="text" required placeholder="e.g. A4 Notebook" />
                </div>

                <div class="form-row-2">
                    <div class="form-field">
                        <label>Code</label>
                        <input v-model="form.code" type="text" placeholder="e.g. NB-A4" />
                    </div>
                    <div class="form-field">
                        <label>HSN Code</label>
                        <input v-model="form.hsn_code" type="text" />
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-field">
                        <label>Unit Price (₹) *</label>
                        <input v-model.number="form.unit_price" type="number" step="0.01" min="0" required />
                    </div>
                    <div class="form-field">
                        <label>Status *</label>
                        <select v-model="form.status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-field">
                        <label>Current Stock *</label>
                        <input v-model.number="form.current_stock" type="number" min="0" required />
                    </div>
                    <div class="form-field">
                        <label>Min Stock (alert level) *</label>
                        <input v-model.number="form.min_stock" type="number" min="0" required />
                    </div>
                </div>

                <div class="form-field">
                    <label>Description</label>
                    <textarea v-model="form.description" rows="2" placeholder="Optional details about this item"></textarea>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                <Button type="submit" form="item-form" :loading="saving">{{ editingItem ? 'Save Changes' : 'Create Item' }}</Button>
            </template>
        </Modal>
    </SchoolLayout>
</template>

<style scoped>
.badge {
    display: inline-block;
    padding: 0.25rem 0.625rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: capitalize;
}
.badge-green { background: #d1fae5; color: #059669; }
.badge-gray  { background: #f1f5f9; color: #94a3b8; }

/* Form fields — Tailwind preflight workaround */
.form-field { display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: 0.875rem; }
.form-field label { font-size: 0.78rem; font-weight: 600; color: #475569; }
.form-field input, .form-field select, .form-field textarea {
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    outline: none;
    width: 100%;
    background: #fff;
}
.form-field input:focus, .form-field select:focus, .form-field textarea:focus {
    border-color: var(--accent, #6366f1);
}
.form-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.875rem;
    margin-bottom: 0.875rem;
}
@media (max-width: 640px) {
    .form-row-2 { grid-template-columns: 1fr; }
}

.pgn { padding: 0.25rem 0.625rem; border: 1px solid var(--border, #e5e7eb); border-radius: 0.375rem; font-size: 0.78rem; color: #475569; text-decoration: none; background: white; }
.pgn-active { background: var(--accent, #6366f1); color: white; border-color: var(--accent, #6366f1); }
</style>
