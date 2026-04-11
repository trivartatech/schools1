<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, reactive, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    allocations: Object,
    availableBeds: Array,
    students: Array,
});

const showModal    = ref(false);
const showVacate   = ref(false);
const showTransfer = ref(false);
const editing      = ref(null);
const loading      = ref(false);
const errors       = ref({});

const form = reactive({
    student_id: '', hostel_bed_id: '', admission_date: '', guardian_name: '', guardian_phone: '',
    guardian_relation: '', medical_info: '', mess_type: 'Veg'
});
const vacateForm = reactive({ vacate_date: '' });
const transferForm = reactive({ new_bed_id: '' });

watch(() => form.student_id, (newId) => {
    if (newId && !editing.value) { // Only auto-fill if not editing manually
        const st = props.students?.find(s => s.id === newId);
        if (st && st.student_parent) {
            let p = st.student_parent;
            form.guardian_name = p.guardian_name || p.father_name || p.mother_name || '';
            form.guardian_phone = p.primary_phone || p.father_phone || '';
            form.guardian_relation = p.guardian_name ? 'Guardian' : (p.father_name ? 'Father' : 'Mother');
        } else {
            form.guardian_name = ''; form.guardian_phone = ''; form.guardian_relation = '';
        }
    }
});

const hasBeds = () => props.availableBeds && props.availableBeds.length > 0;

function openModal() {
    editing.value = null;
    errors.value  = {};
    Object.assign(form, {
        student_id:       '',
        hostel_bed_id:    hasBeds() ? props.availableBeds[0].id : '',
        admission_date:   new Date().toISOString().split('T')[0],
        guardian_name:    '',
        guardian_phone:   '',
        guardian_relation:'',
        medical_info:     '',
        mess_type:        'Veg',
    });
    showModal.value = true;
}

function save() {
    loading.value = true;
    errors.value  = {};
    router.post(`/school/hostel/allocations`, form, {
        onSuccess: () => { showModal.value = false; },
        onError:   (e) => { errors.value = e; },
        onFinish:  () => { loading.value = false; },
    });
}

function openVacate(allocation) {
    editing.value          = allocation;
    vacateForm.vacate_date = new Date().toISOString().split('T')[0];
    showVacate.value = true;
}

function saveVacate() {
    loading.value = true;
    router.post(`/school/hostel/allocations/${editing.value.id}/vacate`, vacateForm, {
        onSuccess: () => { showVacate.value = false; },
        onFinish:  () => { loading.value = false; },
    });
}

function openTransfer(allocation) {
    editing.value = allocation;
    transferForm.new_bed_id = '';
    errors.value = {};
    showTransfer.value = true;
}

function saveTransfer() {
    loading.value = true;
    errors.value = {};
    router.post(`/school/hostel/allocations/${editing.value.id}/transfer`, transferForm, {
        onSuccess: () => { showTransfer.value = false; },
        onError: (e) => { errors.value = e; },
        onFinish: () => { loading.value = false; },
    });
}
</script>

<template>
    <SchoolLayout title="Student Allocations">

        <div class="page-header">
            <div>
                <h1 class="page-header-title">Hostel Admissions</h1>
                <p class="page-header-sub">Manage student room allocations across hostel buildings.</p>
            </div>
            <Button @click="openModal()">+ Allocate Student</Button>
        </div>

        <!-- No beds warning -->
        <div v-if="!availableBeds || !availableBeds.length" class="warning-banner">
            No available beds found. Please add rooms and beds before allocating students.
        </div>

        <div class="card">
            <div style="overflow-x: auto;">
                <Table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Hostel / Room / Bed</th>
                            <th>Admission Date</th>
                            <th>Guardian Info</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in allocations.data" :key="a.id">
                            <td style="font-weight: 500;">
                                {{ a.student.first_name }} {{ a.student.last_name }}
                                <span class="badge badge-gray" style="margin-left: 0.25rem;">{{ a.student.admission_no }}</span>
                            </td>
                            <td v-if="a.bed">{{ a.bed.room.hostel.name }} / Rm {{ a.bed.room.room_number }} / {{ a.bed.name }}</td>
                            <td v-else><span class="badge badge-amber">Unassigned</span></td>
                            <td>{{ a.admission_date }}</td>
                            <td>
                                <div style="font-weight: 500;">{{ a.guardian_name }}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ a.guardian_phone }}</div>
                            </td>
                            <td>
                                <span class="badge" :class="a.status === 'Active' ? 'badge-green' : 'badge-gray'">{{ a.status }}</span>
                            </td>
                            <td style="text-align: right;">
                                <div v-if="a.status === 'Active'" style="display:flex;gap:4px;justify-content:flex-end;">
                                    <Button variant="secondary" size="xs" @click="openTransfer(a)">Transfer</Button>
                                    <Button variant="danger" size="xs" @click="openVacate(a)">Vacate</Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!allocations.data.length">
                            <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">No allocations found.</td>
                        </tr>
                    </tbody>
                </Table>
            </div>

            <!-- Pagination -->
            <div v-if="allocations.last_page > 1"
                 style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-top:1px solid var(--border);font-size:0.82rem;color:var(--text-muted);">
                <span>Showing {{ allocations.from }}–{{ allocations.to }} of {{ allocations.total }}</span>
                <div style="display:flex;gap:4px;">
                    <Button v-for="link in allocations.links" :key="link.label"
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

        <!-- ALLOCATE MODAL -->
        <Teleport to="body">
        <div v-if="showModal" class="modal-backdrop" @mousedown.self="showModal = false">
            <div class="modal">
                <div class="card-header">
                    <h3 class="card-title">Allocate Room to Student</h3>
                </div>
                <div class="card-body">
                    <form @submit.prevent="save">
                        <!-- Server errors -->
                        <div v-if="Object.keys(errors).length"
                             style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;font-size:0.82rem;color:#dc2626;margin-bottom:14px;">
                            <div v-for="(msg, field) in errors" :key="field">{{ msg }}</div>
                        </div>

                        <!-- No beds warning inside modal -->
                        <div v-if="!availableBeds || !availableBeds.length" class="warning-banner" style="margin-bottom:14px;">
                            No available beds. Please add rooms first.
                        </div>

                        <div class="form-row">
                            <div class="form-field">
                                <label>Student *</label>
                                <select v-model="form.student_id" required>
                                    <option value="">Select Student</option>
                                    <option v-for="s in students" :key="s.id" :value="s.id">{{ s.first_name }} {{ s.last_name }} ({{ s.admission_no }})</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row" style="margin-top: 1rem;">
                            <div class="form-field">
                                <label>Available Bed *</label>
                                <select v-model="form.hostel_bed_id" required>
                                    <option v-for="b in availableBeds" :key="b.id" :value="b.id">{{ b.room.hostel.name }} / Rm {{ b.room.room_number }} / {{ b.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row" style="margin-top: 1rem;">
                            <div class="form-field">
                                <label>Admission Date *</label>
                                <input v-model="form.admission_date" type="date" required>
                            </div>
                        </div>
                        <div class="form-row-2" style="margin-top: 1rem;">
                            <div class="form-field">
                                <label>Guardian Name</label>
                                <input v-model="form.guardian_name">
                            </div>
                            <div class="form-field">
                                <label>Guardian Phone</label>
                                <input v-model="form.guardian_phone">
                            </div>
                        </div>
                        <div class="form-row" style="margin-top: 1rem;">
                            <div class="form-field">
                                <label>Mess Type</label>
                                <select v-model="form.mess_type" required>
                                    <option>Veg</option>
                                    <option>Non-Veg</option>
                                    <option>Custom</option>
                                    <option>None</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                            <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                            <Button type="submit" :loading="loading">Allocate</Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        </Teleport>

        <!-- VACATE MODAL -->
        <Teleport to="body">
        <div v-if="showVacate" class="modal-backdrop" @mousedown.self="showVacate = false">
            <div class="modal" style="max-width: 24rem;">
                <div class="card-header">
                    <h3 class="card-title">Vacate Room</h3>
                </div>
                <div class="card-body">
                    <form @submit.prevent="saveVacate">
                        <div class="form-row">
                            <div class="form-field">
                                <label>Vacate Date *</label>
                                <input v-model="vacateForm.vacate_date" type="date" required>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                            <Button variant="secondary" type="button" @click="showVacate = false">Cancel</Button>
                            <Button variant="danger" type="submit" :loading="loading">Confirm Vacate</Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- TRANSFER MODAL -->
        <Teleport to="body">
        <div v-if="showTransfer" class="modal-backdrop" @mousedown.self="showTransfer = false">
            <div class="modal" style="max-width: 28rem;">
                <div class="card-header">
                    <h3 class="card-title">Transfer Room</h3>
                </div>
                <div class="card-body">
                    <form @submit.prevent="saveTransfer">
                        <div v-if="Object.keys(errors).length"
                             style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;font-size:0.82rem;color:#dc2626;margin-bottom:14px;">
                            <div v-for="(msg, field) in errors" :key="field">{{ msg }}</div>
                        </div>
                        <div v-if="editing" style="margin-bottom:14px;padding:10px 14px;background:#f8fafc;border-radius:8px;font-size:.84rem;">
                            <strong>{{ editing.student.first_name }} {{ editing.student.last_name }}</strong>
                            <span style="color:var(--text-muted);margin-left:6px;">{{ editing.student.admission_no }}</span>
                            <div v-if="editing.bed" style="color:#64748b;font-size:.78rem;margin-top:4px;">
                                Current: {{ editing.bed.room.hostel.name }} / Rm {{ editing.bed.room.room_number }} / {{ editing.bed.name }}
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-field">
                                <label>New Bed *</label>
                                <select v-model="transferForm.new_bed_id" required>
                                    <option value="">Select new bed</option>
                                    <option v-for="b in availableBeds" :key="b.id" :value="b.id">{{ b.room.hostel.name }} / Rm {{ b.room.room_number }} / {{ b.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                            <Button variant="secondary" type="button" @click="showTransfer = false">Cancel</Button>
                            <Button type="submit" :disabled="loading || !transferForm.new_bed_id">{{ loading ? 'Transferring...' : 'Transfer' }}</Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </Teleport>

    </SchoolLayout>
</template>

<style scoped>
.warning-banner {
    background: #fffbeb; border: 1px solid #fcd34d; border-radius: var(--radius);
    padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.84rem; color: #92400e;
}
.modal-backdrop {
    position: fixed; inset: 0; background: rgba(15,23,42,.5);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.modal {
    background: #fff; border-radius: 0.75rem; width: 100%; max-width: 32rem;
    max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
</style>

