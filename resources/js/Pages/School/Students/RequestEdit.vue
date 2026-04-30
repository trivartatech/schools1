<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    student: Object
});

const form = useForm({
    // Student Identity
    first_name: props.student?.first_name || '',
    last_name: props.student?.last_name || '',
    dob: props.student?.dob || '',
    gender: props.student?.gender || 'Male',
    blood_group: props.student?.blood_group || '',
    birth_place: props.student?.birth_place || '',
    mother_tongue: props.student?.mother_tongue || '',
    religion: props.student?.religion || '',
    caste: props.student?.caste || '',
    category: props.student?.category || '',
    aadhaar_no: props.student?.aadhaar_no || '',
    address: props.student?.address || '',

    // Parent Details
    primary_phone: props.student?.student_parent?.primary_phone || '',
    father_name: props.student?.student_parent?.father_name || '',
    father_phone: props.student?.student_parent?.father_phone || '',
    father_occupation: props.student?.student_parent?.father_occupation || '',
    father_qualification: props.student?.student_parent?.father_qualification || '',
    mother_name: props.student?.student_parent?.mother_name || '',
    mother_phone: props.student?.student_parent?.mother_phone || '',
    mother_occupation: props.student?.student_parent?.mother_occupation || '',
    mother_qualification: props.student?.student_parent?.mother_qualification || '',
    guardian_name: props.student?.student_parent?.guardian_name || '',
    guardian_email: props.student?.student_parent?.guardian_email || '',
    guardian_phone: props.student?.student_parent?.guardian_phone || '',
    parent_address: props.student?.student_parent?.address || '',

    reason: ''
});

const submit = () => {
    form.post(`/school/students/${props.student.id}/request-edit`);
};
</script>

<template>
    <Head title="Request Profile Edit" />
    <SchoolLayout title="Request Profile Edit">

        <!-- Page header -->
        <PageHeader>
            <template #title>
                <div class="ph-title-row">
                    <Link :href="`/school/students/${student.id}`" class="back-btn" aria-label="Back">
                        <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </Link>
                    <h1 class="page-header-title">Request Profile Edit</h1>
                </div>
            </template>
            <template #subtitle>
                <p class="page-header-sub">Admission No: <strong>{{ student.admission_no }}</strong></p>
            </template>
        </PageHeader>

        <!-- Workflow notice -->
        <div class="workflow-notice">
            <span class="workflow-notice-icon">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <p>
                <strong>Profile Update Workflow</strong> &mdash;
                Change the values you wish to update and provide a reason. Your request will be sent to the School Admin for approval.
            </p>
        </div>

        <form @submit.prevent="submit" class="request-form">

            <!-- Student Information -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title section-title">
                        <span class="section-badge">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </span>
                        Student Information
                    </h3>
                </div>
                <div class="card-body">
                    <div class="form-grid form-grid--3">
                        <div class="form-field">
                            <label class="form-label">First Name</label>
                            <input v-model="form.first_name" type="text" class="form-input">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Last Name</label>
                            <input v-model="form.last_name" type="text" class="form-input">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Date of Birth</label>
                            <input v-model="form.dob" type="date" class="form-input">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Gender</label>
                            <select v-model="form.gender" class="form-input">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Blood Group</label>
                            <input v-model="form.blood_group" type="text" class="form-input" placeholder="e.g. O+">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Aadhaar No.</label>
                            <input v-model="form.aadhaar_no" type="text" class="form-input" placeholder="12-digit Aadhaar">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Religion</label>
                            <input v-model="form.religion" type="text" class="form-input">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Caste</label>
                            <input v-model="form.caste" type="text" class="form-input">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Category</label>
                            <input v-model="form.category" type="text" class="form-input" placeholder="e.g. OBC, SC, General">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Mother Tongue</label>
                            <input v-model="form.mother_tongue" type="text" class="form-input">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Birth Place</label>
                            <input v-model="form.birth_place" type="text" class="form-input">
                        </div>
                        <div class="form-field form-field--full">
                            <label class="form-label">Corresponding Address</label>
                            <textarea v-model="form.address" rows="2" class="form-input form-textarea"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parent / Guardian Details -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title section-title">
                        <span class="section-badge">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </span>
                        Parent / Guardian Details
                    </h3>
                </div>
                <div class="card-body">

                    <!-- Primary phone highlight -->
                    <div class="primary-phone-row">
                        <div class="form-field">
                            <label class="form-label">
                                Primary Mobile
                                <span class="form-hint">(used for login / SMS / WhatsApp)</span>
                            </label>
                            <input v-model="form.primary_phone" type="text" class="form-input form-input--highlight" placeholder="Primary contact number">
                        </div>
                    </div>

                    <!-- Father / Mother blocks -->
                    <div class="parent-grid">
                        <div class="parent-block">
                            <p class="parent-block-label">
                                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                Father
                            </p>
                            <div class="form-field">
                                <label class="form-label">Father's Name</label>
                                <input v-model="form.father_name" type="text" class="form-input">
                            </div>
                            <div class="form-field">
                                <label class="form-label">Father's Phone</label>
                                <input v-model="form.father_phone" type="text" class="form-input">
                            </div>
                            <div class="form-field">
                                <label class="form-label">Father's Occupation</label>
                                <input v-model="form.father_occupation" type="text" class="form-input">
                            </div>
                            <div class="form-field">
                                <label class="form-label">Father's Qualification</label>
                                <input v-model="form.father_qualification" type="text" class="form-input" placeholder="e.g. B.Sc">
                            </div>
                        </div>

                        <div class="parent-block">
                            <p class="parent-block-label">
                                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                Mother
                            </p>
                            <div class="form-field">
                                <label class="form-label">Mother's Name</label>
                                <input v-model="form.mother_name" type="text" class="form-input">
                            </div>
                            <div class="form-field">
                                <label class="form-label">Mother's Phone</label>
                                <input v-model="form.mother_phone" type="text" class="form-input">
                            </div>
                            <div class="form-field">
                                <label class="form-label">Mother's Occupation</label>
                                <input v-model="form.mother_occupation" type="text" class="form-input">
                            </div>
                            <div class="form-field">
                                <label class="form-label">Mother's Qualification</label>
                                <input v-model="form.mother_qualification" type="text" class="form-input" placeholder="e.g. M.A">
                            </div>
                        </div>
                    </div>

                    <!-- Guardian Name / Email / Phone -->
                    <div class="form-grid form-grid--3 guardian-row">
                        <div class="form-field">
                            <label class="form-label">
                                Guardian Name
                                <span class="form-hint">(if applicable)</span>
                            </label>
                            <input v-model="form.guardian_name" type="text" class="form-input">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Guardian Email</label>
                            <input v-model="form.guardian_email" type="email" class="form-input" placeholder="e.g. parent@email.com">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Guardian Phone</label>
                            <input v-model="form.guardian_phone" type="text" class="form-input" placeholder="Alternate mobile number">
                        </div>
                    </div>

                    <!-- Parent address -->
                    <div class="form-field" style="margin-top: 1rem;">
                        <label class="form-label">Permanent Parent Address</label>
                        <textarea v-model="form.parent_address" rows="2" class="form-input form-textarea"></textarea>
                    </div>

                </div>
            </div>

            <!-- Reason for Change -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title section-title">
                        <span class="section-badge">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </span>
                        Reason for Change
                        <span class="optional-tag">Optional</span>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="form-field">
                        <textarea
                            v-model="form.reason"
                            rows="3"
                            class="form-input form-textarea"
                            placeholder="E.g. Address changed, correcting typo in name, updated contact info..."
                        ></textarea>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <Button variant="secondary" as="link" :href="`/school/students/${student.id}`">Cancel</Button>
                <Button type="submit" :loading="form.processing">
                    <svg v-if="form.processing" class="spin-icon" width="15" height="15" fill="none" viewBox="0 0 24 24">
                        <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                    </svg>
                    <svg v-else width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Submit Request
                </Button>
            </div>

        </form>

    </SchoolLayout>
</template>

<style scoped>
/* ── Header ── */
.ph-title-row { display: flex; align-items: center; gap: .875rem; }
.back-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--surface);
    color: #64748b;
    text-decoration: none;
    transition: background .15s, color .15s;
    flex-shrink: 0;
}
.back-btn:hover { background: #f1f5f9; color: #1e293b; }

/* ── Workflow notice ── */
.workflow-notice {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    padding: .875rem 1.125rem;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-left: 4px solid #3b82f6;
    border-radius: var(--radius);
    font-size: .875rem;
    color: #1e40af;
    margin-bottom: 1.125rem;
    line-height: 1.55;
}
.workflow-notice p { margin: 0; }
.workflow-notice-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #3b82f6;
    flex-shrink: 0;
    margin-top: .1rem;
}

/* ── Form layout ── */
.request-form { display: flex; flex-direction: column; gap: 1.125rem; }

.form-grid { display: grid; gap: 1rem; }
.form-grid--2 { grid-template-columns: repeat(2, 1fr); }
.form-grid--3 { grid-template-columns: repeat(3, 1fr); }
@media (max-width: 900px) { .form-grid--3 { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .form-grid--2, .form-grid--3 { grid-template-columns: 1fr; } }
.form-field--full { grid-column: 1 / -1; }

/* ── Section badge ── */
.section-title { display: flex; align-items: center; gap: .5rem; }
.section-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: #e0e7ff;
    color: #4338ca;
    flex-shrink: 0;
}
.optional-tag {
    font-size: .6875rem;
    font-weight: 500;
    color: #94a3b8;
    background: #f1f5f9;
    padding: .1rem .45rem;
    border-radius: 999px;
    margin-left: .25rem;
}

/* ── Form fields ── */
.form-label {
    display: block;
    font-size: .8125rem;
    font-weight: 500;
    color: #374151;
    margin-bottom: .3rem;
}
.form-hint {
    font-weight: 400;
    color: #94a3b8;
    font-size: .75rem;
}
.form-input {
    width: 100%;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: .4375rem .75rem;
    font-size: .875rem;
    font-family: inherit;
    background: #fff;
    transition: border-color .15s, box-shadow .15s;
    box-sizing: border-box;
}
.form-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, .12);
}
.form-input--highlight { font-weight: 600; border-color: #a5b4fc; }
.form-input--highlight:focus { border-color: var(--accent); }
.form-textarea { resize: vertical; min-height: 60px; }

/* ── Parent section ── */
.primary-phone-row { margin-bottom: 1.25rem; max-width: 380px; }
.parent-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}
@media (max-width: 680px) { .parent-grid { grid-template-columns: 1fr; } }
.parent-block {
    display: flex;
    flex-direction: column;
    gap: .75rem;
    padding: 1rem 1.125rem;
    background: #f8fafc;
    border: 1px solid var(--border);
    border-radius: var(--radius);
}
.parent-block-label {
    display: flex;
    align-items: center;
    gap: .35rem;
    font-size: .6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #64748b;
    margin: 0 0 .125rem;
}
.guardian-row { align-items: start; }

/* ── Actions ── */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: .625rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}
.spin-icon { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
