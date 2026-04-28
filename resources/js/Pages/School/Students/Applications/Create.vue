<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    classes:        Array,
    routes:         { type: Array,  default: () => [] },
    standardMonths: { type: Number, default: 10 },
});

const form = useForm({
    class_id: '', section_id: '', student_type: 'New Student',
    first_name: '', last_name: '', dob: '', birth_place: '', mother_tongue: '',
    gender: 'Male', blood_group: '', religion: '', caste: '', category: '', aadhaar_no: '',
    photo: null, student_address: '',
    // Student extras
    nationality: 'Indian',
    city: '', state: '', pincode: '',
    emergency_contact_name: '', emergency_contact_phone: '',
    // Parent
    primary_phone: '', father_name: '', mother_name: '', guardian_name: '',
    father_phone: '', mother_phone: '', father_occupation: '', mother_occupation: '',
    parent_address: '',
    // Parent extras
    guardian_email: '', guardian_phone: '',
    father_qualification: '', mother_qualification: '',
    // Background
    previous_school: '', previous_class: '', annual_income: '',
    // Transport
    transport_route_id: '',
    transport_stop_id: '',
    transport_pickup_type: 'both',
    transport_months: Math.floor(props.standardMonths || 10),
    transport_days:   0,
});

const sections = ref([]);
const isFetchingSections = ref(false);
const fetchSections = async () => {
    if (!form.class_id) { sections.value = []; form.section_id = ''; return; }
    isFetchingSections.value = true;
    try {
        const res = await fetch(`/school/classes/${form.class_id}/sections`);
        sections.value = await res.json();
        if (sections.value.length === 1) form.section_id = sections.value[0].id;
        else form.section_id = '';
    } finally {
        isFetchingSections.value = false;
    }
};

const routeStops = computed(() => {
    if (!form.transport_route_id) return [];
    const route = props.routes.find(r => r.id == form.transport_route_id);
    return route?.stops ?? [];
});
const selectedStop = computed(() => routeStops.value.find(s => s.id == form.transport_stop_id));
const onRouteChange = () => { form.transport_stop_id = ''; };

// Pro-rata transport fee preview
const transportMonthsOpted = computed(() => {
    const m = Math.max(0, Math.min(24, Number(form.transport_months) || 0));
    const d = Math.max(0, Math.min(30, Number(form.transport_days)   || 0));
    return Math.round((m + d / 30) * 100) / 100;
});
const transportComputedFee = computed(() => {
    if (!selectedStop.value?.fee) return 0;
    const std = Number(props.standardMonths) > 0 ? Number(props.standardMonths) : 10;
    return Math.round(((Number(selectedStop.value.fee) / std) * transportMonthsOpted.value) * 100) / 100;
});

const submit = () => {
    form.post('/school/registrations', { forceFormData: true });
};
</script>

<template>
    <Head title="New Student Application" />
    <SchoolLayout title="New Student Application">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <Button variant="secondary" size="sm" as="link" href="/school/registrations" class="back-btn">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back
                </Button>
                <div>
                    <div class="page-header-title">New Student Registration</div>
                    <div class="page-header-sub">Submit an application for review. The student will be admitted after approval.</div>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="reg-form">

            <!-- ── 1. Academic Placement ─────────────────────────────────── -->
            <div class="card">
                <div class="card-header">
                    <div class="section-heading">
                        <span class="section-badge badge badge-blue">1</span>
                        <span class="card-title">Academic Placement</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-row form-row-3">
                        <div class="form-field">
                            <label>Class <span class="req">*</span></label>
                            <select v-model="form.class_id" @change="fetchSections" required>
                                <option value="" disabled>Select Class</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <span v-if="form.errors.class_id" class="form-error">{{ form.errors.class_id }}</span>
                        </div>
                        <div class="form-field">
                            <label>Section</label>
                            <select v-model="form.section_id" :disabled="!form.class_id || isFetchingSections">
                                <option value="">{{ isFetchingSections ? 'Loading…' : 'Select Section' }}</option>
                                <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Student Type</label>
                            <select v-model="form.student_type">
                                <option value="New Student">New Student</option>
                                <option value="Old Student">Old Student</option>
                            </select>
                            <span class="field-hint" style="font-size:.7rem;color:#6b7280;">Drives fee-rule matching ("New only" / "Old only" structures).</span>
                            <span v-if="form.errors.student_type" class="form-error">{{ form.errors.student_type }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── 2. Student Information ────────────────────────────────── -->
            <div class="card">
                <div class="card-header">
                    <div class="section-heading">
                        <span class="section-badge badge badge-blue">2</span>
                        <span class="card-title">Student Information</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-row form-row-3">
                        <div class="form-field">
                            <label>First Name <span class="req">*</span></label>
                            <input v-model="form.first_name" type="text" required placeholder="e.g. Rahul">
                            <span v-if="form.errors.first_name" class="form-error">{{ form.errors.first_name }}</span>
                        </div>
                        <div class="form-field">
                            <label>Last Name</label>
                            <input v-model="form.last_name" type="text" placeholder="e.g. Sharma">
                        </div>
                        <div class="form-field">
                            <label>Date of Birth <span class="req">*</span></label>
                            <input v-model="form.dob" type="date" required>
                            <span v-if="form.errors.dob" class="form-error">{{ form.errors.dob }}</span>
                        </div>
                        <div class="form-field">
                            <label>Gender <span class="req">*</span></label>
                            <select v-model="form.gender" required>
                                <option>Male</option>
                                <option>Female</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Blood Group</label>
                            <select v-model="form.blood_group">
                                <option value="">Unknown</option>
                                <option>A+</option><option>A-</option>
                                <option>B+</option><option>B-</option>
                                <option>O+</option><option>O-</option>
                                <option>AB+</option><option>AB-</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Birth Place</label>
                            <input v-model="form.birth_place" type="text" placeholder="City / Village">
                        </div>
                        <div class="form-field">
                            <label>Mother Tongue</label>
                            <input v-model="form.mother_tongue" type="text" placeholder="e.g. Hindi">
                        </div>
                        <div class="form-field">
                            <label>Religion</label>
                            <input v-model="form.religion" type="text" placeholder="e.g. Hindu">
                        </div>
                        <div class="form-field">
                            <label>Caste</label>
                            <input v-model="form.caste" type="text">
                        </div>
                        <div class="form-field">
                            <label>Category</label>
                            <input v-model="form.category" type="text" placeholder="e.g. OBC / SC / General">
                        </div>
                        <div class="form-field">
                            <label>Aadhaar No.</label>
                            <input v-model="form.aadhaar_no" type="text" placeholder="12-digit">
                        </div>
                        <div class="form-field">
                            <label>Student Photo</label>
                            <input type="file" accept="image/*" @change="e => form.photo = e.target.files[0]" class="file-input">
                            <span v-if="form.errors.photo" class="form-error">{{ form.errors.photo }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── 3. Address & Additional ───────────────────────────────── -->
            <div class="card">
                <div class="card-header">
                    <div class="section-heading">
                        <span class="section-badge badge badge-blue">3</span>
                        <span class="card-title">Address &amp; Additional Details</span>
                    </div>
                </div>
                <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">
                    <div class="form-field">
                        <label>Student Address</label>
                        <textarea v-model="form.student_address" rows="2" placeholder="Full residential address"></textarea>
                    </div>
                    <div class="form-row form-row-3">
                        <div class="form-field">
                            <label>City</label>
                            <input v-model="form.city" type="text" placeholder="City">
                        </div>
                        <div class="form-field">
                            <label>State</label>
                            <input v-model="form.state" type="text" placeholder="State">
                        </div>
                        <div class="form-field">
                            <label>Pincode</label>
                            <input v-model="form.pincode" type="text" placeholder="e.g. 400001">
                        </div>
                    </div>
                    <div class="form-row form-row-3">
                        <div class="form-field">
                            <label>Nationality</label>
                            <input v-model="form.nationality" type="text" placeholder="e.g. Indian">
                        </div>
                        <div class="form-field">
                            <label>Emergency Contact Name</label>
                            <input v-model="form.emergency_contact_name" type="text" placeholder="Full name">
                        </div>
                        <div class="form-field">
                            <label>Emergency Contact Phone</label>
                            <input v-model="form.emergency_contact_phone" type="text" placeholder="Mobile number">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── 4. Parent / Guardian ──────────────────────────────────── -->
            <div class="card">
                <div class="card-header">
                    <div class="section-heading">
                        <span class="section-badge badge badge-blue">4</span>
                        <span class="card-title">Parent / Guardian Details</span>
                    </div>
                </div>
                <div class="card-body">

                    <!-- Sibling notice -->
                    <div class="info-notice">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color:#3b82f6;flex-shrink:0;margin-top:1px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p><strong>Important:</strong> The <strong>Primary Mobile Number</strong> links this student to an existing parent. If the number already exists, the student will be added as a sibling automatically.</p>
                    </div>

                    <div class="form-row primary-phone-row">
                        <div class="form-field">
                            <label>Primary Mobile <span class="req">*</span></label>
                            <input v-model="form.primary_phone" type="text" required placeholder="e.g. 9876543210" class="input-highlight">
                            <span class="field-hint">Used for login &amp; SMS alerts</span>
                            <span v-if="form.errors.primary_phone" class="form-error">{{ form.errors.primary_phone }}</span>
                        </div>
                    </div>

                    <div class="parent-columns">
                        <div class="parent-column">
                            <div class="parent-column-heading">Father Details</div>
                            <div class="form-field">
                                <label>Father's Name</label>
                                <input v-model="form.father_name" type="text" placeholder="Full name">
                            </div>
                            <div class="form-field">
                                <label>Father's Phone</label>
                                <input v-model="form.father_phone" type="text" placeholder="Mobile number">
                            </div>
                            <div class="form-field">
                                <label>Occupation</label>
                                <input v-model="form.father_occupation" type="text" placeholder="e.g. Business">
                            </div>
                            <div class="form-field">
                                <label>Qualification</label>
                                <input v-model="form.father_qualification" type="text" placeholder="e.g. B.Sc">
                            </div>
                        </div>
                        <div class="parent-column">
                            <div class="parent-column-heading">Mother Details</div>
                            <div class="form-field">
                                <label>Mother's Name</label>
                                <input v-model="form.mother_name" type="text" placeholder="Full name">
                            </div>
                            <div class="form-field">
                                <label>Mother's Phone</label>
                                <input v-model="form.mother_phone" type="text" placeholder="Mobile number">
                            </div>
                            <div class="form-field">
                                <label>Occupation</label>
                                <input v-model="form.mother_occupation" type="text" placeholder="e.g. Teacher">
                            </div>
                            <div class="form-field">
                                <label>Qualification</label>
                                <input v-model="form.mother_qualification" type="text" placeholder="e.g. M.A">
                            </div>
                        </div>
                    </div>

                    <div class="form-row form-row-3" style="margin-top:20px;">
                        <div class="form-field">
                            <label>Guardian Name</label>
                            <input v-model="form.guardian_name" type="text" placeholder="If different from father/mother">
                        </div>
                        <div class="form-field">
                            <label>Guardian Email</label>
                            <input v-model="form.guardian_email" type="email" placeholder="e.g. parent@email.com">
                        </div>
                        <div class="form-field">
                            <label>Guardian Phone</label>
                            <input v-model="form.guardian_phone" type="text" placeholder="Alternate number">
                        </div>
                    </div>

                    <div class="form-field" style="margin-top:4px;">
                        <label>Parent Address</label>
                        <textarea v-model="form.parent_address" rows="2" placeholder="Full residential address"></textarea>
                    </div>
                </div>
            </div>

            <!-- ── 5. Previous School & Background ──────────────────────── -->
            <div class="card">
                <div class="card-header">
                    <div class="section-heading">
                        <span class="section-badge badge badge-blue">5</span>
                        <span class="card-title">Previous School &amp; Background</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-row form-row-3">
                        <div class="form-field">
                            <label>Previous School Name</label>
                            <input v-model="form.previous_school" type="text" placeholder="Name of previous school">
                        </div>
                        <div class="form-field">
                            <label>Previous Class</label>
                            <input v-model="form.previous_class" type="text" placeholder="e.g. Class 5">
                        </div>
                        <div class="form-field">
                            <label>Annual Family Income</label>
                            <select v-model="form.annual_income">
                                <option value="">Select Range</option>
                                <option>Below 1 Lakh</option>
                                <option>1–3 Lakhs</option>
                                <option>3–5 Lakhs</option>
                                <option>5–10 Lakhs</option>
                                <option>Above 10 Lakhs</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── 6. Transport Route ────────────────────────────────────── -->
            <div class="card">
                <div class="card-header">
                    <div class="section-heading">
                        <span class="section-badge badge badge-blue">6</span>
                        <span class="card-title">Transport Route</span>
                    </div>
                    <p style="font-size:.775rem;color:#64748b;margin:2px 0 0;">Optional — transport will be assigned when the application is approved.</p>
                </div>
                <div class="card-body" v-if="routes.length">
                    <div class="form-row form-row-3">
                        <div class="form-field">
                            <label>Route</label>
                            <select v-model="form.transport_route_id" @change="onRouteChange">
                                <option value="">— No Transport —</option>
                                <option v-for="r in routes" :key="r.id" :value="r.id">{{ r.route_name }}</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Boarding Stop</label>
                            <select v-model="form.transport_stop_id" :disabled="!form.transport_route_id">
                                <option value="">Select Stop</option>
                                <option v-for="s in routeStops" :key="s.id" :value="s.id">
                                    {{ s.stop_name }}{{ s.fee ? ' — ₹' + s.fee : '' }}
                                </option>
                            </select>
                            <span v-if="selectedStop?.fee" class="field-hint">
                                Stop full-term fee: <strong>₹{{ selectedStop.fee }}</strong>
                                <span style="color:#94a3b8;">(for {{ standardMonths }} months)</span>
                            </span>
                        </div>
                        <div class="form-field">
                            <label>Pickup Type</label>
                            <select v-model="form.transport_pickup_type" :disabled="!form.transport_route_id">
                                <option value="both">Both (Pickup &amp; Drop)</option>
                                <option value="pickup">Pickup Only</option>
                                <option value="drop">Drop Only</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="form.transport_route_id" class="form-row form-row-3" style="margin-top:0.75rem;">
                        <div class="form-field">
                            <label>Months Opted *</label>
                            <input v-model.number="form.transport_months" type="number" min="0" max="24" step="1">
                            <span class="field-hint" style="color:#94a3b8;">Whole months (0–24)</span>
                        </div>
                        <div class="form-field">
                            <label>Extra Days</label>
                            <input v-model.number="form.transport_days" type="number" min="0" max="30" step="1">
                            <span class="field-hint" style="color:#94a3b8;">0–30 days</span>
                        </div>
                        <div class="form-field">
                            <label>Transport Fee (auto)</label>
                            <div style="padding:0.5rem 0.75rem;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:0.5rem;font-size:0.9rem;color:#065f46;line-height:1.45;">
                                <div><strong style="font-size:1rem;">₹{{ transportComputedFee }}</strong></div>
                                <div style="font-size:0.75rem;color:#047857;">
                                    {{ form.transport_months || 0 }} mo{{ form.transport_days ? ' + ' + form.transport_days + ' d' : '' }}
                                    = {{ transportMonthsOpted }} months
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body" v-else>
                    <p style="font-size:.8125rem;color:#94a3b8;">No active transport routes found. Add routes in the Transport module first.</p>
                </div>
            </div>

            <!-- ── Actions ──────────────────────────────────────────────── -->
            <div class="form-actions">
                <Button variant="secondary" as="link" href="/school/registrations">Cancel</Button>
                <Button type="submit" :loading="form.processing">
                    <svg v-if="form.processing" class="spinner-icon" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Submit Application</span>
                </Button>
            </div>

        </form>
    </SchoolLayout>
</template>

<style scoped>
.page-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}
.back-btn { flex-shrink: 0; }

.reg-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding-bottom: 48px;
}

.section-heading {
    display: flex;
    align-items: center;
    gap: 10px;
}
.section-badge {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    flex-shrink: 0;
    padding: 0;
}
.req {
    color: var(--danger);
    font-size: 0.8rem;
}
.field-hint {
    font-size: 0.72rem;
    color: var(--text-muted, #94a3b8);
    margin-top: 2px;
    display: block;
}
.info-notice {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: var(--radius);
    padding: 12px 14px;
    margin-bottom: 20px;
}
.info-notice p {
    font-size: 0.8125rem;
    color: #1e40af;
    margin: 0;
    line-height: 1.5;
}
.primary-phone-row {
    margin-bottom: 20px;
    max-width: 320px;
}
.input-highlight {
    background: #fffbeb !important;
    border-color: #f59e0b !important;
    font-weight: 600;
}
.input-highlight:focus {
    border-color: var(--accent) !important;
    background: #fff !important;
}
.file-input {
    padding: 6px 0 !important;
    border: none !important;
    box-shadow: none !important;
    font-size: 0.8125rem;
}
.parent-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
}
.parent-column {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.parent-column-heading {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--text-muted, #94a3b8);
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
}
.form-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 4px;
}
.spinner-icon {
    width: 15px;
    height: 15px;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
@media (max-width: 640px) {
    .form-row-2, .form-row-3 { grid-template-columns: 1fr; }
    .parent-columns { grid-template-columns: 1fr; gap: 24px; }
    .primary-phone-row { max-width: 100%; }
}
</style>
