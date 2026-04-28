<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import axios from 'axios';
import { useToast } from '@/Composables/useToast';
import Table from '@/Components/ui/Table.vue';

const toast = useToast();

const props = defineProps({
    schedules:        { type: Array,  required: true },
    allowedMap:       { type: Object, default: null },
    sections:         { type: Array,  required: true },
    scheduleSubjects: { type: Array,  required: true },
});

// Selection State
const selectedScheduleId = ref('');
const selectedClassId = ref('');
const selectedSectionId = ref('');
const selectedScheduleSubjectId = ref('');

// Data State
const students = ref([]);
const marksMap = ref({});
const assessmentItems = ref([]);
const loadingStudents = ref(false);

const form = useForm({
    exam_schedule_id: '',
    section_id: '',
    exam_schedule_subject_id: '',
    marks: {} // marks[student_id][item_id] = {marks_obtained, is_absent, teacher_remarks}
});

// ── Dropdown Data Computations ──

// Classes allowed to be graded
const allowedClasses = computed(() => {
    if (!props.schedules) return [];

    // Admin / unrestricted: derive classes directly from schedules
    if (!props.allowedMap) {
        const map = new Map();
        for (const s of props.schedules) {
            if (s.course_class) map.set(s.course_class_id, s.course_class);
        }
        return Array.from(map.values());
    }

    // Teacher / restricted: only show classes present in allowedMap
    const classMap = new Map();
    for (const section of props.sections) {
        if (props.allowedMap[section.course_class_id] !== undefined) {
            classMap.set(section.course_class_id, {
                id: section.course_class_id,
                name: props.schedules.find(s => s.course_class_id == section.course_class_id)?.course_class?.name || section.course_class_id
            });
        }
    }
    return Array.from(classMap.values());
});

const allowedSections = computed(() => {
    if (!selectedClassId.value) return [];
    const sectionsForClass = props.sections.filter(s => s.course_class_id == selectedClassId.value);

    // Admin / unrestricted
    if (!props.allowedMap) return sectionsForClass;

    // Teacher / restricted
    const allowedMapForClass = props.allowedMap[selectedClassId.value];
    if (!allowedMapForClass) return [];
    return sectionsForClass.filter(sec => allowedMapForClass[sec.id] !== undefined);
});

const allowedSubjects = computed(() => {
    if (!selectedScheduleId.value || !selectedClassId.value || !selectedSectionId.value) return [];

    const subjectsForSchedule = props.scheduleSubjects.filter(ss => ss.exam_schedule_id == selectedScheduleId.value);

    // Admin / unrestricted: show all subjects for this schedule
    if (!props.allowedMap) return subjectsForSchedule;

    // Teacher / restricted: filter by RBAC
    const allowedMapForSection = props.allowedMap[selectedClassId.value]?.[selectedSectionId.value];
    if (allowedMapForSection === 'ALL') return subjectsForSchedule;
    if (Array.isArray(allowedMapForSection)) {
        return subjectsForSchedule.filter(ss => allowedMapForSection.includes(ss.subject_id));
    }
    return [];
});

const activeScheduleSchedules = computed(() => {
    if (selectedClassId.value) {
        return props.schedules.filter(s => s.course_class_id == selectedClassId.value);
    }
    return props.schedules;
});

// Watchers to clear downstream dropdowns
watch(selectedClassId, () => {
    selectedScheduleId.value = '';
    selectedSectionId.value = '';
    selectedScheduleSubjectId.value = '';
    clearTable();
});

watch(selectedScheduleId, () => {
    selectedScheduleSubjectId.value = '';
    clearTable();
});

watch(selectedSectionId, () => {
    selectedScheduleSubjectId.value = '';
    clearTable();
});

watch(selectedScheduleSubjectId, (newVal) => {
    if (newVal) {
        fetchStudents();
    } else {
        clearTable();
    }
});

const clearTable = () => {
    students.value = [];
    assessmentItems.value = [];
    marksMap.value = {};
    form.marks = {};
};

const fetchStudents = async () => {
    if (!selectedScheduleId.value || !selectedSectionId.value || !selectedScheduleSubjectId.value) return;

    loadingStudents.value = true;
    try {
        const response = await axios.get('/school/exam-marks/students', {
            params: {
                exam_schedule_id: selectedScheduleId.value,
                section_id: selectedSectionId.value,
                exam_schedule_subject_id: selectedScheduleSubjectId.value
            }
        });

        students.value = response.data.students;
        assessmentItems.value = response.data.assessmentItems;
        marksMap.value = response.data.marksMap || {};

        // Build the form.marks object structure
        const nextMarks = {};
        for (const student of students.value) {
            nextMarks[student.id] = {};
            for (const item of assessmentItems.value) {
                const existing = marksMap.value[student.id]?.[item.id] || {};
                nextMarks[student.id][item.id] = {
                    marks_obtained: existing.marks_obtained ?? '',
                    is_absent: existing.is_absent ?? false,
                    teacher_remarks: existing.teacher_remarks ?? ''
                };
            }
        }
        form.marks = nextMarks;

        form.exam_schedule_id = selectedScheduleId.value;
        form.section_id = selectedSectionId.value;
        form.exam_schedule_subject_id = selectedScheduleSubjectId.value;
    } catch (e) {
        console.error(e);
        toast.error('Failed to load students. See console for details.');
    } finally {
        loadingStudents.value = false;
    }
};

const saveMarks = () => {
    form.post('/school/exam-marks', {
        preserveScroll: true,
        onSuccess: () => {
            // Optional success notification logic here
        }
    });
};
</script>

<template>
    <Head title="Marks Entry" />
    <SchoolLayout title="Marks Entry">
        <PageHeader title="Marks Entry" subtitle="Enter scholastic and co-scholastic marks for assessing students.">
            <template #actions>
                <Button v-if="students.length > 0" @click="saveMarks" :disabled="form.processing">
                                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Save Marks
                            </Button>
            </template>
        </PageHeader>

        <!-- Filters Section -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <div class="form-row" style="grid-template-columns:repeat(4,1fr);">
                    <div class="form-field">
                        <label>Select Class *</label>
                        <select v-model="selectedClassId">
                            <option value="">-- Choose Class --</option>
                            <option v-for="cls in allowedClasses" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Select Exam *</label>
                        <select v-model="selectedScheduleId" :disabled="!selectedClassId">
                            <option value="">-- Choose Exam --</option>
                            <option v-for="sc in activeScheduleSchedules" :key="sc.id" :value="sc.id">{{ sc.exam_type?.name }}</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Select Section *</label>
                        <select v-model="selectedSectionId" :disabled="!selectedClassId">
                            <option value="">-- Choose Section --</option>
                            <option v-for="sec in allowedSections" :key="sec.id" :value="sec.id">{{ sec.name }}</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Select Subject *</label>
                        <select v-model="selectedScheduleSubjectId" :disabled="!selectedSectionId">
                            <option value="">-- Choose Subject --</option>
                            <option v-for="sub in allowedSubjects" :key="sub.id" :value="sub.id">{{ sub.subject?.name }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Marks Entry Table -->
        <div class="card" style="position:relative;">

            <div v-if="loadingStudents" style="position:absolute;inset:0;background:rgba(255,255,255,.8);z-index:10;display:flex;align-items:center;justify-content:center;">
                <div style="display:flex;align-items:center;color:#2563eb;font-weight:500;gap:8px;">
                    <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                    Loading Students...
                </div>
            </div>

            <div v-else-if="!selectedScheduleSubjectId" class="card-body" style="text-align:center;padding:48px;color:#94a3b8;">
                <svg class="w-12 h-12" style="color:#cbd5e1;margin:0 auto 12px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Select Class, Exam, Section, and Subject to enter marks.
            </div>

            <div v-else-if="students.length === 0" class="card-body" style="text-align:center;padding:48px;color:#94a3b8;">
                No students found in this section.
            </div>

            <div v-else-if="assessmentItems.length === 0" class="card-body" style="text-align:center;padding:48px;color:#ef4444;">
                No assessment items defined for this subject. Please configure the exam assessment scale first.
            </div>

            <div v-else style="overflow-x:auto;">
                <Table>
                    <thead>
                        <tr>
                            <th style="width:64px;text-align:center;">Roll No</th>
                            <th style="min-width:200px;">Student Name</th>
                            <th v-for="item in assessmentItems" :key="item.id" style="text-align:center;min-width:120px;">
                                <div style="font-weight:600;">{{ item.name }}</div>
                                <div style="font-size:.75rem;font-weight:400;color:#64748b;">Max: {{ item.max_marks }}</div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="student in students" :key="student.id">
                            <td style="text-align:center;font-weight:500;color:#475569;">{{ student.roll_no || '-' }}</td>
                            <td style="font-weight:500;">{{ student.first_name }} {{ student.last_name }}</td>

                            <td v-for="item in assessmentItems" :key="item.id" style="text-align:center;position:relative;padding:0;">
                                <div style="display:flex;align-items:center;justify-content:center;padding:8px;position:absolute;inset:0;">
                                    <template v-if="form.marks[student.id]?.[item.id]">
                                        <div style="display:flex;align-items:center;justify-content:center;margin-right:6px;">
                                            <input type="checkbox"
                                                v-model="form.marks[student.id][item.id].is_absent"
                                                style="width:16px;height:16px;cursor:pointer;"
                                                :aria-label="`Mark ${student.first_name} ${student.last_name} absent for ${item.name}`"
                                                @change="() => { if(form.marks[student.id][item.id].is_absent) form.marks[student.id][item.id].marks_obtained = ''; }"
                                            />
                                            <span v-if="form.marks[student.id][item.id].is_absent" style="font-size:.625rem;font-weight:700;color:#ef4444;margin-left:4px;text-transform:uppercase;">ABS</span>
                                        </div>
                                        <input type="number"
                                            v-model="form.marks[student.id][item.id].marks_obtained"
                                            style="width:72px;text-align:center;font-weight:600;border:1px solid #d1d5db;border-radius:6px;padding:4px 6px;font-size:.8125rem;"
                                            :style="form.marks[student.id][item.id].is_absent ? 'background:#fef2f2;color:#991b1b;' : ''"
                                            :placeholder="item.max_marks"
                                            :max="item.max_marks"
                                            min="0"
                                            step="0.1"
                                            :aria-label="`${student.first_name} ${student.last_name} — ${item.name} marks (max ${item.max_marks})`"
                                            :disabled="form.marks[student.id][item.id].is_absent"
                                            @input="() => {
                                                const val = parseFloat(form.marks[student.id][item.id].marks_obtained);
                                                const maxVal = parseFloat(item.max_marks);
                                                if (val > maxVal) {
                                                    form.marks[student.id][item.id].marks_obtained = maxVal;
                                                } else if (val < 0) {
                                                    form.marks[student.id][item.id].marks_obtained = 0;
                                                }
                                            }"
                                        />
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>

            <!-- Unsaved changes footer -->
            <div v-if="students.length > 0 && form.isDirty" style="background:#fefce8;border-top:1px solid #fde68a;padding:12px 24px;display:flex;justify-content:space-between;align-items:center;font-size:.875rem;color:#92400e;position:sticky;bottom:0;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    You have unsaved marks. Don't forget to save.
                </div>
                <Button @click="saveMarks" :loading="form.processing">
                    Save Marks
                </Button>
            </div>
        </div>
    </SchoolLayout>
</template>
