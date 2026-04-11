<script setup>
import Button from '@/Components/ui/Button.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps({
    scheduleData: Object,
    students: Array,
    sectionData: Object,
});

// Removed onMounted auto-print to allow images/fonts to load and prevent browser blocking


const signatureLabels = ['Class Teacher', 'Controller of Exams', 'Principal', 'Parent/Guardian'];
const instructions = [
    'Candidates must carry this admit card to the examination hall.',
    'No electronic gadgets or mobile phones are allowed inside the examination hall.',
    'Candidates must report to the examination center 30 minutes before the commencement of the exam.',
    'Tampering with this admit card will lead to disqualification.'
];
</script>

<template>
    <Head title="Print Admit Cards" />
    
    <div class="print-wrapper">
        <div class="print-actions no-print flex gap-4 p-4 bg-gray-100 border-b justify-end sticky top-0 z-50 shadow-sm">
            <div class="text-sm text-gray-600 flex items-center mr-auto">Press Ctrl+P (Windows) or Cmd+P (Mac) to print</div>
            <Button variant="secondary" onclick="window.close()">Close Window</Button>
            <Button @click="() => window.print()">Print Now</Button>
        </div>

        <div class="print-container">
            <!-- Iterate over each student -->
            <div v-for="student in students" :key="student.id" class="admit-card page-break">
                
                <!-- Header -->
                <div class="ac-header">
                    <div class="ac-logo">
                        <svg class="h-14 w-14 text-indigo-800" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                    </div>
                    <div class="ac-school-info">
                        <h1 class="font-black text-2xl text-gray-900 tracking-wide uppercase">{{ $page.props.school?.name || 'YOUR SCHOOL NAME' }}</h1>
                        <p class="text-sm font-medium text-gray-600">Affiliated to Central Board of Secondary Education</p>
                        <h2 class="mt-2 text-lg font-bold text-indigo-800 bg-indigo-50 inline-block px-4 py-1 rounded border border-indigo-100 uppercase tracking-widest">
                            Admit Card - {{ scheduleData?.exam_type?.name }}
                        </h2>
                    </div>
                </div>

                <!-- Student Info Section -->
                <div class="ac-info-grid">
                    <div class="ac-details-left">
                        <table class="ac-details-table">
                            <tr>
                                <th>Student Name</th>
                                <td class="font-bold text-gray-900 border-b">{{ student.first_name }} {{ student.last_name }}</td>
                            </tr>
                            <tr>
                                <th>Class & Section</th>
                                <td class="font-bold text-gray-900 border-b">{{ scheduleData?.course_class?.name }} - {{ sectionData?.name }}</td>
                            </tr>
                            <tr>
                                <th>Roll Number</th>
                                <td class="font-bold text-gray-900 border-b">{{ student.roll_no || 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Admission No.</th>
                                <td class="text-gray-800 border-b">{{ student.admission_no || 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Father's Name</th>
                                <td class="text-gray-800 border-b">{{ student.student_parent?.father_name || 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Date of Birth</th>
                                <td class="text-gray-800 border-b">{{ student.dob || 'N/A' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="ac-photo-box bg-gray-50 flex items-center justify-center border-2 border-gray-300">
                        <img v-if="student.photo_url" :src="student.photo_url" class="ac-photo" alt="Student Photo" style="width: 100%; height: 100%; object-fit: cover;" />
                        <span v-else class="text-xs text-gray-400 font-medium tracking-wide text-center px-2">PHOTO</span>
                    </div>
                </div>

                <!-- Timetable Grid -->
                <div class="ac-timetable mt-6 pt-6 border-t border-gray-200">
                    <h3 class="font-bold text-gray-800 mb-3 text-sm uppercase tracking-wider">Examination Schedule</h3>
                    <table class="ac-table">
                        <thead>
                            <tr class="bg-gray-100">
                                <th>Date</th>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Timing</th>
                                <th>Duration</th>
                                <th>Invigilator Sign</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="sub in scheduleData?.schedule_subjects" :key="sub.id" class="text-sm">
                                <td class="font-semibold text-gray-800 whitespace-nowrap">{{ sub.exam_date || 'TBA' }}</td>
                                <td class="text-gray-600">{{ sub.subject?.code }}</td>
                                <td class="font-bold text-gray-800">{{ sub.subject?.name }}</td>
                                <td class="text-gray-700 whitespace-nowrap">{{ sub.start_time || 'TBA' }}</td>
                                <td class="text-gray-600">{{ sub.duration_minutes ? sub.duration_minutes + ' m' : '-' }}</td>
                                <td class="w-24 border-dotted"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Instructions -->
                <div class="ac-instructions mt-6">
                    <h3 class="font-bold text-gray-800 text-xs uppercase tracking-wider mb-2">Important Instructions to the Candidates</h3>
                    <ul class="list-decimal pl-4 text-xs text-gray-700 space-y-1">
                        <li v-for="(inst, i) in instructions" :key="i">{{ inst }}</li>
                    </ul>
                </div>

                <!-- Signatures -->
                <div class="ac-signatures mt-12 grid grid-cols-4 gap-6 text-center">
                    <div v-for="(sig, i) in signatureLabels" :key="i" class="sig-box flex flex-col items-center justify-end h-20 relative">
                        <div class="w-full border-t border-gray-400 mt-auto pt-2 text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            {{ sig }}
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<style>
/* CSS Reset for Print View */
body { margin: 0; padding: 0; background: #e5e7eb; -webkit-font-smoothing: antialiased; }

.print-wrapper { background: #e5e7eb; min-height: 100vh; padding-bottom: 40px; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
.print-container { max-width: 210mm; margin: 0 auto; display:flex; flex-direction:column; gap:40px; padding-top: 20px;}
.admit-card {
    background: white;
    padding: 30mm 20mm; /* A4 standard padding */
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    min-height: 297mm; /* A4 height */
    position: relative;
    box-sizing: border-box;
    color: #1f2937;
}

/* Tailwind Helpers missing because no layout */
.flex { display: flex; } .justify-between { justify-content: space-between; } .items-center { align-items: center; } .gap-4 { gap: 1rem; }
.justify-end { justify-content: flex-end; } .p-4 { padding: 1rem; } .bg-gray-100 { background-color: #f3f4f6; } .border-b { border-bottom-width: 1px; border-color: #e5e7eb; }
.sticky { position: sticky; } .top-0 { top: 0; } .z-50 { z-index: 50; } .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
.text-sm { font-size: 0.875rem; line-height: 1.25rem; } .text-gray-600 { color: #4b5563; } .mr-auto { margin-right: auto; }
.text-indigo-800 { color: #3730a3; } .font-black { font-weight: 900; } .text-2xl { font-size: 1.5rem; line-height: 2rem; } .text-gray-900 { color: #111827; } .tracking-wide { letter-spacing: 0.025em; } .uppercase { text-transform: uppercase; }
.font-medium { font-weight: 500; } .mt-2 { margin-top: 0.5rem; } .text-lg { font-size: 1.125rem; line-height: 1.75rem; } .font-bold { font-weight: 700; } .bg-indigo-50 { background-color: #eef2ff; } .inline-block { display: inline-block; } .px-4 { padding-left: 1rem; padding-right: 1rem; } .py-1 { padding-top: 0.25rem; padding-bottom: 0.25rem; } .rounded { border-radius: 0.25rem; } .border { border-width: 1px; } .border-indigo-100 { border-color: #e0e7ff; } .tracking-widest { letter-spacing: 0.1em; }
.mt-6 { margin-top: 1.5rem; } .pt-6 { padding-top: 1.5rem; } .border-t { border-top-width: 1px; } .border-gray-200 { border-color: #e5e7eb; }
.mb-3 { margin-bottom: 0.75rem; } .mb-2 { margin-bottom: 0.5rem; } .text-xs { font-size: 0.75rem; line-height: 1rem; } .tracking-wider { letter-spacing: 0.05em; }
.list-decimal { list-style-type: decimal; } .pl-4 { padding-left: 1rem; } .text-gray-700 { color: #374151; } .space-y-1 > :not([hidden]) ~ :not([hidden]) { --tw-space-y-reverse: 0; margin-top: calc(0.25rem * calc(1 - var(--tw-space-y-reverse))); margin-bottom: calc(0.25rem * var(--tw-space-y-reverse)); }
.mt-12 { margin-top: 3rem; } .grid { display: grid; } .grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); } .gap-6 { gap: 1.5rem; } .text-center { text-align: center; }
.flex-col { flex-direction: column; } .h-20 { height: 5rem; } .relative { position: relative; } .w-full { width: 100%; } .mt-auto { margin-top: auto; } .pt-2 { padding-top: 0.5rem; } .border-gray-400 { border-color: #9ca3af; }


/* Header */
.ac-header { display: flex; align-items: center; gap: 20px; border-bottom: 3px solid #1e3a8a; padding-bottom: 20px; margin-bottom: 30px; }
.ac-school-info { flex: 1; text-align: center; }

/* Grid Layout */
.ac-info-grid { display: flex; justify-content: space-between; gap: 40px; }
.ac-details-left { flex: 1; }

/* Info Table */
.ac-details-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.ac-details-table th, .ac-details-table td { padding: 8px 12px; font-size: 14px; text-align: left; }
.ac-details-table th { width: 35%; color: #4b5563; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; background: #f9fafb; border-bottom: 1px solid #e5e7eb;}
.ac-details-table td { border-bottom: 1px solid #e5e7eb; }

/* Photo Box */
.ac-photo-box { width: 30mm; height: 38mm; border: 2px solid #cbd5e1; border-radius: 4px; overflow: hidden; display:flex; align-items:center; justify-content:center; background:#f8fafc; padding:2px;}
.photo-placeholder { text-align: center; padding: 10px; color: #cbd5e1;}
.ac-photo { width: 100%; height: 100%; object-fit: cover; }

/* Timetable */
.ac-table { width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #e5e7eb; }
.ac-table th, .ac-table td { border: 1px solid #e5e7eb; padding: 10px; text-align: left; }
.ac-table th { background: #f3f4f6; font-size: 11px; font-weight: 700; color: #4b5563; text-transform: uppercase; letter-spacing: 0.05em; }

/* Printing Rules */
@media print {
    body { margin: 0; background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none !important; }
    .print-wrapper { background: white; padding: 0; }
    .print-container { gap: 0; padding-top:0; width: 100%; max-width: none;}
    .admit-card {
        box-shadow: none; min-height: 297mm; padding: 15mm; margin:0; border: none; width: 100%; page-break-after: always;
    }
    .page-break { page-break-after: always; }
    /* Force background colors */
    .bg-indigo-50 { background-color: #eef2ff !important; }
    .bg-gray-100 { background-color: #f3f4f6 !important; }
    .ac-details-table th { background-color: #f9fafb !important; }
}
</style>
