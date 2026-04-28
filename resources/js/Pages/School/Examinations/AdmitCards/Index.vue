<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useToast } from '@/Composables/useToast';
import Table from '@/Components/ui/Table.vue';

const toast = useToast();

const props = defineProps({
    schedules: Array,
});

// ─── Filter State ───────────────────────────────────────
const filters = ref({
    exam_schedule_id: '',
    course_class_id: '', // Used just for cascading UI, actual query uses schedule_id + section_id
    section_id: '',
});

const selectedSchedule = computed(() => {
    return props.schedules.find(s => s.id == filters.value.exam_schedule_id) || null;
});

const availableClasses = computed(() => {
    // Unique classes from available schedules
    const clss = [];
    props.schedules.forEach(s => {
        if (!clss.find(c => c.id === s.course_class_id)) {
            clss.push(s.course_class);
        }
    });
    return clss;
});

const availableSchedulesForClass = computed(() => {
    if (!filters.value.course_class_id) return [];
    return props.schedules.filter(s => s.course_class_id == filters.value.course_class_id);
});

const availableSections = computed(() => {
    if (!selectedSchedule.value) return [];
    return selectedSchedule.value.sections || [];
});

// ─── Data State ─────────────────────────────────────────
const students = ref([]);
const scheduleData = ref(null);
const loading = ref(false);
const printView = ref(false); // Toggle to show print layout vs filter table
const selectedStudentIds = ref([]);

// ─── Actions ────────────────────────────────────────────
function fetchStudents() {
    if (!filters.value.exam_schedule_id || !filters.value.section_id) return;
    
    loading.value = true;
    axios.post('/school/admit-cards/generate', {
        exam_schedule_id: filters.value.exam_schedule_id,
        section_id: filters.value.section_id
    }).then(res => {
        scheduleData.value = res.data.schedule;
        students.value = res.data.students;
        selectedStudentIds.value = students.value.map(s => s.id); // Default select all
    }).catch(e => {
        console.error("Failed to load students", e);
        toast.error('Failed to load data. Please check selections.');
    }).finally(() => {
        loading.value = false;
    });
}

function toggleStudent(id) {
    const idx = selectedStudentIds.value.indexOf(id);
    if (idx === -1) selectedStudentIds.value.push(id);
    else selectedStudentIds.value.splice(idx, 1);
}

function toggleAll() {
    if (selectedStudentIds.value.length === students.value.length) {
        selectedStudentIds.value = [];
    } else {
        selectedStudentIds.value = students.value.map(s => s.id);
    }
}

const selectedStudentsData = computed(() => {
    return students.value.filter(s => selectedStudentIds.value.includes(s.id));
});

function openPrintView() {
    if (selectedStudentIds.value.length === 0) { toast.warning('Select at least one student.'); return; }
    
    // Construct the URL with query parameters
    const queryParams = new URLSearchParams({
        exam_schedule_id: filters.value.exam_schedule_id,
        section_id: filters.value.section_id,
        student_ids: selectedStudentIds.value.join(',')
    }).toString();

    // Open target blank print view
    window.open(`/school/admit-cards/print?${queryParams}`, '_blank');
}

</script>

<template>
    <Head title="Admit Cards" />
    <SchoolLayout>
        <PageHeader title="Admit Cards" subtitle="Generate and print admit cards for exams." />

        <!-- ── Filters ── -->
        <div class="card mb-6">
            <div class="form-row form-row-4">
                <div class="form-field">
                    <label>Class</label>
                    <select v-model="filters.course_class_id" @change="filters.exam_schedule_id = ''; filters.section_id = ''; students = []">
                        <option value="">Select Class</option>
                        <option v-for="c in availableClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Exam Schedule</label>
                    <select v-model="filters.exam_schedule_id" @change="filters.section_id = ''; students = []" :disabled="!filters.course_class_id">
                        <option value="">Select Exam</option>
                        <option v-for="s in availableSchedulesForClass" :key="s.id" :value="s.id">{{ s.exam_type?.name }}</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Section</label>
                    <select v-model="filters.section_id" :disabled="!filters.exam_schedule_id" @change="students = []">
                        <option value="">Select Section</option>
                        <option v-for="sec in availableSections" :key="sec.id" :value="sec.id">{{ sec.name }}</option>
                    </select>
                </div>
                <div class="form-field flex items-end">
                    <Button type="button" @click="fetchStudents" :disabled="loading || !filters.section_id" class="w-full h-10">
                        {{ loading ? 'Loading...' : 'Fetch Students' }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- ── Student List ── -->
        <div class="card" v-if="students.length > 0">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-gray-800">Students ({{ selectedStudentIds.length }}/{{ students.length }} Selected)</h3>
                <Button type="button" @click="openPrintView" :disabled="selectedStudentIds.length === 0">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print Admit Cards
                </Button>
            </div>
            
            <div class="overflow-x-auto">
                <Table>
                    <thead>
                        <tr>
                            <th style="width: 50px;" class="text-center">
                                <input type="checkbox" :checked="selectedStudentIds.length === students.length && students.length > 0" @change="toggleAll" />
                            </th>
                            <th>Roll No</th>
                            <th>Student Name</th>
                            <th>Admission No</th>
                            <th>Father's Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="student in students" :key="student.id" class="cursor-pointer hover:bg-gray-50" @click="toggleStudent(student.id)">
                            <td class="text-center" @click.stop>
                                <input type="checkbox" :checked="selectedStudentIds.includes(student.id)" @change="toggleStudent(student.id)" />
                            </td>
                            <td class="font-medium text-gray-900">{{ student.roll_no || '-' }}</td>
                            <td class="font-semibold text-indigo-600">{{ student.first_name }} {{ student.last_name }}</td>
                            <td class="text-gray-500">{{ student.admission_no || '-' }}</td>
                            <td class="text-gray-500">{{ student.student_parent?.father_name || '-' }}</td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>
        <div v-else-if="scheduleData && students.length === 0" class="card text-center p-8 text-gray-500">
            No students found in this section.
        </div>
    </SchoolLayout>
</template>

<style scoped>
/* Scoped styles */
</style>
