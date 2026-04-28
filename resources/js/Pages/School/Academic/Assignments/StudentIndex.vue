<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useForm, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref, computed } from 'vue';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    assignments: Object,   // paginated — only the student's class/section
    mySubmissions: Object, // keyed by assignment_id
    filters: Object,
});

// ── Submit Modal ─────────────────────────────────────────
const activeAssignment = ref(null);
const submitForm = useForm({ content: '', attachments: [] });

const openSubmit = (assignment) => {
    activeAssignment.value = assignment;
    const existing = props.mySubmissions?.[assignment.id];
    submitForm.content     = existing?.content ?? '';
    submitForm.attachments = [];
};

const doSubmit = () => {
    submitForm.post(
        route('school.academic.assignments.submit', activeAssignment.value.id),
        {
            forceFormData: true,
            onSuccess: () => { activeAssignment.value = null; submitForm.reset(); },
        }
    );
};

const handleFiles = (e) => {
    submitForm.attachments = Array.from(e.target.files);
};

// ── Helpers ───────────────────────────────────────────────
const todayStr = school.today();

const assignmentStatus = (a) => {
    const sub = props.mySubmissions?.[a.id];
    if (sub) return sub.marks !== null && sub.marks !== undefined
        ? { label: `Graded: ${sub.marks}/${a.max_marks}`, cls: 'badge-green' }
        : { label: sub.is_late ? 'Submitted (Late)' : 'Submitted', cls: sub.is_late ? 'badge-amber' : 'badge-blue' };
    if (a.status === 'closed' || a.due_date < todayStr)
        return { label: 'Missed', cls: 'badge-red' };
    return { label: 'Pending', cls: 'badge-gray' };
};

const daysLeft = (due) => {
    const diff = Math.ceil((new Date(due) - new Date()) / 86400000);
    if (diff < 0)  return { text: `${Math.abs(diff)}d overdue`, cls: 'text-red-600' };
    if (diff === 0) return { text: 'Due today!', cls: 'text-amber-600' };
    return { text: `${diff}d left`, cls: 'text-slate-500' };
};

const formatDate = (d) => school.fmtDate(d);
// Serve attachments through the Laravel /api/media proxy so we don't depend
// on the /storage symlink being readable by nginx, and to dodge nginx's
// image-extension location block.
const getFileUrl = (p) => {
    if (!p) return '';
    if (/^https?:\/\//i.test(p)) return p;
    const clean = String(p).replace(/^\/+/, '').replace(/^(?:storage|public)\//i, '');
    return `/api/media?p=${encodeURIComponent(clean)}`;
};

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

// Active/past split
const activeAssignments = computed(() =>
    (props.assignments?.data ?? []).filter(a => a.status === 'published' && a.due_date >= todayStr)
);
const pastAssignments = computed(() =>
    (props.assignments?.data ?? []).filter(a => a.status !== 'published' || a.due_date < todayStr)
);
</script>

<template>
    <SchoolLayout title="My Assignments">
        <PageHeader title="My Assignments" subtitle="View, submit, and track your homework" />

        <!-- Active Assignments -->
        <div v-if="activeAssignments.length > 0" class="mb-8">
            <h3 class="section-heading mb-4">Active / Upcoming</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="a in activeAssignments" :key="a.id"
                     class="card hover:shadow-md transition-shadow border-l-4"
                     :class="a.due_date === todayStr ? 'border-amber-400' : 'border-indigo-400'">
                    <div class="card-body">
                        <div class="flex justify-between items-start mb-2">
                            <span :class="['badge', assignmentStatus(a).cls]">{{ assignmentStatus(a).label }}</span>
                            <span :class="['text-xs font-bold', daysLeft(a.due_date).cls]">
                                {{ daysLeft(a.due_date).text }}
                            </span>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 mb-1">{{ a.title }}</h3>
                        <p class="text-xs text-slate-500 mb-1">{{ a.subject?.name }}</p>
                        <p class="text-xs text-slate-400 mb-4">Due: {{ formatDate(a.due_date) }} · Max {{ a.max_marks }} marks</p>

                        <div v-if="a.description" class="text-xs text-slate-600 mb-4 bg-slate-50 rounded p-2 line-clamp-3">
                            {{ a.description }}
                        </div>

                        <!-- Attachments from teacher -->
                        <div v-if="a.attachments?.length" class="flex flex-wrap gap-1 mb-4">
                            <button v-for="(f, i) in a.attachments" :key="i"
                               @click.stop="openFileViewer(f, `${a.title} - File ${i+1}`)"
                               class="text-xs text-indigo-600 hover:underline font-medium cursor-pointer flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                File {{ i+1 }}
                            </button>
                        </div>

                        <!-- My submission info -->
                        <div v-if="mySubmissions?.[a.id]" class="mb-3 p-2 bg-emerald-50 border border-emerald-200 rounded text-xs">
                            <div class="font-bold text-emerald-700">✓ Submitted</div>
                            <div v-if="mySubmissions[a.id].marks !== null" class="text-emerald-600 mt-1">
                                Marks: <strong>{{ mySubmissions[a.id].marks }}/{{ a.max_marks }}</strong>
                            </div>
                            <div v-if="mySubmissions[a.id].remarks" class="text-slate-600 mt-1 italic">
                                "{{ mySubmissions[a.id].remarks }}"
                            </div>
                        </div>

                        <Button @click="openSubmit(a)"
                                size="sm"
                                block
                                :variant="mySubmissions?.[a.id] ? 'secondary' : 'primary'">
                            {{ mySubmissions?.[a.id] ? 'Update Submission' : 'Submit Assignment' }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Past / Closed -->
        <div v-if="pastAssignments.length > 0">
            <h3 class="section-heading mb-4 text-slate-400">Past / Closed</h3>
            <div class="card overflow-hidden">
                <Table>
                    <thead>
                        <tr>
                            <th>Assignment</th>
                            <th>Subject</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Marks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in pastAssignments" :key="a.id">
                            <td class="font-medium text-slate-800">{{ a.title }}</td>
                            <td class="text-slate-500 text-xs">{{ a.subject?.name }}</td>
                            <td class="text-xs text-slate-500">{{ formatDate(a.due_date) }}</td>
                            <td><span :class="['badge', assignmentStatus(a).cls]">{{ assignmentStatus(a).label }}</span></td>
                            <td>
                                <span v-if="mySubmissions?.[a.id]?.marks !== null && mySubmissions?.[a.id]?.marks !== undefined"
                                      class="font-bold text-emerald-600">
                                    {{ mySubmissions[a.id].marks }}/{{ a.max_marks }}
                                </span>
                                <span v-else class="text-slate-400 text-xs">—</span>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <div v-if="!activeAssignments.length && !pastAssignments.length" class="card py-16 text-center">
            <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-slate-500">No assignments yet.</p>
        </div>

        <!-- Pagination -->
        <div v-if="assignments?.last_page > 1" class="flex justify-center gap-2 mt-6">
            <Link v-for="page in assignments.links" :key="page.label"
                  :href="page.url || '#'"
                  :class="['pag-btn', page.active ? 'pag-active' : '', !page.url ? 'pag-disabled' : '']"
                  v-html="page.label" />
        </div>

        <!-- Submit Modal -->
        <div v-if="activeAssignment"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
             @mousedown.self="activeAssignment = null">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b flex justify-between items-center sticky top-0 bg-white z-10">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Submit Assignment</h3>
                        <p class="text-xs text-indigo-600 font-bold">{{ activeAssignment.title }}</p>
                    </div>
                    <button @click="activeAssignment = null" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="doSubmit" class="p-6 space-y-4" enctype="multipart/form-data">
                    <div class="form-field">
                        <label>Your Answer / Notes</label>
                        <textarea v-model="submitForm.content" rows="5"
                                  placeholder="Type your answer here..."></textarea>
                        <p v-if="submitForm.errors.content" class="field-error">{{ submitForm.errors.content }}</p>
                    </div>
                    <div class="form-field">
                        <label>Attach Files (PDF, DOC, JPG, ZIP — max 10MB each)</label>
                        <input type="file" multiple @change="handleFiles"
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip" />
                        <p v-if="submitForm.errors.attachments" class="field-error">{{ submitForm.errors.attachments }}</p>
                    </div>
                    <div class="flex gap-3">
                        <Button variant="secondary" type="button" @click="activeAssignment = null" class="flex-1">Cancel</Button>
                        <Button type="submit" :loading="submitForm.processing" class="flex-1">
                            Submit
                        </Button>
                    </div>
                </form>
            </div>
        </div>
        <!-- File Viewer Modal -->
        <div v-if="viewingFile"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm"
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
