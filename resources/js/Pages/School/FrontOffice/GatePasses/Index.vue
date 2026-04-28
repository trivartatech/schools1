<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useToast } from '@/Composables/useToast';
import Table from '@/Components/ui/Table.vue';

const { can } = usePermissions();
const toast = useToast();

const props = defineProps({
    gatePasses: { type: Array, default: () => [] }
});

const form = useForm({
    pass_type: 'Student',
    verification_method: 'Pre-approved',
    picked_up_by_name: '',
    relationship: '',
    reason: '',
    photo_base64: null,
});

const showForm = ref(false);

const videoRef = ref(null);
const canvasRef = ref(null);
const stream = ref(null);
const capturedPhoto = ref(null);
const cameraActive = ref(false);
const showCameraModal = ref(false);

const openCamera = async () => {
    showCameraModal.value = true;
    try {
        stream.value = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } });
        if (videoRef.value) {
            videoRef.value.srcObject = stream.value;
            cameraActive.value = true;
        }
    } catch (err) {
        console.error("Camera access denied:", err);
        toast.error("Camera access denied. Please grant permissions.");
        showCameraModal.value = false;
    }
};

const capturePhoto = () => {
    if (videoRef.value && canvasRef.value) {
        const context = canvasRef.value.getContext('2d');
        canvasRef.value.width = videoRef.value.videoWidth;
        canvasRef.value.height = videoRef.value.videoHeight;
        context.drawImage(videoRef.value, 0, 0);

        capturedPhoto.value = canvasRef.value.toDataURL('image/jpeg', 0.8);
        form.photo_base64 = capturedPhoto.value;
        stopCamera();
    }
};

const stopCamera = () => {
    if (stream.value) {
        stream.value.getTracks().forEach(track => track.stop());
        stream.value = null;
    }
    cameraActive.value = false;
    showCameraModal.value = false;
};

const retakePhoto = () => {
    capturedPhoto.value = null;
    form.photo_base64 = null;
    openCamera();
};

onUnmounted(() => stopCamera());

const submit = () => {
    form.post('/school/front-office/gate-passes', {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
            capturedPhoto.value = null;
        }
    });
};

const activeTab = ref('All');
const tabs = ['All', 'Pending', 'Approved', 'Exited', 'Rejected', 'Returned'];

const filteredPasses = computed(() => {
    if (activeTab.value === 'All') return props.gatePasses;
    return props.gatePasses.filter(p => p.status === activeTab.value);
});

const updateStatus = (pass, newStatus) => {
    if (newStatus === 'Rejected' || newStatus === 'Approved') {
        const notes = prompt(`Enter ${newStatus} notes (optional):`);
        if (notes === null) return;
        router.patch(`/school/front-office/gate-passes/${pass.id}/status`, { status: newStatus, approval_notes: notes }, { preserveScroll: true });
    } else {
        router.patch(`/school/front-office/gate-passes/${pass.id}/status`, { status: newStatus }, { preserveScroll: true });
    }
};
</script>

<template>
    <SchoolLayout title="Gate Passes">

        <PageHeader title="Secure Gate Pass" subtitle="Authorized exit management with identity verification.">
            <template #actions>
                <Button v-if="can('create_front_office')" @click="showForm = !showForm">
                    {{ showForm ? 'Close Form' : '+ Issue New Pass' }}
                </Button>
            </template>
        </PageHeader>

        <!-- NEW GATE PASS FORM -->
        <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="translate-y-[-20px] opacity-0"
                    enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-[-20px]">
        <div v-show="showForm" class="card mb-6">
            <div class="card-header">
                <h2 class="card-title">Issue Identity Verification Pass</h2>
            </div>
            <div class="card-body">
                <form @submit.prevent="submit">
                    <div class="gp-form-layout">
                        <!-- Left: Form Fields -->
                        <div>
                            <div class="form-row-2">
                                <div class="form-field">
                                    <label>Pass Type</label>
                                    <select v-model="form.pass_type" required>
                                        <option value="Student">Student Exit Pass</option>
                                        <option value="Visitor">Visitor Exit Pass</option>
                                        <option value="Staff">Staff Permission</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label>Verification Method</label>
                                    <select v-model="form.verification_method" required>
                                        <option value="Pre-approved">Pre-approved List</option>
                                        <option value="OTP">OTP Verification</option>
                                        <option value="Call">Call Verification</option>
                                        <option value="Manual">Manual Security Check</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label>Picked Up By (Name)</label>
                                    <input v-model="form.picked_up_by_name" type="text" placeholder="Authorized Person Name" required>
                                </div>
                                <div class="form-field">
                                    <label>Relationship</label>
                                    <input v-model="form.relationship" type="text" placeholder="e.g. Father, Uncle, Self">
                                </div>
                            </div>
                            <div class="form-row" style="margin-top:1rem;">
                                <div class="form-field">
                                    <label>Reason for Leave</label>
                                    <textarea v-model="form.reason" rows="2" placeholder="Brief reason..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Camera Capture -->
                        <div class="gp-camera-panel">
                            <label style="text-align:center;margin-bottom:.75rem;font-weight:600;">Live Photo Capture</label>

                            <div class="gp-photo-frame">
                                <img v-if="capturedPhoto" :src="capturedPhoto" class="gp-photo-img" alt="Picker" />
                                <div v-else class="gp-photo-placeholder">
                                    <span class="gp-photo-label">Verify Identity</span>
                                </div>
                                <div class="gp-photo-overlay" @click="openCamera">
                                    <span>{{ capturedPhoto ? 'Recapture' : 'Launch Camera' }}</span>
                                </div>
                            </div>

                            <Button variant="secondary" size="sm" v-if="!capturedPhoto" @click.prevent="openCamera" type="button" block class="mt-3">
                                Scan Face
                            </Button>
                            <Button variant="secondary" size="xs" v-else @click.prevent="retakePhoto" type="button" class="mt-3">
                                Clear Photo
                            </Button>
                        </div>
                    </div>

                    <div class="form-actions">
                        <Button variant="secondary" type="button" @click="showForm = false">Cancel</Button>
                        <Button type="submit" :loading="form.processing">Generate Secure Pass</Button>
                    </div>
                </form>
            </div>
        </div>
        </Transition>

        <!-- TABS -->
        <div class="tab-bar">
            <button v-for="tab in tabs" :key="tab" @click="activeTab = tab"
                    class="tab-item" :class="{ 'tab-active': activeTab === tab }">
                {{ tab }}
            </button>
        </div>

        <!-- GATE PASS TABLE -->
        <div class="card" style="overflow:hidden;">
            <Table :empty="filteredPasses.length === 0">
                <thead>
                    <tr>
                        <th>Verified Identity</th>
                        <th>Target</th>
                        <th>Type & Method</th>
                        <th style="text-align:center;">Security Status</th>
                        <th v-if="can('edit_front_office')" style="text-align:right;">Workflow</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="pass in filteredPasses" :key="pass.id">
                        <!-- Identity -->
                        <td>
                            <div class="gp-identity">
                                <img :src="pass.picker_photo_path ? `/storage/${pass.picker_photo_path}` : `https://ui-avatars.com/api/?name=${pass.picked_up_by_name}&color=6366f1&background=e0e7ff`"
                                     class="gp-identity-photo" />
                                <div>
                                    <div style="font-weight:600;">{{ pass.picked_up_by_name || 'N/A' }}</div>
                                    <div style="font-size:.75rem;color:var(--text-muted);">{{ pass.relationship || 'Self' }}</div>
                                </div>
                            </div>
                        </td>
                        <!-- Target -->
                        <td style="color:var(--text-secondary);">{{ pass.pass_type }}</td>
                        <!-- Method -->
                        <td>
                            <span class="badge badge-gray">{{ pass.verification_method }}</span>
                        </td>
                        <!-- Status -->
                        <td style="text-align:center;">
                            <span v-if="pass.status === 'Pending'" class="badge badge-amber">Pending Auth</span>
                            <span v-else-if="pass.status === 'Approved'" class="badge badge-green">Approved</span>
                            <span v-else-if="pass.status === 'Exited'" class="badge badge-blue">Exited</span>
                            <span v-else-if="pass.status === 'Returned'" class="badge badge-gray">Returned</span>
                            <span v-else class="badge badge-red">Rejected</span>
                        </td>
                        <!-- Actions -->
                        <td v-if="can('edit_front_office')" style="text-align:right;">
                            <div class="gp-actions">
                                <template v-if="pass.status === 'Pending'">
                                    <Button variant="success" size="xs" @click="updateStatus(pass, 'Approved')">Approve</Button>
                                    <Button variant="danger" size="xs" @click="updateStatus(pass, 'Rejected')">Reject</Button>
                                </template>
                                <template v-else-if="pass.status === 'Approved'">
                                    <Button size="xs" @click="updateStatus(pass, 'Exited')">Mark Exit</Button>
                                </template>
                                <template v-else-if="pass.status === 'Exited' && pass.pass_type === 'Staff'">
                                    <Button variant="secondary" size="xs" @click="updateStatus(pass, 'Returned')">Mark Returned</Button>
                                </template>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <template #empty>
                    <EmptyState
                        title="No gate passes found"
                        description="No gate passes match this criteria."
                    />
                </template>
            </Table>
        </div>

        <!-- CAMERA MODAL -->
        <Modal v-model:open="showCameraModal" title="Identity Capture" size="md" @update:open="(v) => { if (!v) stopCamera(); }">
            <div class="camera-viewport">
                <video ref="videoRef" autoplay playsinline class="camera-video" :style="{ display: cameraActive ? 'block' : 'none' }"></video>
                <canvas ref="canvasRef" style="display:none;"></canvas>
                <div v-if="!cameraActive" class="camera-loading">
                    Initializing Camera...
                </div>
                <div v-if="cameraActive" class="camera-overlay">
                    <div class="camera-face-guide"></div>
                </div>
            </div>
            <template #footer>
                <span style="font-size:.75rem;color:var(--text-muted);margin-right:auto;">Align face within boundary</span>
                <Button @click="capturePhoto">Snap Photo</Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
/* Form layout */
.gp-form-layout {
    display: grid;
    grid-template-columns: 1fr 220px;
    gap: 1.5rem;
}
@media (max-width: 768px) { .gp-form-layout { grid-template-columns: 1fr; } }

.gp-camera-panel {
    border-left: 1px solid var(--border);
    padding-left: 1.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.gp-photo-frame {
    position: relative;
    width: 12rem;
    height: 12rem;
    border-radius: .75rem;
    background: var(--bg);
    border: 2px dashed var(--border);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
.gp-photo-img { object-fit: cover; width: 100%; height: 100%; }
.gp-photo-placeholder { color: var(--text-muted); display: flex; flex-direction: column; align-items: center; }
.gp-photo-label { font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
.gp-photo-overlay {
    position: absolute;
    inset: 0;
    background: rgba(15,23,42,.6);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity .2s;
    cursor: pointer;
}
.gp-photo-overlay:hover { opacity: 1; }
.gp-photo-overlay span { color: #fff; font-size: .875rem; font-weight: 600; }

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: .75rem;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

/* Tab bar */
.tab-bar {
    display: flex;
    border-bottom: 2px solid var(--border);
    margin-bottom: 1.5rem;
    font-size: .875rem;
    font-weight: 500;
    gap: .25rem;
}
.tab-item {
    padding: .75rem 1.25rem;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    background: none;
    border-top: none;
    border-left: none;
    border-right: none;
    cursor: pointer;
    color: var(--text-muted);
    transition: color .15s;
}
.tab-active {
    border-bottom-color: var(--accent);
    color: var(--accent);
}

/* Table identity cell */
.gp-identity { display: flex; align-items: center; gap: .75rem; }
.gp-identity-photo {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: .5rem;
    object-fit: cover;
    border: 2px solid var(--border);
}
.gp-actions { display: flex; justify-content: flex-end; gap: .375rem; }

/* Camera */
.camera-viewport {
    background: #0f172a;
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
    height: 380px;
    margin: -16px -20px;
}
.camera-video { max-width: 100%; max-height: 100%; border-radius: .5rem; }
.camera-loading {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    color: #cbd5e1;
    font-size: .875rem;
}
.camera-overlay {
    position: absolute; inset: 0;
    pointer-events: none;
    display: flex; align-items: center; justify-content: center;
}
.camera-face-guide {
    width: 12rem;
    height: 16rem;
    border: 2px dashed rgba(255,255,255,.5);
    border-radius: 40%;
    box-shadow: 0 0 0 9999px rgba(0,0,0,.4);
}

/* Form fields — Tailwind preflight workaround */
.form-field { display: flex; flex-direction: column; gap: 0.35rem; }
.form-field label { font-size: 0.78rem; font-weight: 600; color: #374151; }
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
.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
</style>
