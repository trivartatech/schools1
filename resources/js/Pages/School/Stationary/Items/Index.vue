<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, reactive } from 'vue';
import { router, Head } from '@inertiajs/vue3';
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

function destroy(item) {
    if (!confirm(`Delete item "${item.name}"? This cannot be undone.`)) return;
    router.delete(`/school/stationary/items/${item.id}`, { preserveScroll: true });
}

function fmt(n) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 }).format(n ?? 0);
}
</script>

<template>
    <Head title="Stationary Items" />
    <SchoolLayout title="Stationary Items">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">📦 Stationary Items</h1>
                <p class="page-header-sub">Master list of items the school sells to students.</p>
            </div>
            <Button v-if="can('create_stationary_items')" @click="openModal()">+ Add Item</Button>
        </div>

        <!-- Stats grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <p class="stat-value">{{ stats?.total ?? 0 }}</p>
                <p class="stat-label">Total Items</p>
            </div>
            <div class="stat-card">
                <p class="stat-value" style="color:#059669;">{{ stats?.active ?? 0 }}</p>
                <p class="stat-label">Active</p>
            </div>
            <div class="stat-card" :class="{ 'stat-card--alert': (stats?.low_stock ?? 0) > 0 }">
                <p class="stat-value" style="color:#b45309;">{{ stats?.low_stock ?? 0 }}</p>
                <p class="stat-label">Low Stock Alerts</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom: 1rem;">
            <div class="card-body" style="padding: 0.75rem 1rem;">
                <div style="display:flex;gap:0.625rem;align-items:center;flex-wrap:wrap;">
                    <input v-model="search" @keydown.enter="applyFilters" type="text"
                           placeholder="Search by name or code…"
                           style="flex: 1; min-width: 240px; border: 1px solid var(--border, #e5e7eb); border-radius: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.875rem;" />
                    <select v-model="status" @change="applyFilters"
                            style="border: 1px solid var(--border, #e5e7eb); border-radius: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.875rem; background: white;">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <Button variant="secondary" size="sm" @click="applyFilters">Apply</Button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card">
            <div style="overflow-x: auto;">
                <Table v-if="items.data.length">
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
                </Table>
                <div v-else style="text-align: center; padding: 4rem 1rem; color: #9ca3af;">
                    <div style="font-size: 2.4rem; margin-bottom: 0.5rem;">📦</div>
                    <p style="font-size: 0.95rem; color: #475569; font-weight: 500;">No stationary items yet.</p>
                    <p style="font-size: 0.82rem; color: #94a3b8; margin-top: 0.25rem;">
                        <a v-if="can('create_stationary_items')" href="#" @click.prevent="openModal()" style="color: var(--accent, #6366f1);">Add the first item</a>
                    </p>
                </div>
            </div>

            <div v-if="items.last_page > 1" style="padding: 0.75rem 1rem; display: flex; gap: 0.375rem; flex-wrap: wrap; border-top: 1px solid var(--border, #e5e7eb);">
                <a v-for="link in items.links" :key="link.label"
                   :href="link.url || '#'" v-html="link.label"
                   :class="link.active ? 'pgn pgn-active' : 'pgn'"
                   :style="!link.url ? 'pointer-events:none;opacity:0.4' : ''"></a>
            </div>
        </div>

        <!-- ─── Add / Edit Modal ──────────────────────────────────────────── -->
        <Teleport to="body">
        <div v-if="showModal" class="modal-backdrop" @mousedown.self="showModal = false">
            <div class="modal" style="max-width: 36rem;">
                <div class="card-header" style="position: sticky; top: 0; background: var(--surface, #fff); z-index: 10; justify-content: space-between;">
                    <span class="card-title">{{ editingItem ? 'Edit Item' : 'Add Item' }}</span>
                    <Button variant="secondary" size="xs" @click="showModal = false">✕</Button>
                </div>
                <div class="card-body">
                    <form @submit.prevent="save">
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

                        <div style="display:flex;justify-content:flex-end;gap:0.5rem;padding-top:0.75rem;border-top:1px solid var(--border, #e5e7eb);margin-top:0.75rem;">
                            <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                            <Button type="submit" :loading="saving">{{ editingItem ? 'Save Changes' : 'Create Item' }}</Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </Teleport>
    </SchoolLayout>
</template>

<style scoped>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.875rem;
    margin-bottom: 1.25rem;
}
@media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }

.stat-card {
    background: var(--surface, #fff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 0.625rem;
    padding: 0.875rem 1rem;
}
.stat-card--alert {
    border-color: rgba(245, 158, 11, 0.4);
    background: rgba(245, 158, 11, 0.04);
}
.stat-value {
    font-size: 1.6rem;
    font-weight: 800;
    color: #111827;
    margin: 0;
    line-height: 1.1;
}
.stat-label {
    font-size: 0.74rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 600;
    margin-top: 0.25rem;
}

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

.modal-backdrop {
    position: fixed; inset: 0; background: rgba(15,23,42,.5);
    display: flex; align-items: flex-start; justify-content: center;
    z-index: 1000; padding: 2.5rem 1rem;
    overflow-y: auto;
}
.modal {
    background: var(--surface, #fff);
    border-radius: 0.75rem;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}

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
