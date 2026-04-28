<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref, computed, watch } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import ExportDropdown from '@/Components/ExportDropdown.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    assignments: Object,  // paginated
    classes: Array,
    filters: Object,
});

const { can } = usePermissions();

const showCreateModal = ref(false);

// ── Filter ──────────────────────────────────────────────
const filterForm = ref({
    class_id:   props.filters?.class_id   || '',
    subject_id: props.filters?.subject_id || '',
    status:     props.filters?.status     || '',
});

const applyFilters = () => {
    router.get(route('school.academic.assignments.index'), filterForm.value, { preserveState: true });
};

// ── Create form ─────────────────────────────────────────
const form = useForm({
    class_id:    '',
    section_ids: [],
    subject_id:  '',
    title:       '',
    description: '',
    due_date:    '',
    max_marks:   100,
    status:      'published',
    attachments: [],
});

const selectedClass = computed(() =>
    props.classes.find(c => c.id === parseInt(form.class_id))
);

const sections = computed(() =>
    selectedClass.value ? (selectedClass.value.sections || []) : []
);

const selectedSections = computed(() =>
    sections.value.filter(s => form.section_ids.includes(s.id))
);

const availableSubjects = computed(() => {
    const map = new Map();
    if (selectedClass.value?.subjects) {
        selectedClass.value.subjects.forEach(s => map.set(s.id, s));
    }
    selectedSections.value.forEach(sec => {
        if (sec.subjects) sec.subjects.forEach(s => map.set(s.id, s));
    });
    return Array.from(map.values());
});

// Reset subject when class changes
watch(() => form.class_id, () => {
    form.section_ids = [];
    form.subject_id  = '';
});

const submit = () => {
    form.post(route('school.academic.assignments.store'), {
        forceFormData: true,
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

// ── Helpers ──────────────────────────────────────────────
const formatDate = (date) => school.fmtDate(date);

/**
 * Compare date strings only (YYYY-MM-DD) — avoids false "Expired" on due-day.
 */
const todayStr = () => school.today();

const isExpired = (assignment) => {
    if (assignment.status === 'closed') return true;
    return assignment.due_date < todayStr();
};

const statusBadgeClass = (assignment) => {
    if (assignment.status === 'draft')   return 'badge-gray';
    if (assignment.status === 'closed' || isExpired(assignment)) return 'badge-red';
    return 'badge-green';
};

const statusLabel = (assignment) => {
    if (assignment.status === 'draft')   return 'Draft';
    if (assignment.status === 'closed')  return 'Closed';
    if (isExpired(assignment))           return 'Expired';
    return 'Active';
};

const handleFiles = (e) => {
    form.attachments = Array.from(e.target.files);
};

const duplicateAssignment = (assignment) => {
    router.post(route('school.academic.assignments.duplicate', assignment.id));
};
</script>

<template>
    <SchoolLayout title="Assignments">
        <PageHeader title="Assignments" subtitle="Manage homework and digital submissions">
            <template #actions>
                <ExportDropdown
                    base-url="/school/export/assignments"
                    :params="{ class_id: filterForm.class_id, subject_id: filterForm.subject_id, status: filterForm.status }"
                />
                <Button v-if="can('create_academic')" as="link" :href="route('school.academic.assignments.create')">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Assignment
                </Button>

            </template>
        </PageHeader>

        <!-- Filters -->
        <FilterBar :active="!!(filterForm.class_id || filterForm.status)" @clear="filterForm = {class_id:'',subject_id:'',status:''}; applyFilters()">
            <select v-model="filterForm.class_id" @change="applyFilters" style="width:160px;">
                <option value="">All Classes</option>
                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="filterForm.status" @change="applyFilters" style="width:150px;">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="closed">Closed</option>
            </select>
        </FilterBar>

        <!-- Assignments Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="assignment in assignments.data" :key="assignment.id"
                 class="card hover:shadow-md transition-shadow flex flex-col">
                <div class="card-body flex flex-col flex-1">
                    <div class="flex justify-between items-start mb-2">
                        <span :class="['badge', statusBadgeClass(assignment)]">
                            {{ statusLabel(assignment) }}
                        </span>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                            Due: {{ formatDate(assignment.due_date) }}
                        </span>
                    </div>

                    <h3 class="text-base font-bold text-slate-800 mb-1 truncate">{{ assignment.title }}</h3>
                    <p class="text-xs text-slate-500 mb-4">
                        {{ assignment.course_class?.name }} — {{ assignment.subject?.name }}
                    </p>

                    <div class="flex items-center justify-between mt-auto pt-4 border-t border-slate-100">
                        <div class="text-xs text-slate-600">
                            <strong>{{ assignment.submissions_count ?? 0 }}</strong> Submissions
                        </div>
                        <div class="flex items-center gap-2">
                            <button v-if="can('create_academic')"
                                    @click.prevent="duplicateAssignment(assignment)"
                                    class="text-xs text-slate-400 hover:text-indigo-600" title="Duplicate as draft">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </button>
                            <Link :href="route('school.academic.assignments.show', assignment.id)"
                                  class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                                View →
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="assignments.data.length === 0" class="col-span-full card py-16 text-center">
                <svg class="w-12 h-12 mx-auto text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-slate-500">No assignments found for the selected filters.</p>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="assignments.last_page > 1" class="flex justify-center gap-2 mt-6">
            <Button v-for="page in assignments.links" :key="page.label"
                  as="link" variant="tab" size="sm"
                  :active="page.active"
                  :disabled="!page.url"
                  :href="page.url || '#'"
                  v-html="page.label" />
        </div>

        <!-- ── Create Modal ── -->
        <div v-if="showCreateModal"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="card-header sticky top-0 bg-white z-10 flex justify-between items-center">
                    <h3 class="card-title">New Assignment</h3>
                    <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="submit" class="card-body space-y-4">
                    <div class="form-row-2">
                        <div class="form-field">
                            <label>Class <span class="text-red-500">*</span></label>
                            <select v-model="form.class_id" required>
                                <option value="">Select Class</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p v-if="form.errors.class_id" class="field-error">{{ form.errors.class_id }}</p>
                        </div>
                        <div class="form-field">
                            <label>Sections</label>
                            <div class="flex flex-wrap gap-x-4 gap-y-2 p-3 bg-slate-50 border border-slate-200 rounded-lg"
                                 :class="{'opacity-50 pointer-events-none': !form.class_id}">
                                <label v-for="s in sections" :key="s.id" class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" :value="s.id" v-model="form.section_ids"
                                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4" />
                                    <span class="text-xs font-medium text-slate-700">{{ s.name }}</span>
                                </label>
                                <div v-if="sections.length === 0 && form.class_id" class="text-xs text-slate-400 italic">No sections.</div>
                                <div v-if="!form.class_id" class="text-xs text-slate-400 italic">Select a class first.</div>
                            </div>
                            <p v-if="form.errors.section_ids" class="field-error">{{ form.errors.section_ids }}</p>
                        </div>
                    </div>

                    <div class="form-field">
                        <label>Subject <span class="text-red-500">*</span></label>
                        <select v-model="form.subject_id"
                                :disabled="!form.class_id"
                                required>
                            <option value="">Select Subject</option>
                            <option v-for="s in availableSubjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <p v-if="form.errors.subject_id" class="field-error">{{ form.errors.subject_id }}</p>
                    </div>

                    <div class="form-field">
                        <label>Title <span class="text-red-500">*</span></label>
                        <input type="text" v-model="form.title" placeholder="e.g. Quadratic Equations Practice" required />
                        <p v-if="form.errors.title" class="field-error">{{ form.errors.title }}</p>
                    </div>

                    <div class="form-field">
                        <label>Instructions / Description</label>
                        <textarea v-model="form.description" rows="4" placeholder="Describe what students need to do..."></textarea>
                        <p v-if="form.errors.description" class="field-error">{{ form.errors.description }}</p>
                    </div>

                    <div class="form-row-2">
                        <div class="form-field">
                            <label>Due Date <span class="text-red-500">*</span></label>
                            <input type="date" v-model="form.due_date" required />
                            <p v-if="form.errors.due_date" class="field-error">{{ form.errors.due_date }}</p>
                        </div>
                        <div class="form-field">
                            <label>Max Marks</label>
                            <input type="number" v-model="form.max_marks" min="1" required />
                            <p v-if="form.errors.max_marks" class="field-error">{{ form.errors.max_marks }}</p>
                        </div>
                    </div>

                    <div class="form-field">
                        <label>Publish Status</label>
                        <select v-model="form.status">
                            <option value="draft">Save as Draft</option>
                            <option value="published">Publish Now</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label>Attachments</label>
                        <input type="file" multiple @change="handleFiles"
                               accept=".pdf,.ppt,.pptx,.doc,.docx,.mp4,.mov,.avi,.jpg,.jpeg,.png,.gif" />
                        <p class="text-xs text-slate-400 mt-1">Allowed: PDF, PPT, DOC, MP4, MOV, AVI, JPG, PNG, GIF</p>
                        <p v-if="form.errors.attachments" class="field-error">{{ form.errors.attachments }}</p>
                    </div>

                    <div class="flex gap-3 pt-4 border-t border-slate-100">
                        <Button variant="secondary" type="button" @click="showCreateModal = false" class="flex-1">Cancel</Button>
                        <Button type="submit" :loading="form.processing" class="flex-1">
                            Create Assignment
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
