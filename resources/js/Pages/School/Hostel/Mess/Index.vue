<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Table from '@/Components/ui/Table.vue';
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useConfirm } from '@/Composables/useConfirm';

const confirm = useConfirm();

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

async function destroy(id) {
    const ok = await confirm({
        title: 'Delete menu item?',
        message: 'This menu entry will be permanently removed.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/hostel/mess/menu/${id}`);
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

        <PageHeader title="Weekly Mess Schedule" subtitle="Manage meal menus for each hostel by day and meal type.">
            <template #actions>
                <Button @click="openModal()">+ Add Menu Item</Button>
            </template>
        </PageHeader>

        <!-- Filters -->
        <FilterBar
            :active="!!(filterForm.hostel_id || filterForm.day)"
            @clear="filterForm.hostel_id = ''; filterForm.day = ''; applyFilters()"
        >
            <select v-model="filterForm.hostel_id" @change="applyFilters" style="width:180px;">
                <option value="">All Hostels</option>
                <option v-for="h in hostels" :key="h.id" :value="h.id">{{ h.name }}</option>
            </select>
            <select v-model="filterForm.day" @change="applyFilters" style="width:160px;">
                <option value="">All Days</option>
                <option v-for="d in DAYS" :key="d" :value="d">{{ d }}</option>
            </select>
        </FilterBar>

        <!-- Table -->
        <div class="card">
            <Table :empty="!menus.data.length">
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
                </tbody>
                <template #empty>
                    <EmptyState
                        title="No menu items found"
                        description="Add weekly menu entries for each hostel by day and meal type."
                        action-label="+ Add Menu Item"
                        @action="openModal()"
                    />
                </template>
            </Table>

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
        <Modal v-model:open="showModal" :title="editing ? 'Edit Menu Item' : 'Add Menu Item'" size="sm">
            <form @submit.prevent="save" id="menu-form">
                <!-- Server errors -->
                <div v-if="Object.keys(errors).length"
                     style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;font-size:0.82rem;color:#dc2626;margin-bottom:14px;">
                    <div v-for="(msg, field) in errors" :key="field">{{ msg }}</div>
                </div>

                <div class="form-field">
                    <label>Hostel *</label>
                    <select v-model="form.hostel_id" required>
                        <option v-for="h in hostels" :key="h.id" :value="h.id">{{ h.name }}</option>
                    </select>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px;">
                    <div class="form-field">
                        <label>Day *</label>
                        <select v-model="form.day" required>
                            <option v-for="d in DAYS" :key="d" :value="d">{{ d }}</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Meal *</label>
                        <select v-model="form.meal_type" required>
                            <option v-for="m in MEALS" :key="m" :value="m">{{ m }}</option>
                        </select>
                    </div>
                </div>

                <div class="form-field" style="margin-top:14px;">
                    <label>Menu Items *</label>
                    <textarea v-model="form.items" required rows="4"
                        placeholder="e.g. Idli, Sambar, Chutney, Coffee"></textarea>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                <Button type="submit" form="menu-form" :loading="loading">Save</Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
.form-field { display: flex; flex-direction: column; gap: 5px; }
.form-field label { font-size: 0.78rem; font-weight: 600; color: #374151; }
</style>
