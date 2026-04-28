<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    pendingGrading:       Array,   // assignments with ungraded submissions
    syllabusCompletionPct: Number,
    syllabusStats:        Object,  // { total, completed }
    todayClasses:         Array,
    recentDiaries:        Array,
    upcomingDue:          Array,
});

const formatDT   = (d) => school.fmtDateTime(d);
const formatDate = (d) => school.fmtDate(d);

const now = new Date();
const classStatus = (c) => {
    const start = new Date(c.start_time).getTime();
    const end   = c.end_time ? new Date(c.end_time).getTime() : start + 3600000;
    if (Date.now() >= start && Date.now() <= end) return 'live';
    if (start > Date.now()) return 'upcoming';
    return 'past';
};

const dueIn = (due) => {
    const diff = Math.ceil((new Date(due) - new Date()) / 86400000);
    if (diff === 0) return 'today';
    if (diff === 1) return 'tomorrow';
    return `in ${diff} days`;
};
</script>

<template>
    <SchoolLayout title="Academic Dashboard">
        <PageHeader title="Academic Dashboard" subtitle="Your teaching overview at a glance">
            <template #actions>
                <Button variant="secondary" as="link" :href="route('school.academic.calendar')">
                                📅 View Calendar
                            </Button>
            </template>
        </PageHeader>

        <!-- Summary Row -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="card text-center">
                <div class="card-body py-4">
                    <div class="text-2xl font-bold text-red-500">{{ pendingGrading.length }}</div>
                    <div class="text-xs text-slate-500 mt-1">Assignments Needing Grades</div>
                </div>
            </div>
            <div class="card text-center">
                <div class="card-body py-4">
                    <div class="text-2xl font-bold text-indigo-600">
                        {{ syllabusCompletionPct !== null ? syllabusCompletionPct + '%' : '—' }}
                    </div>
                    <div class="text-xs text-slate-500 mt-1">Syllabus Covered</div>
                    <div v-if="syllabusStats" class="text-[10px] text-slate-400 mt-0.5">
                        {{ syllabusStats.completed }}/{{ syllabusStats.total }} topics
                    </div>
                </div>
            </div>
            <div class="card text-center">
                <div class="card-body py-4">
                    <div class="text-2xl font-bold text-emerald-600">{{ todayClasses.length }}</div>
                    <div class="text-xs text-slate-500 mt-1">Classes Today</div>
                </div>
            </div>
            <div class="card text-center">
                <div class="card-body py-4">
                    <div class="text-2xl font-bold text-amber-500">{{ upcomingDue.length }}</div>
                    <div class="text-xs text-slate-500 mt-1">Assignments Due This Week</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- ── Pending Grading ── -->
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <h3 class="card-title text-red-600">📝 Pending Grading</h3>
                    <Link :href="route('school.academic.assignments.index')" class="text-xs text-indigo-600 font-medium hover:underline">
                        All Assignments →
                    </Link>
                </div>
                <div v-if="pendingGrading.length > 0" class="divide-y divide-slate-100">
                    <div v-for="a in pendingGrading" :key="a.id"
                         class="flex items-center justify-between px-4 py-3 hover:bg-slate-50 transition-colors">
                        <div>
                            <div class="font-medium text-slate-800 text-sm truncate max-w-[200px]">{{ a.title }}</div>
                            <div class="text-xs text-slate-500">{{ a.class }} / {{ a.section }} · {{ a.subject }}</div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <div class="text-center">
                                <div class="text-sm font-bold text-red-600">{{ a.ungraded }}</div>
                                <div class="text-[10px] text-slate-400">ungraded</div>
                            </div>
                            <Button size="xs" as="link" :href="route('school.academic.assignments.show', a.id)">Grade</Button>
                        </div>
                    </div>
                </div>
                <div v-else class="card-body py-8 text-center text-slate-400 text-sm">
                    ✓ All submissions are graded!
                </div>
            </div>

            <!-- ── Today's Online Classes ── -->
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <h3 class="card-title text-emerald-600">🎥 Today's Classes</h3>
                    <Link :href="route('school.academic.resources.index')" class="text-xs text-indigo-600 font-medium hover:underline">
                        All Resources →
                    </Link>
                </div>
                <div v-if="todayClasses.length > 0" class="divide-y divide-slate-100">
                    <div v-for="cls in todayClasses" :key="cls.id"
                         class="flex items-center justify-between px-4 py-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-slate-800 text-sm">{{ cls.subject?.name }}</span>
                                <span v-if="classStatus(cls) === 'live'"
                                      class="badge bg-emerald-500 text-white text-[10px] animate-pulse">LIVE</span>
                            </div>
                            <div class="text-xs text-slate-500">
                                {{ cls.course_class?.name }}/{{ cls.section?.name }} ·
                                {{ formatDT(cls.start_time) }}
                            </div>
                        </div>
                        <Button size="xs" as="a" :href="cls.meeting_link" target="_blank" class="shrink-0">
                            Join
                        </Button>
                    </div>
                </div>
                <div v-else class="card-body py-8 text-center text-slate-400 text-sm">
                    No online classes scheduled for today.
                </div>
            </div>

            <!-- ── Upcoming Due Dates ── -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title text-amber-600">⏰ Assignments Due This Week</h3>
                </div>
                <div v-if="upcomingDue.length > 0" class="divide-y divide-slate-100">
                    <div v-for="a in upcomingDue" :key="a.id"
                         class="flex items-center justify-between px-4 py-3 hover:bg-slate-50">
                        <div>
                            <div class="font-medium text-slate-800 text-sm">{{ a.title }}</div>
                            <div class="text-xs text-slate-500">{{ a.course_class?.name }}/{{ a.section?.name }} · {{ a.subject?.name }}</div>
                        </div>
                        <div class="text-right shrink-0 ml-4">
                            <div class="text-xs font-bold text-amber-600">Due {{ dueIn(a.due_date) }}</div>
                            <div class="text-[10px] text-slate-400">{{ formatDate(a.due_date) }}</div>
                        </div>
                    </div>
                </div>
                <div v-else class="card-body py-8 text-center text-slate-400 text-sm">
                    No assignments due in the next 7 days.
                </div>
            </div>

            <!-- ── Recent Diary Entries ── -->
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <h3 class="card-title text-purple-600">📖 Recent Diary Entries</h3>
                    <Link :href="route('school.academic.diary.index')" class="text-xs text-indigo-600 font-medium hover:underline">
                        All Entries →
                    </Link>
                </div>
                <div v-if="recentDiaries.length > 0" class="divide-y divide-slate-100">
                    <div v-for="d in recentDiaries" :key="d.id"
                         class="px-4 py-3 hover:bg-slate-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-indigo-600">{{ d.subject?.name }}</span>
                                <span class="text-[10px] text-slate-400">{{ d.course_class?.name }}/{{ d.section?.name }}</span>
                            </div>
                            <div class="flex items-center gap-3 text-[11px] text-slate-400">
                                <span title="Read by">👁 {{ d.reads_count }}</span>
                                <span title="Completed by">✓ {{ d.completions_count }}</span>
                            </div>
                        </div>
                        <p class="text-xs text-slate-600 mt-1 line-clamp-2">{{ d.content }}</p>
                    </div>
                </div>
                <div v-else class="card-body py-8 text-center text-slate-400 text-sm">
                    No diary entries in the last 7 days.
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>
