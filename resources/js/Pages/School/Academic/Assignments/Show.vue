<script setup>
import Button from '@/Components/ui/Button.vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref, computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    assignment: Object,
    submissions: Array,
    stats: Object,   // { total, submitted, graded, pending }
});

const { can } = usePermissions();

// ── Grading mode: 'individual' | 'bulk' ──────────────────
const gradingMode      = ref('individual');
const activeSubmission = ref(null);

// Individual grading form
const gradingForm = useForm({ marks: '', remarks: '' });

const selectForGrading = (sub) => {
    activeSubmission.value = sub;
    gradingForm.marks   = sub.marks   ?? '';
    gradingForm.remarks = sub.remarks ?? '';
};

const submitGrade = () => {
    gradingForm.post(
        route('school.academic.assignments.grade-student', [props.assignment.id, activeSubmission.value.student.id]),
        { onSuccess: () => { activeSubmission.value = null; } }
    );
};

// Bulk grading — editable rows
const bulkGrades = ref(
    props.submissions.map(s => ({
        student_id: s.student?.id,
        name:       `${s.student?.first_name ?? ''} ${s.student?.last_name ?? ''}`.trim(),
        adm:        s.student?.admission_no ?? '—',
        is_late:    s.is_late,
        marks:      s.marks ?? '',
        remarks:    s.remarks ?? '',
        submitted:  !!s.submitted_at,
    }))
);

const bulkForm = useForm({ grades: [] });

const saveBulkGrades = () => {
    bulkForm.grades = bulkGrades.value
        .filter(g => g.marks !== '' && g.marks !== null)
        .map(g => ({ student_id: g.student_id, marks: g.marks, remarks: g.remarks }));

    if (!bulkForm.grades.length) return;

    bulkForm.post(route('school.academic.assignments.bulk-grade', props.assignment.id));
};

// ── Grade Distribution ─────────────────────────────────────
const gradedSubmissions = computed(() =>
    props.submissions.filter(s => s.marks !== null && s.marks !== undefined)
);

const gradeStats = computed(() => {
    if (!gradedSubmissions.value.length) return null;
    const marks = gradedSubmissions.value.map(s => Number(s.marks));
    const max   = props.assignment.max_marks;
    const avg   = marks.reduce((a, b) => a + b, 0) / marks.length;
    const pass  = Math.round(max * 0.4); // 40% pass mark
    const passed = marks.filter(m => m >= pass).length;
    // Buckets: 0-25%, 26-50%, 51-75%, 76-100%
    const buckets = [0, 0, 0, 0];
    marks.forEach(m => {
        const pct = (m / max) * 100;
        if (pct <= 25)       buckets[0]++;
        else if (pct <= 50)  buckets[1]++;
        else if (pct <= 75)  buckets[2]++;
        else                 buckets[3]++;
    });
    const bucketMax = Math.max(...buckets, 1);
    return { avg: avg.toFixed(1), high: Math.max(...marks), low: Math.min(...marks), passed, failed: marks.length - passed, buckets, bucketMax, pass };
});

// ── Helpers ───────────────────────────────────────────────
const closeAssignment = () => {
    if (!confirm('Close this assignment? Students will no longer be able to submit.')) return;
    router.post(route('school.academic.assignments.close', props.assignment.id));
};

const duplicateAssignment = () => {
    router.post(route('school.academic.assignments.duplicate', props.assignment.id));
};

const formatDate = (date) =>
    new Date(date).toLocaleString('en-IN', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });

const formatDateOnly = (date) =>
    new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });

const getFileUrl = (path) => `/storage/${path}`;

// ── File viewer modal ────────────────────────────────────
const viewingFile = ref(null);
const viewingFileName = ref('');

const openFileViewer = (filePath, name) => {
    viewingFile.value = filePath;
    viewingFileName.value = name;
};
const closeFileViewer = () => { viewingFile.value = null; };

const fileExt = (p) => p?.split('.').pop().toLowerCase();
const fileType = (p) => {
    const ext = fileExt(p);
    if (ext === 'pdf') return 'pdf';
    if (['jpg','jpeg','png','gif','webp'].includes(ext)) return 'image';
    if (['mp4','mov','avi','webm'].includes(ext)) return 'video';
    return 'other';
};

const todayStr = new Date().toISOString().split('T')[0];
const isExpired = props.assignment.due_date < todayStr;

const statusLabel = () => {
    if (props.assignment.status === 'draft')   return { text: 'Draft',    cls: 'badge-gray' };
    if (props.assignment.status === 'closed')  return { text: 'Closed',   cls: 'badge-red'  };
    if (isExpired)                             return { text: 'Expired',  cls: 'badge-red'  };
    return                                            { text: 'Active',   cls: 'badge-green' };
};
</script>

<template>
    <SchoolLayout title="Assignment Details">
        <div class="page-header">
            <div class="flex items-center gap-3 flex-wrap">
                <h2 class="page-header-title">{{ assignment.title }}</h2>
                <span :class="['badge', statusLabel().cls]">{{ statusLabel().text }}</span>
            </div>
            <div class="flex gap-2 flex-wrap">
                <Button variant="secondary" v-if="can('create_academic')" @click="duplicateAssignment" class="text-indigo-600 border-indigo-200 hover:bg-indigo-50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    Duplicate
                </Button>
                <Button variant="secondary" v-if="can('edit_academic') && assignment.status === 'published'" @click="closeAssignment" class="text-amber-600 border-amber-300 hover:bg-amber-50">
                    Close Assignment
                </Button>
                <Button variant="secondary" as="link" :href="route('school.academic.assignments.index')">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </Button>
            </div>
        </div>

        <!-- Stats Banner -->
        <div v-if="stats" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="card text-center">
                <div class="card-body py-4">
                    <div class="text-2xl font-bold text-slate-800">{{ stats.total }}</div>
                    <div class="text-xs text-slate-500 mt-1">Total Students</div>
                </div>
            </div>
            <div class="card text-center">
                <div class="card-body py-4">
                    <div class="text-2xl font-bold text-indigo-600">{{ stats.submitted }}</div>
                    <div class="text-xs text-slate-500 mt-1">Submitted</div>
                </div>
            </div>
            <div class="card text-center">
                <div class="card-body py-4">
                    <div class="text-2xl font-bold text-emerald-600">{{ stats.graded }}</div>
                    <div class="text-xs text-slate-500 mt-1">Graded</div>
                </div>
            </div>
            <div class="card text-center">
                <div class="card-body py-4">
                    <div class="text-2xl font-bold text-amber-500">{{ stats.pending }}</div>
                    <div class="text-xs text-slate-500 mt-1">Pending</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Overview -->
            <div class="lg:col-span-1 space-y-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Overview</h3></div>
                    <div class="card-body py-4 space-y-4">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 font-medium">Class</span>
                            <span class="font-bold text-slate-800">{{ assignment.course_class?.name ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 font-medium">Subject</span>
                            <span class="font-bold text-slate-800">{{ assignment.subject?.name ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 font-medium">Due Date</span>
                            <span class="font-bold text-slate-800">{{ formatDateOnly(assignment.due_date) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 font-medium">Max Marks</span>
                            <span class="font-bold text-slate-800">{{ assignment.max_marks }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 font-medium">Set by</span>
                            <span class="font-bold text-slate-800">{{ assignment.teacher?.user?.name ?? 'N/A' }}</span>
                        </div>

                        <div v-if="assignment.attachments?.length > 0" class="pt-2">
                            <h4 class="text-xs font-bold text-slate-400 uppercase mb-2">Attachments</h4>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="(file, idx) in assignment.attachments" :key="idx"
                                   @click="openFileViewer(file, `${assignment.title} - File ${idx + 1}`)"
                                   class="flex items-center gap-1 px-2 py-1 bg-slate-50 border rounded text-xs font-bold text-indigo-600 hover:bg-slate-100 cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    File {{ idx + 1 }}
                                </button>
                            </div>
                        </div>

                        <div v-if="assignment.description" class="pt-2">
                            <h4 class="text-xs font-bold text-slate-400 uppercase mb-2">Instructions</h4>
                            <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ assignment.description }}</p>
                        </div>
                    </div>
                </div>

                <!-- Grade Distribution -->
                <div v-if="gradeStats" class="card">
                    <div class="card-header"><h3 class="card-title">Grade Distribution</h3></div>
                    <div class="card-body py-4 space-y-4">
                        <div class="grid grid-cols-2 gap-3 text-center">
                            <div class="bg-slate-50 rounded-lg p-3">
                                <div class="text-lg font-bold text-slate-800">{{ gradeStats.avg }}</div>
                                <div class="text-xs text-slate-500">Average</div>
                            </div>
                            <div class="bg-slate-50 rounded-lg p-3">
                                <div class="text-lg font-bold text-emerald-600">{{ gradeStats.passed }}</div>
                                <div class="text-xs text-slate-500">Passed (≥40%)</div>
                            </div>
                            <div class="bg-slate-50 rounded-lg p-3">
                                <div class="text-lg font-bold text-indigo-600">{{ gradeStats.high }}</div>
                                <div class="text-xs text-slate-500">Highest</div>
                            </div>
                            <div class="bg-slate-50 rounded-lg p-3">
                                <div class="text-lg font-bold text-red-500">{{ gradeStats.low }}</div>
                                <div class="text-xs text-slate-500">Lowest</div>
                            </div>
                        </div>
                        <!-- Score bucket bars -->
                        <div class="space-y-2">
                            <div v-for="(count, i) in gradeStats.buckets" :key="i" class="flex items-center gap-2">
                                <span class="text-xs text-slate-500 w-20 shrink-0">
                                    {{ ['0–25%', '26–50%', '51–75%', '76–100%'][i] }}
                                </span>
                                <div class="flex-1 bg-slate-100 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all duration-500"
                                         :class="['bg-red-400','bg-amber-400','bg-blue-400','bg-emerald-500'][i]"
                                         :style="`width:${Math.round(count / gradeStats.bucketMax * 100)}%`">
                                    </div>
                                </div>
                                <span class="text-xs font-bold text-slate-600 w-4">{{ count }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Submissions -->
            <div class="lg:col-span-2 space-y-4">
                <!-- Grading mode toggle -->
                <div v-if="can('edit_academic') && submissions.length > 0" class="flex gap-2">
                    <Button size="sm"
                            :variant="gradingMode === 'individual' ? 'primary' : 'secondary'"
                            @click="gradingMode = 'individual'">
                        Individual Grading
                    </Button>
                    <Button size="sm"
                            :variant="gradingMode === 'bulk' ? 'primary' : 'secondary'"
                            @click="gradingMode = 'bulk'">
                        Bulk Grading
                    </Button>
                </div>

                <!-- ── Individual Grading Table ── -->
                <div v-show="gradingMode === 'individual'" class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            Student Submissions
                            <span class="text-slate-400 font-normal text-sm ml-1">
                                ({{ submissions.filter(s => s.submitted_at).length }}/{{ submissions.length }})
                            </span>
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <Table>
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Adm. No.</th>
                                    <th>Submitted On</th>
                                    <th>Content</th>
                                    <th>Marks</th>
                                    <th v-if="can('edit_academic')">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="sub in submissions" :key="sub.id"
                                    :class="sub.marks !== null && sub.marks !== undefined ? '' : (sub.submitted_at ? 'bg-blue-50/30' : '')">
                                    <td>
                                        <div class="font-bold">{{ sub.student?.first_name }} {{ sub.student?.last_name }}</div>
                                    </td>
                                    <td>{{ sub.student?.admission_no ?? '—' }}</td>
                                    <td>
                                        <div v-if="sub.submitted_at" class="flex items-center gap-1">
                                            <span class="text-slate-700 text-xs">{{ formatDate(sub.submitted_at) }}</span>
                                            <span v-if="sub.is_late" class="badge badge-red text-[10px] px-1 py-0.5">Late</span>
                                        </div>
                                        <span v-else class="badge badge-amber">Pending</span>
                                    </td>
                                    <td>
                                        <div v-if="sub.content" class="text-xs text-slate-600 max-w-[160px] truncate" :title="sub.content">
                                            {{ sub.content }}
                                        </div>
                                        <div v-else-if="sub.attachments?.length" class="flex flex-wrap gap-1">
                                            <button v-for="(f, idx) in sub.attachments" :key="idx"
                                               @click="openFileViewer(f, `${sub.student?.first_name} - File ${idx + 1}`)"
                                               class="text-xs font-bold text-indigo-600 hover:underline cursor-pointer flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                File {{ idx + 1 }}
                                            </button>
                                        </div>
                                        <span v-else class="text-slate-400 text-xs">—</span>
                                    </td>
                                    <td>
                                        <span v-if="sub.marks !== null && sub.marks !== undefined"
                                              class="font-bold text-emerald-600">
                                            {{ sub.marks }}/{{ assignment.max_marks }}
                                        </span>
                                        <span v-else class="text-slate-400">—</span>
                                    </td>
                                    <td v-if="can('edit_academic')">
                                        <Button variant="secondary" size="sm" @click="selectForGrading(sub)">
                                            {{ sub.marks !== null && sub.marks !== undefined ? 'Re-grade' : 'Grade' }}
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="submissions.length === 0">
                                    <td :colspan="can('edit_academic') ? 6 : 5" class="py-8 text-center text-slate-400">
                                        No students enrolled in this class/section.
                                    </td>
                                </tr>
                            </tbody>
                        </Table>
                    </div>
                </div>

                <!-- ── Bulk Grading Table ── -->
                <div v-show="gradingMode === 'bulk'" class="card">
                    <div class="card-header flex items-center justify-between">
                        <h3 class="card-title">Bulk Grading</h3>
                        <span class="text-xs text-slate-400">Fill marks for multiple students and save at once</span>
                    </div>
                    <div class="overflow-x-auto">
                        <Table>
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Adm. No.</th>
                                    <th>Status</th>
                                    <th>Marks (max {{ assignment.max_marks }})</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="g in bulkGrades" :key="g.student_id"
                                    :class="g.marks !== '' ? 'bg-emerald-50/40' : ''">
                                    <td class="font-medium text-slate-800">{{ g.name }}</td>
                                    <td class="text-slate-500 text-xs">{{ g.adm }}</td>
                                    <td>
                                        <span v-if="g.submitted" class="flex items-center gap-1">
                                            <span class="badge badge-green text-[10px]">Submitted</span>
                                            <span v-if="g.is_late" class="badge badge-red text-[10px]">Late</span>
                                        </span>
                                        <span v-else class="badge badge-gray text-[10px]">Pending</span>
                                    </td>
                                    <td class="w-32">
                                        <input type="number" v-model="g.marks"
                                               :max="assignment.max_marks" min="0" step="0.5"
                                               class="w-full border rounded px-2 py-1 text-sm focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                               placeholder="—" />
                                    </td>
                                    <td>
                                        <input type="text" v-model="g.remarks"
                                               class="w-full border rounded px-2 py-1 text-sm focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                               placeholder="Optional remarks" />
                                    </td>
                                </tr>
                            </tbody>
                        </Table>
                    </div>
                    <div class="card-body pt-0 pb-4 flex justify-end">
                        <Button @click="saveBulkGrades" :loading="bulkForm.processing">
                            {{ `Save Grades (${bulkGrades.filter(g => g.marks !== '').length} filled)` }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Individual Grading Modal ── -->
        <div v-if="activeSubmission"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
             @mousedown.self="activeSubmission = null">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
                <div class="p-6 border-b flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Grade Submission</h3>
                        <p class="text-xs text-indigo-600 font-bold">
                            {{ activeSubmission.student?.first_name }} {{ activeSubmission.student?.last_name }}
                            <span v-if="activeSubmission.is_late" class="ml-1 badge badge-red">Late</span>
                        </p>
                    </div>
                    <button @click="activeSubmission = null" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div v-if="activeSubmission.content" class="bg-slate-50 border rounded-lg p-3">
                        <h4 class="text-xs font-bold text-slate-400 uppercase mb-2">Student's Answer</h4>
                        <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ activeSubmission.content }}</p>
                    </div>
                    <div v-if="activeSubmission.attachments?.length > 0">
                        <h4 class="text-xs font-bold text-slate-400 uppercase mb-2">Attached Files</h4>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="(file, idx) in activeSubmission.attachments" :key="idx"
                               @click="openFileViewer(file, `${activeSubmission.student?.first_name} - File ${idx + 1}`)"
                               class="flex items-center gap-2 p-2 bg-slate-50 border rounded text-xs font-bold text-indigo-600 hover:bg-slate-100 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                File {{ idx + 1 }}
                            </button>
                        </div>
                    </div>
                    <form @submit.prevent="submitGrade" class="space-y-4">
                        <div class="form-field">
                            <label>Marks Obtained (Max: {{ assignment.max_marks }})</label>
                            <input type="number" v-model="gradingForm.marks"
                                   :max="assignment.max_marks" min="0" step="0.5" required />
                            <p v-if="gradingForm.errors.marks" class="field-error">{{ gradingForm.errors.marks }}</p>
                        </div>
                        <div class="form-field">
                            <label>Teacher's Remarks</label>
                            <textarea v-model="gradingForm.remarks" rows="3" placeholder="Feedback for the student..."></textarea>
                        </div>
                        <div class="flex gap-3">
                            <Button variant="secondary" type="button" @click="activeSubmission = null" class="flex-1">Close</Button>
                            <Button type="submit" :loading="gradingForm.processing" class="flex-1">
                                Save Grade
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- File Viewer Modal -->
        <div v-if="viewingFile"
             class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm"
             @mousedown.self="closeFileViewer">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col" style="height: 90vh;">
                <div class="flex items-center justify-between p-4 border-b shrink-0">
                    <h3 class="font-bold text-slate-800">{{ viewingFileName }}</h3>
                    <div class="flex items-center gap-2 shrink-0">
                        <Button size="sm" as="a" :href="getFileUrl(viewingFile)" download>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Download
                        </Button>
                        <button @click="closeFileViewer" class="text-slate-400 hover:text-slate-600 p-1">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-auto bg-slate-50 flex items-center justify-center">
                    <iframe v-if="fileType(viewingFile) === 'pdf'" :src="getFileUrl(viewingFile)" class="w-full h-full border-none" />
                    <img v-else-if="fileType(viewingFile) === 'image'" :src="getFileUrl(viewingFile)" :alt="viewingFileName" class="max-w-full max-h-full object-contain p-4" />
                    <video v-else-if="fileType(viewingFile) === 'video'" :src="getFileUrl(viewingFile)" controls class="max-w-full max-h-full" />
                    <div v-else class="flex flex-col items-center gap-4 p-10 text-center">
                        <svg class="w-16 h-16 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 2v6h6"/></svg>
                        <p class="text-lg font-bold text-slate-700">{{ viewingFileName }}</p>
                        <p class="text-sm text-slate-500">This file type cannot be previewed in the browser.</p>
                        <Button as="a" :href="getFileUrl(viewingFile)" download>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Download File
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.field-error { font-size: 0.75rem; color: #ef4444; margin-top: 0.25rem; }
</style>
