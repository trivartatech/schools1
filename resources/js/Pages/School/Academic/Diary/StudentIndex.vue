<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref } from 'vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    diaries:       Object,  // paginated
    myCompletions: Array,   // diary_ids the student has completed
    filters:       Object,
});

const toggleComplete = (diary) => {
    router.post(route('school.academic.diary.toggle-completion', diary.id), {}, { preserveScroll: true });
};

const selectedDate = ref(props.filters?.date || new Date().toISOString().split('T')[0]);

const applyDate = (d) => {
    selectedDate.value = d;
    router.get(route('school.academic.diary.student'), { date: d }, { preserveState: true });
};

// Build ±7 day slider
const buildSlider = () => {
    const days = [];
    const base = new Date(selectedDate.value);
    for (let i = -7; i <= 7; i++) {
        const d = new Date(base);
        d.setDate(d.getDate() + i);
        const str = d.toISOString().split('T')[0];
        days.push({
            str,
            day:  d.toLocaleDateString('en-IN', { weekday: 'short' }),
            date: d.getDate(),
            isToday: str === new Date().toISOString().split('T')[0],
        });
    }
    return days;
};

const expandedId = ref(null);
const formatDate = (d) => school.fmtDate(d);
// Serve attachments through the Laravel /api/media proxy so we don't depend
// on the /storage symlink being readable by nginx, and to dodge nginx's
// image-extension location block (which matches on the URL path, so
// keeping the file path inside ?p= avoids interception entirely).
const getFileUrl = (p) => {
    if (!p) return '';
    if (/^https?:\/\//i.test(p)) return p;
    const clean = String(p).replace(/^\/+/, '').replace(/^(?:storage|public)\//i, '');
    return `/api/media?p=${encodeURIComponent(clean)}`;
};
const fileExt = (p) => p?.split('.').pop().toLowerCase();
const fileIcon = (p) => {
    const ext = fileExt(p);
    if (['jpg','jpeg','png','gif'].includes(ext)) return '🖼️';
    if (ext === 'pdf') return '📄';
    if (['doc','docx'].includes(ext)) return '📝';
    return '📎';
};

// ── File viewer modal ────────────────────────────────────
const viewingFile = ref(null);
const viewingFileName = ref('');

const openFileViewer = (filePath, name) => {
    viewingFile.value = filePath;
    viewingFileName.value = name;
};
const closeFileViewer = () => { viewingFile.value = null; };

const fileType = (p) => {
    const ext = fileExt(p);
    if (ext === 'pdf') return 'pdf';
    if (['jpg','jpeg','png','gif','webp'].includes(ext)) return 'image';
    if (['mp4','mov','avi','webm'].includes(ext)) return 'video';
    return 'other';
};
</script>

<template>
    <SchoolLayout title="Class Diary">
        <PageHeader title="Class Diary" subtitle="Daily classwork and homework notes from your teachers" />

        <!-- Date Slider -->
        <div class="card mb-6 overflow-hidden">
            <div class="flex overflow-x-auto gap-1 p-3 scrollbar-hide">
                <button v-for="d in buildSlider()" :key="d.str"
                        @click="applyDate(d.str)"
                        :class="[
                            'flex flex-col items-center px-3 py-2 rounded-lg text-xs font-medium shrink-0 min-w-[48px] transition-all',
                            d.str === selectedDate ? 'bg-indigo-600 text-white shadow' :
                            d.isToday ? 'bg-indigo-50 text-indigo-600 border border-indigo-200' :
                            'text-slate-500 hover:bg-slate-50'
                        ]">
                    <span>{{ d.day }}</span>
                    <span class="text-base font-bold">{{ d.date }}</span>
                </button>
            </div>
        </div>

        <!-- Diary Entries -->
        <div v-if="diaries?.data?.length > 0" class="space-y-4">
            <div v-for="diary in diaries.data" :key="diary.id"
                 class="card hover:shadow-sm transition-shadow">
                <div class="card-body">
                    <!-- Header row -->
                    <div class="flex items-start justify-between gap-4 mb-3">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-slate-800 text-sm">{{ diary.subject?.name }}</span>
                                <span class="text-xs text-slate-400">·</span>
                                <span class="text-xs text-slate-500">{{ formatDate(diary.date) }}</span>
                            </div>
                            <div class="text-xs text-indigo-600 font-medium mt-0.5">
                                by {{ diary.teacher?.user?.name ?? 'Teacher' }}
                            </div>
                        </div>
                        <button @click="expandedId = expandedId === diary.id ? null : diary.id"
                                class="text-slate-400 hover:text-slate-600 shrink-0">
                            <svg class="w-5 h-5 transition-transform" :class="expandedId === diary.id ? 'rotate-180' : ''"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Content (always visible, clamped unless expanded) -->
                    <p class="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed"
                       :class="expandedId !== diary.id ? 'line-clamp-4' : ''">
                        {{ diary.content }}
                    </p>

                    <!-- Homework Complete toggle -->
                    <div class="mt-3 flex items-center gap-2">
                        <button @click="toggleComplete(diary)"
                                :class="[
                                    'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all border',
                                    myCompletions?.includes(diary.id)
                                        ? 'bg-emerald-50 border-emerald-300 text-emerald-700'
                                        : 'bg-white border-slate-200 text-slate-500 hover:border-emerald-300 hover:text-emerald-600'
                                ]">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ myCompletions?.includes(diary.id) ? 'Done ✓' : 'Mark as Done' }}
                        </button>
                        <span v-if="diary.completions_count > 1" class="text-xs text-slate-400">
                            {{ diary.completions_count }} students completed
                        </span>
                    </div>

                    <!-- Show more -->
                    <button v-if="expandedId !== diary.id && diary.content?.length > 200"
                            @click="expandedId = diary.id"
                            class="text-xs text-indigo-600 font-medium mt-1 hover:underline">
                        Show more
                    </button>

                    <!-- Attachments -->
                    <div v-if="diary.attachments?.length > 0 && expandedId === diary.id"
                         class="mt-4 pt-3 border-t border-slate-100">
                        <p class="text-xs font-bold text-slate-400 uppercase mb-2">Attachments</p>
                        <div class="flex flex-wrap gap-2">
                            <Button variant="secondary" v-for="(f, i) in diary.attachments" :key="i"
                               @click="openFileViewer(f, `Attachment ${i + 1}`)"
                               size="xs">
                                {{ fileIcon(f) }}
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Attachment {{ i + 1 }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="card py-16 text-center">
            <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <p class="text-slate-500 font-medium">No diary entries for this date.</p>
            <p class="text-xs text-slate-400 mt-1">Select a different date above.</p>
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

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.line-clamp-4 { display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; }
</style>
