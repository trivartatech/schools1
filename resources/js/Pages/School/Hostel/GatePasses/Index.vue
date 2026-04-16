<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import WebcamCapture from '@/Components/WebcamCapture.vue';
import GatePassCard from '@/Components/GatePassCard.vue';
import Table from '@/Components/ui/Table.vue';

/** Local datetime string for datetime-local input (avoids UTC day-shift). */
function localISODT(d = new Date()) {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}T${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
}

const props = defineProps({
    gatePasses: Object,
    students: Array,
});

// ----- Modal State -----
const showModal = ref(false);
const showCreateModal = ref(false);
const showOtpModal = ref(false);
const showViewModal = ref(false);
const showPassCard = ref(false);
const showWebcam = ref(false);
const webcamContext = reactive({ photoType: '', passId: null, title: '' });

const selectedPass = ref(null);
const createLoading = ref(false);
const otpInput = ref('');
const actionData = reactive({ status: '', actual_out_time: '', actual_in_time: '', late_reason: '' });

const createForm = reactive({
    student_id: '', leave_type: 'Day Out', from_date: '', to_date: '', reason: '', destination: '',
    escort_name: '', escort_relation: 'Father', escort_phone: '', escort_id_proof_type: 'Aadhaar', parent_name: ''
});

const statusBadge = {
    Pending: 'badge-amber',
    Approved: 'badge-blue',
    Rejected: 'badge-red',
    Out: 'badge-amber',
    Returned: 'badge-green',
};
const parentBadge = {
    Pending: 'badge-gray',
    Approved: 'badge-green',
    Rejected: 'badge-red',
};

// ----- Create Gate Pass -----
function openCreateModal() {
    const now = new Date();
    const later = new Date(now.getTime() + 8 * 3600000);
    Object.assign(createForm, {
        student_id: '', leave_type: 'Day Out',
        from_date: localISODT(now), to_date: localISODT(later),
        reason: '', destination: '', escort_name: '', escort_relation: 'Father',
        escort_phone: '', escort_id_proof_type: 'Aadhaar', parent_name: ''
    });
    showCreateModal.value = true;
}
function saveGatePass() {
    createLoading.value = true;
    router.post('/school/hostel/gate-passes', createForm, {
        onSuccess: () => showCreateModal.value = false,
        onFinish: () => createLoading.value = false
    });
}

// ----- Status Actions -----
function updateStatus(gp, status) {
    selectedPass.value = gp;
    actionData.status = status;
    if (status === 'Out' || status === 'Returned') {
        actionData.actual_out_time = localISODT();
        actionData.actual_in_time = localISODT();
        actionData.late_reason = '';
        showModal.value = true;
    } else {
        router.patch(`/school/hostel/gate-passes/${gp.id}/status`, { status }, { preserveScroll: true });
    }
}
function saveStatusUpdate() {
    router.patch(`/school/hostel/gate-passes/${selectedPass.value.id}/status`, actionData, {
        onSuccess: () => showModal.value = false
    });
}

// ----- OTP -----
function openOtpModal(gp) {
    selectedPass.value = gp;
    otpInput.value = '';
    showOtpModal.value = true;
}
function sendOtp(gp) {
    router.post(`/school/hostel/gate-passes/${gp.id}/send-otp`, {}, { preserveScroll: true });
}
function verifyOtp() {
    router.post(`/school/hostel/gate-passes/${selectedPass.value.id}/verify-otp`, { otp: otpInput.value }, {
        onSuccess: () => { showOtpModal.value = false; otpInput.value = ''; }
    });
}

// ----- View / Pass Card -----
function viewPass(gp) { selectedPass.value = gp; showViewModal.value = true; }
function openPassCard(gp) { selectedPass.value = gp; showPassCard.value = true; }

// ----- Photo Capture -----
function openWebcam(gp, photoType, title) {
    selectedPass.value = gp;
    webcamContext.photoType = photoType;
    webcamContext.passId = gp.id;
    webcamContext.title = title;
    showWebcam.value = true;
}
function onPhotoCaptured(base64Data) {
    showWebcam.value = false;
    router.post(`/school/hostel/gate-passes/${webcamContext.passId}/photo`, {
        photo_type: webcamContext.photoType,
        photo_data: base64Data,
    }, { preserveScroll: true });
}

// ----- Delete -----
function destroy(gp) {
    if (confirm('Delete this gate pass?')) {
        router.delete(`/school/hostel/gate-passes/${gp.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <SchoolLayout title="Gate Passes">

        <div class="page-header">
            <div>
                <h1 class="page-header-title">Gate Passes</h1>
                <p class="page-header-sub">Student leave & exit management with escort tracking.</p>
            </div>
            <Button @click="openCreateModal">+ New Gate Pass</Button>
        </div>

        <!-- Table -->
        <div class="card">
            <div style="overflow-x: auto;">
                <Table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Type & Duration</th>
                            <th>Escort</th>
                            <th>Parent</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="gp in gatePasses.data" :key="gp.id">
                            <td>
                                <div style="font-weight: 600;">{{ gp.student?.first_name }} {{ gp.student?.last_name }}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ gp.student?.admission_no }}</div>
                            </td>
                            <td>
                                <div style="font-weight: 600;">{{ gp.leave_type }}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">Out: {{ gp.from_date?.slice(0,16) }}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">In: {{ gp.to_date?.slice(0,16) }}</div>
                            </td>
                            <td>
                                <div v-if="gp.escort_name">
                                    <div style="font-weight: 500;">{{ gp.escort_name }}</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">{{ gp.escort_relation }} &middot; {{ gp.escort_phone }}</div>
                                </div>
                                <span v-else style="font-size: 0.75rem; color: var(--text-muted); font-style: italic;">No escort</span>
                            </td>
                            <td>
                                <span class="badge" :class="parentBadge[gp.parent_approval] || 'badge-gray'">
                                    {{ gp.parent_approval || 'N/A' }}
                                </span>
                                <div v-if="gp.parent_otp_verified" style="font-size: 0.75rem; color: var(--success); margin-top: 0.25rem;">OTP Verified</div>
                            </td>
                            <td>
                                <span class="badge" :class="statusBadge[gp.status] || 'badge-gray'">{{ gp.status }}</span>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-btns">
                                    <Button variant="secondary" size="xs" @click="viewPass(gp)">Details</Button>
                                    <Button variant="secondary" size="xs" @click="openPassCard(gp)">Pass+QR</Button>
                                    <template v-if="gp.status === 'Pending'">
                                        <Button size="xs" @click="updateStatus(gp, 'Approved')">Approve</Button>
                                        <Button variant="danger" size="xs" @click="updateStatus(gp, 'Rejected')">Reject</Button>
                                    </template>
                                    <template v-if="gp.status === 'Approved'">
                                        <Button variant="secondary" size="xs" v-if="!gp.parent_otp_verified" @click="sendOtp(gp)">Send OTP</Button>
                                        <Button variant="secondary" size="xs" v-if="!gp.parent_otp_verified" @click="openOtpModal(gp)">Verify OTP</Button>
                                        <Button variant="secondary" size="xs" @click="updateStatus(gp, 'Out')">Mark Out</Button>
                                    </template>
                                    <template v-if="gp.status === 'Out'">
                                        <Button variant="secondary" size="xs" @click="openWebcam(gp, 'student_exit_photo', 'Capture Student Photo')">Student Photo</Button>
                                        <Button variant="secondary" size="xs" @click="openWebcam(gp, 'escort_exit_photo', 'Capture Escort Photo')">Escort Photo</Button>
                                        <Button variant="success" size="xs" @click="updateStatus(gp, 'Returned')">Mark Returned</Button>
                                    </template>
                                    <template v-if="gp.status === 'Returned'">
                                        <Button variant="secondary" size="xs" @click="openWebcam(gp, 'student_return_photo', 'Return Photo')">Return Photo</Button>
                                    </template>
                                    <Button variant="danger" size="xs" @click="destroy(gp)">Delete</Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!gatePasses.data.length">
                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No gate passes found. Click "+ New Gate Pass" to create one.
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <!-- ===== CREATE MODAL ===== -->
        <Teleport to="body">
        <div v-if="showCreateModal" class="modal-backdrop" @mousedown.self="showCreateModal = false">
            <div class="modal modal-lg">
                <div class="card-header" style="position: sticky; top: 0; background: var(--surface); z-index: 10;">
                    <h3 class="card-title">New Gate Pass Request</h3>
                    <button @click="showCreateModal = false" class="modal-close">&times;</button>
                </div>
                <div class="card-body">
                    <form @submit.prevent="saveGatePass">
                        <div class="form-row">
                            <div class="form-field">
                                <label>Student *</label>
                                <select v-model="createForm.student_id" required>
                                    <option value="">Select Hostel Student</option>
                                    <option v-for="s in students" :key="s.id" :value="s.id">{{ s.first_name }} {{ s.last_name }} ({{ s.admission_no }})</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row-2" style="margin-top: 1rem;">
                            <div class="form-field">
                                <label>Leave Type *</label>
                                <select v-model="createForm.leave_type" required>
                                    <option>Day Out</option><option>Night Out</option><option>Home Time</option><option>Emergency</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Destination</label>
                                <input v-model="createForm.destination" placeholder="City, Home, Hospital...">
                            </div>
                        </div>
                        <div class="form-row-2" style="margin-top: 1rem;">
                            <div class="form-field">
                                <label>From *</label>
                                <input v-model="createForm.from_date" type="datetime-local" required>
                            </div>
                            <div class="form-field">
                                <label>Return By *</label>
                                <input v-model="createForm.to_date" type="datetime-local" required>
                            </div>
                        </div>
                        <div class="form-row" style="margin-top: 1rem;">
                            <div class="form-field">
                                <label>Reason *</label>
                                <textarea v-model="createForm.reason" required rows="2"></textarea>
                            </div>
                        </div>

                        <!-- Escort Section -->
                        <div class="info-section info-section--blue" style="margin-top: 1rem;">
                            <p class="section-heading" style="margin-bottom: 0.75rem;">Escort / Pickup Person</p>
                            <div class="form-row-2">
                                <div class="form-field">
                                    <label>Escort Name</label>
                                    <input v-model="createForm.escort_name" placeholder="Full name">
                                </div>
                                <div class="form-field">
                                    <label>Relationship</label>
                                    <select v-model="createForm.escort_relation">
                                        <option>Father</option><option>Mother</option><option>Guardian</option>
                                        <option>Sibling</option><option>Relative</option><option>Driver</option><option>Self</option><option>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row-2" style="margin-top: 0.75rem;">
                                <div class="form-field">
                                    <label>Phone</label>
                                    <input v-model="createForm.escort_phone" type="tel">
                                </div>
                                <div class="form-field">
                                    <label>ID Proof Type</label>
                                    <select v-model="createForm.escort_id_proof_type">
                                        <option>Aadhaar</option><option>Driving License</option><option>PAN Card</option><option>Passport</option><option>Voter ID</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Parent Section -->
                        <div class="info-section info-section--amber" style="margin-top: 1rem;">
                            <p class="section-heading" style="margin-bottom: 0.75rem;">Parent / Guardian</p>
                            <div class="form-row">
                                <div class="form-field">
                                    <label>Parent Name</label>
                                    <input v-model="createForm.parent_name" placeholder="Parent / guardian name">
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                            <Button variant="secondary" type="button" @click="showCreateModal = false">Cancel</Button>
                            <Button type="submit" :loading="createLoading">
                                Create Gate Pass
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- ===== STATUS MODAL (Out / Return) ===== -->
        <Teleport to="body">
        <div v-if="showModal" class="modal-backdrop" @mousedown.self="showModal = false">
            <div class="modal modal-sm">
                <div class="card-header">
                    <h3 class="card-title">{{ actionData.status === 'Out' ? 'Mark Student Out' : 'Mark Student Returned' }}</h3>
                    <button @click="showModal = false" class="modal-close">&times;</button>
                </div>
                <div class="card-body">
                    <form @submit.prevent="saveStatusUpdate">
                        <div v-if="actionData.status === 'Out'" class="form-row">
                            <div class="form-field">
                                <label>Actual Out Time</label>
                                <input v-model="actionData.actual_out_time" type="datetime-local" required>
                            </div>
                        </div>
                        <div v-if="actionData.status === 'Returned'">
                            <div class="form-row">
                                <div class="form-field">
                                    <label>Actual Return Time</label>
                                    <input v-model="actionData.actual_in_time" type="datetime-local" required>
                                </div>
                            </div>
                            <div class="form-row" style="margin-top: 1rem;">
                                <div class="form-field">
                                    <label>Late Return Reason</label>
                                    <textarea v-model="actionData.late_reason" rows="2" placeholder="Leave blank if on time"></textarea>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                            <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                            <Button type="submit">Save</Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- ===== OTP MODAL ===== -->
        <Teleport to="body">
        <div v-if="showOtpModal" class="modal-backdrop" @mousedown.self="showOtpModal = false">
            <div class="modal modal-xs" style="text-align: center;">
                <div class="card-body" style="padding: 2rem;">
                    <h3 style="font-weight: 700; font-size: 1.125rem; margin-bottom: 0.25rem;">Parent OTP Verification</h3>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1.5rem;">Enter the 6-digit OTP sent to parent's mobile</p>
                    <input v-model="otpInput" type="text" maxlength="6" inputmode="numeric" placeholder="000000" class="otp-input">
                    <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.25rem;">
                        <Button variant="secondary" @click="showOtpModal = false">Cancel</Button>
                        <Button @click="verifyOtp">Verify</Button>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- ===== VIEW DETAILS MODAL ===== -->
        <Teleport to="body">
        <div v-if="showViewModal && selectedPass" class="modal-backdrop" @mousedown.self="showViewModal = false">
            <div class="modal modal-md">
                <div class="card-header">
                    <h3 class="card-title">Gate Pass #{{ selectedPass.id }} — Details</h3>
                    <button @click="showViewModal = false" class="modal-close">&times;</button>
                </div>
                <div class="card-body">
                    <!-- Status badges -->
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.25rem;">
                        <span class="badge" :class="statusBadge[selectedPass.status]">{{ selectedPass.status }}</span>
                        <span class="badge" :class="parentBadge[selectedPass.parent_approval]">Parent: {{ selectedPass.parent_approval }}</span>
                        <span v-if="selectedPass.parent_otp_verified" class="badge badge-green">OTP Verified</span>
                    </div>

                    <!-- Details Grid -->
                    <div class="detail-grid">
                        <div><p class="detail-label">Student</p><p class="detail-value">{{ selectedPass.student?.first_name }} {{ selectedPass.student?.last_name }}</p></div>
                        <div><p class="detail-label">Leave Type</p><p class="detail-value">{{ selectedPass.leave_type }}</p></div>
                        <div><p class="detail-label">From</p><p class="detail-value">{{ selectedPass.from_date?.slice(0,16) }}</p></div>
                        <div><p class="detail-label">Return By</p><p class="detail-value">{{ selectedPass.to_date?.slice(0,16) }}</p></div>
                        <div style="grid-column: 1 / -1;"><p class="detail-label">Reason</p><p class="detail-value">{{ selectedPass.reason }}</p></div>
                        <div v-if="selectedPass.destination" style="grid-column: 1 / -1;"><p class="detail-label">Destination</p><p class="detail-value">{{ selectedPass.destination }}</p></div>
                    </div>

                    <!-- Escort Details -->
                    <div v-if="selectedPass.escort_name" class="info-section info-section--blue" style="margin-top: 1rem;">
                        <p class="section-heading" style="margin-bottom: 0.5rem;">Escort Details</p>
                        <div class="detail-grid">
                            <div><p class="detail-label">Name</p><p class="detail-value">{{ selectedPass.escort_name }}</p></div>
                            <div><p class="detail-label">Relation</p><p class="detail-value">{{ selectedPass.escort_relation }}</p></div>
                            <div><p class="detail-label">Phone</p><p class="detail-value">{{ selectedPass.escort_phone }}</p></div>
                            <div><p class="detail-label">ID Proof</p><p class="detail-value">{{ selectedPass.escort_id_proof_type }}</p></div>
                        </div>
                    </div>

                    <!-- Gate Photos -->
                    <div v-if="selectedPass.student_exit_photo || selectedPass.escort_exit_photo || selectedPass.student_return_photo" class="info-section" style="margin-top: 1rem;">
                        <p class="section-heading" style="margin-bottom: 0.5rem;">Gate Photos</p>
                        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                            <div v-if="selectedPass.student_exit_photo" class="photo-thumb">
                                <img :src="'/storage/' + selectedPass.student_exit_photo">
                                <p>Student Exit</p>
                            </div>
                            <div v-if="selectedPass.escort_exit_photo" class="photo-thumb">
                                <img :src="'/storage/' + selectedPass.escort_exit_photo">
                                <p>Escort Exit</p>
                            </div>
                            <div v-if="selectedPass.student_return_photo" class="photo-thumb">
                                <img :src="'/storage/' + selectedPass.student_return_photo">
                                <p>Return Photo</p>
                            </div>
                        </div>
                    </div>

                    <!-- Actual Times -->
                    <div v-if="selectedPass.actual_out_time || selectedPass.actual_in_time" class="info-section" style="margin-top: 1rem;">
                        <p class="section-heading" style="margin-bottom: 0.5rem;">Actual Times</p>
                        <div class="detail-grid">
                            <div><p class="detail-label">Out</p><p class="detail-value">{{ selectedPass.actual_out_time?.slice(0,16) || '---' }}</p></div>
                            <div><p class="detail-label">Returned</p><p class="detail-value">{{ selectedPass.actual_in_time?.slice(0,16) || '---' }}</p></div>
                        </div>
                        <div v-if="selectedPass.late_reason" style="margin-top: 0.5rem;"><p class="detail-label">Late Reason</p><p class="detail-value">{{ selectedPass.late_reason }}</p></div>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- ===== GATE PASS CARD + QR ===== -->
        <Teleport to="body">
        <div v-if="showPassCard && selectedPass" class="modal-backdrop" @mousedown.self="showPassCard = false">
            <div class="modal modal-sm">
                <div class="card-header">
                    <h3 class="card-title">Digital Gate Pass</h3>
                    <button @click="showPassCard = false" class="modal-close">&times;</button>
                </div>
                <div class="card-body">
                    <GatePassCard :gate-pass="selectedPass" />
                </div>
            </div>
        </div>
        </Teleport>

        <!-- ===== WEBCAM CAPTURE ===== -->
        <WebcamCapture
            v-if="showWebcam"
            :title="webcamContext.title"
            @captured="onPhotoCaptured"
            @close="showWebcam = false"
        />
    </SchoolLayout>
</template>

<style scoped>
.action-btns { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 0.25rem; }

/* Modal system */
.modal-backdrop {
    position: fixed; inset: 0; background: rgba(15,23,42,.5);
    display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;
}
.modal {
    background: var(--surface); border-radius: 0.75rem; width: 100%;
    max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-lg { max-width: 40rem; }
.modal-md { max-width: 32rem; }
.modal-sm { max-width: 28rem; }
.modal-xs { max-width: 22rem; }
.modal-close {
    background: none; border: none; font-size: 1.5rem; color: var(--text-muted);
    cursor: pointer; line-height: 1;
}
.modal-close:hover { color: var(--text-primary); }

/* Info sections */
.info-section {
    padding: 0.75rem 1rem; border-radius: var(--radius);
    background: var(--bg); border: 1px solid var(--border);
}
.info-section--blue { background: #eff6ff; border-color: #bfdbfe; }
.info-section--amber { background: #fffbeb; border-color: #fde68a; }

/* Detail grid */
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem 1.5rem; }
.detail-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }
.detail-value { font-size: 0.875rem; font-weight: 500; color: var(--text-primary); }

/* Photo thumbnails */
.photo-thumb { text-align: center; }
.photo-thumb img { width: 6rem; height: 6rem; border-radius: var(--radius); object-fit: cover; border: 1px solid var(--border); }
.photo-thumb p { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem; }

/* OTP input */
.otp-input {
    width: 100%; text-align: center; font-size: 1.75rem; letter-spacing: 0.5em;
    font-family: monospace; height: 3.5rem;
}
</style>
