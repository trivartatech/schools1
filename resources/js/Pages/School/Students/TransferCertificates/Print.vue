<script setup>
import { Link } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    tc: Object,
});

const formatDate = (d) => {
    if (!d) return '——————';
    return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'long', year: 'numeric' });
};

const student = props.tc.student;
const school  = props.tc.school;

// Compute years of study
const admissionYear = student?.admission_date ? new Date(student.admission_date).getFullYear() : '—';
const leavingYear   = props.tc.leaving_date   ? new Date(props.tc.leaving_date).getFullYear()  : '—';
</script>

<template>
    <!-- Print toolbar (hidden on print) -->
    <div class="no-print fixed top-0 left-0 right-0 z-50 bg-slate-800 text-white flex items-center justify-between px-6 py-3 shadow-lg">
        <div class="flex items-center gap-3">
            <span class="font-semibold">Transfer Certificate</span>
            <span class="text-slate-400 text-sm">— {{ tc.certificate_no }}</span>
        </div>
        <div class="flex gap-3">
            <Button @click="window.print()">
                🖨 Print
            </Button>
            <Link :href="route('school.transfer-certificates.show', tc.id)"
                  class="bg-slate-600 hover:bg-slate-500 text-white text-sm px-4 py-1.5 rounded-lg font-medium transition-colors">
                ← Back
            </Link>
        </div>
    </div>

    <!-- TC Certificate -->
    <div class="print-page">
        <!-- School Header -->
        <div class="tc-header">
            <div class="flex items-center justify-center gap-5 mb-3">
                <img v-if="school?.logo" :src="school.logo" class="h-16 w-16 object-contain">
                <div class="text-center">
                    <h1 class="school-name">{{ school?.name }}</h1>
                    <p class="school-address">{{ school?.address }}</p>
                    <p class="school-contact" v-if="school?.phone || school?.email">
                        <span v-if="school?.phone">📞 {{ school.phone }}</span>
                        <span v-if="school?.email" class="ml-3">✉ {{ school.email }}</span>
                    </p>
                </div>
            </div>
            <div class="tc-title-bar">
                <h2 class="tc-title">TRANSFER CERTIFICATE</h2>
            </div>
        </div>

        <!-- Certificate Meta -->
        <div class="cert-meta">
            <div>
                <span class="meta-label">Certificate No:</span>
                <span class="meta-value font-bold">{{ tc.certificate_no }}</span>
            </div>
            <div>
                <span class="meta-label">Date of Issue:</span>
                <span class="meta-value">{{ formatDate(tc.issued_at) }}</span>
            </div>
        </div>

        <!-- Fields Table -->
        <table class="tc-fields">
            <tbody>
                <tr>
                    <td class="field-no">1.</td>
                    <td class="field-label">Name of the Student</td>
                    <td class="field-colon">:</td>
                    <td class="field-value"><strong>{{ student?.first_name }} {{ student?.last_name }}</strong></td>
                </tr>
                <tr>
                    <td class="field-no">2.</td>
                    <td class="field-label">Admission Number</td>
                    <td class="field-colon">:</td>
                    <td class="field-value">{{ student?.admission_no }}</td>
                </tr>
                <tr>
                    <td class="field-no">3.</td>
                    <td class="field-label">Date of Birth</td>
                    <td class="field-colon">:</td>
                    <td class="field-value">{{ formatDate(student?.dob) }}</td>
                </tr>
                <tr v-if="student?.gender">
                    <td class="field-no">4.</td>
                    <td class="field-label">Gender</td>
                    <td class="field-colon">:</td>
                    <td class="field-value">{{ student.gender }}</td>
                </tr>
                <tr v-if="student?.nationality">
                    <td class="field-no">5.</td>
                    <td class="field-label">Nationality</td>
                    <td class="field-colon">:</td>
                    <td class="field-value">{{ student.nationality }}</td>
                </tr>
                <tr>
                    <td class="field-no">6.</td>
                    <td class="field-label">Date of Admission</td>
                    <td class="field-colon">:</td>
                    <td class="field-value">{{ formatDate(student?.admission_date) }}</td>
                </tr>
                <tr>
                    <td class="field-no">7.</td>
                    <td class="field-label">Class in which Last Studied</td>
                    <td class="field-colon">:</td>
                    <td class="field-value">{{ tc.last_class_studied || '—' }}</td>
                </tr>
                <tr>
                    <td class="field-no">8.</td>
                    <td class="field-label">Date of Leaving</td>
                    <td class="field-colon">:</td>
                    <td class="field-value">{{ formatDate(tc.leaving_date) }}</td>
                </tr>
                <tr>
                    <td class="field-no">9.</td>
                    <td class="field-label">Reason for Leaving</td>
                    <td class="field-colon">:</td>
                    <td class="field-value">{{ tc.reason || 'Not specified' }}</td>
                </tr>
                <tr>
                    <td class="field-no">10.</td>
                    <td class="field-label">Conduct and Character</td>
                    <td class="field-colon">:</td>
                    <td class="field-value"><strong>{{ tc.conduct }}</strong></td>
                </tr>
                <tr>
                    <td class="field-no">11.</td>
                    <td class="field-label">Fee Paid Up To</td>
                    <td class="field-colon">:</td>
                    <td class="field-value">{{ formatDate(tc.fee_paid_upto) }}</td>
                </tr>
                <tr>
                    <td class="field-no">12.</td>
                    <td class="field-label">Any Dues Pending</td>
                    <td class="field-colon">:</td>
                    <td class="field-value">{{ tc.has_dues ? 'Yes' : 'No' }}</td>
                </tr>
                <tr v-if="tc.remarks">
                    <td class="field-no">13.</td>
                    <td class="field-label">Remarks</td>
                    <td class="field-colon">:</td>
                    <td class="field-value">{{ tc.remarks }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Declaration -->
        <div class="tc-declaration">
            <p>
                This is to certify that <strong>{{ student?.first_name }} {{ student?.last_name }}</strong>,
                Admission No. <strong>{{ student?.admission_no }}</strong>, was a bonafide student of this
                institution from <strong>{{ formatDate(student?.admission_date) }}</strong> to
                <strong>{{ formatDate(tc.leaving_date) }}</strong>. The student has been found
                <strong>{{ tc.conduct }}</strong> in conduct and character during the period of study.
            </p>
        </div>

        <!-- Signatures -->
        <div class="signatures">
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-label">Class Teacher</div>
            </div>
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-label">Accountant / Bursar</div>
            </div>
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-label">Principal / Head of Institution</div>
            </div>
        </div>

        <!-- Footer note -->
        <div class="tc-footer">
            <p>This certificate is issued on the specific request of the student/parent/guardian.</p>
            <p class="text-xs mt-1 text-slate-400">Generated by School ERP · {{ new Date().toLocaleDateString('en-IN') }}</p>
        </div>
    </div>
</template>

<style scoped>
/* Screen: offset for toolbar */
.print-page {
    max-width: 800px;
    margin: 80px auto 40px;
    padding: 48px;
    background: white;
    font-family: 'Times New Roman', Times, serif;
    color: #1a1a1a;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

/* School header */
.school-name    { font-size: 1.6rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #1e3a8a; }
.school-address { font-size: 0.85rem; color: #475569; }
.school-contact { font-size: 0.8rem; color: #64748b; margin-top: 2px; }

.tc-title-bar {
    border-top: 3px double #1e3a8a;
    border-bottom: 3px double #1e3a8a;
    margin: 12px 0;
    padding: 6px 0;
    text-align: center;
}
.tc-title { font-size: 1.1rem; font-weight: 800; letter-spacing: 4px; color: #1e3a8a; margin: 0; }

/* Meta row */
.cert-meta {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
    font-size: 0.88rem;
}
.meta-label { color: #64748b; margin-right: 6px; }
.meta-value  { color: #1a1a1a; }

/* Fields */
.tc-fields { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.tc-fields tr { border-bottom: 1px dotted #cbd5e1; }
.tc-fields td { padding: 7px 4px; vertical-align: top; }
.field-no    { width: 32px; color: #64748b; }
.field-label { width: 240px; color: #374151; }
.field-colon { width: 20px; color: #94a3b8; }
.field-value { color: #1a1a1a; }

/* Declaration */
.tc-declaration {
    margin: 24px 0;
    padding: 16px;
    background: #f8fafc;
    border-left: 4px solid #1e3a8a;
    border-radius: 4px;
    font-size: 0.88rem;
    line-height: 1.8;
    color: #1e293b;
}

/* Signatures */
.signatures {
    display: flex;
    justify-content: space-between;
    margin-top: 48px;
    gap: 16px;
}
.sig-box   { text-align: center; flex: 1; }
.sig-line  { border-bottom: 1.5px solid #1a1a1a; margin-bottom: 6px; height: 48px; }
.sig-label { font-size: 0.78rem; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }

/* Footer */
.tc-footer { text-align: center; font-size: 0.78rem; color: #94a3b8; margin-top: 32px; border-top: 1px solid #e2e8f0; padding-top: 12px; }

/* ── Print styles ── */
@media print {
    .no-print { display: none !important; }
    .print-page {
        margin: 0;
        padding: 32px;
        border: none;
        border-radius: 0;
        max-width: 100%;
        box-shadow: none;
    }
    @page { margin: 1.5cm; size: A4; }
}
</style>
