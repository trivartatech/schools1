<script setup>
import Button from '@/Components/ui/Button.vue';
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

        <div class="page-header">
            <div>
                <h1 class="page-header-title">Secure Gate Pass</h1>
                <p class="page-header-sub">Authorized exit management with identity verification.</p>
            </div>
            <Button v-if="can('create_front_office')" @click="showForm = !showForm">
                {{ showForm ? 'Close Form' : '+ Issue New Pass' }}
            </Button>
        </div>

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
                                    <svg class="w-10 h-10" style="opacity:.4;margin-bottom:.5rem;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span class="gp-photo-label">Verify Identity</span>
                                </div>
                                <div class="gp-photo-overlay" @click="openCamera">
                                    <span>{{ capturedPhoto ? 'Recapture' : 'Launch Camera' }}</span>
                                </div>
                            </div>

                            <Button variant="secondary" size="sm" v-if="!capturedPhoto" @click.prevent="openCamera" type="button" block class="mt-3">
                                <svg class="w-4 h-4" style="margin-right:.375rem;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/></svg>
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
            <div style="overflow-x:auto;">
                <Table>
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
                        <tr v-if="filteredPasses.length === 0">
                            <td colspan="5" style="text-align:center;padding:3rem;color:var(--text-muted);">
                                No gate passes found matching this criteria.
                            </td>
                        </tr>
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
                </Table>
            </div>
        </div>

        <!-- CAMERA MODAL -->
        <Teleport to="body">
            <div v-show="showCameraModal" class="modal-backdrop" @click.self="stopCamera">
                <div class="modal" style="width:100%;max-width:500px;">
                    <div class="modal-header">
                        <h3 class="modal-title">Identity Capture</h3>
                        <button @click="stopCamera" class="modal-close">&times;</button>
                    </div>
                    <div class="camera-viewport">
                        <video ref="videoRef" autoplay playsinline class="camera-video" :style="{ display: cameraActive ? 'block' : 'none' }"></video>
                        <canvas ref="canvasRef" style="display:none;"></canvas>
                        <div v-if="!cameraActive" class="camera-loading">
                            <svg class="w-8 h-8 animate-spin" style="margin-bottom:.75rem;color:var(--accent);" fill="none" viewBox="0 0 24 24"><circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Initializing Camera...
                        </div>
                        <div v-if="cameraActive" class="camera-overlay">
                            <div class="camera-face-guide"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <span style="font-size:.75rem;color:var(--text-muted);">Align face within boundary</span>
                        <Button @click="capturePhoto">Snap Photo</Button>
                    </div>
                </div>
            </div>
        </Teleport>

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

/* Modal */
.modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(15,23,42,.5);
    backdrop-filter: blur(2px);
    display: flex; align-items: center; justify-content: center;
    z-index: 1000;
}
.modal {
    background: #fff; border-radius: 12px;
    box-shadow: 0 25px 50px rgba(0,0,0,.25);
    overflow: hidden;
}
.modal-header {
    padding: 16px 20px; border-bottom: 1px solid var(--border);
    display: flex; justify-content: space-between; align-items: center;
}
.modal-title { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.modal-close {
    background: none; border: none; font-size: 1.5rem; line-height: 1;
    color: var(--text-muted); cursor: pointer; padding: 0 4px;
}
.modal-close:hover { color: var(--text-primary); }
.modal-footer {
    padding: 16px 20px; border-top: 1px solid var(--border);
    background: var(--bg);
    display: flex; justify-content: space-between; align-items: center;
}

/* Camera */
.camera-viewport {
    background: #0f172a;
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
    height: 380px;
}
.camera-video { max-width: 100%; max-height: 100%; border-radius: .5rem; }
.camera-loading {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    color: var(--text-muted);
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
</style>
