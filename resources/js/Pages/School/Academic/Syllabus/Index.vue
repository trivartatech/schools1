<script setup>
import Button from '@/Components/ui/Button.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import { router, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref, computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import axios from 'axios';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    topics:      Array,
    statuses:    Object,   // keyed by topic_id
    classes:     Array,
    sections:    Array,
    filters:     Object,
    progressPct: Number,   // 0-100, only when section_id is set
});

const { can } = usePermissions();

// ── Modals ───────────────────────────────────────────────
const showAddTopic      = ref(false);
const showEditTopic     = ref(false);
const showUpdateStatus  = ref(false);
const activeTopic       = ref(null);

// ── Filters ──────────────────────────────────────────────
const filterForm = ref({
    class_id:   props.filters?.class_id   || '',
    subject_id: props.filters?.subject_id || '',
    section_id: props.filters?.section_id || '',
});

const applyFilters = () => {
    router.get(route('school.academic.syllabus.index'), filterForm.value, { preserveState: true });
};

// Subjects available for the selected class in filter bar
const selectedClassForFilter = computed(() =>
    props.classes.find(c => c.id === parseInt(filterForm.value.class_id))
);

const subjectsForFilter = computed(() => {
    if (!selectedClassForFilter.value) return [];
    const map = new Map();
    (selectedClassForFilter.value.subjects || []).forEach(s => map.set(s.id, s));
    (selectedClassForFilter.value.sections || []).forEach(sec =>
        (sec.subjects || []).forEach(s => map.set(s.id, s))
    );
    return Array.from(map.values());
});

// ── Add Topic Form ────────────────────────────────────────
const topicForm = useForm({
    class_id:     props.filters?.class_id   || '',
    subject_id:   props.filters?.subject_id || '',
    chapter_name: '',
    topic_name:   '',
    sort_order:   1,
});

const selectedClassForTopic = computed(() =>
    props.classes.find(c => c.id === parseInt(topicForm.class_id))
);

const subjectsForTopic = computed(() => {
    if (!selectedClassForTopic.value) return [];
    const map = new Map();
    (selectedClassForTopic.value.subjects || []).forEach(s => map.set(s.id, s));
    (selectedClassForTopic.value.sections || []).forEach(sec =>
        (sec.subjects || []).forEach(s => map.set(s.id, s))
    );
    return Array.from(map.values());
});

const storeTopic = () => {
    topicForm.post(route('school.academic.syllabus.store-topic'), {
        onSuccess: () => {
            showAddTopic.value = false;
            topicForm.reset('chapter_name', 'topic_name');
        },
    });
};

// ── Edit Topic Form ───────────────────────────────────────
const editTopicForm = useForm({
    chapter_name: '',
    topic_name:   '',
    sort_order:   1,
});

const openEditTopic = (topic) => {
    activeTopic.value        = topic;
    editTopicForm.chapter_name = topic.chapter_name;
    editTopicForm.topic_name   = topic.topic_name;
    editTopicForm.sort_order   = topic.sort_order ?? 1;
    showEditTopic.value      = true;
};

const updateTopic = () => {
    editTopicForm.put(route('school.academic.syllabus.update-topic', activeTopic.value.id), {
        onSuccess: () => { showEditTopic.value = false; },
    });
};

const destroyTopic = (topic) => {
    if (!confirm(`Delete topic "${topic.topic_name}"? This cannot be undone.`)) return;
    router.delete(route('school.academic.syllabus.destroy-topic', topic.id));
};

// ── Update Status Form ────────────────────────────────────
const statusForm = useForm({
    section_id:     props.filters?.section_id || '',
    status:         'pending',
    planned_date:   '',
    completed_date: '',
});

const openStatusModal = (topic) => {
    activeTopic.value = topic;
    const existing = props.statuses?.[topic.id];
    statusForm.status         = existing?.status         || 'pending';
    statusForm.planned_date   = existing?.planned_date   || '';
    statusForm.completed_date = existing?.completed_date || '';
    showUpdateStatus.value = true;
};

const updateStatus = () => {
    statusForm.post(route('school.academic.syllabus.update-status', activeTopic.value.id), {
        onSuccess: () => { showUpdateStatus.value = false; },
    });
};

// ── Helpers ──────────────────────────────────────────────
const statusBadgeClass = (status) => {
    if (status === 'completed')  return 'badge-green';
    if (status === 'in_progress') return 'badge-amber';
    return 'badge-gray';
};

// Group topics by chapter (preserves insertion order)
const chapters = computed(() => {
    const groups = {};
    props.topics.forEach(t => {
        if (!groups[t.chapter_name]) groups[t.chapter_name] = [];
        groups[t.chapter_name].push(t);
    });
    return groups;
});

// Overall completion for current filter
const completedCount = computed(() =>
    props.topics.filter(t => props.statuses?.[t.id]?.status === 'completed').length
);

// ── Reset Progress ────────────────────────────────────────
const showResetModal = ref(false);
const resetForm = useForm({
    class_id:   props.filters?.class_id   || '',
    subject_id: props.filters?.subject_id || '',
    section_id: props.filters?.section_id || '',
});

const doReset = () => {
    resetForm.post(route('school.academic.syllabus.reset-progress'), {
        onSuccess: () => { showResetModal.value = false; },
    });
};

// ── CSV Export ────────────────────────────────────────────
const exportCSV = () => {
    const params = new URLSearchParams();
    if (filterForm.value.class_id)   params.set('class_id', filterForm.value.class_id);
    if (filterForm.value.subject_id) params.set('subject_id', filterForm.value.subject_id);
    if (filterForm.value.section_id) params.set('section_id', filterForm.value.section_id);
    window.location.href = route('school.academic.syllabus.export') + '?' + params.toString();
};
</script>

<template>
    <SchoolLayout title="Syllabus Tracker">
        <div class="page-header">
            <div>
                <h2 class="page-header-title">Syllabus Tracker</h2>
                <p class="page-header-sub">Track curriculum completion status</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <!-- CSV Export -->
                <Button variant="secondary" v-if="topics.length > 0" @click="exportCSV" class="text-emerald-600 border-emerald-200 hover:bg-emerald-50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Export CSV
                </Button>
                <!-- Reset Progress -->
                <Button variant="secondary" v-if="can('edit_academic') && topics.length > 0" @click="resetForm.class_id = filterForm.value.class_id; resetForm.subject_id = filterForm.value.subject_id; resetForm.section_id = filterForm.value.section_id; showResetModal = true" class="text-red-500 border-red-200 hover:bg-red-50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Reset Progress
                </Button>
                <!-- Add Topic -->
                <Button v-if="can('create_academic')" as="link" :href="route('school.academic.syllabus.create')">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Topic
                </Button>
            </div>
        </div>

        <!-- Filters -->
        <FilterBar :active="!!(filterForm.class_id || filterForm.subject_id || filterForm.section_id)" @clear="filterForm = {class_id:'',subject_id:'',section_id:''}; applyFilters()">
            <select v-model="filterForm.class_id" @change="filterForm.subject_id=''; applyFilters()" style="width:160px;">
                <option value="">Select Class</option>
                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="filterForm.subject_id" @change="applyFilters" :disabled="!filterForm.class_id" style="width:160px;">
                <option value="">Select Subject</option>
                <option v-for="s in subjectsForFilter" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <select v-model="filterForm.section_id" @change="applyFilters" style="width:160px;">
                <option value="">Master View</option>
                <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
        </FilterBar>

        <!-- Progress Bar (when section is selected) -->
        <div v-if="filterForm.section_id && topics.length > 0" class="card mb-6">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-bold text-slate-700">Syllabus Coverage</span>
                    <span class="text-sm font-bold text-indigo-600">
                        {{ completedCount }}/{{ topics.length }} topics completed
                        ({{ progressPct ?? Math.round(completedCount / topics.length * 100) }}%)
                    </span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
                    <div class="bg-indigo-600 h-3 rounded-full transition-all duration-500"
                         :style="`width: ${progressPct ?? Math.round(completedCount / topics.length * 100)}%`">
                    </div>
                </div>
            </div>
        </div>

        <!-- Syllabus Breakdown -->
        <div class="space-y-6">
            <div v-for="(chapterTopics, chapter) in chapters" :key="chapter" class="card overflow-hidden">
                <div class="card-header">
                    <h3 class="section-heading">{{ chapter }}</h3>
                </div>
                <div class="card-body p-0">
                    <Table>
                        <thead>
                            <tr>
                                <th class="w-12">#</th>
                                <th>Topic Name</th>
                                <th v-if="filterForm.section_id">Status</th>
                                <th v-if="filterForm.section_id">Completed On</th>
                                <th v-if="can('edit_academic') || can('delete_academic')" class="w-32 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(topic, idx) in chapterTopics" :key="topic.id">
                                <td class="text-slate-400">{{ topic.sort_order || (idx + 1) }}</td>
                                <td class="font-medium text-slate-800">{{ topic.topic_name }}</td>
                                <td v-if="filterForm.section_id">
                                    <span :class="['badge', statusBadgeClass(statuses?.[topic.id]?.status)]">
                                        {{ (statuses?.[topic.id]?.status ?? 'pending').replace('_', ' ').toUpperCase() }}
                                    </span>
                                </td>
                                <td v-if="filterForm.section_id" class="text-xs text-slate-500">
                                    {{ statuses?.[topic.id]?.completed_date
                                        ? school.fmtDate(statuses[topic.id].completed_date)
                                        : '—' }}
                                </td>
                                <td v-if="can('edit_academic') || can('delete_academic')" class="text-right">
                                    <div class="flex gap-1 justify-end">
                                        <!-- Update section status -->
                                        <Button variant="secondary" size="xs" v-if="filterForm.section_id && can('edit_academic')" @click="openStatusModal(topic)">Update</Button>
                                        <!-- Edit topic master data -->
                                        <Button variant="secondary" size="xs" v-if="can('edit_academic')" @click="openEditTopic(topic)" title="Edit topic">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </Button>
                                        <!-- Delete topic -->
                                        <Button variant="danger" size="xs" v-if="can('delete_academic')" @click="destroyTopic(topic)" title="Delete topic">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
            </div>

            <div v-if="topics.length === 0" class="card py-16 text-center">
                <svg class="w-12 h-12 mx-auto text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                <p class="text-slate-500">No topics added for this class/subject yet.</p>
                <p class="text-xs text-slate-400 mt-1">Select a class and subject above, then click "Add Topic".</p>
            </div>
        </div>

        <!-- ── Add Topic Modal ── -->
        <div v-if="showAddTopic"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
                <div class="card-header flex justify-between items-center">
                    <h3 class="card-title">Add Syllabus Topic</h3>
                    <button @click="showAddTopic = false" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">×</button>
                </div>
                <form @submit.prevent="storeTopic" class="card-body space-y-4">
                    <div class="form-row-2">
                        <div class="form-field">
                            <label>Class <span class="text-red-500">*</span></label>
                            <select v-model="topicForm.class_id" required>
                                <option value="">Select Class</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p v-if="topicForm.errors.class_id" class="field-error">{{ topicForm.errors.class_id }}</p>
                        </div>
                        <div class="form-field">
                            <label>Subject <span class="text-red-500">*</span></label>
                            <select v-model="topicForm.subject_id" required :disabled="!topicForm.class_id">
                                <option value="">Select Subject</option>
                                <option v-for="s in subjectsForTopic" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <p v-if="topicForm.errors.subject_id" class="field-error">{{ topicForm.errors.subject_id }}</p>
                        </div>
                    </div>
                    <div class="form-field">
                        <label>Chapter Name <span class="text-red-500">*</span></label>
                        <input type="text" v-model="topicForm.chapter_name"
                               placeholder="e.g. Chapter 1: Introduction" required />
                        <p v-if="topicForm.errors.chapter_name" class="field-error">{{ topicForm.errors.chapter_name }}</p>
                    </div>
                    <div class="form-field">
                        <label>Topic Name <span class="text-red-500">*</span></label>
                        <input type="text" v-model="topicForm.topic_name"
                               placeholder="e.g. History of Computing" required />
                        <p v-if="topicForm.errors.topic_name" class="field-error">{{ topicForm.errors.topic_name }}</p>
                    </div>
                    <div class="form-field">
                        <label>Sort Order</label>
                        <input type="number" v-model="topicForm.sort_order" min="1" />
                    </div>
                    <div class="flex gap-3 pt-4">
                        <Button variant="secondary" type="button" @click="showAddTopic = false" class="flex-1">Cancel</Button>
                        <Button type="submit" :loading="topicForm.processing" class="flex-1">
                            Save Topic
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── Edit Topic Modal ── -->
        <div v-if="showEditTopic"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
                <div class="card-header flex justify-between items-center">
                    <h3 class="card-title">Edit Topic</h3>
                    <button @click="showEditTopic = false" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">×</button>
                </div>
                <form @submit.prevent="updateTopic" class="card-body space-y-4">
                    <div class="form-field">
                        <label>Chapter Name <span class="text-red-500">*</span></label>
                        <input type="text" v-model="editTopicForm.chapter_name" required />
                        <p v-if="editTopicForm.errors.chapter_name" class="field-error">{{ editTopicForm.errors.chapter_name }}</p>
                    </div>
                    <div class="form-field">
                        <label>Topic Name <span class="text-red-500">*</span></label>
                        <input type="text" v-model="editTopicForm.topic_name" required />
                        <p v-if="editTopicForm.errors.topic_name" class="field-error">{{ editTopicForm.errors.topic_name }}</p>
                    </div>
                    <div class="form-field">
                        <label>Sort Order</label>
                        <input type="number" v-model="editTopicForm.sort_order" min="1" />
                    </div>
                    <div class="flex gap-3 pt-4">
                        <Button variant="secondary" type="button" @click="showEditTopic = false" class="flex-1">Cancel</Button>
                        <Button type="submit" :loading="editTopicForm.processing" class="flex-1">
                            Update Topic
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── Reset Progress Modal ── -->
        <div v-if="showResetModal"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
             @mousedown.self="showResetModal = false">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
                <div class="card-header flex justify-between items-center">
                    <h3 class="card-title text-red-600">Reset Progress</h3>
                    <button @click="showResetModal = false" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">×</button>
                </div>
                <div class="card-body space-y-4">
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
                        <strong>⚠ Warning:</strong> This will reset all topic statuses back to <strong>Pending</strong> for the selected class/subject{{ resetForm.section_id ? '/section' : ' (all sections)' }}.
                        Planned dates and completion dates will be cleared. This is typically done at the start of a new academic year.
                    </div>
                    <div class="form-field">
                        <label>Scope</label>
                        <select v-model="resetForm.section_id">
                            <option value="">All Sections</option>
                            <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }} only</option>
                        </select>
                    </div>
                    <div class="flex gap-3">
                        <Button variant="secondary" type="button" @click="showResetModal = false" class="flex-1">Cancel</Button>
                        <Button variant="danger" class="flex-1"
                                :loading="resetForm.processing"
                                @click="doReset">
                            {{ resetForm.processing ? 'Resetting...' : 'Yes, Reset Progress' }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Update Status Modal ── -->
        <div v-if="showUpdateStatus"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
                <div class="card-header">
                    <h3 class="card-title">Update Status</h3>
                    <p class="text-xs font-bold text-indigo-600 mt-0.5">{{ activeTopic?.topic_name }}</p>
                </div>
                <form @submit.prevent="updateStatus" class="card-body space-y-4">
                    <div class="form-field">
                        <label>Current Status</label>
                        <select v-model="statusForm.status" required>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div v-if="statusForm.status !== 'pending'" class="form-field">
                        <label>Planned Date</label>
                        <input type="date" v-model="statusForm.planned_date" />
                    </div>
                    <div v-if="statusForm.status === 'completed'" class="form-field">
                        <label>Completion Date</label>
                        <input type="date" v-model="statusForm.completed_date" />
                    </div>
                    <div class="flex gap-3 pt-4">
                        <Button variant="secondary" type="button" @click="showUpdateStatus = false" class="flex-1">Close</Button>
                        <Button type="submit" :loading="statusForm.processing" class="flex-1">
                            Save Status
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.field-error { font-size: 0.75rem; color: #ef4444; margin-top: 0.25rem; }
</style>
