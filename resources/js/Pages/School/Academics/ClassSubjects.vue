<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, watch, computed, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import SlidePanel from '@/Components/SlidePanel.vue';
import { useDelete } from '@/Composables/useDelete';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({ assignments: Array, classes: Array, sections: Array, subjects: Array });

// Reactive local copy so toggling co-scholastic or removing an assignment reflects immediately
const localAssignments = ref([...props.assignments]);
watch(() => props.assignments, (v) => { localAssignments.value = [...v] });

const panelOpen = ref(false);
const filteredSections = ref([]);
const subjectSearch = ref('');

const form = useForm({
    course_class_id: '',
    section_id: '',
    subject_ids: [],       // ← now an array
    is_co_scholastic: false,
});

// When class changes, filter sections
watch(() => form.course_class_id, (classId) => {
    form.section_id = '';
    form.subject_ids = [];
    filteredSections.value = classId
        ? props.sections.filter(s => s.course_class_id == classId)
        : [];
});

// Subjects already assigned to the selected class+section (to show as checked/disabled)
const alreadyAssigned = computed(() => new Set(
    localAssignments.value
        .filter(a =>
            a.course_class_id == form.course_class_id &&
            (form.section_id ? a.section_id == form.section_id : !a.section_id)
        )
        .map(a => a.subject_id)
));

// Filtered subject list by search
const filteredSubjects = computed(() => {
    const q = subjectSearch.value.toLowerCase();
    return props.subjects.filter(s => !q || s.name.toLowerCase().includes(q) || (s.code || '').toLowerCase().includes(q));
});

const toggleSubject = (id) => {
    const idx = form.subject_ids.indexOf(id);
    if (idx === -1) form.subject_ids.push(id);
    else form.subject_ids.splice(idx, 1);
};

const selectAll = () => {
    form.subject_ids = filteredSubjects.value
        .filter(s => !alreadyAssigned.value.has(s.id))
        .map(s => s.id);
};
const clearAll = () => { form.subject_ids = []; };

// Co-scholastic subjects (pre-highlight for convenience)
const isCoScholasticSubject = (sub) => sub.is_co_scholastic;

const openPanel = () => {
    form.reset();
    filteredSections.value = [];
    subjectSearch.value = '';
    panelOpen.value = true;
};
const closePanel = () => { panelOpen.value = false; form.reset(); subjectSearch.value = ''; };

const submit = () => {
    form.post('/school/class-subjects', { onSuccess: () => closePanel() });
};

const toggleCo = (a) => {
    const newVal = !a.is_co_scholastic;
    // Optimistic local update
    const idx = localAssignments.value.findIndex(x => x.id === a.id);
    if (idx !== -1) localAssignments.value[idx] = { ...localAssignments.value[idx], is_co_scholastic: newVal };
    router.post(`/school/class-subjects/${a.id}`, { is_co_scholastic: newVal, _method: 'put' }, { preserveScroll: true });
};
const { del } = useDelete();
const destroy = (id) => {
    del(`/school/class-subjects/${id}`, 'Remove this subject assignment?');
};

// Group assignments by class, ordered by class numeric_value
const grouped = computed(() => {
    // Build an ordered list of class keys sorted by numeric_value
    const classOrder = [...props.classes]
        .sort((a, b) => (a.numeric_value ?? 0) - (b.numeric_value ?? 0) || a.name.localeCompare(b.name));

    const map = {};
    for (const cls of classOrder) {
        const items = localAssignments.value
            .filter(a => a.course_class_id === cls.id)
            .sort((a, b) => (a.subject?.sort_order ?? 0) - (b.subject?.sort_order ?? 0));
        if (items.length > 0) {
            map[cls.name] = items;
        }
    }
    // Include any unmatched (orphan) assignments
    for (const a of localAssignments.value) {
        const key = a.course_class?.name ?? 'Unknown';
        if (!Object.prototype.hasOwnProperty.call(map, key)) {
            map[key] = [];
        }
        const already = map[key].some(x => x.id === a.id);
        if (!already) map[key].push(a);
    }
    return map;
});

// Get selected class + section label for confirmation area
const selectedClassName = computed(() => props.classes.find(c => c.id == form.course_class_id)?.name || '');
const selectedSectionName = computed(() => {
    if (!form.section_id) return 'All Sections';
    return 'Section ' + (filteredSections.value.find(s => s.id == form.section_id)?.name || '');
});
</script>

<template>
    <SchoolLayout title="Assign Subjects">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Class-Subject Assignments</h1>
                <p class="page-header-sub">Assign multiple subjects at once to a class or section.</p>
            </div>
            <Button @click="openPanel">
                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Assign Subjects
            </Button>
        </div>

        <!-- Assignments grouped by class -->
        <div v-if="Object.keys(grouped).length === 0" class="card p-10 text-center text-slate-500 border-dashed">
            No subjects assigned yet. Click "Assign Subjects" to get started.
        </div>

        <div v-for="(items, className) in grouped" :key="className" class="card mb-6 overflow-hidden">
            <div class="card-header bg-slate-50 border-b border-slate-200">
                <h3 class="card-title font-bold text-slate-700">📚 {{ className }}</h3>
            </div>
            <div class="overflow-x-auto">
                <Table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Part</th>
                            <th>Section</th>
                            <th>Type</th>
                            <th class="w-32 text-center">Co-Scholastic</th>
                            <th class="w-24 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in items" :key="a.id">
                            <td>
                                <div class="font-medium text-slate-900">{{ a.subject?.name }}</div>
                                <div class="text-xs text-slate-400 mt-0.5">{{ a.subject?.code }}</div>
                            </td>
                            <td>
                                <span v-if="a.subject?.part" class="badge badge-amber">{{ a.subject.part }}</span>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="text-slate-500">
                                <span v-if="a.section">Section {{ a.section.name }}</span>
                                <span v-else class="text-slate-400 italic text-xs">All sections</span>
                            </td>
                            <td>
                                <span :class="a.subject?.type === 'theory' ? 'badge-purple' : 'badge-orange'"
                                      class="badge capitalize">{{ a.subject?.type }}</span>
                            </td>
                            <td class="text-center">
                                <button @click="toggleCo(a)"
                                    :class="a.is_co_scholastic ? 'bg-teal-100 text-teal-800 border border-teal-200' : 'bg-slate-100 text-slate-600 border border-slate-200'"
                                    class="px-3 py-1 rounded-full text-xs font-semibold transition-colors w-24 text-center">
                                    {{ a.is_co_scholastic ? 'Grade' : 'Marks' }}
                                </button>
                            </td>
                            <td class="text-right">
                                <button @click="destroy(a.id)" class="text-red-500 hover:text-red-700 text-sm font-medium">Remove</button>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <!-- Slide Panel: multi-select assignment -->
        <SlidePanel :open="panelOpen" title="Assign Subjects" width="w-[480px]" @close="closePanel">
            <form @submit.prevent="submit" class="flex flex-col h-full gap-5">

                <div class="form-row-2">
                    <!-- Class -->
                    <div class="form-field">
                        <label>Class <span class="text-red-500">*</span></label>
                        <select v-model="form.course_class_id" required>
                            <option value="" disabled>Select a class...</option>
                            <option v-for="cls in classes" :key="cls.id" :value="cls.id">
                                {{ cls.name }}{{ cls.department ? ' - ' + cls.department.name : '' }}
                            </option>
                        </select>
                        <span v-if="form.errors.course_class_id" class="form-error">{{ form.errors.course_class_id }}</span>
                    </div>

                    <!-- Section -->
                    <div class="form-field">
                        <label>Section <span class="text-slate-400 font-normal">(Optional)</span></label>
                        <select v-model="form.section_id" :disabled="!form.course_class_id">
                            <option value="">All sections</option>
                            <option v-for="sec in filteredSections" :key="sec.id" :value="sec.id">Section {{ sec.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- Step 3: Subject multi-select -->
                <div class="flex-1 flex flex-col min-h-0 border border-slate-200 rounded-lg overflow-hidden flex flex-col">
                    <div class="bg-slate-50 py-3 px-4 border-b border-slate-200">
                        <div class="flex items-center justify-between mb-3">
                            <label class="text-sm font-bold text-slate-700 m-0 p-0">Select Subjects</label>
                            <div class="flex gap-3 text-xs font-medium">
                                <button type="button" @click="selectAll" :disabled="!form.course_class_id"
                                    class="text-blue-600 hover:text-blue-800 disabled:opacity-40">Select All</button>
                                <button type="button" @click="clearAll" class="text-slate-500 hover:text-slate-700">Clear</button>
                            </div>
                        </div>
                        <div class="relative">
                            <input v-model="subjectSearch" type="text" placeholder="Search subjects..." class="w-full text-sm py-2 pl-9 border-slate-300 rounded-md" />
                            <svg class="absolute left-3 top-2.5 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto bg-white divide-y divide-slate-100 p-0" style="max-height: 380px;">
                        <div v-if="filteredSubjects.length === 0" class="p-6 text-center text-slate-500 text-sm">No subjects found.</div>
                        <label v-for="sub in filteredSubjects" :key="sub.id"
                            class="flex items-start gap-3 px-4 py-3 cursor-pointer transition-colors"
                            :class="[
                                alreadyAssigned.has(sub.id) ? 'bg-slate-50 opacity-60 cursor-not-allowed' : 'hover:bg-blue-50/50',
                                form.subject_ids.includes(sub.id) ? 'bg-blue-50/50' : ''
                            ]">
                            <input type="checkbox"
                                :value="sub.id"
                                :checked="form.subject_ids.includes(sub.id) || alreadyAssigned.has(sub.id)"
                                :disabled="alreadyAssigned.has(sub.id)"
                                @change="toggleSubject(sub.id)"
                                class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-semibold text-slate-800 truncate" :class="{ 'text-blue-700': form.subject_ids.includes(sub.id) }">{{ sub.name }}</span>
                                    <div class="flex gap-1.5 shrink-0">
                                        <span v-if="sub.part" class="text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded">{{ sub.part }}</span>
                                        <span v-if="sub.is_co_scholastic" class="text-[10px] font-bold uppercase tracking-wider bg-teal-100 text-teal-800 px-1.5 py-0.5 rounded">Grade</span>
                                    </div>
                                </div>
                                <div class="text-xs text-slate-500 flex items-center gap-1.5 mt-1">
                                    <span class="font-mono">{{ sub.code || 'N/A' }}</span>
                                    <span>&bull;</span>
                                    <span class="capitalize">{{ sub.type }}</span>
                                    <span v-if="alreadyAssigned.has(sub.id)" class="text-orange-600 font-medium ml-auto">&check; Assigned</span>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-lg p-3 cursor-pointer" @click="form.is_co_scholastic = !form.is_co_scholastic">
                    <input v-model="form.is_co_scholastic" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-teal-600 pointer-events-none" />
                    <div>
                        <div class="text-sm font-semibold text-slate-800">Mark selected as Co-Scholastic</div>
                        <div class="text-xs text-slate-500 mt-0.5">Applies grading (A+, A, B...) instead of numerical marks.</div>
                    </div>
                </div>

                <div v-if="form.course_class_id && form.subject_ids.length > 0" class="text-sm font-medium text-slate-700 bg-blue-50 border border-blue-100 p-3 rounded-lg">
                    Assigning <span class="text-blue-700 font-bold">{{ form.subject_ids.length }}</span> subject(s) to <strong>{{ selectedClassName }}</strong> <span v-if="selectedSectionName !== 'All Sections'">/ {{ selectedSectionName }}</span>
                </div>

                <p v-if="form.errors.subject_ids" class="text-sm text-red-600">{{ form.errors.subject_ids }}</p>

                <!-- Footer -->
                <div class="pt-4 border-t border-slate-100 flex items-center justify-between mt-auto">
                    <div class="text-xs text-slate-500">
                        <span v-if="form.subject_ids.length > 0" class="font-semibold text-blue-600">{{ form.subject_ids.length }} selected</span>
                        <span v-else>0 selected</span>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="secondary" type="button" @click="closePanel">Cancel</Button>
                        <Button type="submit" :loading="form.processing" :disabled="form.subject_ids.length === 0">
                            {{ form.processing ? 'Saving...' : 'Assign Subjects' }}
                        </Button>
                    </div>
                </div>
            </form>
        </SlidePanel>
    </SchoolLayout>
</template>
