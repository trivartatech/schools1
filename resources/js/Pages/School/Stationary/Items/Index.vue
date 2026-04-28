<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import Table from '@/Components/ui/Table.vue';

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
const errors      = ref({});

const form = reactive({
    name: '', code: '', unit_price: 0, hsn_code: '',
    current_stock: 0, min_stock: 0, status: 'active', description: '',
});

function applyFilters() {
    router.get('/school/stationary/items',
        { q: search.value, status: status.value },
        { preserveState: true, preserveScroll: true, replace: true });
}

function openModal(item = null) {
    editingItem.value = item;
    errors.value = {};
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
    errors.value = {};
    const url    = editingItem.value ? `/school/stationary/items/${editingItem.value.id}` : '/school/stationary/items';
    const method = editingItem.value ? 'put' : 'post';
    router[method](url, { ...form }, {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false; },
        onError:   (e) => { errors.value = e; },
        onFinish:  () => { saving.value = false; },
    });
}

function destroy(item) {
    if (!confirm(`Delete item "${item.name}"? This cannot be undone.`)) return;
    router.delete(`/school/stationary/items/${item.id}`, { preserveScroll: true });
}

function fmt(n) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 }).format(n ?? 0);
}
</script>

<template>
    <SchoolLayout title="Stationary Items">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Stationary Items</h1>
                <p class="page-header-sub">Master list of items the school sells to students</p>
            </div>
            <Button v-if="can('create_stationary_items')" @click="openModal()">+ Add Item</Button>
        </div>

        <div class="summary-grid" style="grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px;">
            <div class="card"><div class="card-body" style="padding: 14px 18px;">
                <p style="font-size: 0.78rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Total Items</p>
                <p style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">{{ stats?.total ?? 0 }}</p>
            </div></div>
            <div class="card"><div class="card-body" style="padding: 14px 18px;">
                <p style="font-size: 0.78rem; color: #059669; font-weight: 600; text-transform: uppercase;">Active</p>
                <p style="font-size: 1.5rem; font-weight: 700; color: #059669;">{{ stats?.active ?? 0 }}</p>
            </div></div>
            <div class="card"><div class="card-body" style="padding: 14px 18px;">
                <p style="font-size: 0.78rem; color: #b45309; font-weight: 600; text-transform: uppercase;">Low Stock Alerts</p>
                <p style="font-size: 1.5rem; font-weight: 700; color: #b45309;">{{ stats?.low_stock ?? 0 }}</p>
            </div></div>
        </div>

        <div class="card" style="margin-bottom: 16px;">
            <div class="card-body" style="padding: 12px 16px; display: flex; gap: 10px; align-items: center;">
                <input v-model="search" @keydown.enter="applyFilters" type="text"
                       placeholder="Search by name or code..."
                       style="flex: 1; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.86rem;" />
                <select v-model="status" @change="applyFilters"
                        style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.86rem;">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <Button variant="secondary" @click="applyFilters">Apply</Button>
            </div>
        </div>

        <div class="card">
            <Table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items.data" :key="item.id">
                        <td>
                            <div style="font-weight: 600;">{{ item.name }}</div>
                            <div v-if="item.description" style="font-size: 0.78rem; color: #94a3b8;">{{ item.description }}</div>
                        </td>
                        <td><span style="font-family: monospace;">{{ item.code || '—' }}</span></td>
                        <td class="text-right">{{ fmt(item.unit_price) }}</td>
                        <td class="text-right">
                            <span :style="item.current_stock <= item.min_stock ? 'color:#b45309;font-weight:600;' : ''">
                                {{ item.current_stock }}
                            </span>
                            <span v-if="item.current_stock <= item.min_stock" title="Low stock">⚠️</span>
                        </td>
                        <td>
                            <span :class="['badge', item.status === 'active' ? 'badge-green' : 'badge-gray']">{{ item.status }}</span>
                        </td>
                        <td>
                            <Button v-if="can('edit_stationary_items')" size="sm" variant="secondary" @click="openModal(item)">Edit</Button>
                            <Button v-if="can('delete_stationary_items')" size="sm" variant="danger" @click="destroy(item)">Delete</Button>
                        </td>
                    </tr>
                    <tr v-if="!items.data.length">
                        <td colspan="6" style="text-align: center; padding: 28px; color: #94a3b8;">
                            No stationary items yet. <a v-if="can('create_stationary_items')" href="#" @click.prevent="openModal()" style="color: #6366f1;">Add the first item</a>
                        </td>
                    </tr>
                </tbody>
            </Table>
            <div v-if="items.last_page > 1" style="padding: 12px 16px; display: flex; gap: 6px; flex-wrap: wrap;">
                <a v-for="link in items.links" :key="link.label"
                   :href="link.url || '#'"
                   v-html="link.label"
                   :class="link.active ? 'pgn pgn-active' : 'pgn'"
                   :style="!link.url ? 'pointer-events:none;opacity:0.4' : ''"></a>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
            <div class="modal-card" style="max-width: 560px;">
                <div class="modal-header">
                    <h3>{{ editingItem ? 'Edit Item' : 'Add Item' }}</h3>
                    <button class="modal-close" @click="showModal = false">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>Item Name *</label>
                        <input v-model="form.name" type="text" class="form-input" required />
                        <p v-if="errors.name" class="form-err">{{ errors.name }}</p>
                    </div>
                    <div class="form-row-2">
                        <div>
                            <label>Code</label>
                            <input v-model="form.code" type="text" class="form-input" placeholder="e.g. NB-A4" />
                            <p v-if="errors.code" class="form-err">{{ errors.code }}</p>
                        </div>
                        <div>
                            <label>HSN Code</label>
                            <input v-model="form.hsn_code" type="text" class="form-input" />
                        </div>
                    </div>
                    <div class="form-row-2">
                        <div>
                            <label>Unit Price (₹) *</label>
                            <input v-model.number="form.unit_price" type="number" step="0.01" min="0" class="form-input" required />
                            <p v-if="errors.unit_price" class="form-err">{{ errors.unit_price }}</p>
                        </div>
                        <div>
                            <label>Status *</label>
                            <select v-model="form.status" class="form-input">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row-2">
                        <div>
                            <label>Current Stock *</label>
                            <input v-model.number="form.current_stock" type="number" min="0" class="form-input" required />
                        </div>
                        <div>
                            <label>Min Stock (alert level) *</label>
                            <input v-model.number="form.min_stock" type="number" min="0" class="form-input" required />
                        </div>
                    </div>
                    <div class="form-row">
                        <label>Description</label>
                        <textarea v-model="form.description" rows="2" class="form-input"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <Button variant="secondary" @click="showModal = false">Cancel</Button>
                    <Button :loading="saving" @click="save">{{ editingItem ? 'Save Changes' : 'Create Item' }}</Button>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.text-right { text-align: right; }
.badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
.badge-green { background: #d1fae5; color: #059669; }
.badge-gray  { background: #f1f5f9; color: #94a3b8; }

.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); display: flex; align-items: flex-start; justify-content: center; z-index: 50; padding: 40px 20px; overflow-y: auto; }
.modal-card { background: white; border-radius: 12px; width: 100%; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 22px; border-bottom: 1px solid #e2e8f0; }
.modal-header h3 { font-size: 1.05rem; font-weight: 700; color: #1e293b; margin: 0; }
.modal-close { background: none; border: 0; font-size: 1.4rem; color: #94a3b8; cursor: pointer; line-height: 1; }
.modal-body { padding: 18px 22px; display: flex; flex-direction: column; gap: 14px; }
.modal-footer { padding: 14px 22px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; }

.form-row { display: flex; flex-direction: column; gap: 4px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-row label, .form-row-2 label { font-size: 0.78rem; font-weight: 600; color: #475569; }
.form-input { border: 1px solid #cbd5e1; border-radius: 8px; padding: 7px 10px; font-size: 0.86rem; outline: none; width: 100%; }
.form-input:focus { border-color: #6366f1; }
.form-err { font-size: 0.74rem; color: #dc2626; }

.pgn { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.78rem; color: #475569; text-decoration: none; background: white; }
.pgn-active { background: #6366f1; color: white; border-color: #6366f1; }
</style>
