<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import WebcamCapture from '@/Components/WebcamCapture.vue';
import VisitorPassCard from '@/Components/VisitorPassCard.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    visitors: Object,
    students: Array,
    staff: Array,
    filters: Object,
});

const showModal = ref(false);
const showWebcam = ref(false);
const showPassCard = ref(false);
const editing = ref(null);
const selectedVisitor = ref(null);
const webcamVisitorId = ref(null);
const loading = ref(false);

const form = reactive({
    meet_user_type: 'Student', student_id: '', staff_id: '', visitor_type: 'Parent', visitor_count: 1,
    visitor_name: '', relation: '', phone: '', date: '', in_time: '', purpose: '',
    id_proof: '', id_proof_type: 'Aadhaar', out_time: '', remarks: ''
});

function openModal(item = null) {
    editing.value = item;
    if (item) {
        Object.assign(form, {
            out_time: new Date().toTimeString().substring(0, 5), remarks: item.remarks || ''
        });
    } else {
        Object.assign(form, {
            meet_user_type: 'Student', student_id: '', staff_id: '', visitor_type: 'Parent', visitor_count: 1,
            visitor_name: '', relation: '', phone: '',
            date: new Date().toISOString().split('T')[0],
            in_time: new Date().toTimeString().substring(0, 5),
            purpose: '', id_proof: '', id_proof_type: 'Aadhaar'
        });
    }
    showModal.value = true;
}

function save() {
    loading.value = true;
    if (editing.value) {
        router.put(`/school/hostel/visitors/${editing.value.id}`, form, {
            onSuccess: () => showModal.value = false, onFinish: () => loading.value = false
        });
    } else {
        router.post(`/school/hostel/visitors`, form, {
            onSuccess: () => showModal.value = false, onFinish: () => loading.value = false
        });
    }
}

function openWebcam(visitor) {
    webcamVisitorId.value = visitor.id;
    showWebcam.value = true;
}

function onPhotoCaptured(base64Data) {
    showWebcam.value = false;
    router.post(`/school/hostel/visitors/${webcamVisitorId.value}/photo`, {
        photo_data: base64Data,
    }, { preserveScroll: true });
}

function viewPass(visitor) {
    selectedVisitor.value = visitor;
    showPassCard.value = true;
}
</script>

<template>
    <SchoolLayout title="Hostel Visitors">

        <div class="page-header">
            <div>
                <h1 class="page-header-title">Visitor Logs</h1>
                <p class="page-header-sub">Manage hostel visitor entry & exit with photo verification.</p>
            </div>
            <Button @click="openModal()">+ Add Visitor</Button>
        </div>

        <!-- Table -->
        <div class="card">
            <div style="overflow-x: auto;">
                <Table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Visitor</th>
                            <th>Type & Purpose</th>
                            <th>Whom to Meet</th>
                            <th>Entry / Exit</th>
                            <th>Photo</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="v in visitors.data" :key="v.id">
                            <td style="font-weight: 500;">{{ v.date }}</td>
                            <td>
                                <div style="font-weight: 600;">{{ v.visitor_name }}
                                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 400;">({{ v.visitor_count }} pax)</span>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ v.relation || 'N/A' }} &middot; {{ v.phone || 'N/A' }}</div>
                                <div v-if="v.id_proof_type" style="font-size: 0.75rem; color: var(--text-muted);">ID: {{ v.id_proof_type }}</div>
                            </td>
                            <td>
                                <div style="font-weight: 600;">{{ v.visitor_type || 'Visitor' }}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ v.purpose || 'N/A' }}</div>
                            </td>
                            <td>
                                <span v-if="v.meet_user_type === 'Student' && v.student">
                                    {{ v.student.first_name }} {{ v.student.last_name }}
                                    <span class="badge badge-blue" style="margin-left: 0.25rem;">Student</span>
                                </span>
                                <span v-else-if="v.meet_user_type === 'Staff' && v.staff?.user">
                                    {{ v.staff.user.name }}
                                    <span class="badge badge-purple" style="margin-left: 0.25rem;">Staff</span>
                                </span>
                            </td>
                            <td style="font-size: 0.8rem;">
                                <div>In: {{ v.in_time }}</div>
                                <div v-if="v.out_time">Out: {{ v.out_time }}</div>
                                <span v-else class="badge badge-amber">Still Inside</span>
                            </td>
                            <td>
                                <img v-if="v.visitor_photo" :src="'/storage/' + v.visitor_photo" class="visitor-avatar">
                                <div v-else class="visitor-avatar-placeholder">?</div>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-btns">
                                    <Button variant="secondary" size="xs" @click="viewPass(v)">Pass</Button>
                                    <Button variant="secondary" size="xs" @click="openWebcam(v)">Photo</Button>
                                    <Button variant="danger" size="xs" v-if="!v.out_time" @click="openModal(v)">Mark Out</Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!visitors.data.length">
                            <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No visitors logged today. Click "+ Add Visitor" to log one.
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <!-- ADD / MARK OUT MODAL -->
        <Teleport to="body">
        <div v-if="showModal" class="modal-backdrop" @mousedown.self="showModal = false">
            <div class="modal">
                <div class="card-header" style="position: sticky; top: 0; background: var(--surface); z-index: 10;">
                    <h3 class="card-title">{{ editing ? 'Mark Visitor Out' : 'New Visitor Log' }}</h3>
                    <button @click="showModal = false" class="modal-close">&times;</button>
                </div>
                <div class="card-body">
                    <form @submit.prevent="save">
                        <template v-if="!editing">
                            <!-- Whom to Meet -->
                            <div class="form-row-2">
                                <div class="form-field">
                                    <label>Whom to Meet</label>
                                    <select v-model="form.meet_user_type">
                                        <option value="Student">Student</option>
                                        <option value="Staff">Staff</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label>Select Person *</label>
                                    <select v-if="form.meet_user_type === 'Student'" v-model="form.student_id" required>
                                        <option value="">Select Student</option>
                                        <option v-for="s in students" :key="s.id" :value="s.id">{{ s.first_name }} {{ s.last_name }}</option>
                                    </select>
                                    <select v-if="form.meet_user_type === 'Staff'" v-model="form.staff_id" required>
                                        <option value="">Select Staff</option>
                                        <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.user?.name }} ({{ s.employee_id }})</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Visitor Info -->
                            <div class="form-row-2" style="margin-top: 1rem;">
                                <div class="form-field" style="grid-column: 1 / -1;">
                                    <label>Visitor Name *</label>
                                    <input v-model="form.visitor_name" required>
                                </div>
                            </div>
                            <div class="form-row-2" style="margin-top: 0.75rem;">
                                <div class="form-field">
                                    <label>Pax Count</label>
                                    <input type="number" min="1" v-model="form.visitor_count" required>
                                </div>
                                <div class="form-field">
                                    <label>Visitor Type</label>
                                    <input v-model="form.visitor_type" placeholder="Parent, Official...">
                                </div>
                            </div>
                            <div class="form-row-2" style="margin-top: 0.75rem;">
                                <div class="form-field">
                                    <label>Relation</label>
                                    <input v-model="form.relation">
                                </div>
                                <div class="form-field">
                                    <label>Purpose of Visit</label>
                                    <input v-model="form.purpose">
                                </div>
                            </div>
                            <div class="form-row-2" style="margin-top: 0.75rem;">
                                <div class="form-field">
                                    <label>Phone</label>
                                    <input v-model="form.phone">
                                </div>
                                <div class="form-field">
                                    <label>Entry Time *</label>
                                    <input v-model="form.in_time" type="time" required>
                                </div>
                            </div>
                            <div class="form-row-2" style="margin-top: 0.75rem;">
                                <div class="form-field">
                                    <label>ID Proof Type</label>
                                    <select v-model="form.id_proof_type">
                                        <option>Aadhaar</option><option>Driving License</option><option>PAN Card</option>
                                        <option>Passport</option><option>Voter ID</option><option>Other</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label>ID Number</label>
                                    <input v-model="form.id_proof">
                                </div>
                            </div>
                        </template>

                        <!-- Mark Out -->
                        <template v-else>
                            <div class="form-row">
                                <div class="form-field">
                                    <label>Out Time</label>
                                    <input v-model="form.out_time" type="time" required>
                                </div>
                            </div>
                            <div class="form-row" style="margin-top: 1rem;">
                                <div class="form-field">
                                    <label>Remarks</label>
                                    <textarea v-model="form.remarks" rows="2"></textarea>
                                </div>
                            </div>
                        </template>

                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                            <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                            <Button type="submit" :loading="loading">
                                {{ (editing ? 'Mark Out' : 'Log Visitor') }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- VISITOR PASS + QR -->
        <Teleport to="body">
        <div v-if="showPassCard && selectedVisitor" class="modal-backdrop" @mousedown.self="showPassCard = false">
            <div class="modal" style="max-width: 28rem;">
                <div class="card-header">
                    <h3 class="card-title">Visitor Pass</h3>
                    <button @click="showPassCard = false" class="modal-close">&times;</button>
                </div>
                <div class="card-body">
                    <VisitorPassCard :visitor="selectedVisitor" />
                </div>
            </div>
        </div>
        </Teleport>

        <!-- WEBCAM CAPTURE -->
        <WebcamCapture
            v-if="showWebcam"
            title="Capture Visitor Photo"
            @captured="onPhotoCaptured"
            @close="showWebcam = false"
        />
    </SchoolLayout>
</template>

<style scoped>
.action-btns { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 0.25rem; }

.visitor-avatar {
    width: 2.5rem; height: 2.5rem; border-radius: 9999px;
    object-fit: cover; border: 2px solid var(--border);
}
.visitor-avatar-placeholder {
    width: 2.5rem; height: 2.5rem; border-radius: 9999px;
    background: var(--bg); display: flex; align-items: center; justify-content: center;
    color: var(--text-muted); font-size: 1rem; font-weight: 600;
}

/* Modal system */
.modal-backdrop {
    position: fixed; inset: 0; background: rgba(15,23,42,.5);
    display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;
}
.modal {
    background: var(--surface); border-radius: 0.75rem; width: 100%; max-width: 32rem;
    max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-close {
    background: none; border: none; font-size: 1.5rem; color: var(--text-muted);
    cursor: pointer; line-height: 1;
}
.modal-close:hover { color: var(--text-primary); }
</style>
