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
    visitors: { type: Array, default: () => [] },
    expectedVisitors: { type: Array, default: () => [] },
});

// Pre-registration
const showPreReg = ref(false);
const preRegForm = useForm({
    name: '', phone: '', purpose: 'Meeting',
    expected_date: new Date().toISOString().split('T')[0],
    expected_time: '', notes: '', id_type: '', id_number: '',
});
const submitPreReg = () => {
    preRegForm.post('/school/front-office/visitors/pre-register', {
        preserveScroll: true,
        onSuccess: () => { showPreReg.value = false; preRegForm.reset(); },
    });
};
const checkInVisitor = (visitor) => {
    router.post(`/school/front-office/visitors/${visitor.id}/check-in`, {}, { preserveScroll: true });
};

const form = useForm({
    name: '',
    phone: '',
    purpose: '',
    person_to_meet_type: '',
    person_to_meet_id: '',
    notes: '',
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
        toast.error("Camera access denied or unavailable. Please grant permissions.");
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
        form.photo_base64 = capturedPhoto.value; // Assign to form
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

onUnmounted(() => {
    stopCamera();
});

const submit = () => {
    form.post('/school/front-office/visitors', {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
            capturedPhoto.value = null;
        }
    });
};

const markExit = (visitor) => {
    router.put(`/school/front-office/visitors/${visitor.id}`, { mark_exit: true, ...visitor }, {
        preserveScroll: true,
    });
};

const searchLog = ref('');
const filteredVisitors = computed(() => {
    if (!searchLog.value) return props.visitors;
    const q = searchLog.value.toLowerCase();
    return props.visitors.filter(v => v.name.toLowerCase().includes(q) || (v.phone && v.phone.includes(q)));
});
</script>

<template>
    <SchoolLayout title="Visitor Log">

        <div class="page-header">
            <div>
                <h1 class="page-header-title">Visitor Log</h1>
                <p class="page-header-sub">Manage and verify school visitors efficiently.</p>
            </div>
            <div style="display:flex;gap:8px;">
                <Button variant="secondary" v-if="can('create_front_office')" @click="showPreReg = !showPreReg; showForm = false;">
                    {{ showPreReg ? 'Close' : 'Pre-Register' }}
                </Button>
                <Button v-if="can('create_front_office')" @click="showForm = !showForm; showPreReg = false;">
                    {{ showForm ? 'Close Entry Form' : '+ New Visitor Entry' }}
                </Button>
            </div>
        </div>

        <!-- EXPECTED VISITORS BANNER -->
        <div v-if="expectedVisitors.length" class="card" style="margin-bottom:16px;border-left:4px solid #6366f1;">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h3 class="card-title" style="color:#6366f1;">Expected Today ({{ expectedVisitors.length }})</h3>
            </div>
            <div class="card-body" style="padding:0;">
                <Table>
                    <thead><tr><th>Name</th><th>Phone</th><th>Purpose</th><th>Expected Time</th><th>ID</th><th style="text-align:right;">Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="ev in expectedVisitors" :key="ev.id" style="background:#f5f3ff;">
                            <td style="font-weight:600;">{{ ev.name }}</td>
                            <td style="font-size:.82rem;">{{ ev.phone || '—' }}</td>
                            <td><span class="badge badge-blue">{{ ev.purpose }}</span></td>
                            <td style="font-family:monospace;font-size:.82rem;">{{ ev.expected_time || 'Any time' }}</td>
                            <td style="font-size:.78rem;color:var(--text-muted);">{{ ev.id_type ? ev.id_type + ': ' + ev.id_number : '—' }}</td>
                            <td style="text-align:right;">
                                <Button size="xs" @click="checkInVisitor(ev)">Check In</Button>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <!-- PRE-REGISTRATION FORM -->
        <div v-show="showPreReg" class="card mb-6">
            <div class="card-header"><h2 class="card-title">Pre-Register Expected Visitor</h2></div>
            <div class="card-body">
                <form @submit.prevent="submitPreReg">
                    <div class="form-row-2">
                        <div class="form-field"><label>Visitor Name *</label><input v-model="preRegForm.name" required></div>
                        <div class="form-field"><label>Phone</label><input v-model="preRegForm.phone"></div>
                        <div class="form-field"><label>Purpose *</label>
                            <select v-model="preRegForm.purpose" required>
                                <option value="Meeting">Meeting</option><option value="Admission">Admission</option>
                                <option value="Delivery">Delivery</option><option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-field"><label>Expected Date *</label><input v-model="preRegForm.expected_date" type="date" required></div>
                        <div class="form-field"><label>Expected Time</label><input v-model="preRegForm.expected_time" type="time"></div>
                        <div class="form-field"><label>ID Type</label>
                            <select v-model="preRegForm.id_type"><option value="">None</option><option>Aadhaar</option><option>PAN</option><option>Driving License</option><option>Passport</option><option>Other</option></select>
                        </div>
                        <div class="form-field"><label>ID Number</label><input v-model="preRegForm.id_number"></div>
                    </div>
                    <div class="form-row" style="margin-top:1rem;"><div class="form-field"><label>Notes</label><textarea v-model="preRegForm.notes" rows="2"></textarea></div></div>
                    <div style="display:flex;justify-content:flex-end;gap:.75rem;margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--border);">
                        <Button variant="secondary" type="button" @click="showPreReg = false">Cancel</Button>
                        <Button type="submit" :loading="preRegForm.processing">Pre-Register</Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- NEW VISITOR ENTRY FORM -->
        <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="translate-y-[-20px] opacity-0"
                    enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-[-20px]">
        <div v-show="showForm" class="card mb-6">
            <div class="card-header">
                <h2 class="card-title">Register New Visitor</h2>
            </div>
            <div class="card-body">
                <form @submit.prevent="submit">
                    <div style="display: grid; grid-template-columns: 1fr auto; gap: 1.5rem;">
                        <!-- Left Details -->
                        <div>
                            <div class="form-row-2">
                                <div class="form-field">
                                    <label>Visitor Name</label>
                                    <input v-model="form.name" type="text" placeholder="John Doe" required>
                                    <div v-if="form.errors.name" class="form-error">{{ form.errors.name }}</div>
                                </div>
                                <div class="form-field">
                                    <label>Phone Number</label>
                                    <input v-model="form.phone" type="text" placeholder="+91 9876543210">
                                </div>
                                <div class="form-field">
                                    <label>Purpose of Visit</label>
                                    <select v-model="form.purpose" required>
                                        <option value="" disabled>Select Purpose</option>
                                        <option value="Meeting">Meeting / Appointment</option>
                                        <option value="Admission">Admission Enquiry</option>
                                        <option value="Delivery">Package Delivery</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label>Person to Meet (Optional ID)</label>
                                    <input v-model="form.person_to_meet_id" type="text" placeholder="Staff Name or ID">
                                </div>
                            </div>
                            <div class="form-row" style="margin-top: 1rem;">
                                <div class="form-field">
                                    <label>Additional Notes</label>
                                    <textarea v-model="form.notes" rows="2" placeholder="Vehicle number, items carrying, etc..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Right Camera Capture -->
                        <div style="width: 220px; border-left: 1px solid var(--border); padding-left: 1.5rem; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                            <label style="text-align: center; margin-bottom: 0.75rem;">Live Photo Verification</label>

                            <div style="position: relative; width: 12rem; height: 12rem; border-radius: 0.75rem; background: var(--surface-muted); overflow: hidden; display: flex; align-items: center; justify-content: center;">
                                <img v-if="capturedPhoto" :src="capturedPhoto" style="object-fit: cover; width: 100%; height: 100%;" alt="Visitor" />
                                <div v-else style="color: var(--text-muted); display: flex; flex-direction: column; align-items: center;">
                                    <svg style="width: 2.5rem; height: 2.5rem; margin-bottom: 0.5rem; opacity: 0.4;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">No Photo</span>
                                </div>
                                <div style="position: absolute; inset: 0; background: rgba(30,41,59,0.6); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; cursor: pointer;"
                                     @click="openCamera"
                                     @mouseenter="e => e.currentTarget.style.opacity = '1'"
                                     @mouseleave="e => e.currentTarget.style.opacity = '0'">
                                    <span style="color: white; font-size: 0.875rem; font-weight: 600;">{{ capturedPhoto ? 'Retake Photo' : 'Open Camera' }}</span>
                                </div>
                            </div>

                            <Button variant="secondary" size="sm" v-if="capturedPhoto" @click.prevent="retakePhoto" type="button" class="mt-4">
                                Clear &amp; Retake
                            </Button>
                            <Button variant="secondary" size="sm" v-else @click.prevent="openCamera" type="button" style="width: 12rem" class="mt-4">
                                <svg style="width: 1rem; height: 1rem; margin-right: 0.375rem;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                Start Camera
                            </Button>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                        <Button variant="secondary" type="button" @click="showForm = false">Cancel</Button>
                        <Button type="submit" :loading="form.processing">Log Entry</Button>
                    </div>
                </form>
            </div>
        </div>
        </Transition>

        <!-- VISITOR LIST TABLE -->
        <div class="card">
            <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
                <h3 class="card-title">Today's Visitors</h3>
                <input v-model="searchLog" type="text" placeholder="Search visitors..." style="width: 16rem;">
            </div>
            <div class="card-body" style="padding: 0;">
                <div style="overflow-x: auto;">
                    <Table>
                        <thead>
                            <tr>
                                <th>Visitor</th>
                                <th>Purpose</th>
                                <th>IN Time</th>
                                <th>OUT Time</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredVisitors.length === 0">
                                <td colspan="5" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                                    No visitor logs found for today.
                                </td>
                            </tr>
                            <tr v-for="v in filteredVisitors" :key="v.id">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <img :src="v.photo_path ? `/storage/${v.photo_path}` : 'https://ui-avatars.com/api/?name='+v.name+'&color=1169cd&background=eff6ff'"
                                             style="width: 2.5rem; height: 2.5rem; border-radius: 9999px; object-fit: cover; border: 2px solid var(--border);" />
                                        <div>
                                            <div style="font-weight: 500;">{{ v.name }}</div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);">{{ v.phone || 'N/A' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-blue">{{ v.purpose }}</span>
                                </td>
                                <td style="font-family: monospace; font-size: 0.8rem;">
                                    {{ new Date(v.in_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                </td>
                                <td>
                                    <span v-if="v.out_time" style="font-family: monospace; font-size: 0.8rem;">
                                        {{ new Date(v.out_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                    </span>
                                    <span v-else class="badge badge-amber">INSIDE</span>
                                </td>
                                <td style="text-align: right;">
                                    <Button variant="danger" size="xs" v-if="!v.out_time" @click="markExit(v)">
                                        Mark Exit
                                    </Button>
                                    <span v-else class="badge badge-green">Exited</span>
                                </td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
            </div>
        </div>

        <!-- CAMERA MODAL WINDOW -->
        <div v-show="showCameraModal" class="fixed inset-0 z-[100] flex items-center justify-center pt-[10vh]">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-[2px]" @click="stopCamera"></div>

            <div style="position: relative; width: 500px; background: white; border-radius: 1rem; box-shadow: 0 25px 50px rgba(0,0,0,0.25); overflow: hidden;">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="card-title">Identity Capture</h3>
                    <Button variant="secondary" size="xs" @click="stopCamera">
                        <svg style="width: 1.25rem; height: 1.25rem;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </Button>
                </div>

                <div style="background: #0f172a; display: flex; justify-content: center; align-items: center; position: relative; height: 380px;">
                    <video ref="videoRef" autoplay playsinline style="max-width: 100%; max-height: 100%; border-radius: 0.5rem;" :class="{ 'hidden': !cameraActive }"></video>
                    <canvas ref="canvasRef" style="display: none;"></canvas>
                    <div v-if="!cameraActive" style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8;">
                        <svg style="width: 2rem; height: 2rem; animation: spin 1s linear infinite; margin-bottom: 0.75rem; color: #6366f1;" fill="none" viewBox="0 0 24 24"><circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Initializing Camera...
                    </div>
                    <div v-if="cameraActive" style="position: absolute; inset: 0; pointer-events: none; display: flex; align-items: center; justify-content: center;">
                        <div style="width: 12rem; height: 16rem; border: 2px dashed rgba(255,255,255,0.5); border-radius: 40%; box-shadow: 0 0 0 9999px rgba(0,0,0,0.4);"></div>
                    </div>
                </div>

                <div style="padding: 1.25rem; background: var(--surface-muted); display: flex; align-items: center; justify-content: space-between;">
                    <p style="font-size: 0.75rem; color: var(--text-muted);">Please align face within the boundary.</p>
                    <Button @click="capturePhoto">Snap Photo</Button>
                </div>
            </div>
        </div>

    </SchoolLayout>
</template>
