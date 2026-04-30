<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    staff: Object,
    departments: Array,
    designations: Array
});

// Initialize form with current values
const form = useForm({
    name: props.staff?.user?.name || '',
    phone: props.staff?.user?.phone || '',
    department_id: props.staff?.department_id || '',
    designation_id: props.staff?.designation_id || '',
    joining_date: props.staff?.joining_date ? props.staff.joining_date.split('T')[0] : '',
    qualification: props.staff?.qualification || '',
    experience_years: props.staff?.experience_years || '',
    bank_name: props.staff?.bank_name || '',
    bank_account_no: props.staff?.bank_account_no || '',
    ifsc_code: props.staff?.ifsc_code || '',
    pan_no: props.staff?.pan_no || '',
    epf_no: props.staff?.epf_no || '',
    reason: ''
});

const submit = () => {
    form.post(`/school/staff/${props.staff.id}/request-edit`);
};
</script>

<template>
    <SchoolLayout title="Request Profile Edit">

        <!-- Page header -->
        <PageHeader>
            <template #title>
                <div class="ph-title-row">
                    <button type="button" @click="() => window.history.back()" class="back-btn" aria-label="Go back">
                        <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </button>
                    <h1 class="page-header-title">Request Profile Edit</h1>
                </div>
            </template>
            <template #subtitle>
                <p class="page-header-sub">Employee ID: <strong>{{ staff.employee_id }}</strong></p>
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

            <!-- Account & Profile -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title section-title">
                        <span class="section-badge">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </span>
                        Account &amp; Profile
                    </h3>
                </div>
                <div class="card-body">
                    <div class="form-grid form-grid--2">
                        <div class="form-field">
                            <label class="form-label">Full Name</label>
                            <input v-model="form.name" type="text" class="form-input">
                            <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Phone Number</label>
                            <input v-model="form.phone" type="text" class="form-input">
                            <p v-if="form.errors.phone" class="form-error">{{ form.errors.phone }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employment Details -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title section-title">
                        <span class="section-badge">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </span>
                        Employment Details
                    </h3>
                </div>
                <div class="card-body">
                    <div class="form-grid form-grid--3">
                        <div class="form-field">
                            <label class="form-label">Department</label>
                            <select v-model="form.department_id" class="form-input">
                                <option value="">Select Department</option>
                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Designation</label>
                            <select v-model="form.designation_id" class="form-input">
                                <option value="">Select Designation</option>
                                <option v-for="desig in designations" :key="desig.id" :value="desig.id">{{ desig.name }}</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Joining Date</label>
                            <input v-model="form.joining_date" type="date" class="form-input">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Qualification</label>
                            <input v-model="form.qualification" type="text" class="form-input">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Total Experience (Years)</label>
                            <input v-model="form.experience_years" type="number" step="0.5" class="form-input">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial & Compliance -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title section-title">
                        <span class="section-badge">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        </span>
                        Financial &amp; Compliance
                    </h3>
                </div>
                <div class="card-body">
                    <div class="finance-notice">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Financial details are securely stored and only visible to authorised admins.
                    </div>
                    <div class="form-grid form-grid--3">
                        <div class="form-field">
                            <label class="form-label">PAN Number</label>
                            <input v-model="form.pan_no" type="text" class="form-input uppercase" placeholder="ABCDE1234F">
                        </div>
                        <div class="form-field">
                            <label class="form-label">EPF Number</label>
                            <input v-model="form.epf_no" type="text" class="form-input uppercase">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Bank Name</label>
                            <input v-model="form.bank_name" type="text" class="form-input">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Bank Account Number</label>
                            <input v-model="form.bank_account_no" type="text" class="form-input">
                        </div>
                        <div class="form-field">
                            <label class="form-label">IFSC Code</label>
                            <input v-model="form.ifsc_code" type="text" class="form-input uppercase">
                        </div>
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
                            placeholder="E.g. Bank details changed, got married, correcting qualification..."
                        ></textarea>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <Button variant="secondary" type="button" @click="() => window.history.back()">Cancel</Button>
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
    cursor: pointer;
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
.form-textarea { resize: vertical; min-height: 72px; }
.uppercase { text-transform: uppercase; }

/* ── Finance notice ── */
.finance-notice {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .625rem .875rem;
    background: #f8fafc;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: .8125rem;
    color: #64748b;
    margin-bottom: 1rem;
}

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
