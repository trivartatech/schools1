<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    menus:   Object,   // paginated
    hostels: Array,
    filters: Object,
});

const showModal = ref(false);
const editing  = ref(null);
const loading  = ref(false);
const errors   = ref({});

const DAYS  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
const MEALS = ['Breakfast','Lunch','Snacks','Dinner'];

const form = reactive({
    hostel_id: '', day: 'Monday', meal_type: 'Breakfast', items: ''
});

const filterForm = reactive({
    hostel_id: props.filters?.hostel_id || '',
    day:       props.filters?.day       || '',
});

function applyFilters() {
    router.get('/school/hostel/mess', filterForm, { preserveState: true, replace: true });
}

function openModal(item = null) {
    editing.value = item;
    errors.value  = {};
    if (item) {
        Object.assign(form, {
            hostel_id: item.hostel_id,
            day:       item.day,
            meal_type: item.meal_type,
            items:     item.items,
        });
    } else {
        Object.assign(form, {
            hostel_id: props.hostels[0]?.id || '',
            day:       'Monday',
            meal_type: 'Breakfast',
            items:     '',
        });
    }
    showModal.value = true;
}

function save() {
    loading.value = true;
    errors.value  = {};
    const url = editing.value
        ? `/school/hostel/mess/menu/${editing.value.id}`
        : `/school/hostel/mess/menu`;
    const method = editing.value ? router.put : router.post;
    method(url, form, {
        onSuccess: () => { showModal.value = false; },
        onError:   (e) => { errors.value = e; },
        onFinish:  () => { loading.value = false; },
    });
}

function destroy(id) {
    if (confirm('Delete this menu item?')) {
        router.delete(`/school/hostel/mess/menu/${id}`);
    }
}

const mealColors = {
    Breakfast: 'badge-amber',
    Lunch:     'badge-green',
    Snacks:    'badge-blue',
    Dinner:    'badge-purple',
};
</script>

<template>
    <SchoolLayout title="Mess Menu">

        <div class="page-header">
            <div>
                <h1 class="page-header-title">Weekly Mess Schedule</h1>
                <p class="page-header-sub">Manage meal menus for each hostel by day and meal type.</p>
            </div>
            <Button @click="openModal()">+ Add Menu Item</Button>
        </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom: 16px;">
            <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;padding:12px 16px;">
                <div class="form-field" style="margin:0;min-width:160px;">
                    <label>Hostel</label>
                    <select v-model="filterForm.hostel_id" @change="applyFilters">
                        <option value="">All Hostels</option>
                        <option v-for="h in hostels" :key="h.id" :value="h.id">{{ h.name }}</option>
                    </select>
                </div>
                <div class="form-field" style="margin:0;min-width:140px;">
                    <label>Day</label>
                    <select v-model="filterForm.day" @change="applyFilters">
                        <option value="">All Days</option>
                        <option v-for="d in DAYS" :key="d" :value="d">{{ d }}</option>
                    </select>
                </div>
                <Button variant="secondary" size="sm" v-if="filterForm.hostel_id || filterForm.day" @click="filterForm.hostel_id=''; filterForm.day=''; applyFilters()">
                    Clear
                </Button>
            </div>
        </div>

        <!-- Table -->
        <div class="card">
            <div style="overflow-x:auto;">
                <Table>
                    <thead>
                        <tr>
                            <th>Hostel</th>
                            <th>Day</th>
                            <th>Meal</th>
                            <th>Menu Items</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in menus.data" :key="m.id">
                            <td style="font-weight:500;">{{ m.hostel?.name || '—' }}</td>
                            <td style="font-weight:600;color:var(--text-primary);">{{ m.day }}</td>
                            <td>
                                <span class="badge" :class="mealColors[m.meal_type] || 'badge-gray'">
                                    {{ m.meal_type }}
                                </span>
                            </td>
                            <td style="max-width:300px;white-space:pre-wrap;font-size:0.82rem;color:var(--text-secondary);">
                                {{ m.items }}
                            </td>
                            <td style="text-align:right;">
                                <Button variant="secondary" size="xs" @click="openModal(m)" class="mr-1.5">Edit</Button>
                                <Button variant="danger" size="xs" @click="destroy(m.id)">Delete</Button>
                            </td>
                        </tr>
                        <tr v-if="!menus.data.length">
                            <td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted);">
                                No menu items found. Click "+ Add Menu Item" to get started.
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>

            <!-- Pagination -->
            <div v-if="menus.last_page > 1"
                 style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-top:1px solid var(--border);font-size:0.82rem;color:var(--text-muted);">
                <span>Showing {{ menus.from }}–{{ menus.to }} of {{ menus.total }}</span>
                <div style="display:flex;gap:4px;">
                    <Button v-for="link in menus.links" :key="link.label"
                            as="link"
                            size="xs"
                            :href="link.url || '#'"
                            :variant="link.active ? 'primary' : 'secondary'"
                            :disabled="!link.url"
                            :class="!link.url ? 'opacity-40 pointer-events-none' : ''"
                            v-html="link.label" preserve-scroll />
                </div>
            </div>
        </div>

        <!-- Add / Edit Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-backdrop" @mousedown.self="showModal = false">
                <div class="modal" style="max-width:26rem;">
                    <div class="modal-header">
                        <h3 class="modal-title">{{ editing ? 'Edit' : 'Add' }} Menu Item</h3>
                        <button @click="showModal = false" class="modal-close">&times;</button>
                    </div>
                    <form @submit.prevent="save">
                        <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;">

                            <!-- Server errors -->
                            <div v-if="Object.keys(errors).length"
                                 style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;font-size:0.82rem;color:#dc2626;">
                                <div v-for="(msg, field) in errors" :key="field">{{ msg }}</div>
                            </div>

                            <div class="form-field" style="margin:0;">
                                <label>Hostel *</label>
                                <select v-model="form.hostel_id" required>
                                    <option v-for="h in hostels" :key="h.id" :value="h.id">{{ h.name }}</option>
                                </select>
                                <span v-if="errors.hostel_id" class="form-error">{{ errors.hostel_id }}</span>
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div class="form-field" style="margin:0;">
                                    <label>Day *</label>
                                    <select v-model="form.day" required>
                                        <option v-for="d in DAYS" :key="d" :value="d">{{ d }}</option>
                                    </select>
                                    <span v-if="errors.day" class="form-error">{{ errors.day }}</span>
                                </div>
                                <div class="form-field" style="margin:0;">
                                    <label>Meal *</label>
                                    <select v-model="form.meal_type" required>
                                        <option v-for="m in MEALS" :key="m" :value="m">{{ m }}</option>
                                    </select>
                                    <span v-if="errors.meal_type" class="form-error">{{ errors.meal_type }}</span>
                                </div>
                            </div>

                            <div class="form-field" style="margin:0;">
                                <label>Menu Items *</label>
                                <textarea v-model="form.items" required rows="4"
                                    placeholder="e.g. Idli, Sambar, Chutney, Coffee"></textarea>
                                <span v-if="errors.items" class="form-error">{{ errors.items }}</span>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                            <Button type="submit" :loading="loading">
                                Save
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

    </SchoolLayout>
</template>

<style scoped>
.modal-backdrop {
    position:fixed;inset:0;background:rgba(15,23,42,.5);
    display:flex;align-items:center;justify-content:center;z-index:1000;
}
.modal {
    background:#fff;border-radius:14px;width:100%;
    box-shadow:0 20px 40px rgba(0,0,0,.18);
}
.modal-header {
    padding:16px 20px;border-bottom:1px solid #e2e8f0;
    display:flex;justify-content:space-between;align-items:center;
}
.modal-title  { font-size:1rem;font-weight:700;color:#1e293b; }
.modal-close  { background:none;border:none;font-size:1.5rem;color:#94a3b8;cursor:pointer;line-height:1; }
.modal-close:hover { color:#1e293b; }
.modal-body   { padding:20px; }
.modal-footer {
    padding:14px 20px;border-top:1px solid #e2e8f0;
    display:flex;justify-content:flex-end;gap:8px;
    background:#f8fafc;border-radius:0 0 14px 14px;
}
</style>
