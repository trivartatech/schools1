<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import axios from 'axios';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    papers:  Array,
    classes: Array,
    filters: Object,
});

const classId   = ref(props.filters?.class_id || '');
const subjectId = ref(props.filters?.subject_id || '');
const subjects  = ref([]);
const deleting  = ref(null);

async function loadSubjects() {
    if (!classId.value) { subjects.value = []; subjectId.value = ''; return; }
    try {
        const res = await axios.get('/school/question-papers/subjects', { params: { class_id: classId.value } });
        subjects.value = res.data;
    } catch (e) { subjects.value = []; }
}

watch(classId, () => { subjectId.value = ''; loadSubjects(); });

function applyFilter() {
    const params = {};
    if (classId.value) params.class_id = classId.value;
    if (subjectId.value) params.subject_id = subjectId.value;
    router.get('/school/question-papers', params, { preserveState: true });
}

function confirmDelete(paper) {
    if (!confirm(`Delete "${paper.title}"?`)) return;
    deleting.value = paper.id;
    router.delete(`/school/question-papers/${paper.id}`, {
        onFinish: () => { deleting.value = null; },
    });
}
</script>

<template>
    <SchoolLayout title="AI Question Papers">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">AI Question Paper Generator</h1>
                    <p class="text-sm text-gray-500 mt-1">Generate exam question papers using AI</p>
                </div>
                <a href="/school/question-papers/create"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Generate New Paper
                </a>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-sm border p-4">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="w-48">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Class</label>
                        <select v-model="classId" class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All Classes</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="w-48">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Subject</label>
                        <select v-model="subjectId" class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500" :disabled="!subjects.length">
                            <option value="">All Subjects</option>
                            <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <Button variant="secondary" @click="applyFilter">
                        Filter
                    </Button>
                </div>
            </div>

            <!-- Papers Table -->
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <table v-if="papers.length" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Marks</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="paper in papers" :key="paper.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ paper.title }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ paper.course_class?.name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ paper.subject?.name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ paper.total_marks }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ paper.duration_minutes }} min</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ new Date(paper.created_at).toLocaleDateString() }}</td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a :href="`/school/question-papers/${paper.id}`"
                                   class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">View</a>
                                <a :href="`/school/question-papers/${paper.id}/pdf`"
                                   target="_blank"
                                   class="text-green-600 hover:text-green-800 text-sm font-medium">PDF</a>
                                <button @click="confirmDelete(paper)"
                                        :disabled="deleting === paper.id"
                                        class="text-red-600 hover:text-red-800 text-sm font-medium disabled:opacity-50">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="p-12 text-center text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-lg font-medium">No question papers yet</p>
                    <p class="mt-1">Click "Generate New Paper" to create your first AI-generated question paper.</p>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>
