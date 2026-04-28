<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Table from '@/Components/ui/Table.vue';
import { ref, reactive, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useToast } from '@/Composables/useToast';

const toast = useToast();

const props = defineProps({
    allocations: Object,
    availableBeds: Array,
    students: Array,    // fallback "all students" list (used when no class is picked)
    classes: { type: Array, default: () => [] },
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

// ── Class / Section filter state for the allocate modal ──────────────────
const filterClassId   = ref('');
const filterSectionId = ref('');
const sections        = ref([]);          // sections of the chosen class
const classStudents   = ref(null);        // students filtered by class+section (null = no filter applied yet)
const studentsLoading = ref(false);

// What the dropdown actually shows: filtered list when set, otherwise the full list.
const visibleStudents = computed(() => classStudents.value ?? props.students ?? []);

watch(filterClassId, async (classId) => {
    filterSectionId.value = '';
    sections.value        = [];
    classStudents.value   = null;
    form.student_id       = '';
    if (!classId) return;
    try {
        const [secRes, stuRes] = await Promise.all([
            axios.get(`/school/classes/${classId}/sections`),
            axios.get('/school/hostel/allocations/students-by-class', { params: { class_id: classId } }),
        ]);
        sections.value      = secRes.data || [];
        classStudents.value = stuRes.data || [];
    } catch (e) {
        console.error('Failed to load sections/students for class', e);
        toast.error('Could not load students for the selected class. Try again.');
    }
});

watch(filterSectionId, async (sectionId) => {
    if (!filterClassId.value) return;
    studentsLoading.value = true;
    form.student_id       = '';
    try {
        const { data } = await axios.get('/school/hostel/allocations/students-by-class', {
            params: { class_id: filterClassId.value, section_id: sectionId || undefined },
        });
        classStudents.value = data || [];
    } catch (e) {
        console.error('Failed to load students for section', e);
        toast.error('Could not load students for the selected section. Try again.');
    } finally {
        studentsLoading.value = false;
    }
});

watch(() => form.student_id, (newId) => {
    if (newId && !editing.value) { // Only auto-fill if not editing manually
        const st = visibleStudents.value?.find(s => s.id === newId);
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
    editing.value         = null;
    errors.value          = {};
    filterClassId.value   = '';
    filterSectionId.value = '';
    sections.value        = [];
    classStudents.value   = null;
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

        <PageHeader title="Hostel Admissions" subtitle="Manage student room allocations across hostel buildings.">
            <template #actions>
                <Button @click="openModal()">+ Allocate Student</Button>
            </template>
        </PageHeader>

        <!-- No beds warning -->
        <div v-if="!availableBeds || !availableBeds.length" class="warning-banner">
            No available beds found. Please add rooms and beds before allocating students.
        </div>

        <div class="card">
            <Table :empty="!allocations.data.length">
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
                </tbody>
                <template #empty>
                    <EmptyState
                        variant="compact"
                        title="No allocations found"
                        description="No students are currently allocated to hostel rooms."
                    />
                </template>
            </Table>

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
        <Modal v-model:open="showModal" title="Allocate Room to Student" size="lg">
            <form @submit.prevent="save" id="allocate-form">
                <!-- Server errors -->
                <div v-if="Object.keys(errors).length"
                     style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;font-size:0.82rem;color:#dc2626;margin-bottom:14px;">
                    <div v-for="(msg, field) in errors" :key="field">{{ msg }}</div>
                </div>

                <!-- No beds warning inside modal -->
                <div v-if="!availableBeds || !availableBeds.length" class="warning-banner" style="margin-bottom:14px;">
                    No available beds. Please add rooms first.
                </div>

                <!-- Filter by Class / Section -->
                <div class="form-row-2">
                    <div class="form-field">
                        <label>Filter by Class</label>
                        <select v-model="filterClassId">
                            <option value="">All Classes</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Filter by Section</label>
                        <select v-model="filterSectionId" :disabled="!filterClassId || sections.length === 0">
                            <option value="">All Sections</option>
                            <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1rem;">
                    <div class="form-field">
                        <label>Student *</label>
                        <select v-model="form.student_id" required :disabled="studentsLoading">
                            <option value="">{{ studentsLoading ? 'Loading…' : (visibleStudents.length ? 'Select Student' : 'No students match the filter') }}</option>
                            <option v-for="s in visibleStudents" :key="s.id" :value="s.id">{{ s.first_name }} {{ s.last_name }} ({{ s.admission_no }})</option>
                        </select>
                        <p class="hint" v-if="filterClassId">
                            Showing {{ visibleStudents.length }} student(s) in the selected {{ filterSectionId ? 'section' : 'class' }}.
                        </p>
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
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                <Button type="submit" form="allocate-form" :loading="loading">Allocate</Button>
            </template>
        </Modal>

        <!-- VACATE MODAL -->
        <Modal v-model:open="showVacate" title="Vacate Room" size="sm">
            <form @submit.prevent="saveVacate" id="vacate-form">
                <div class="form-row">
                    <div class="form-field">
                        <label>Vacate Date *</label>
                        <input v-model="vacateForm.vacate_date" type="date" required>
                    </div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showVacate = false">Cancel</Button>
                <Button variant="danger" type="submit" form="vacate-form" :loading="loading">Confirm Vacate</Button>
            </template>
        </Modal>

        <!-- TRANSFER MODAL -->
        <Modal v-model:open="showTransfer" title="Transfer Room" size="md">
            <form @submit.prevent="saveTransfer" id="transfer-form">
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
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showTransfer = false">Cancel</Button>
                <Button type="submit" form="transfer-form" :disabled="loading || !transferForm.new_bed_id">{{ loading ? 'Transferring...' : 'Transfer' }}</Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
.warning-banner {
    background: #fffbeb; border: 1px solid #fcd34d; border-radius: var(--radius);
    padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.84rem; color: #92400e;
}

/* Form layout — Tailwind preflight strips browser defaults from <input>/<select>,
   so explicit styles are needed to make them visible inside our modals. These
   styles are scoped to this page and applied to elements inside <Modal> via
   Vue's data-v attribute (which travels with teleported slot content). */
.form-row { display: flex; }
.form-row > .form-field { flex: 1; }
.form-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
.form-field { display: flex; flex-direction: column; gap: 0.35rem; }
.form-field label {
    font-size: 0.78rem; font-weight: 600; color: #374151;
}
.form-field input,
.form-field select,
.form-field textarea {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    background: #fff;
    color: #111827;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.form-field textarea { min-height: 80px; resize: vertical; }
.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.form-field select:disabled,
.form-field input:disabled {
    background: #f9fafb; color: #9ca3af; cursor: not-allowed;
}
.hint { font-size: 0.72rem; color: #6b7280; margin-top: 0.15rem; }
</style>
