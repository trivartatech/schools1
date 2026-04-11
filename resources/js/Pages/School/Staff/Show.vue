<script setup>
import Button from '@/Components/ui/Button.vue';
import { Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import Table from '@/Components/ui/Table.vue';

const { canDo, canRequestEditStaff } = usePermissions();

const props = defineProps({
    staff: Object,
    leaveStats: Object,
    payrolls: Array
});

// Format currency
const formatMoney = (amount) => {
    if (!amount) return '₹0.00';
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);
};

// Format date
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
};

const getMonthName = (monthNum) => {
    if (!monthNum) return '';
    const date = new Date();
    date.setMonth(monthNum - 1);
    return new Intl.DateTimeFormat('en-US', { month: 'long' }).format(date);
};

const totalLeaves = computed(() => {
    let total = 0;
    for (const key in props.leaveStats) {
        total += parseInt(props.leaveStats[key] || 0);
    }
    return total;
});
</script>

<template>
    <SchoolLayout :title="staff.user?.name + ' — Profile'">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <Button variant="icon" size="sm" as="link" href="/school/staff" aria-label="Back to staff list">
                    <template #icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </template>
                </Button>
                <div>
                    <h1 class="page-header-title">Staff Profile</h1>
                    <p class="page-header-sub">Comprehensive employee information and payroll records.</p>
                </div>
            </div>
            <div style="display:flex;gap:8px;">
                <Button variant="secondary" size="sm" as="link" v-if="canRequestEditStaff" :href="`/school/staff/${staff.id}/request-edit`">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Request Update
                </Button>
                <Button as="link" v-if="canDo('edit', 'staff')" :href="`/school/staff/${staff.id}/edit`">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit Profile
                </Button>
            </div>
        </div>

        <div class="staff-profile-grid">
            <!-- ── Left Column ── -->
            <div class="staff-left-col">

                <!-- Identity Card -->
                <div class="card" style="overflow:hidden;">
                    <div class="staff-cover"></div>
                    <div class="staff-identity-body">
                        <div class="staff-avatar-wrap">
                            <img v-if="staff.photo_url" :src="staff.photo_url" class="staff-avatar-img" />
                            <span v-else class="staff-avatar-initials">{{ staff.user?.name?.charAt(0) }}</span>
                        </div>
                        <h2 class="staff-name">{{ staff.user?.name }}</h2>
                        <p class="staff-designation">{{ staff.designation?.name || staff.user?.user_type?.replace('_', ' ') || 'Staff Member' }}</p>
                        <p class="staff-emp-id">EMP# {{ staff.employee_id }}</p>

                        <div class="staff-meta-rows">
                            <div class="staff-meta-row">
                                <span>Department</span>
                                <strong>{{ staff.department?.name || 'Unassigned' }}</strong>
                            </div>
                            <div class="staff-meta-row">
                                <span>Joined</span>
                                <strong>{{ formatDate(staff.joining_date) }}</strong>
                            </div>
                        </div>

                        <div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin-top:12px;">
                            <span class="badge" :class="staff.user?.is_active ? 'badge-green' : 'badge-red'">
                                {{ staff.user?.is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <span v-for="role in staff.user?.roles" :key="role.id" class="badge badge-indigo" style="text-transform:capitalize;">
                                {{ role.name.replace('_', ' ') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Contact -->
                <div class="card">
                    <div class="card-header"><span class="card-title">Contact Details</span></div>
                    <div class="card-body" style="display:flex;flex-direction:column;gap:14px;">
                        <div class="contact-row">
                            <div class="contact-icon"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div>
                            <div><p class="contact-label">Email</p><p class="contact-value">{{ staff.user?.email || 'N/A' }}</p></div>
                        </div>
                        <div class="contact-row">
                            <div class="contact-icon"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg></div>
                            <div><p class="contact-label">Phone</p><p class="contact-value">{{ staff.user?.phone || 'N/A' }}</p></div>
                        </div>
                    </div>
                </div>

                <!-- Leave Summary -->
                <div class="card">
                    <div class="card-header"><span class="card-title">Leave Summary</span></div>
                    <div class="card-body">
                        <div class="leave-stats-grid">
                            <div class="leave-stat leave-stat--amber">
                                <div class="leave-stat-val">{{ leaveStats?.pending || 0 }}</div>
                                <div class="leave-stat-lbl">Pending</div>
                            </div>
                            <div class="leave-stat leave-stat--green">
                                <div class="leave-stat-val">{{ leaveStats?.approved || 0 }}</div>
                                <div class="leave-stat-lbl">Approved</div>
                            </div>
                            <div class="leave-stat leave-stat--red">
                                <div class="leave-stat-val">{{ leaveStats?.rejected || 0 }}</div>
                                <div class="leave-stat-lbl">Rejected</div>
                            </div>
                            <div class="leave-stat leave-stat--gray">
                                <div class="leave-stat-val">{{ totalLeaves }}</div>
                                <div class="leave-stat-lbl">Total</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Right Column ── -->
            <div class="staff-right-col">

                <!-- HR Profile -->
                <div class="card">
                    <div class="card-header"><span class="card-title">HR & Financial Profile</span></div>
                    <div class="card-body">
                        <div class="hr-grid">
                            <div class="hr-field">
                                <p class="hr-label">Qualification</p>
                                <p class="hr-value">{{ staff.qualification || 'Not specified' }}</p>
                            </div>
                            <div class="hr-field">
                                <p class="hr-label">Experience</p>
                                <p class="hr-value">{{ staff.experience_years ? staff.experience_years + ' Years' : 'Not specified' }}</p>
                            </div>
                            <div class="hr-field">
                                <p class="hr-label">Basic Salary</p>
                                <p class="hr-value" style="color:#059669;font-weight:700;">{{ formatMoney(staff.basic_salary) }} / mo</p>
                            </div>
                            <div class="hr-field">
                                <p class="hr-label">PAN Number</p>
                                <p class="hr-value" style="font-family:monospace;text-transform:uppercase;">{{ staff.pan_no || 'N/A' }}</p>
                            </div>
                            <div class="hr-field">
                                <p class="hr-label">Bank Name</p>
                                <p class="hr-value">{{ staff.bank_name || 'N/A' }}</p>
                            </div>
                            <div class="hr-field">
                                <p class="hr-label">Account Details</p>
                                <p class="hr-value" style="font-family:monospace;">{{ staff.bank_account_no ? `A/C: ${staff.bank_account_no}` : 'N/A' }}</p>
                                <p v-if="staff.ifsc_code" style="font-size:0.75rem;color:#94a3b8;margin-top:2px;">IFSC: {{ staff.ifsc_code }}</p>
                            </div>
                            <div v-if="staff.signature_url" class="hr-field">
                                <p class="hr-label">Signature</p>
                                <img :src="staff.signature_url" style="height:40px;max-width:160px;object-fit:contain;opacity:0.8;margin-top:4px;" alt="Signature">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payroll History -->
                <div class="card" style="overflow:hidden;">
                    <div class="card-header"><span class="card-title">Payroll History (Last 6 Months)</span></div>
                    <div v-if="!payrolls?.length" style="padding:40px 24px;text-align:center;color:#94a3b8;">
                        <p style="font-weight:600;color:#1e293b;">No payroll records found.</p>
                        <p style="font-size:0.8125rem;margin-top:4px;">Salary slips will appear here once generated by HR.</p>
                    </div>
                    <div v-else style="overflow-x:auto;">
                        <Table>
                            <thead>
                                <tr>
                                    <th>Month / Payslip</th>
                                    <th style="text-align:right;">Gross</th>
                                    <th style="text-align:right;">Deductions</th>
                                    <th style="text-align:right;">Net Pay</th>
                                    <th style="text-align:center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="pay in payrolls" :key="pay.id">
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <div class="payslip-month-badge">
                                                <span>{{ getMonthName(pay.month).substring(0, 3) }}</span>
                                                <span style="font-size:0.6rem;opacity:0.7;">{{ pay.year }}</span>
                                            </div>
                                            <div>
                                                <p style="font-weight:600;font-size:0.8125rem;color:#0f172a;">Payslip #{{ pay.id }}</p>
                                                <p style="font-size:0.7rem;color:#94a3b8;">{{ pay.payment_date ? formatDate(pay.payment_date) : 'Pending' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align:right;font-weight:600;">{{ formatMoney(parseFloat(pay.basic_pay || 0) + parseFloat(pay.allowances || 0)) }}</td>
                                    <td style="text-align:right;color:#ef4444;font-weight:600;">−{{ formatMoney(pay.deductions) }}</td>
                                    <td style="text-align:right;color:#059669;font-weight:700;font-size:0.9375rem;">{{ formatMoney(pay.net_salary) }}</td>
                                    <td style="text-align:center;">
                                        <span class="badge" :class="pay.status === 'paid' ? 'badge-green' : 'badge-amber'" style="text-transform:capitalize;">{{ pay.status }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </Table>
                    </div>
                </div>

            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
.staff-profile-grid {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
    align-items: start;
}
@media (max-width: 900px) {
    .staff-profile-grid { grid-template-columns: 1fr; }
}
.staff-left-col, .staff-right-col { display: flex; flex-direction: column; gap: 20px; }

/* Identity Card */
.staff-cover {
    height: 80px;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a78bfa 100%);
    margin: -1px -1px 0;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}
.staff-identity-body {
    padding: 0 20px 20px;
    text-align: center;
}
.staff-avatar-wrap {
    width: 76px; height: 76px;
    border-radius: 50%;
    border: 4px solid #fff;
    box-shadow: 0 4px 16px rgba(99,102,241,.25);
    margin: -38px auto 12px;
    overflow: hidden;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
}
.staff-avatar-img { width: 100%; height: 100%; object-fit: cover; }
.staff-avatar-initials { font-size: 1.75rem; font-weight: 700; color: #fff; }
.staff-name { font-size: 1.125rem; font-weight: 700; color: #0f172a; margin: 0 0 2px; }
.staff-designation { font-size: 0.8125rem; color: #6366f1; font-weight: 600; margin: 0 0 4px; text-transform: capitalize; }
.staff-emp-id { font-size: 0.75rem; color: #94a3b8; font-family: monospace; margin: 0 0 14px; }
.staff-meta-rows { border-top: 1px solid var(--border); padding-top: 12px; display: flex; flex-direction: column; gap: 8px; text-align: left; }
.staff-meta-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.8125rem; }
.staff-meta-row span { color: #64748b; }
.staff-meta-row strong { color: #0f172a; }

/* Contact */
.contact-row { display: flex; gap: 12px; align-items: flex-start; }
.contact-icon {
    width: 36px; height: 36px; border-radius: 8px;
    background: #f1f5f9; display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; color: #6366f1;
}
.contact-label { font-size: 0.7rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin: 0; }
.contact-value { font-size: 0.875rem; color: #0f172a; font-weight: 500; margin: 2px 0 0; word-break: break-all; }

/* Leave stats */
.leave-stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.leave-stat {
    border-radius: 10px; padding: 14px 10px;
    text-align: center;
}
.leave-stat-val { font-size: 1.5rem; font-weight: 800; line-height: 1; }
.leave-stat-lbl { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-top: 4px; }
.leave-stat--amber { background: #fffbeb; }
.leave-stat--amber .leave-stat-val { color: #d97706; }
.leave-stat--amber .leave-stat-lbl { color: #92400e; }
.leave-stat--green { background: #f0fdf4; }
.leave-stat--green .leave-stat-val { color: #059669; }
.leave-stat--green .leave-stat-lbl { color: #065f46; }
.leave-stat--red { background: #fef2f2; }
.leave-stat--red .leave-stat-val { color: #dc2626; }
.leave-stat--red .leave-stat-lbl { color: #7f1d1d; }
.leave-stat--gray { background: #f8fafc; }
.leave-stat--gray .leave-stat-val { color: #475569; }
.leave-stat--gray .leave-stat-lbl { color: #334155; }

/* HR Grid */
.hr-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 600px) { .hr-grid { grid-template-columns: 1fr; } }
.hr-field {}
.hr-label { font-size: 0.7rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin: 0 0 3px; }
.hr-value { font-size: 0.875rem; color: #0f172a; font-weight: 500; margin: 0; }

/* Payslip badge */
.payslip-month-badge {
    width: 42px; height: 42px; border-radius: 8px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    color: #fff; font-size: 0.6875rem; font-weight: 700; line-height: 1.2;
    flex-shrink: 0;
}
</style>
