<script setup>
import Button from '@/Components/ui/Button.vue';
import { useForm, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    departments: Array,
    designations: Array,
    generated_employee_id: String,
    available_roles: Object,   // { teacher: 'Teacher', principal: 'Principal', ... }
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    role: 'teacher',
    employee_id: props.generated_employee_id || '',
    department_id: '',
    designation_id: '',
    joining_date: '',
    qualification: '',
    experience_years: '',
    basic_salary: '',
    bank_name: '',
    bank_account_no: '',
    ifsc_code: '',
    pan_no: '',
    epf_no: '',
    photo: null,
    signature: null,
});

const submit = () => { form.post('/school/staff'); };
</script>

<template>
    <SchoolLayout title="Add Staff Member">

        <div class="page-header">
            <div>
                <h1 class="page-header-title">Add Staff Member</h1>
                <p class="page-header-sub">Create a new employee profile and login account</p>
            </div>
            <Button variant="secondary" as="link" href="/school/staff">← Back</Button>
        </div>

        <form @submit.prevent="submit">
            <div class="form-sections">

                <!-- Section 1: Account & Profile -->
                <div class="card">
                    <div class="card-header">
                        <div class="section-header">
                            <div class="section-icon" style="background:#ede9fe;">
                                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#6366f1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <div>
                                <span class="card-title">Account &amp; Profile Information</span>
                                <p class="section-sub">Login credentials and personal identity</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row form-row-3">
                            <div class="form-field">
                                <label>Full Name <span class="req">*</span></label>
                                <input v-model="form.name" type="text" placeholder="Enter full name" required>
                                <div v-if="form.errors.name" class="form-error">{{ form.errors.name }}</div>
                            </div>
                            <div class="form-field">
                                <label>Email Address <span class="req">*</span></label>
                                <input v-model="form.email" type="email" placeholder="staff@school.edu" required>
                                <div v-if="form.errors.email" class="form-error">{{ form.errors.email }}</div>
                            </div>
                            <div class="form-field">
                                <label>Phone Number <span class="req">*</span></label>
                                <input v-model="form.phone" type="text" placeholder="10-digit mobile number" required>
                                <div v-if="form.errors.phone" class="form-error">{{ form.errors.phone }}</div>
                            </div>
                            <div class="form-field">
                                <label>System Role <span class="req">*</span></label>
                                <select v-model="form.role" required id="staff-role-select">
                                    <option v-for="(label, slug) in available_roles" :key="slug" :value="slug">{{ label }}</option>
                                </select>
                                <div v-if="form.errors.role" class="form-error">{{ form.errors.role }}</div>
                                <span class="field-hint">Controls what this staff member can access after login.</span>
                            </div>
                            <div class="form-field">
                                <label>Profile Photo <span class="opt">(Optional, max 5MB)</span></label>
                                <input @input="form.photo = $event.target.files[0]" type="file" accept="image/*" class="file-input">
                                <div v-if="form.errors.photo" class="form-error">{{ form.errors.photo }}</div>
                            </div>
                            <div class="form-field">
                                <label>Staff Signature <span class="opt">(Optional, max 2MB)</span></label>
                                <input @input="form.signature = $event.target.files[0]" type="file" accept="image/*" class="file-input">
                                <div v-if="form.errors.signature" class="form-error">{{ form.errors.signature }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Employment Details -->
                <div class="card">
                    <div class="card-header">
                        <div class="section-header">
                            <div class="section-icon" style="background:#d1fae5;">
                                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#10b981"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <span class="card-title">Employment Details</span>
                                <p class="section-sub">Role, department and tenure information</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row form-row-3">
                            <div class="form-field">
                                <label>Employee ID <span class="req">*</span></label>
                                <input v-model="form.employee_id" type="text" placeholder="Auto-generated" required>
                                <div v-if="form.errors.employee_id" class="form-error">{{ form.errors.employee_id }}</div>
                            </div>
                            <div class="form-field">
                                <label>Department</label>
                                <select v-model="form.department_id">
                                    <option value="">Select Department</option>
                                    <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Designation</label>
                                <select v-model="form.designation_id">
                                    <option value="">Select Designation</option>
                                    <option v-for="desig in designations" :key="desig.id" :value="desig.id">{{ desig.name }}</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Joining Date</label>
                                <input v-model="form.joining_date" type="date">
                            </div>
                            <div class="form-field">
                                <label>Qualification</label>
                                <input v-model="form.qualification" type="text" placeholder="e.g. B.Ed, M.Sc">
                            </div>
                            <div class="form-field">
                                <label>Experience (Years)</label>
                                <input v-model="form.experience_years" type="number" step="0.5" min="0" placeholder="0">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Financial & Compliance -->
                <div class="card">
                    <div class="card-header">
                        <div class="section-header">
                            <div class="section-icon" style="background:#fef3c7;">
                                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#f59e0b"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            </div>
                            <div>
                                <span class="card-title">Financial &amp; Compliance</span>
                                <p class="section-sub">Salary, banking and statutory information</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row form-row-3">
                            <div class="form-field">
                                <label>Basic Salary (₹)</label>
                                <input v-model="form.basic_salary" type="number" step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="form-field">
                                <label>PAN Number</label>
                                <input v-model="form.pan_no" type="text" placeholder="AAAAA0000A" style="text-transform:uppercase">
                            </div>
                            <div class="form-field">
                                <label>EPF Number</label>
                                <input v-model="form.epf_no" type="text" placeholder="EPF Account Number" style="text-transform:uppercase">
                            </div>
                            <div class="form-field">
                                <label>Bank Name</label>
                                <input v-model="form.bank_name" type="text" placeholder="e.g. State Bank of India">
                            </div>
                            <div class="form-field">
                                <label>Bank Account Number</label>
                                <input v-model="form.bank_account_no" type="text" placeholder="Account Number">
                            </div>
                            <div class="form-field">
                                <label>IFSC Code</label>
                                <input v-model="form.ifsc_code" type="text" placeholder="SBIN0000000" style="text-transform:uppercase">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <Button variant="secondary" type="button" @click="() => window.history.back()">Cancel</Button>
                    <Button type="submit" :loading="form.processing">
                        <svg v-if="form.processing" class="spin-icon" fill="none" viewBox="0 0 24 24"><circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Save Staff Member
                    </Button>
                </div>

            </div>
        </form>

    </SchoolLayout>
</template>

<style scoped>
.form-sections {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 12px;
}
.section-icon {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.section-sub {
    font-size: 0.75rem;
    color: #94a3b8;
    margin: 0;
    margin-top: 1px;
}
.req {
    color: var(--danger);
}
.opt {
    color: #94a3b8;
    font-weight: 400;
    font-size: 0.78em;
}
.field-hint {
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 3px;
    display: block;
}
.file-input {
    padding: 6px !important;
    cursor: pointer;
}
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-bottom: 16px;
}
.spin-icon {
    width: 16px;
    height: 16px;
    animation: spin 1s linear infinite;
    display: inline-block;
    vertical-align: middle;
    margin-right: 4px;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
