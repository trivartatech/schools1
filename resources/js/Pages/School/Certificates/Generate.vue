<script setup>
import { ref, computed, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useClassSections } from '@/Composables/useClassSections';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    template: { type: Object, required: true },
    classes:  { type: Array,  required: true },
});

const selectedClass   = ref('');
const selectedSection = ref('');
const selectedStudent = ref('');

const customVals = ref(
    Object.fromEntries((props.template.custom_vars ?? []).map(v => [v.key, '']))
);

const today    = school.today();
const certDate = ref(today);

const { sections, isFetching: fetchingSections, fetchSections } = useClassSections();

// ── Students list ─────────────────────────────────────────────────
const students         = ref([]);
const fetchingStudents = ref(false);

const fetchStudents = async (classId, sectionId) => {
    if (!classId) { students.value = []; return; }
    fetchingStudents.value = true;
    try {
        const p = new URLSearchParams();
        p.set('class_id', classId);
        if (sectionId) p.set('section_id', sectionId);
        const res = await fetch(`/school/utility/students-quick?${p}`);
        students.value = await res.json();
    } catch { students.value = []; }
    finally { fetchingStudents.value = false; }
};

watch(selectedClass, val => {
    selectedSection.value = '';
    selectedStudent.value = '';
    students.value = [];
    fetchSections(val);
    if (val) fetchStudents(val, '');
});

watch(selectedSection, val => {
    selectedStudent.value = '';
    fetchStudents(selectedClass.value, val);
});

// ── Print URL ─────────────────────────────────────────────────────
const printUrl = computed(() => {
    const base = `/school/utility/certificates/${props.template.id}/print`;
    const p    = new URLSearchParams();
    if (selectedClass.value)   p.set('class_id',   selectedClass.value);
    if (selectedSection.value) p.set('section_id', selectedSection.value);
    if (selectedStudent.value) p.set('student_id', selectedStudent.value);
    if (certDate.value)        p.set('cert_date',  certDate.value);
    for (const [key, val] of Object.entries(customVals.value)) {
        if (val) p.set(key, val);
    }
    const qs = p.toString();
    return qs ? `${base}?${qs}` : base;
});

const generate = () => window.open(printUrl.value, '_blank');

const orientationLabel = props.template.orientation === 'portrait' ? 'Portrait A4' : 'Landscape A4';
</script>

<template>
    <Head :title="`Generate – ${template.name}`" />
    <SchoolLayout title="Generate Certificates">

        <div class="max-w-lg mx-auto">

            <a href="/school/utility/certificates"
               class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-5">
                ← Back to Templates
            </a>

            <!-- Template info -->
            <div class="bg-white border border-slate-200 rounded-xl p-5 mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center bg-amber-100 text-amber-600 text-2xl flex-shrink-0">🎓</div>
                    <div>
                        <h2 class="font-semibold text-slate-800">{{ template.name }}</h2>
                        <p class="text-xs text-slate-500 mt-0.5">{{ orientationLabel }}</p>
                    </div>
                    <a :href="`/school/utility/certificates/${template.id}/edit`"
                       class="ml-auto text-xs text-slate-400 hover:text-slate-600 border border-slate-200 rounded-lg px-3 py-1.5 transition-colors">
                        Edit Design
                    </a>
                </div>
            </div>

            <!-- Custom variables -->
            <div v-if="template.custom_vars?.length" class="bg-white border border-slate-200 rounded-xl p-5 mb-5 space-y-4">
                <h3 class="text-sm font-semibold text-slate-700">Certificate Details</h3>
                <div v-for="v in template.custom_vars" :key="v.key">
                    <label class="block text-xs font-medium text-slate-600 mb-1">
                        {{ v.label }}
                        <code class="ml-1 text-slate-400 font-normal bg-slate-100 px-1 rounded">{{'{'}}{{ v.key }}{{'}'}}</code>
                    </label>
                    <input v-model="customVals[v.key]" type="text" :placeholder="v.placeholder || `Enter ${v.label}…`"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
            </div>

            <!-- Date + Filters -->
            <div class="bg-white border border-slate-200 rounded-xl p-5 space-y-4">
                <h3 class="text-sm font-semibold text-slate-700">Date &amp; Students</h3>

                <!-- Date -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Certificate Date</label>
                    <input v-model="certDate" type="date"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>

                <!-- Class -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">
                        Class <span class="text-slate-400 font-normal">(leave blank for all)</span>
                    </label>
                    <select v-model="selectedClass"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All classes</option>
                        <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                    </select>
                </div>

                <!-- Section -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Section</label>
                    <select v-model="selectedSection"
                            :disabled="!selectedClass || fetchingSections || !sections.length"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-slate-50 disabled:text-slate-400">
                        <option value="">All sections</option>
                        <option v-for="sec in sections" :key="sec.id" :value="sec.id">{{ sec.name }}</option>
                    </select>
                </div>

                <!-- Specific student -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">
                        Specific Student <span class="text-slate-400 font-normal">(optional)</span>
                    </label>
                    <select v-model="selectedStudent"
                            :disabled="!selectedClass || fetchingStudents"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-slate-50 disabled:text-slate-400">
                        <option value="">All students{{ students.length ? ` (${students.length})` : '' }}</option>
                        <option v-for="s in students" :key="s.id" :value="s.id">
                            {{ s.name }} — {{ s.admission_no }}
                        </option>
                    </select>
                    <p v-if="fetchingStudents" class="text-xs text-slate-400 mt-1">Loading students…</p>
                    <p v-else-if="selectedClass && !students.length && !fetchingStudents"
                       class="text-xs text-slate-400 mt-1">No active students found.</p>
                </div>
            </div>

            <button @click="generate"
                    class="w-full mt-4 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm rounded-xl transition-colors">
                Generate &amp; Print Certificates
            </button>
            <p class="text-xs text-slate-400 text-center mt-2">Opens in a new tab &bull; One A4 per student</p>

        </div>
    </SchoolLayout>
</template>
