<script setup>
import Button from '@/Components/ui/Button.vue';
import { Link, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    staff: Object
});

const form = useForm({
    basic_salary: props.staff.basic_salary || '0',
    pan_no: props.staff.pan_no || '',
    epf_no: props.staff.epf_no || '',
    bank_name: props.staff.bank_name || '',
    bank_account_no: props.staff.bank_account_no || '',
    ifsc_code: props.staff.ifsc_code || '',
    allowances_config: props.staff.allowances_config || { da_percent: 52, hra_percent: 24, ta_fixed: 1600 },
    deductions_config: props.staff.deductions_config || { pf_percent: 12, esi_percent: 0.75, esi_threshold: 21000 },
    tax_config: props.staff.tax_config || { tds_fixed: 0 }
});

const submit = () => {
    form.patch(`/school/staff/${props.staff.id}/salary`, {
        preserveScroll: true
    });
};
</script>

<template>
    <SchoolLayout title="Edit Salary & Compliance">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Salary &amp; Compliance</h1>
                <p class="page-header-sub">
                    Editing payroll details for
                    <strong style="color:#0f172a;">{{ staff.user?.name }}</strong>
                </p>
            </div>
            <Button variant="secondary" as="link" :href="`/school/staff/${staff.id}`">← Back to Profile</Button>
        </div>

        <!-- Stat Overview Cards -->
        <div class="salary-stats">
            <div class="stat-card">
                <div class="stat-card-icon" style="background:#d1fae5;">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#10b981"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="stat-card-value">{{ $page.props.school.currency }} {{ Number(form.basic_salary).toLocaleString() }}</div>
                    <div class="stat-card-label">Basic Salary</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:#ede9fe;">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#6366f1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <div>
                    <div class="stat-card-value">{{ form.allowances_config.da_percent }}% DA</div>
                    <div class="stat-card-label">Dearness Allowance</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:#fee2e2;">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#ef4444"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="stat-card-value">{{ form.deductions_config.pf_percent }}% PF</div>
                    <div class="stat-card-label">Provident Fund</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:#fef3c7;">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#f59e0b"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                </div>
                <div>
                    <div class="stat-card-value">{{ form.tax_config.tds_fixed > 0 ? $page.props.school.currency + ' ' + form.tax_config.tds_fixed : 'None' }}</div>
                    <div class="stat-card-label">Monthly TDS</div>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit">
            <div class="form-sections">

                <!-- Section 1: Financial Information -->
                <div class="card">
                    <div class="card-header">
                        <div class="section-header">
                            <div class="section-icon" style="background:#d1fae5;">
                                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#10b981"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <span class="card-title">Financial Information</span>
                                <p class="section-sub">Base salary — allowances and deductions are calculated from this</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row" style="max-width:360px;">
                            <div class="form-field">
                                <label>Basic Salary ({{ $page.props.school.currency }}) <span class="req">*</span></label>
                                <div class="currency-input-wrap">
                                    <span class="currency-prefix">{{ $page.props.school.currency }}</span>
                                    <input v-model="form.basic_salary" type="number" step="0.01" min="0" required placeholder="e.g. 45000" class="currency-input">
                                </div>
                                <div v-if="form.errors.basic_salary" class="form-error">{{ form.errors.basic_salary }}</div>
                                <span class="field-hint">Allowances and deductions are calculated automatically during payroll generation.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Allowances & Deductions -->
                <div class="card">
                    <div class="card-header">
                        <div class="section-header">
                            <div class="section-icon" style="background:#ede9fe;">
                                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#6366f1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                            <div>
                                <span class="card-title">Payroll Allowances &amp; Deductions</span>
                                <p class="section-sub">Per-employee overrides for allowance and deduction percentages</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="subsection-label">Allowances</p>
                        <div class="form-row form-row-3" style="margin-bottom:20px;">
                            <div class="form-field">
                                <label>DA Percentage (%)</label>
                                <input v-model="form.allowances_config.da_percent" type="number" step="0.1" placeholder="52">
                            </div>
                            <div class="form-field">
                                <label>HRA Percentage (%)</label>
                                <input v-model="form.allowances_config.hra_percent" type="number" step="0.1" placeholder="24">
                            </div>
                            <div class="form-field">
                                <label>Fixed TA ({{ $page.props.school.currency }})</label>
                                <input v-model="form.allowances_config.ta_fixed" type="number" step="1" placeholder="1600">
                            </div>
                        </div>

                        <div class="divider"></div>

                        <p class="subsection-label">Deductions &amp; Tax</p>
                        <div class="form-row form-row-4">
                            <div class="form-field">
                                <label>PF Percentage (%)</label>
                                <input v-model="form.deductions_config.pf_percent" type="number" step="0.1" placeholder="12">
                            </div>
                            <div class="form-field">
                                <label>ESI Percentage (%)</label>
                                <input v-model="form.deductions_config.esi_percent" type="number" step="0.01" placeholder="0.75">
                            </div>
                            <div class="form-field">
                                <label>ESI Threshold ({{ $page.props.school.currency }})</label>
                                <input v-model="form.deductions_config.esi_threshold" type="number" step="1" placeholder="21000">
                            </div>
                            <div class="form-field">
                                <label>Fixed Monthly TDS ({{ $page.props.school.currency }})</label>
                                <input v-model="form.tax_config.tds_fixed" type="number" step="1" placeholder="0">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Compliance & Statutory -->
                <div class="card">
                    <div class="card-header">
                        <div class="section-header">
                            <div class="section-icon" style="background:#fef3c7;">
                                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#f59e0b"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            </div>
                            <div>
                                <span class="card-title">Compliance &amp; Statutory</span>
                                <p class="section-sub">PAN, EPF and other mandatory identifiers</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row form-row-2">
                            <div class="form-field">
                                <label>PAN Number</label>
                                <input v-model="form.pan_no" type="text" placeholder="ABCDE1234F" style="text-transform:uppercase;font-family:monospace;">
                                <div v-if="form.errors.pan_no" class="form-error">{{ form.errors.pan_no }}</div>
                            </div>
                            <div class="form-field">
                                <label>EPF Registration No.</label>
                                <input v-model="form.epf_no" type="text" placeholder="DL/CPM/12345/000" style="text-transform:uppercase;font-family:monospace;">
                                <div v-if="form.errors.epf_no" class="form-error">{{ form.errors.epf_no }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Bank Account -->
                <div class="card">
                    <div class="card-header">
                        <div class="section-header">
                            <div class="section-icon" style="background:#e0f2fe;">
                                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#0ea5e9"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            </div>
                            <div>
                                <span class="card-title">Bank Account Details</span>
                                <p class="section-sub">Used for salary disbursement</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row form-row-3">
                            <div class="form-field">
                                <label>Bank Name</label>
                                <input v-model="form.bank_name" type="text" placeholder="e.g. State Bank of India">
                                <div v-if="form.errors.bank_name" class="form-error">{{ form.errors.bank_name }}</div>
                            </div>
                            <div class="form-field">
                                <label>Account Number</label>
                                <input v-model="form.bank_account_no" type="text" placeholder="XXXXXXXXXX123" style="font-family:monospace;">
                                <div v-if="form.errors.bank_account_no" class="form-error">{{ form.errors.bank_account_no }}</div>
                            </div>
                            <div class="form-field">
                                <label>IFSC Code</label>
                                <input v-model="form.ifsc_code" type="text" placeholder="SBIN0001234" style="text-transform:uppercase;font-family:monospace;">
                                <div v-if="form.errors.ifsc_code" class="form-error">{{ form.errors.ifsc_code }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <Button variant="secondary" as="link" :href="`/school/staff/${staff.id}`">Cancel</Button>
                    <Button type="submit" :loading="form.processing">
                        <svg v-if="form.processing" class="spin-icon" fill="none" viewBox="0 0 24 24"><circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Save Financial Details
                    </Button>
                </div>

            </div>
        </form>

    </SchoolLayout>
</template>

<style scoped>
/* Stats */
.salary-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}
@media (max-width: 900px) { .salary-stats { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 500px) { .salary-stats { grid-template-columns: 1fr; } }

/* Form layout */
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
.subsection-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #94a3b8;
    margin: 0 0 12px;
}
.divider {
    border: none;
    border-top: 1px solid var(--border);
    margin: 16px 0 20px;
}
.req { color: var(--danger); }
.field-hint {
    font-size: 0.72rem;
    color: #64748b;
    display: block;
    margin-top: 3px;
}

/* Currency input */
.currency-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.currency-prefix {
    position: absolute;
    left: 10px;
    font-weight: 500;
    color: #64748b;
    pointer-events: none;
    font-size: 0.875rem;
}
.currency-input {
    padding-left: 28px !important;
    width: 100%;
}

/* 2-col grid */
.form-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
/* 4-col grid */
.form-row-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}
@media (max-width: 900px) {
    .form-row-2 { grid-template-columns: 1fr; }
    .form-row-4 { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 500px) {
    .form-row-4 { grid-template-columns: 1fr; }
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
