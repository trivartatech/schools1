<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Table from '@/Components/ui/Table.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import { ref, reactive, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';

const { can } = usePermissions();
const confirm = useConfirm();
const toast = useToast();
const page = usePage();
const formErrors = computed(() => page.props.errors || {});

const props = defineProps({
    allocations:    Array,
    routes:         Array,
    vehicles:       Array,
    classes:        Array,
    standardMonths: { type: Number, default: 10 },
});

const showModal   = ref(false);
const editingItem = ref(null);
const saving      = ref(false);

const form = reactive({
    student_ids: [], class_id: '', section_id: '', route_id: '', stop_id: '', vehicle_id: '',
    pickup_type: 'both', months: 10, days: 0,
    start_date: '', end_date: '', status: 'active',
});

const fetchingStudents = ref(false);
const students = ref([]);
const sections = ref([]);

// ── Table filters (client-side) ─────────────────────────────────
const filters = reactive({ search: '', class_id: '', section_id: '' });
const filterSections = ref([]);

watch(() => filters.class_id, async (val) => {
    filters.section_id = '';
    filterSections.value = [];
    if (!val) return;
    try {
        const res = await fetch(`/school/classes/${val}/sections`);
        filterSections.value = await res.json();
    } catch (e) {
        console.error('Error fetching filter sections', e);
    }
});

const filtersActive = computed(() => !!(filters.search || filters.class_id || filters.section_id));
function clearFilters() { filters.search = ''; filters.class_id = ''; filters.section_id = ''; }

const filteredAllocations = computed(() => {
    const q = filters.search.trim().toLowerCase();
    const cid = filters.class_id ? Number(filters.class_id) : null;
    const sid = filters.section_id ? Number(filters.section_id) : null;
    return props.allocations.filter(a => {
        if (cid || sid) {
            const h = a.student?.current_academic_history;
            if (!h) return false;
            if (cid && Number(h.class_id) !== cid) return false;
            if (sid && Number(h.section_id) !== sid) return false;
        }
        if (q) {
            const name = (a.student?.user?.name || [a.student?.first_name, a.student?.last_name].filter(Boolean).join(' ') || '').toLowerCase();
            const adm  = (a.student?.admission_no || '').toLowerCase();
            if (!name.includes(q) && !adm.includes(q)) return false;
        }
        return true;
    });
});

watch(() => form.class_id, async (val) => {
    form.section_id = '';
    sections.value = [];
    students.value = [];
    form.student_ids = [];
    if (!val) return;
    try {
        const res = await fetch(`/school/classes/${val}/sections`);
        sections.value = await res.json();
    } catch (e) {
        console.error('Error fetching sections', e);
        toast.error('Could not load sections for this class. Try again.');
    }
    fetchStudents();
});

watch(() => form.section_id, () => {
    fetchStudents();
});

async function fetchStudents() {
    if (!form.class_id) return;
    fetchingStudents.value = true;
    students.value = [];
    form.student_ids = [];
    try {
        let url = `/school/transport/allocations/students-by-class?class_id=${form.class_id}`;
        if (form.section_id) url += `&section_id=${form.section_id}`;
        const res = await fetch(url);
        students.value = await res.json();
    } catch (e) {
        console.error('Error fetching students', e);
        toast.error('Failed to load students for this section. Try again.');
    } finally {
        fetchingStudents.value = false;
    }
}

function toggleAllStudents(event) {
    if (event.target.checked) {
        form.student_ids = students.value.map(s => s.id);
    } else {
        form.student_ids = [];
    }
}

const routeStops = computed(() => {
    if (!form.route_id) return [];
    const route = props.routes.find(r => r.id == form.route_id);
    return route?.stops || [];
});

const stopFee = computed(() => {
    const stop = routeStops.value.find(s => s.id == form.stop_id);
    return stop?.fee || 0;
});

const monthsOpted = computed(() => {
    const m = Math.max(0, Math.min(24, Number(form.months) || 0));
    const d = Math.max(0, Math.min(30, Number(form.days)   || 0));
    return Math.round((m + d / 30) * 100) / 100;
});

const computedFee = computed(() => {
    const std = Number(props.standardMonths) > 0 ? Number(props.standardMonths) : 10;
    const fee = (Number(stopFee.value) / std) * monthsOpted.value;
    return Math.round(fee * 100) / 100;
});

const termTooShort = computed(() => monthsOpted.value > 0 && monthsOpted.value < 0.5);

watch(() => form.route_id, (newRouteId) => {
    form.stop_id = '';
    if (newRouteId) {
        const routeVehicles = props.vehicles.filter(v => v.route_id == newRouteId);
        form.vehicle_id = routeVehicles.length ? routeVehicles[0].id : '';
    } else {
        form.vehicle_id = '';
    }
});

function openModal(alloc = null) {
    editingItem.value = alloc;
    form.class_id = '';
    form.section_id = '';
    sections.value = [];
    students.value = [];

    if (alloc) {
        const total = Number(alloc.months_opted ?? props.standardMonths ?? 10);
        const whole = Math.floor(total);
        const extraDays = Math.round((total - whole) * 30);
        Object.assign(form, {
            student_ids: [alloc.student_id], route_id: alloc.route_id, stop_id: alloc.stop_id,
            vehicle_id:  alloc.vehicle_id || '', pickup_type: alloc.pickup_type,
            months: whole, days: extraDays,
            start_date:  alloc.start_date || '', end_date: alloc.end_date || '', status: alloc.status,
        });
        students.value = [{
            id: alloc.student.id,
            name: alloc.student.user?.name || [alloc.student.first_name, alloc.student.last_name].filter(Boolean).join(' ') || '',
            admission_no: alloc.student.admission_no
        }];
    } else {
        Object.assign(form, { student_ids: [], route_id: '', stop_id: '', vehicle_id: '',
            pickup_type: 'both', months: Math.floor(props.standardMonths || 10), days: 0,
            start_date: '', end_date: '', status: 'active', class_id: '', section_id: '' });
    }
    showModal.value = true;
}

function save() {
    saving.value = true;
    const url    = editingItem.value ? `/school/transport/allocations/${editingItem.value.id}` : '/school/transport/allocations';
    const method = editingItem.value ? 'put' : 'post';
    router[method](url, { ...form }, {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false; },
        onFinish:  () => { saving.value = false; },
    });
}

async function destroy(alloc) {
    const ok = await confirm({
        title: 'Remove allocation?',
        message: 'This student will no longer be assigned to transport.',
        confirmLabel: 'Remove',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/transport/allocations/${alloc.id}`, { preserveScroll: true });
}

const pickupLabel = (t) => ({ pickup: 'Pickup Only', drop: 'Drop Only', both: 'Both' })[t] || t;

function printAllPasses() {
    const ids = filteredAllocations.value.map(a => a.id);
    if (!ids.length) {
        toast.warning('No allocations to print.');
        return;
    }
    const params = ids.map(id => `ids[]=${id}`).join('&');
    window.open(`/school/transport/allocations/bus-passes?${params}`, '_blank');
}

const statCards = computed(() => [
    { label: 'Total Assigned', value: props.allocations.length, color: 'accent' },
    { label: 'Active',         value: props.allocations.filter(a => a.status === 'active').length, color: 'success' },
    {
        label: 'Avg Transport Fee',
        value: '₹' + (props.allocations.length ? Math.round(props.allocations.reduce((s, a) => s + parseFloat(a.transport_fee || 0), 0) / props.allocations.length) : 0),
        color: 'purple',
    },
]);
</script>

<template>
    <SchoolLayout title="Student Transport Allocation">

        <PageHeader title="Student Transport Allocation" subtitle="Assign students to routes and stops">
            <template #actions>
                <Button variant="secondary" @click="printAllPasses">🖨 Print All Passes</Button>
                <Button v-if="can('create_transport_allocations')" @click="openModal()">+ Assign Student</Button>
            </template>
        </PageHeader>

        <!-- Stats -->
        <StatsRow :cols="3" :stats="statCards" />

        <!-- Filters -->
        <FilterBar :active="filtersActive" @clear="clearFilters">
            <div class="fb-search">
                <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                <input v-model="filters.search" type="search" placeholder="Search by name or admission no.">
            </div>
            <select v-model="filters.class_id" style="width:140px;">
                <option value="">All classes</option>
                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-if="filters.class_id && filterSections.length > 0" v-model="filters.section_id" style="width:140px;">
                <option value="">All sections</option>
                <option v-for="s in filterSections" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
        </FilterBar>

        <!-- Allocations Table -->
        <div class="card">
            <Table :empty="!filteredAllocations.length">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Class / Section</th>
                        <th>Route</th>
                        <th>Stop</th>
                        <th>Vehicle</th>
                        <th style="text-align:center;">Pickup</th>
                        <th style="text-align:center;">Fee</th>
                        <th style="text-align:center;">Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in filteredAllocations" :key="a.id">
                        <td>
                            <p style="font-weight:600;color:#111827;">{{ a.student?.user?.name || [a.student?.first_name, a.student?.last_name].filter(Boolean).join(' ') || '—' }}</p>
                            <p style="font-size:0.75rem;color:#9ca3af;">{{ a.student?.admission_no }}</p>
                        </td>
                        <td>
                            <span style="font-size:0.85rem;color:#374151;">
                                {{ [a.student?.current_academic_history?.course_class?.name, a.student?.current_academic_history?.section?.name].filter(Boolean).join(' - ') || '—' }}
                            </span>
                        </td>
                        <td>{{ a.route?.route_name || '—' }}</td>
                        <td>{{ a.stop?.stop_name || '—' }}</td>
                        <td style="font-family:monospace;">{{ a.vehicle?.vehicle_number || '—' }}</td>
                        <td style="text-align:center;"><span class="badge badge-blue">{{ pickupLabel(a.pickup_type) }}</span></td>
                        <td style="text-align:center;font-weight:600;color:var(--success);">₹{{ a.transport_fee }}</td>
                        <td style="text-align:center;">
                            <span :class="a.status === 'active' ? 'badge badge-green' : 'badge badge-gray'" style="text-transform:capitalize;">{{ a.status }}</span>
                        </td>
                        <td style="text-align:right;">
                            <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.5rem;">
                                <a :href="`/school/transport/allocations/${a.id}/bus-pass`" target="_blank" style="text-decoration:none;">
                                    <Button variant="secondary" size="xs">Bus Pass</Button>
                                </a>
                                <Button variant="secondary" size="xs" v-if="can('edit_transport_allocations')" @click="openModal(a)">Edit</Button>
                                <Button variant="danger" size="xs" v-if="can('delete_transport_allocations')" @click="destroy(a)">Remove</Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <template #empty>
                    <EmptyState
                        v-if="filtersActive"
                        title="No matches"
                        description="No allocations match the current filter."
                        action-label="Clear filters"
                        @action="clearFilters"
                    />
                    <EmptyState
                        v-else
                        title="No students assigned yet"
                        description="Assign students to a route and stop to start tracking transport."
                        :action-label="can('create_transport_allocations') ? '+ Assign Student' : ''"
                        @action="openModal()"
                    />
                </template>
            </Table>
        </div>

        <!-- Assignment Modal -->
        <Modal v-model:open="showModal" :title="editingItem ? 'Edit Allocation' : 'Assign Student'" size="md">
            <form @submit.prevent="save" id="alloc-form">
                <!-- Validation Errors -->
                <div v-if="Object.keys(formErrors).length" style="background:#fef2f2;border:1px solid #fecaca;border-radius:0.5rem;padding:0.75rem 1rem;margin-bottom:1rem;">
                    <p v-for="(msg, key) in formErrors" :key="key" style="font-size:0.8rem;color:#dc2626;margin:0.125rem 0;">{{ Array.isArray(msg) ? msg[0] : msg }}</p>
                </div>
                <!-- Student Selection (new) -->
                <div v-if="!editingItem">
                    <div class="form-row-2">
                        <div class="form-field">
                            <label>Class *</label>
                            <select v-model="form.class_id">
                                <option value="">-- Select Class --</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Section</label>
                            <select v-model="form.section_id" :disabled="!form.class_id">
                                <option value="">-- All Sections --</option>
                                <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="form.class_id" class="form-field" style="margin-top:14px;">
                        <label style="display:flex;align-items:center;justify-content:space-between;">
                            <span>Select Students ({{ students.length }} available) *</span>
                            <label v-if="students.length > 0" style="display:flex;align-items:center;gap:0.375rem;cursor:pointer;font-weight:400;">
                                <input type="checkbox" @change="toggleAllStudents" :checked="students.length > 0 && form.student_ids.length === students.length">
                                <span>Select All</span>
                            </label>
                        </label>
                        <div v-if="fetchingStudents" style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.75rem;max-height:12rem;overflow-y:auto;background:#f9fafb;display:flex;align-items:center;justify-content:center;font-size:0.875rem;color:#6b7280;">
                            Loading students...
                        </div>
                        <div v-else-if="students.length === 0" style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.75rem;max-height:12rem;overflow-y:auto;background:#f9fafb;display:flex;align-items:center;justify-content:center;font-size:0.875rem;color:#6b7280;">
                            No students found.
                        </div>
                        <div v-else style="border:1px solid #e5e7eb;border-radius:0.5rem;max-height:12rem;overflow-y:auto;background:#fff;">
                            <label v-for="s in students" :key="s.id" style="display:flex;align-items:center;gap:0.75rem;padding:0.625rem;cursor:pointer;border-bottom:1px solid #f3f4f6;">
                                <input type="checkbox" v-model="form.student_ids" :value="s.id">
                                <div style="display:flex;flex-direction:column;">
                                    <span style="font-size:0.875rem;font-weight:500;color:#111827;">{{ s.name }}</span>
                                    <span style="font-size:0.75rem;color:#9ca3af;">{{ s.admission_no }}</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Editing: student display -->
                <div v-else class="form-field">
                    <label>Student</label>
                    <div style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.5rem 0.75rem;background:#f9fafb;font-size:0.875rem;color:#374151;">
                        <span style="font-weight:500;">{{ students[0]?.name }}</span>
                        <span style="margin-left:0.5rem;font-size:0.75rem;color:#9ca3af;">({{ students[0]?.admission_no }})</span>
                    </div>
                </div>

                <!-- Route + Stop -->
                <div class="form-row-2" style="margin-top:14px;">
                    <div class="form-field">
                        <label>Route *</label>
                        <select v-model="form.route_id" required>
                            <option value="">-- Select Route --</option>
                            <option v-for="r in routes" :key="r.id" :value="r.id">{{ r.route_name }}</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Stop *</label>
                        <select v-model="form.stop_id" required :disabled="!form.route_id">
                            <option value="">-- Select Stop --</option>
                            <option v-for="s in routeStops" :key="s.id" :value="s.id">{{ s.stop_name }}</option>
                        </select>
                    </div>
                </div>

                <!-- Months + Days -->
                <div class="form-row-2" style="margin-top:14px;">
                    <div class="form-field">
                        <label>Months Opted *</label>
                        <input v-model.number="form.months" type="number" min="0" max="24" step="1" required>
                    </div>
                    <div class="form-field">
                        <label>Extra Days</label>
                        <input v-model.number="form.days" type="number" min="0" max="30" step="1">
                    </div>
                </div>

                <!-- Fee preview -->
                <div v-if="stopFee > 0" style="padding:0.625rem 0.875rem;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.3);border-radius:0.5rem;font-size:0.8125rem;color:#065f46;margin:14px 0 0;line-height:1.5;">
                    <div>Stop fee: <strong>₹{{ stopFee }}</strong> <span style="color:#6b7280;">(for {{ standardMonths }} months)</span></div>
                    <div>Student opts for: <strong>{{ form.months || 0 }} months{{ form.days ? ' + ' + form.days + ' days' : '' }}</strong> = <strong>{{ monthsOpted }} months</strong></div>
                    <div style="margin-top:0.25rem;padding-top:0.25rem;border-top:1px dashed rgba(16,185,129,0.3);">
                        Transport fee: <strong style="color:var(--success);font-size:0.95rem;">₹{{ computedFee }}</strong>
                    </div>
                </div>
                <div v-if="termTooShort" style="padding:0.5rem 0.75rem;background:#fef3c7;border:1px solid #fcd34d;border-radius:0.5rem;font-size:0.8125rem;color:#92400e;margin-top:14px;">
                    Minimum term is 15 days (0.5 months).
                </div>

                <!-- Vehicle + Pickup Type -->
                <div class="form-row-2" style="margin-top:14px;">
                    <div class="form-field">
                        <label>Assigned Vehicle (Auto-selected)</label>
                        <select v-model="form.vehicle_id" disabled>
                            <option value="">-- No vehicle on this route --</option>
                            <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.vehicle_number }}</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Pickup Type *</label>
                        <select v-model="form.pickup_type" required>
                            <option value="both">Both</option>
                            <option value="pickup">Pickup Only</option>
                            <option value="drop">Drop Only</option>
                        </select>
                    </div>
                </div>

                <!-- Dates -->
                <div class="form-row-2" style="margin-top:14px;">
                    <div class="form-field">
                        <label>Start Date</label>
                        <input v-model="form.start_date" type="date">
                    </div>
                    <div class="form-field">
                        <label>End Date</label>
                        <input v-model="form.end_date" type="date">
                    </div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                <Button type="submit" form="alloc-form" :disabled="saving || form.student_ids.length === 0 || termTooShort || monthsOpted === 0" :loading="saving">
                    {{ editingItem ? 'Update' : `Assign (${form.student_ids.length})` }}
                </Button>
            </template>
        </Modal>
    </SchoolLayout>
</template>
