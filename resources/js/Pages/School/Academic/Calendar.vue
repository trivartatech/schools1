<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    events:  Array,   // [{ type, date, title, label, color, link }]
    year:    Number,
    month:   Number,
    classes: Array,
    filters: Object,
});

// ── Filters ───────────────────────────────────────────────
const filterForm = ref({
    class_id:   props.filters?.class_id   || '',
    section_id: props.filters?.section_id || '',
});

const navigate = (yearDelta, monthDelta) => {
    let y = props.year;
    let m = props.month + monthDelta + (yearDelta * 12);
    while (m > 12) { m -= 12; y++; }
    while (m < 1)  { m += 12; y--; }
    router.get(route('school.academic.calendar'), {
        year: y, month: m, ...filterForm.value
    }, { preserveState: true });
};

const applyFilters = () => {
    router.get(route('school.academic.calendar'), {
        year: props.year, month: props.month, ...filterForm.value
    }, { preserveState: true });
};

// ── Calendar grid ─────────────────────────────────────────
const firstWeekday = computed(() =>
    new Date(props.year, props.month - 1, 1).getDay()
);
const daysInMonth = computed(() =>
    new Date(props.year, props.month, 0).getDate()
);

const calendarCells = computed(() => {
    const cells = [];
    for (let i = 0; i < firstWeekday.value; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth.value; d++) {
        const str = `${props.year}-${String(props.month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        cells.push({ d, str, events: props.events.filter(e => e.date === str) });
    }
    return cells;
});

const monthName = computed(() =>
    new Date(props.year, props.month - 1).toLocaleDateString('en-IN', { month: 'long', year: 'numeric' })
);

const todayStr = new Date().toISOString().split('T')[0];

// ── Type config ────────────────────────────────────────────
const typeConfig = {
    assignment:  { bg: 'bg-red-100',    text: 'text-red-700',    dot: 'bg-red-500',    icon: '📝' },
    online_class:{ bg: 'bg-blue-100',   text: 'text-blue-700',   dot: 'bg-blue-500',   icon: '🎥' },
    syllabus:    { bg: 'bg-green-100',  text: 'text-green-700',  dot: 'bg-green-500',  icon: '📚' },
    diary:       { bg: 'bg-purple-100', text: 'text-purple-700', dot: 'bg-purple-500', icon: '📖' },
    holiday:     { bg: 'bg-orange-100', text: 'text-orange-700', dot: 'bg-orange-500', icon: '🏖️' },
};

const cfg = (type) => typeConfig[type] ?? { bg: 'bg-slate-100', text: 'text-slate-700', dot: 'bg-slate-400', icon: '•' };

// ── Selected day detail ────────────────────────────────────
const selectedDay = ref(null);
const dayEvents   = computed(() => selectedDay.value
    ? props.events.filter(e => e.date === selectedDay.value)
    : []
);

// Sidebar: this month events count by type
const eventCounts = computed(() => {
    const counts = { assignment: 0, online_class: 0, syllabus: 0, diary: 0, holiday: 0 };
    props.events.forEach(e => { if (counts[e.type] !== undefined) counts[e.type]++; });
    return counts;
});

const sections = computed(() => {
    const cls = props.classes.find(c => c.id === parseInt(filterForm.value.class_id));
    return cls?.sections ?? [];
});
</script>

<template>
    <SchoolLayout title="Academic Calendar">
        <PageHeader title="Academic Calendar" subtitle="Assignments, online classes, syllabus &amp; diary — all in one view">
            <template #actions>
                <Button variant="secondary" as="link" :href="route('school.academic.dashboard')">← Dashboard</Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

            <!-- Left sidebar: filters + legend + counts -->
            <div class="lg:col-span-1 space-y-4">
                <!-- Filters -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title text-sm">Filters</h3></div>
                    <div class="card-body space-y-3">
                        <div class="form-field">
                            <label>Class</label>
                            <select v-model="filterForm.class_id" @change="filterForm.section_id=''; applyFilters()">
                                <option value="">All Classes</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Section</label>
                            <select v-model="filterForm.section_id" @change="applyFilters" :disabled="!filterForm.class_id">
                                <option value="">All Sections</option>
                                <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Legend + counts -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title text-sm">This Month</h3></div>
                    <div class="card-body space-y-2">
                        <div v-for="[type, label] in [['assignment','Assignments'],['online_class','Online Classes'],['syllabus','Syllabus Dates'],['diary','Diary Entries'],['holiday','Holidays & Events']]"
                             :key="type"
                             class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-base">{{ cfg(type).icon }}</span>
                                <span class="text-xs font-medium text-slate-600">{{ label }}</span>
                            </div>
                            <span :class="['text-xs font-bold px-2 py-0.5 rounded-full', cfg(type).bg, cfg(type).text]">
                                {{ eventCounts[type] }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Selected day panel -->
                <div v-if="selectedDay" class="card">
                    <div class="card-header flex justify-between items-center">
                        <h3 class="card-title text-sm">
                            {{ new Date(selectedDay).toLocaleDateString('en-IN', { day:'2-digit', month:'short' }) }}
                        </h3>
                        <button @click="selectedDay = null" class="text-slate-400 text-xl leading-none">×</button>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <div v-if="dayEvents.length === 0" class="card-body text-xs text-slate-400 text-center py-4">
                            No events this day.
                        </div>
                        <a v-for="(e, i) in dayEvents" :key="i"
                           :href="e.link"
                           :class="['block px-4 py-2.5 hover:opacity-80 transition-opacity', cfg(e.type).bg]">
                            <div :class="['text-xs font-bold', cfg(e.type).text]">{{ cfg(e.type).icon }} {{ e.title }}</div>
                            <div class="text-[11px] text-slate-500 mt-0.5">{{ e.label }}</div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Calendar -->
            <div class="lg:col-span-3">
                <div class="card overflow-hidden">
                    <!-- Month navigation -->
                    <div class="flex items-center justify-between p-4 border-b border-slate-100">
                        <Button variant="secondary" size="sm" @click="navigate(0, -1)">‹ Prev</Button>
                        <h3 class="text-lg font-bold text-slate-800">{{ monthName }}</h3>
                        <Button variant="secondary" size="sm" @click="navigate(0, 1)">Next ›</Button>
                    </div>

                    <!-- Day headers -->
                    <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50">
                        <div v-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d"
                             class="py-2 text-center text-xs font-bold text-slate-400 uppercase">{{ d }}</div>
                    </div>

                    <!-- Cells -->
                    <div class="grid grid-cols-7">
                        <div v-for="(cell, idx) in calendarCells" :key="idx"
                             class="min-h-[90px] border-r border-b border-slate-100 last:border-r-0 p-1.5 cursor-pointer transition-colors"
                             :class="[
                                 !cell ? 'bg-slate-50' : 'hover:bg-slate-50',
                                 cell?.str === selectedDay ? 'bg-indigo-50' : '',
                                 cell?.str === todayStr ? 'ring-2 ring-inset ring-indigo-400' : '',
                             ]"
                             @click="cell && (selectedDay = cell.str)">
                            <!-- Date number -->
                            <div v-if="cell" class="mb-1">
                                <span :class="[
                                    'inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold',
                                    cell.str === todayStr ? 'bg-indigo-600 text-white' : 'text-slate-600'
                                ]">{{ cell.d }}</span>
                            </div>

                            <!-- Events (show up to 3, then +N) -->
                            <div v-if="cell" class="space-y-0.5">
                                <div v-for="e in cell.events.slice(0, 3)" :key="e.title + e.type"
                                     :class="['rounded text-[10px] px-1.5 py-0.5 truncate font-medium leading-snug', cfg(e.type).bg, cfg(e.type).text]">
                                    {{ cfg(e.type).icon }} {{ e.title }}
                                </div>
                                <div v-if="cell.events.length > 3"
                                     class="text-[10px] text-slate-400 px-1 font-medium">
                                    +{{ cell.events.length - 3 }} more
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>
