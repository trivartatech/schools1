<script setup>
import Button from '@/Components/ui/Button.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref, computed, onMounted } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import ExportDropdown from '@/Components/ExportDropdown.vue';
import axios from 'axios';

const props = defineProps({
    diaries: Object,
    classes: Array,
    filters: Object,
});

const { can } = usePermissions();

const filterForm = ref({
    class_id: props.filters.class_id || '',
    section_id: props.filters.section_id || '',
    date: props.filters.date || new Date().toISOString().split('T')[0],
});

// Generate a sliding window of dates around the currently selected date
const dateList = computed(() => {
    let dates = [];
    let currentSelect = new Date(filterForm.value.date);
    for(let i = -7; i <= 7; i++) {
        let d = new Date(currentSelect);
        d.setDate(d.getDate() + i);
        dates.push(d);
    }
    return dates;
});

const selectDate = (d) => {
    // Format to YYYY-MM-DD in local time instead of strict ISO to avoid timezone shifts
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    filterForm.value.date = `${year}-${month}-${day}`;
    applyFilter();
};

const navigateDates = (offset) => {
    let current = new Date(filterForm.value.date);
    current.setDate(current.getDate() + offset);
    selectDate(current);
};

const applyFilter = () => {
    router.get(route('school.academic.diary.index'), filterForm.value, { preserveState: true });
};

const deleteEntry = (id) => {
    if (confirm('Are you sure you want to delete this diary entry?')) {
        router.delete(route('school.academic.diary.destroy', id));
    }
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

// ── View Mode: list | calendar ─────────────────────────────
const viewMode = ref('list');

// ── Calendar ───────────────────────────────────────────────
const calendarYear  = ref(new Date().getFullYear());
const calendarMonth = ref(new Date().getMonth() + 1);
const calendarData  = ref({});  // { 'YYYY-MM-DD': count }

const loadCalendar = async () => {
    try {
        const res = await axios.get(route('school.academic.diary.calendar'), {
            params: {
                year: calendarYear.value,
                month: calendarMonth.value,
                class_id: filterForm.value.class_id || undefined,
                section_id: filterForm.value.section_id || undefined,
            },
        });
        calendarData.value = res.data;
    } catch (e) { /* non-fatal */ }
};

onMounted(() => { if (viewMode.value === 'calendar') loadCalendar(); });

const navigateCalendar = (delta) => {
    calendarMonth.value += delta;
    if (calendarMonth.value > 12) { calendarMonth.value = 1;  calendarYear.value++; }
    if (calendarMonth.value < 1)  { calendarMonth.value = 12; calendarYear.value--; }
    loadCalendar();
};

const calendarDays = computed(() => {
    const firstDay = new Date(calendarYear.value, calendarMonth.value - 1, 1).getDay();
    const daysInMonth = new Date(calendarYear.value, calendarMonth.value, 0).getDate();
    const days = [];
    for (let i = 0; i < firstDay; i++) days.push(null);
    for (let d = 1; d <= daysInMonth; d++) {
        const str = `${calendarYear.value}-${String(calendarMonth.value).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        days.push({ d, str, count: calendarData.value[str] || 0 });
    }
    return days;
});

const monthName = computed(() =>
    new Date(calendarYear.value, calendarMonth.value - 1).toLocaleDateString('en-IN', { month: 'long', year: 'numeric' })
);

const todayStr = new Date().toISOString().split('T')[0];

const selectCalendarDate = (str) => {
    filterForm.value.date = str;
    viewMode.value = 'list';
    applyFilter();
};

// ── File viewer modal ────────────────────────────────────
const viewingFile = ref(null);
const viewingFileName = ref('');
const viewingAttachments = ref(null); // diary whose attachments are shown

const getFileUrl = (p) => `/storage/${p}`;
const fileExt = (p) => p?.split('.').pop().toLowerCase();
const fileIcon = (p) => {
    const ext = fileExt(p);
    if (['jpg','jpeg','png','gif'].includes(ext)) return '🖼️';
    if (ext === 'pdf') return '📄';
    if (['doc','docx'].includes(ext)) return '📝';
    return '📎';
};
const fileType = (p) => {
    const ext = fileExt(p);
    if (ext === 'pdf') return 'pdf';
    if (['jpg','jpeg','png','gif','webp'].includes(ext)) return 'image';
    if (['mp4','mov','avi','webm'].includes(ext)) return 'video';
    return 'other';
};

const showAttachments = (diary) => { viewingAttachments.value = diary; };
const openFileViewer = (filePath, name) => { viewingFile.value = filePath; viewingFileName.value = name; };
const closeFileViewer = () => { viewingFile.value = null; };
const closeAttachments = () => { viewingAttachments.value = null; };

// ── CSV Export ─────────────────────────────────────────────
const showExportModal = ref(false);
const exportForm = useForm({ from: '', to: '' });

const doExport = () => {
    const params = new URLSearchParams({
        from: exportForm.from,
        to:   exportForm.to,
        ...(filterForm.value.class_id   ? { class_id: filterForm.value.class_id } : {}),
        ...(filterForm.value.section_id ? { section_id: filterForm.value.section_id } : {}),
    });
    window.location.href = route('school.academic.diary.export') + '?' + params.toString();
    showExportModal.value = false;
};
</script>

<template>
    <SchoolLayout title="Student Diary">
        <div class="page-header">
            <div>
                <h2 class="page-header-title">Student Diary</h2>
                <p class="page-header-sub">Manage daily classwork and homework notes</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <!-- View toggle -->
                <div class="flex border border-slate-200 rounded-lg overflow-hidden">
                    <button @click="viewMode = 'list'"
                            :class="['px-3 py-1.5 text-xs font-semibold transition-colors', viewMode === 'list' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-500 hover:bg-slate-50']">
                        ☰ List
                    </button>
                    <button @click="viewMode = 'calendar'; loadCalendar()"
                            :class="['px-3 py-1.5 text-xs font-semibold transition-colors', viewMode === 'calendar' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-500 hover:bg-slate-50']">
                        📅 Calendar
                    </button>
                </div>
                <!-- Export -->
                <ExportDropdown
                    base-url="/school/export/diary"
                    :params="{ class_id: filterForm.class_id, section_id: filterForm.section_id, date: filterForm.date }"
                />
                <Button as="link" v-if="can('create_academic')" :href="route('school.academic.diary.create')">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Entry
                </Button>
            </div>
        </div>

        <!-- Date Slider -->
        <div class="card mb-6 overflow-hidden">
            <div class="flex items-center">
                <button @click="navigateDates(-7)" class="p-4 text-slate-400 hover:text-indigo-600 hover:bg-slate-50 transition-colors border-r border-slate-100">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <div class="flex-1 flex items-center overflow-x-auto hide-scrollbar scroll-smooth">
                    <button v-for="(d, index) in dateList" :key="index"
                        @click="selectDate(d)"
                        class="flex-1 min-w-[70px] py-3 px-1 flex flex-col items-center justify-center gap-0.5 transition-all border-b-2"
                        :class="filterForm.date === `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}` ? 'border-indigo-600 bg-indigo-50' : 'border-transparent hover:bg-slate-50'">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ d.toLocaleDateString('en-IN', { weekday: 'short' }) }}</span>
                        <span class="text-lg font-bold leading-none" :class="filterForm.date === `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}` ? 'text-indigo-700' : 'text-slate-800'">{{ d.getDate() }}</span>
                        <span class="text-xs text-slate-500">{{ d.toLocaleDateString('en-IN', { month: 'short' }) }}</span>
                    </button>
                </div>
                <button @click="navigateDates(7)" class="p-4 text-slate-400 hover:text-indigo-600 hover:bg-slate-50 transition-colors border-l border-slate-100">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

            <div class="diary-filter-row">
                <select v-model="filterForm.class_id" @change="applyFilter" class="diary-filter-select">
                    <option value="">All Classes</option>
                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <select v-model="filterForm.section_id" @change="applyFilter" class="diary-filter-select">
                    <option value="">All Sections</option>
                    <option v-for="s in (classes.find(c => c.id === parseInt(filterForm.class_id))?.sections || [])" :key="s.id" :value="s.id">
                        {{ s.name }}
                    </option>
                </select>
                <Button @click="applyFilter">Filter</Button>
                <Button variant="secondary" @click="filterForm = {class_id: '', section_id: '', date: new Date().toISOString().split('T')[0]}; applyFilter()">Reset</Button>
            </div>
        </div>

        <!-- ── Calendar View ── -->
        <div v-if="viewMode === 'calendar'" class="card mb-6">
            <div class="card-header flex items-center justify-between">
                <Button variant="secondary" size="sm" @click="navigateCalendar(-1)">‹ Prev</Button>
                <h3 class="card-title">{{ monthName }}</h3>
                <Button variant="secondary" size="sm" @click="navigateCalendar(1)">Next ›</Button>
            </div>
            <div class="card-body p-3">
                <!-- Day-of-week headers -->
                <div class="grid grid-cols-7 text-center mb-2">
                    <div v-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d"
                         class="text-xs font-bold text-slate-400 uppercase py-1">{{ d }}</div>
                </div>
                <!-- Calendar grid -->
                <div class="grid grid-cols-7 gap-1">
                    <div v-for="(day, idx) in calendarDays" :key="idx"
                         class="aspect-square flex flex-col items-center justify-center rounded-lg text-sm cursor-pointer transition-all"
                         :class="[
                             !day ? '' :
                             day.str === filterForm.date ? 'bg-indigo-600 text-white font-bold shadow' :
                             day.str === todayStr ? 'bg-indigo-50 text-indigo-600 font-semibold border border-indigo-200' :
                             'hover:bg-slate-50 text-slate-700'
                         ]"
                         @click="day && selectCalendarDate(day.str)">
                        <span v-if="day">{{ day.d }}</span>
                        <div v-if="day && day.count > 0" class="flex gap-0.5 mt-0.5">
                            <span v-for="n in Math.min(day.count, 3)" :key="n"
                                  class="w-1.5 h-1.5 rounded-full"
                                  :class="day.str === filterForm.date ? 'bg-white/70' : 'bg-indigo-400'">
                            </span>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-center text-slate-400 mt-3">Click a date to view entries</p>
            </div>
        </div>

        <!-- Diary List -->
        <div v-if="viewMode === 'list'" class="space-y-4">
            <div v-for="diary in diaries.data" :key="diary.id" class="card diary-card border-l-4 border-l-indigo-500">
                <div class="card-body">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="badge badge-blue">{{ diary.subject.name }}</span>
                                <span class="text-xs font-semibold text-slate-500">{{ diary.course_class.name }} - {{ diary.section.name }}</span>
                            </div>
                            <h3 class="text-sm font-bold text-slate-800">{{ formatDate(diary.date) }}</h3>
                        </div>
                        <div class="flex gap-2">
                            <Button variant="danger" size="xs" v-if="can('delete_academic')" @click="deleteEntry(diary.id)">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </Button>
                        </div>
                    </div>

                    <div class="diary-content text-sm text-slate-600 line-clamp-3 mb-3">{{ diary.content }}</div>

                    <div class="flex items-center justify-between border-t border-slate-100 pt-3 flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600">
                                {{ diary.teacher.user.name.charAt(0) }}
                            </div>
                            <span class="text-xs text-slate-500">By {{ diary.teacher.user.name }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <!-- Read receipts -->
                            <div v-if="diary.reads_count !== undefined"
                                 class="flex items-center gap-1 text-slate-400 text-xs" title="Parents/students who have read this">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <span>{{ diary.reads_count }} seen</span>
                            </div>
                            <!-- Homework completions -->
                            <div v-if="diary.completions_count !== undefined"
                                 class="flex items-center gap-1 text-emerald-500 text-xs" title="Students who completed homework">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span>{{ diary.completions_count }} done</span>
                            </div>
                            <!-- Attachments -->
                            <button v-if="diary.attachments?.length > 0"
                                    @click="showAttachments(diary)"
                                    class="flex items-center gap-1 text-indigo-600 hover:text-indigo-800 cursor-pointer transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                <span class="text-xs font-bold">{{ diary.attachments.length }} file(s)</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="diaries.data.length === 0" class="card py-12 text-center">
                <div class="mb-3 text-slate-300">
                    <svg class="w-12 h-12 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </div>
                <p class="text-slate-500 font-medium">No diary entries found for the selected filters.</p>
            </div>
        </div>

        <!-- ── Export CSV Modal ── -->
        <div v-if="showExportModal"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
             @mousedown.self="showExportModal = false">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
                <div class="card-header flex justify-between items-center">
                    <h3 class="card-title">Export Diary Entries (CSV)</h3>
                    <button @click="showExportModal = false" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">×</button>
                </div>
                <div class="card-body space-y-4">
                    <div class="form-row-2">
                        <div class="form-field">
                            <label>From Date <span class="text-red-500">*</span></label>
                            <input type="date" v-model="exportForm.from" required />
                        </div>
                        <div class="form-field">
                            <label>To Date <span class="text-red-500">*</span></label>
                            <input type="date" v-model="exportForm.to" required />
                        </div>
                    </div>
                    <p class="text-xs text-slate-400">Will export entries for the currently selected class/section filters within this date range.</p>
                    <div class="flex gap-3">
                        <Button variant="secondary" @click="showExportModal = false" class="flex-1">Cancel</Button>
                        <Button @click="doExport" :disabled="!exportForm.from || !exportForm.to" class="flex-1">
                            Download CSV
                        </Button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Attachments List Modal -->
        <div v-if="viewingAttachments"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
             @mousedown.self="closeAttachments">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-bold text-slate-800">Attachments</h3>
                    <button @click="closeAttachments" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-4 space-y-2">
                    <Button variant="secondary" v-for="(f, i) in viewingAttachments.attachments" :key="i"
                            @click="openFileViewer(f, `Attachment ${i + 1}`)"
                            block>
                        <span class="text-lg">{{ fileIcon(f) }}</span>
                        <span class="flex-1 text-left">Attachment {{ i + 1 }}</span>
                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </Button>
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
.diary-filter-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 10px 16px;
    border-top: 1px solid #f1f5f9;
    background: #f8fafc;
}
.diary-filter-select {
    height: 38px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    font-size: 0.875rem;
    color: #1e293b;
    padding: 0 12px;
    outline: none;
    width: 160px;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.diary-filter-select:focus {
    border-color: #1169cd;
    box-shadow: 0 0 0 3px rgba(17, 105, 205, 0.08);
}
.diary-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.diary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.diary-content {
    white-space: pre-wrap;
}
.hide-scrollbar::-webkit-scrollbar {
    display: none;
}
.hide-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
