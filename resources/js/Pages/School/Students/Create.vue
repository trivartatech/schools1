<script setup>
import Button from '@/Components/ui/Button.vue';
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useClassSections } from '@/Composables/useClassSections';

const props = defineProps({
    classes:        { type: Array,  required: true },
    routes:         { type: Array,  default: () => [] },
    standardMonths: { type: Number, default: 10 },
});

const form = useForm({
    // Academic
    class_id: '',
    section_id: '',

    // Student
    first_name: '',
    last_name: '',
    dob: '',
    birth_place: '',
    mother_tongue: '',
    gender: 'Male',
    blood_group: '',
    religion: '',
    caste: '',
    category: '',
    aadhaar_no: '',
    photo: null,
    student_address: '',

    // Student extras
    nationality: 'Indian',
    city: '',
    state: '',
    pincode: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',

    // Parent
    primary_phone: '',
    father_name: '',
    mother_name: '',
    guardian_name: '',
    father_phone: '',
    mother_phone: '',
    father_occupation: '',
    mother_occupation: '',
    parent_address: '',

    // Parent extras
    guardian_email: '',
    guardian_phone: '',
    father_qualification: '',
    mother_qualification: '',

    // Options
    same_address: true,

    // Transport
    transport_route_id:    '',
    transport_stop_id:     '',
    transport_pickup_type: 'both',
    transport_months:      Math.floor(props.standardMonths || 10),
    transport_days:        0,
});

const { sections, isFetching: isFetchingSections, fetchError: sectionFetchError, fetchSections } = useClassSections();

// Transport: derive stops from selected route
const routeStops = computed(() => {
    if (!form.transport_route_id) return [];
    const route = props.routes.find(r => r.id == form.transport_route_id);
    return route?.stops ?? [];
});
const selectedStop = computed(() => routeStops.value.find(s => s.id == form.transport_stop_id));

// Pro-rata transport fee preview (matches backend: stop.fee / standardMonths * monthsOpted)
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

const onRouteChange = () => {
    form.transport_stop_id = '';
};

const onClassChange = async () => {
    form.section_id = '';
    await fetchSections(form.class_id);
    // Auto-select if only 1 section
    if (sections.value.length === 1) {
        form.section_id = sections.value[0].id;
    }
};

const submit = () => {
    if (form.same_address) {
        form.student_address = form.parent_address;
    }

    form.post('/school/students', {
        preserveScroll: true,
    });
};
</script>

<template>
    <SchoolLayout title="New Student Admission">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <Button variant="secondary" size="sm" as="link" href="/school/students" class="back-btn">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back
                </Button>
                <div>
                    <div class="page-header-title">Add New Student</div>
                    <div class="page-header-sub">Enroll a new student into the school system.</div>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="admission-form">

            <!-- Section 1: Academic Info -->
            <div class="card">
                <div class="card-header">
                    <div class="section-heading">
                        <span class="section-badge badge badge-indigo">1</span>
                        <span class="card-title">Academic Placement</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-row form-row-2">
                        <div class="form-field">
                            <label>Class <span class="required">*</span></label>
                            <select v-model="form.class_id" @change="onClassChange" required>
                                <option value="" disabled>Select Class</option>
                                <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                            </select>
                            <span v-if="form.errors.class_id" class="form-error">{{ form.errors.class_id }}</span>
                        </div>

                        <div class="form-field">
                            <label>Section <span class="required">*</span></label>
                            <select v-model="form.section_id" :disabled="!form.class_id || isFetchingSections" required>
                                <option value="" disabled>{{ isFetchingSections ? 'Loading...' : 'Select Section' }}</option>
                                <option v-for="sec in sections" :key="sec.id" :value="sec.id">{{ sec.name }}</option>
                            </select>
                            <span v-if="sectionFetchError" class="form-error">{{ sectionFetchError }}</span>
                            <span v-else-if="form.errors.section_id" class="form-error">{{ form.errors.section_id }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Personal Info -->
            <div class="card">
                <div class="card-header">
                    <div class="section-heading">
                        <span class="section-badge badge badge-indigo">2</span>
                        <span class="card-title">Personal Information</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-row form-row-3">
                        <div class="form-field">
                            <label>First Name <span class="required">*</span></label>
                            <input v-model="form.first_name" type="text" required placeholder="e.g. Rahul">
                            <span v-if="form.errors.first_name" class="form-error">{{ form.errors.first_name }}</span>
                        </div>

                        <div class="form-field">
                            <label>Last Name</label>
                            <input v-model="form.last_name" type="text" placeholder="e.g. Sharma">
                            <span v-if="form.errors.last_name" class="form-error">{{ form.errors.last_name }}</span>
                        </div>

                        <div class="form-field">
                            <label>Date of Birth <span class="required">*</span></label>
                            <input v-model="form.dob" type="date" required>
                            <span v-if="form.errors.dob" class="form-error">{{ form.errors.dob }}</span>
                        </div>

                        <div class="form-field">
                            <label>Gender <span class="required">*</span></label>
                            <select v-model="form.gender" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <span v-if="form.errors.gender" class="form-error">{{ form.errors.gender }}</span>
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
                            <span v-if="form.errors.birth_place" class="form-error">{{ form.errors.birth_place }}</span>
                        </div>

                        <div class="form-field">
                            <label>Mother Tongue</label>
                            <input v-model="form.mother_tongue" type="text" placeholder="e.g. Hindi">
                            <span v-if="form.errors.mother_tongue" class="form-error">{{ form.errors.mother_tongue }}</span>
                        </div>

                        <div class="form-field">
                            <label>Aadhaar / ID Card No.</label>
                            <input v-model="form.aadhaar_no" type="text" placeholder="12-digit Aadhaar">
                            <span v-if="form.errors.aadhaar_no" class="form-error">{{ form.errors.aadhaar_no }}</span>
                        </div>

                        <div class="form-field">
                            <label>Student Photo</label>
                            <input @input="form.photo = $event.target.files[0]" type="file" accept="image/*" class="file-input">
                            <span v-if="form.errors.photo" class="form-error">{{ form.errors.photo }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Additional Info -->
            <div class="card">
                <div class="card-header">
                    <div class="section-heading">
                        <span class="section-badge badge badge-indigo">3</span>
                        <span class="card-title">Additional Information</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-row form-row-3">
                        <div class="form-field">
                            <label>Nationality</label>
                            <input v-model="form.nationality" type="text" placeholder="e.g. Indian">
                            <span v-if="form.errors.nationality" class="form-error">{{ form.errors.nationality }}</span>
                        </div>
                        <div class="form-field">
                            <label>City</label>
                            <input v-model="form.city" type="text" placeholder="City">
                            <span v-if="form.errors.city" class="form-error">{{ form.errors.city }}</span>
                        </div>
                        <div class="form-field">
                            <label>State</label>
                            <input v-model="form.state" type="text" placeholder="State">
                            <span v-if="form.errors.state" class="form-error">{{ form.errors.state }}</span>
                        </div>
                    </div>
                    <div class="form-row form-row-2">
                        <div class="form-field">
                            <label>Pincode</label>
                            <input v-model="form.pincode" type="text" placeholder="e.g. 400001">
                            <span v-if="form.errors.pincode" class="form-error">{{ form.errors.pincode }}</span>
                        </div>
                    </div>
                    <div class="form-row form-row-2">
                        <div class="form-field">
                            <label>Emergency Contact Name</label>
                            <input v-model="form.emergency_contact_name" type="text" placeholder="Full name">
                            <span v-if="form.errors.emergency_contact_name" class="form-error">{{ form.errors.emergency_contact_name }}</span>
                        </div>
                        <div class="form-field">
                            <label>Emergency Contact Phone</label>
                            <input v-model="form.emergency_contact_phone" type="text" placeholder="Mobile number">
                            <span v-if="form.errors.emergency_contact_phone" class="form-error">{{ form.errors.emergency_contact_phone }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 4: Parent Info -->
            <div class="card">
                <div class="card-header">
                    <div class="section-heading">
                        <span class="section-badge badge badge-indigo">4</span>
                        <span class="card-title">Parent / Guardian Details</span>
                    </div>
                </div>
                <div class="card-body">

                    <!-- Sibling Notice -->
                    <div class="info-notice">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="notice-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p><strong>Important:</strong> The <strong>Primary Mobile Number</strong> is used to identify existing parents. If the number already exists, this student will be automatically linked as a sibling to that parent's account.</p>
                    </div>

                    <!-- Primary Phone -->
                    <div class="form-row primary-phone-row">
                        <div class="form-field">
                            <label>Primary Mobile <span class="required">*</span></label>
                            <input v-model="form.primary_phone" type="text" required placeholder="e.g. 9876543210" class="input-highlight">
                            <span class="field-hint">Used for login &amp; SMS alerts</span>
                            <span v-if="form.errors.primary_phone" class="form-error">{{ form.errors.primary_phone }}</span>
                        </div>
                    </div>

                    <!-- Father / Mother split -->
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
                                <label>Father's Qualification</label>
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
                                <label>Mother's Qualification</label>
                                <input v-model="form.mother_qualification" type="text" placeholder="e.g. M.A">
                            </div>
                        </div>
                    </div>

                    <!-- Guardian Name / Email / Phone -->
                    <div class="form-row form-row-3" style="margin-top: 20px;">
                        <div class="form-field">
                            <label>Guardian Name</label>
                            <input v-model="form.guardian_name" type="text" placeholder="If different from father/mother">
                            <span v-if="form.errors.guardian_name" class="form-error">{{ form.errors.guardian_name }}</span>
                        </div>
                        <div class="form-field">
                            <label>Guardian Email</label>
                            <input v-model="form.guardian_email" type="email" placeholder="e.g. parent@email.com">
                            <span v-if="form.errors.guardian_email" class="form-error">{{ form.errors.guardian_email }}</span>
                        </div>
                        <div class="form-field">
                            <label>Guardian Phone</label>
                            <input v-model="form.guardian_phone" type="text" placeholder="Alternate mobile number">
                            <span v-if="form.errors.guardian_phone" class="form-error">{{ form.errors.guardian_phone }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 5: Address -->
            <div class="card">
                <div class="card-header">
                    <div class="section-heading">
                        <span class="section-badge badge badge-indigo">5</span>
                        <span class="card-title">Address &amp; Communication</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-field">
                        <label>Residence Address</label>
                        <textarea v-model="form.parent_address" rows="3" placeholder="Full address with PIN code"></textarea>
                    </div>
                </div>
            </div>

            <!-- Section 6: Transport -->
            <div class="card">
                <div class="card-header">
                    <div class="section-heading">
                        <span class="section-badge badge badge-indigo">6</span>
                        <span class="card-title">Transport Route</span>
                    </div>
                    <p style="font-size:.775rem;color:#64748b;margin:2px 0 0;">Optional — assign the student to a bus route &amp; stop.</p>
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

                    <!-- Term opted (only when a route is picked) -->
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

            <!-- Form Actions -->
            <div class="form-actions">
                <Button variant="secondary" as="link" href="/school/students">
                    Cancel
                </Button>
                <Button type="submit" :loading="form.processing">
                    <svg v-if="form.processing" class="spinner-icon" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Complete Admission</span>
                </Button>
            </div>

        </form>
    </SchoolLayout>
</template>

<style scoped>
/* ── Page header layout ── */
.page-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.back-btn {
    flex-shrink: 0;
}

/* ── Form wrapper ── */
.admission-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding-bottom: 48px;
}

/* ── Section heading inside card-header ── */
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

/* ── Required asterisk ── */
.required {
    color: var(--danger);
    font-size: 0.8rem;
}

/* ── Field hint text ── */
.field-hint {
    font-size: 0.72rem;
    color: var(--text-muted, #94a3b8);
    margin-top: 2px;
}

/* ── Sibling info notice ── */
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

.notice-icon {
    color: #3b82f6;
    flex-shrink: 0;
    margin-top: 1px;
}

.info-notice p {
    font-size: 0.8125rem;
    color: #1e40af;
    margin: 0;
    line-height: 1.5;
}

/* ── Primary phone row: single field, capped width ── */
.primary-phone-row {
    margin-bottom: 20px;
    max-width: 320px;
}

/* ── Highlighted input for primary phone ── */
.input-highlight {
    background: #fffbeb !important;
    border-color: #f59e0b !important;
    font-weight: 600;
}

.input-highlight:focus {
    border-color: var(--accent) !important;
    background: #fff !important;
}

/* ── File input ── */
.file-input {
    padding: 6px 0 !important;
    border: none !important;
    box-shadow: none !important;
    font-size: 0.8125rem;
}

/* ── Parent two-column split ── */
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

/* ── Form actions bar ── */
.form-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 4px;
}

/* ── Submit spinner ── */
.spinner-icon {
    width: 15px;
    height: 15px;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}

/* ── Responsive ── */
@media (max-width: 640px) {
    .form-row-2,
    .form-row-3 {
        grid-template-columns: 1fr;
    }

    .parent-columns {
        grid-template-columns: 1fr;
        gap: 24px;
    }

    .primary-phone-row {
        max-width: 100%;
    }
}
</style>
