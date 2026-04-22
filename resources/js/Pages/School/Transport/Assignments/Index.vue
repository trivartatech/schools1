<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, reactive, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import Table from '@/Components/ui/Table.vue';

const { can } = usePermissions();
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

// Fetch sections when class changes
watch(() => form.class_id, async (val) => {
    form.section_id = '';
    sections.value = [];
    students.value = [];
    form.student_ids = [];
    if (!val) return;
    try {
        const res = await fetch(`/school/classes/${val}/sections`);
        sections.value = await res.json();
    } catch (e) { console.error('Error fetching sections', e); }
    fetchStudents();
});

// Fetch students when section changes
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

// Derived: stops for selected route
const routeStops = computed(() => {
    if (!form.route_id) return [];
    const route = props.routes.find(r => r.id == form.route_id);
    return route?.stops || [];
});

// Auto-fill fee display when stop changes
const stopFee = computed(() => {
    const stop = routeStops.value.find(s => s.id == form.stop_id);
    return stop?.fee || 0;
});

// Pro-rata term math: months + days/30, clamped to [0, 24+1)
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

// When route changes, reset stop and auto-select vehicle
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

function destroy(alloc) {
    if (!confirm(`Remove transport allocation for this student?`)) return;
    router.delete(`/school/transport/allocations/${alloc.id}`, { preserveScroll: true });
}

const pickupLabel = (t) => ({ pickup: 'Pickup Only', drop: 'Drop Only', both: 'Both' })[t] || t;
</script>

<template>
    <SchoolLayout title="Student Transport Allocation">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Student Transport Allocation</h1>
                <p class="page-header-sub">Assign students to routes and stops</p>
            </div>
            <Button v-if="can('create_transport_allocations')" @click="openModal()">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Assign Student
            </Button>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="card">
                <div class="card-body" style="display:flex;align-items:center;gap:0.75rem;">
                    <div style="width:2.5rem;height:2.5rem;border-radius:0.5rem;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;">
                        <svg class="w-5 h-5" style="color:var(--accent)" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div><p style="font-size:0.75rem;color:#6b7280;">Total Assigned</p><p style="font-size:1.25rem;font-weight:700;color:#111827;">{{ allocations.length }}</p></div>
                </div>
            </div>
            <div class="card">
                <div class="card-body" style="display:flex;align-items:center;gap:0.75rem;">
                    <div style="width:2.5rem;height:2.5rem;border-radius:0.5rem;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;">
                        <svg class="w-5 h-5" style="color:var(--success)" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div><p style="font-size:0.75rem;color:#6b7280;">Active</p><p style="font-size:1.25rem;font-weight:700;color:#111827;">{{ allocations.filter(a=>a.status==='active').length }}</p></div>
                </div>
            </div>
            <div class="card">
                <div class="card-body" style="display:flex;align-items:center;gap:0.75rem;">
                    <div style="width:2.5rem;height:2.5rem;border-radius:0.5rem;background:rgba(139,92,246,0.1);display:flex;align-items:center;justify-content:center;">
                        <svg class="w-5 h-5" style="color:#8b5cf6;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p style="font-size:0.75rem;color:#6b7280;">Avg Transport Fee</p>
                        <p style="font-size:1.25rem;font-weight:700;color:#111827;">₹{{ allocations.length ? Math.round(allocations.reduce((s,a)=>s+parseFloat(a.transport_fee||0),0)/allocations.length) : 0 }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Allocations Table -->
        <div class="card">
            <div class="card-body" style="padding:0;">
                <Table v-if="allocations.length">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Route</th>
                            <th>Stop</th>
                            <th>Vehicle</th>
                            <th style="text-align:center;">Pickup</th>
                            <th style="text-align:center;">Fee</th>
                            <th style="text-align:center;">Status</th>
                            <th v-if="can('edit_transport_allocations') || can('delete_transport_allocations')" style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in allocations" :key="a.id">
                            <td>
                                <p style="font-weight:600;color:#111827;">{{ a.student?.user?.name || [a.student?.first_name, a.student?.last_name].filter(Boolean).join(' ') || '—' }}</p>
                                <p style="font-size:0.75rem;color:#9ca3af;">{{ a.student?.admission_no }}</p>
                            </td>
                            <td>{{ a.route?.route_name || '—' }}</td>
                            <td>{{ a.stop?.stop_name || '—' }}</td>
                            <td style="font-family:monospace;">{{ a.vehicle?.vehicle_number || '—' }}</td>
                            <td style="text-align:center;"><span class="badge badge-blue">{{ pickupLabel(a.pickup_type) }}</span></td>
                            <td style="text-align:center;font-weight:600;color:var(--success);">₹{{ a.transport_fee }}</td>
                            <td style="text-align:center;">
                                <span :class="a.status === 'active' ? 'badge badge-green' : 'badge badge-gray'" style="text-transform:capitalize;">{{ a.status }}</span>
                            </td>
                            <td v-if="can('edit_transport_allocations') || can('delete_transport_allocations')" style="text-align:right;">
                                <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.5rem;">
                                    <Button variant="secondary" size="xs" v-if="can('edit_transport_allocations')" @click="openModal(a)">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </Button>
                                    <Button variant="danger" size="xs" v-if="can('delete_transport_allocations')" @click="destroy(a)">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </Table>
                <div v-else style="text-align:center;padding:4rem 0;color:#9ca3af;">
                    <svg class="w-12 h-12" style="margin:0 auto 0.75rem;color:#e5e7eb;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <p style="font-size:0.875rem;">No students assigned yet.</p>
                </div>
            </div>
        </div>

        <!-- Assignment Modal -->
        <Teleport to="body">
        <div v-if="showModal" class="modal-backdrop" @mousedown.self="showModal = false">
            <div class="modal">
                <div class="card-header" style="justify-content:space-between;">
                    <span class="card-title">{{ editingItem ? 'Edit Allocation' : 'Assign Student' }}</span>
                    <Button variant="secondary" size="xs" @click="showModal = false">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </Button>
                </div>
                <div class="card-body">
                    <form @submit.prevent="save">
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

                            <div v-if="form.class_id" class="form-field">
                                <label style="display:flex;align-items:center;justify-content:space-between;">
                                    <span>Select Students ({{ students.length }} available) *</span>
                                    <label v-if="students.length > 0" style="display:flex;align-items:center;gap:0.375rem;cursor:pointer;font-weight:400;">
                                        <input type="checkbox" @change="toggleAllStudents" :checked="students.length > 0 && form.student_ids.length === students.length">
                                        <span>Select All</span>
                                    </label>
                                </label>
                                <div style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.75rem;max-height:12rem;overflow-y:auto;background:#f9fafb;display:flex;align-items:center;justify-content:center;font-size:0.875rem;color:#6b7280;" v-if="fetchingStudents">
                                    Loading students...
                                </div>
                                <div v-else-if="students.length === 0" style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.75rem;max-height:12rem;overflow-y:auto;background:#f9fafb;display:flex;align-items:center;justify-content:center;font-size:0.875rem;color:#6b7280;">
                                    No students found.
                                </div>
                                <div v-else style="border:1px solid #e5e7eb;border-radius:0.5rem;max-height:12rem;overflow-y:auto;background:#fff;divide-y:1px solid #f3f4f6;">
                                    <label v-for="s in students" :key="s.id" style="display:flex;align-items:center;gap:0.75rem;padding:0.625rem;cursor:pointer;">
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
                        <div class="form-row-2">
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

                        <!-- Months + Days (term the student opts for) -->
                        <div class="form-row-2">
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
                        <div v-if="stopFee > 0" style="padding:0.625rem 0.875rem;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.3);border-radius:0.5rem;font-size:0.8125rem;color:#065f46;margin-bottom:0.75rem;line-height:1.5;">
                            <div>Stop fee: <strong>₹{{ stopFee }}</strong> <span style="color:#6b7280;">(for {{ standardMonths }} months)</span></div>
                            <div>Student opts for: <strong>{{ form.months || 0 }} months{{ form.days ? ' + ' + form.days + ' days' : '' }}</strong> = <strong>{{ monthsOpted }} months</strong></div>
                            <div style="margin-top:0.25rem;padding-top:0.25rem;border-top:1px dashed rgba(16,185,129,0.3);">
                                Transport fee: <strong style="color:var(--success);font-size:0.95rem;">₹{{ computedFee }}</strong>
                            </div>
                        </div>
                        <div v-if="termTooShort" style="padding:0.5rem 0.75rem;background:#fef3c7;border:1px solid #fcd34d;border-radius:0.5rem;font-size:0.8125rem;color:#92400e;margin-bottom:0.75rem;">
                            Minimum term is 15 days (0.5 months).
                        </div>

                        <!-- Vehicle + Pickup Type -->
                        <div class="form-row-2">
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
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Start Date</label>
                                <input v-model="form.start_date" type="date">
                            </div>
                            <div class="form-field">
                                <label>End Date</label>
                                <input v-model="form.end_date" type="date">
                            </div>
                        </div>

                        <div style="display:flex;justify-content:flex-end;gap:0.75rem;padding-top:0.5rem;">
                            <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                            <Button type="submit" :disabled="saving || form.student_ids.length === 0 || termTooShort || monthsOpted === 0">
                                {{ saving ? 'Saving…' : (editingItem ? 'Update' : `Assign (${form.student_ids.length})`) }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </Teleport>
    </SchoolLayout>
</template>

<style scoped>
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.25rem; }
@media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }
.modal-backdrop {
    position: fixed; inset: 0; background: rgba(15,23,42,.5);
    display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;
}
.modal {
    background: var(--surface); border-radius: 0.75rem; width: 100%; max-width: 32rem;
    max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
</style>
