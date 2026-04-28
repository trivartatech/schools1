<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref, computed } from 'vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    scores:  Array,   // [{ class_name, subject_name, syllabus_pct, grading_pct, avg_marks, diary_week, health_score, total_topics, total_assignments }]
    classes: Array,
    filters: Object,
});

const filterForm = ref({ class_id: props.filters?.class_id || '' });

const applyFilters = () => {
    router.get(route('school.academic.health-score'), filterForm.value, { preserveState: true });
};

const filtered = computed(() =>
    filterForm.value.class_id
        ? props.scores.filter(s => String(s.class_id) === String(filterForm.value.class_id))
        : props.scores
);

const healthColor = (score) => {
    if (score === null || score === undefined) return 'bg-slate-200 text-slate-500';
    if (score >= 75) return 'bg-emerald-500 text-white';
    if (score >= 50) return 'bg-amber-400 text-white';
    return 'bg-red-500 text-white';
};

const barColor = (score) => {
    if (score === null || score === undefined) return 'bg-slate-200';
    if (score >= 75) return 'bg-emerald-500';
    if (score >= 50) return 'bg-amber-400';
    return 'bg-red-500';
};

const pctBar = (val) => (val === null || val === undefined) ? 0 : Math.min(val, 100);
</script>

<template>
    <SchoolLayout title="Academic Health Score">
        <PageHeader title="Academic Health Score" subtitle="Subject-wise overview of syllabus coverage, grading, and engagement">
            <template #actions>
                <Button variant="secondary" as="link" :href="route('school.academic.dashboard')">← Dashboard</Button>
            </template>
        </PageHeader>

        <!-- How it works -->
        <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 mb-6 text-sm text-indigo-700 flex flex-wrap gap-4 items-center">
            <div class="font-bold text-indigo-800">🧮 Score Formula:</div>
            <div>Syllabus Coverage × 60% + Assignment Grading Rate × 40%</div>
            <div class="ml-auto flex gap-3 text-xs">
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span> ≥ 75% Healthy</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-amber-400 inline-block"></span> 50–74% Moderate</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-red-500 inline-block"></span> &lt; 50% Needs Attention</span>
            </div>
        </div>

        <!-- Filter -->
        <div class="card mb-6">
            <div class="card-body">
                <div class="flex gap-4 items-end">
                    <div class="form-field min-w-[180px]">
                        <label>Filter by Class</label>
                        <select v-model="filterForm.class_id" @change="applyFilters">
                            <option value="">All Classes</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <Button variant="secondary" size="sm" @click="filterForm.class_id = ''; applyFilters()">Reset</Button>
                </div>
            </div>
        </div>

        <!-- Score table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <Table>
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Syllabus</th>
                            <th>Grading</th>
                            <th>Avg Marks</th>
                            <th title="Diary entries in last 7 days">Diary (Week)</th>
                            <th>Health Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in filtered" :key="s.class_id + '-' + s.subject_id"
                            :class="s.health_score !== null && s.health_score < 50 ? 'bg-red-50/40' : ''">
                            <td class="font-medium text-slate-800">{{ s.class_name }}</td>
                            <td class="text-slate-600">{{ s.subject_name }}</td>

                            <!-- Syllabus % bar -->
                            <td>
                                <div v-if="s.syllabus_pct !== null" class="flex items-center gap-2">
                                    <div class="w-20 bg-slate-100 rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full transition-all" :class="barColor(s.syllabus_pct)"
                                             :style="`width:${pctBar(s.syllabus_pct)}%`"></div>
                                    </div>
                                    <span class="text-xs font-bold text-slate-600">{{ s.syllabus_pct }}%</span>
                                </div>
                                <span v-else class="text-xs text-slate-400">No topics</span>
                            </td>

                            <!-- Grading % bar -->
                            <td>
                                <div v-if="s.grading_pct !== null" class="flex items-center gap-2">
                                    <div class="w-20 bg-slate-100 rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full transition-all" :class="barColor(s.grading_pct)"
                                             :style="`width:${pctBar(s.grading_pct)}%`"></div>
                                    </div>
                                    <span class="text-xs font-bold text-slate-600">{{ s.grading_pct }}%</span>
                                </div>
                                <span v-else class="text-xs text-slate-400">—</span>
                            </td>

                            <!-- Avg marks -->
                            <td>
                                <span v-if="s.avg_marks !== null" class="font-bold text-slate-700">{{ s.avg_marks }}</span>
                                <span v-else class="text-slate-400 text-xs">—</span>
                            </td>

                            <!-- Diary this week -->
                            <td>
                                <span v-if="s.diary_week > 0" class="badge badge-blue text-xs">{{ s.diary_week }} entries</span>
                                <span v-else class="badge badge-gray text-xs">None</span>
                            </td>

                            <!-- Health score badge -->
                            <td>
                                <span v-if="s.health_score !== null"
                                      :class="['inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-bold min-w-[52px]', healthColor(s.health_score)]">
                                    {{ s.health_score }}%
                                </span>
                                <span v-else class="badge badge-gray text-xs">N/A</span>
                            </td>
                        </tr>

                        <tr v-if="filtered.length === 0">
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                No data available. Add syllabus topics and assignments to see scores.
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <!-- Summary row -->
        <div v-if="filtered.length > 0" class="mt-4 flex gap-4 flex-wrap text-xs text-slate-500 justify-end">
            <span>{{ filtered.filter(s => s.health_score >= 75).length }} healthy</span>
            <span class="text-amber-600">{{ filtered.filter(s => s.health_score >= 50 && s.health_score < 75).length }} moderate</span>
            <span class="text-red-600">{{ filtered.filter(s => s.health_score < 50).length }} need attention</span>
            <span class="text-slate-400">out of {{ filtered.length }} class-subject combinations</span>
        </div>
    </SchoolLayout>
</template>
