<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    student: Object,
});

const form = useForm({
    _method: 'PUT',
    // Identity
    admission_no: props.student.admission_no || '',
    // Student
    first_name: props.student.first_name || '',
    last_name: props.student.last_name || '',
    dob: props.student.dob ? props.student.dob.substring(0, 10) : '',
    birth_place: props.student.birth_place || '',
    mother_tongue: props.student.mother_tongue || '',
    gender: props.student.gender || 'Male',
    blood_group: props.student.blood_group || '',
    religion: props.student.religion || '',
    caste: props.student.caste || '',
    category: props.student.category || '',
    aadhaar_no: props.student.aadhaar_no || '',
    nationality: props.student.nationality || 'Indian',
    photo: null,
    address: props.student.address || '',

    // Student extras
    city: props.student.city || '',
    state: props.student.state || '',
    pincode: props.student.pincode || '',
    emergency_contact_name: props.student.emergency_contact_name || '',
    emergency_contact_phone: props.student.emergency_contact_phone || '',

    // Parent
    primary_phone: props.student.student_parent?.primary_phone || '',
    father_name: props.student.student_parent?.father_name || '',
    mother_name: props.student.student_parent?.mother_name || '',
    guardian_name: props.student.student_parent?.guardian_name || '',
    father_phone: props.student.student_parent?.father_phone || '',
    mother_phone: props.student.student_parent?.mother_phone || '',
    father_occupation: props.student.student_parent?.father_occupation || '',
    mother_occupation: props.student.student_parent?.mother_occupation || '',
    parent_address: props.student.student_parent?.address || '',

    // Parent extras
    guardian_email: props.student.student_parent?.guardian_email || '',
    guardian_phone: props.student.student_parent?.guardian_phone || '',
    father_qualification: props.student.student_parent?.father_qualification || '',
    mother_qualification: props.student.student_parent?.mother_qualification || '',

    // Academic-history override (persisted on the current year's row)
    student_type: props.student.current_academic_history?.student_type || 'New Student',
});

const submit = () => {
    form.post(`/school/students/${props.student.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <SchoolLayout :title="`Edit ${student.first_name}'s Profile`">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <Button variant="secondary" size="sm" as="link" :href="`/school/students/${student.id}`" class="back-btn">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back
                </Button>
                <div>
                    <div class="page-header-title">Edit Student</div>
                    <div class="page-header-sub">
                        Updating profile for {{ student.first_name }} {{ student.last_name }}
                        <span class="badge badge-indigo adm-badge">{{ student.admission_no }}</span>
                    </div>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="edit-form">

            <!-- Section 1: Personal Details -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Personal Details</span>
                </div>
                <div class="card-body">

                    <!-- Admission No row -->
                    <div class="admission-row">
                        <div class="admission-label">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                            </svg>
                            Admission No.
                        </div>
                        <div class="form-field admission-input-field">
                            <input v-model="form.admission_no" type="text" placeholder="e.g. ADM20260001" class="admission-input">
                            <span v-if="form.errors.admission_no" class="form-error">{{ form.errors.admission_no }}</span>
                        </div>
                        <span class="admission-hint">Must be unique across the school</span>
                    </div>

                    <!-- Student Type — overrides the count-based new/old classification -->
                    <div class="form-row form-row-3" style="margin-bottom: 1rem;">
                        <div class="form-field">
                            <label>Student Type</label>
                            <select v-model="form.student_type">
                                <option value="New Student">New Student</option>
                                <option value="Old Student">Old Student</option>
                            </select>
                            <span class="field-hint" style="font-size:.7rem;color:#6b7280;">Drives fee-rule matching for this academic year ("New only" / "Old only" structures).</span>
                            <span v-if="form.errors.student_type" class="form-error">{{ form.errors.student_type }}</span>
                        </div>
                    </div>

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
                            <label>Religion</label>
                            <input v-model="form.religion" type="text">
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
                            <label>Aadhaar / ID Card No.</label>
                            <input v-model="form.aadhaar_no" type="text" placeholder="12-digit Aadhaar">
                            <span v-if="form.errors.aadhaar_no" class="form-error">{{ form.errors.aadhaar_no }}</span>
                        </div>

                        <div class="form-field">
                            <label>Nationality</label>
                            <input v-model="form.nationality" type="text" placeholder="e.g. Indian">
                            <span v-if="form.errors.nationality" class="form-error">{{ form.errors.nationality }}</span>
                        </div>

                        <div class="form-field">
                            <label>Update Photo</label>
                            <div class="photo-field">
                                <img v-if="student.photo" :src="`/storage/${student.photo}`" class="photo-thumb" alt="Current Photo">
                                <input @input="form.photo = $event.target.files[0]" type="file" accept="image/*" class="file-input">
                            </div>
                            <span v-if="form.errors.photo" class="form-error">{{ form.errors.photo }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Parent Info -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Parent / Guardian Details</span>
                </div>
                <div class="card-body">

                    <!-- Primary Phone -->
                    <div class="primary-phone-row">
                        <div class="form-field">
                            <label>Primary Mobile <span class="required">*</span></label>
                            <input v-model="form.primary_phone" type="text" required placeholder="e.g. 9876543210" class="input-highlight">
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

            <!-- Section 3: Address -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Address &amp; Communication</span>
                </div>
                <div class="card-body">
                    <div class="form-row form-row-2">
                        <div class="form-field">
                            <label>Student Address</label>
                            <textarea v-model="form.address" rows="3" placeholder="Student's current address"></textarea>
                        </div>
                        <div class="form-field">
                            <label>Parent Address</label>
                            <textarea v-model="form.parent_address" rows="3" placeholder="Parent's address"></textarea>
                        </div>
                    </div>
                    <div class="form-row form-row-3" style="margin-top: 16px;">
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
                        <div class="form-field">
                            <label>Pincode</label>
                            <input v-model="form.pincode" type="text" placeholder="e.g. 400001">
                            <span v-if="form.errors.pincode" class="form-error">{{ form.errors.pincode }}</span>
                        </div>
                    </div>
                    <div class="form-row form-row-2" style="margin-top: 16px;">
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

            <!-- Form Actions -->
            <div class="form-actions">
                <Button variant="secondary" as="link" :href="`/school/students/${student.id}`">
                    Cancel
                </Button>
                <Button type="submit" :loading="form.processing">
                    <svg v-if="form.processing" class="spinner-icon" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Save Changes</span>
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

.adm-badge {
    margin-left: 8px;
    font-size: 0.7rem;
    vertical-align: middle;
}

/* ── Form wrapper ── */
.edit-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding-bottom: 48px;
}

/* ── Required asterisk ── */
.required {
    color: var(--danger);
    font-size: 0.8rem;
}

/* ── Admission number row ── */
.admission-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 16px;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: var(--radius);
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.admission-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #4338ca;
    flex-shrink: 0;
    white-space: nowrap;
}

.admission-input-field {
    flex: 1;
    min-width: 180px;
}

.admission-input {
    font-family: 'Courier New', monospace;
    font-weight: 700;
    border-color: #a5b4fc !important;
}

.admission-hint {
    font-size: 0.72rem;
    color: #6366f1;
    white-space: nowrap;
    flex-shrink: 0;
}

/* ── Photo field ── */
.photo-field {
    display: flex;
    align-items: center;
    gap: 12px;
}

.photo-thumb {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border);
    flex-shrink: 0;
}

/* ── File input ── */
.file-input {
    flex: 1;
    padding: 6px 0 !important;
    border: none !important;
    box-shadow: none !important;
    font-size: 0.8125rem;
}

/* ── Primary phone row: single field, capped width ── */
.primary-phone-row {
    margin-bottom: 20px;
    max-width: 320px;
}

/* ── Highlighted input for primary phone ── */
.input-highlight {
    font-weight: 600;
    border-color: #d1d5db !important;
}

.input-highlight:focus {
    border-color: var(--accent) !important;
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

    .admission-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .admission-input-field {
        width: 100%;
    }
}
</style>
