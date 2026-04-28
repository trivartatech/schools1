<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { computed, watch } from 'vue';

const props = defineProps({
    classes: Array,
});

const form = useForm({
    class_id:    '',
    section_ids: [],
    subject_id:  '',
    title:       '',
    description: '',
    due_date:    new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0],
    max_marks:   100,
    status:      'published',
    attachments: [],
});

const today = new Date().toISOString().split('T')[0];

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

watch(() => form.class_id, () => {
    form.section_ids = [];
    form.subject_id  = '';
});

const handleFiles = (e) => {
    form.attachments = Array.from(e.target.files);
};

const submit = () => {
    form.post(route('school.academic.assignments.store'), {
        forceFormData: true,
        onSuccess: () => router.visit(route('school.academic.assignments.index')),
    });
};
</script>

<template>
    <SchoolLayout title="New Assignment">
        <PageHeader title="New Assignment" subtitle="Create and assign homework to students">
            <template #actions>
                <Button variant="secondary" as="link" :href="route('school.academic.assignments.index')">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                Back to List
                            </Button>
            </template>
        </PageHeader>

        <div class="max-w-3xl">
            <form @submit.prevent="submit" class="card">
                <div class="card-body space-y-5">

                    <!-- Title -->
                    <div>
                        <label class="block mb-1 font-semibold text-slate-700">Title <span class="text-red-500">*</span></label>
                        <input type="text" v-model="form.title" placeholder="e.g. Chapter 3 – Practice Problems" required />
                        <div v-if="form.errors.title" class="text-xs text-red-500 mt-1">{{ form.errors.title }}</div>
                    </div>

                    <!-- Class + Sections -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 font-semibold text-slate-700">Target Class <span class="text-red-500">*</span></label>
                            <select v-model="form.class_id" required>
                                <option value="">Select Class</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <div v-if="form.errors.class_id" class="text-xs text-red-500 mt-1">{{ form.errors.class_id }}</div>
                        </div>

                        <div>
                            <label class="block mb-1 font-semibold text-slate-700">Sections <span class="text-red-500">*</span></label>
                            <div class="flex flex-wrap gap-x-6 gap-y-3 p-4 bg-slate-50 border border-slate-200 rounded-lg" :class="{'opacity-50 cursor-not-allowed': !form.class_id}">
                                <label v-for="s in sections" :key="s.id" class="flex items-center gap-2 cursor-pointer group">
                                    <input type="checkbox" :value="s.id" v-model="form.section_ids" :disabled="!form.class_id" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4.5 h-4.5" />
                                    <span class="text-sm font-medium text-slate-700 group-hover:text-indigo-600 transition-colors">{{ s.name }}</span>
                                </label>
                                <div v-if="sections.length === 0 && form.class_id" class="text-xs text-slate-400 italic">No sections for this class.</div>
                                <div v-if="!form.class_id" class="text-xs text-slate-400 italic">Select a class first.</div>
                            </div>
                            <div v-if="form.errors.section_ids" class="text-xs text-red-500 mt-1">{{ form.errors.section_ids }}</div>
                        </div>
                    </div>

                    <!-- Subject + Due Date -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 font-semibold text-slate-700">Subject <span class="text-red-500">*</span></label>
                            <select v-model="form.subject_id" required :disabled="!form.class_id">
                                <option value="">Select Subject</option>
                                <option v-for="s in availableSubjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <div v-if="form.errors.subject_id" class="text-xs text-red-500 mt-1">{{ form.errors.subject_id }}</div>
                        </div>

                        <div>
                            <label class="block mb-1 font-semibold text-slate-700">Due Date <span class="text-red-500">*</span></label>
                            <input type="date" v-model="form.due_date" :min="today" required />
                            <div v-if="form.errors.due_date" class="text-xs text-red-500 mt-1">{{ form.errors.due_date }}</div>
                        </div>
                    </div>

                    <!-- Max Marks + Status -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 font-semibold text-slate-700">Max Marks <span class="text-red-500">*</span></label>
                            <input type="number" v-model="form.max_marks" min="1" max="9999" required />
                            <div v-if="form.errors.max_marks" class="text-xs text-red-500 mt-1">{{ form.errors.max_marks }}</div>
                        </div>

                        <div>
                            <label class="block mb-1 font-semibold text-slate-700">Status</label>
                            <select v-model="form.status">
                                <option value="published">Published</option>
                                <option value="draft">Draft</option>
                            </select>
                            <div v-if="form.errors.status" class="text-xs text-red-500 mt-1">{{ form.errors.status }}</div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block mb-1 font-semibold text-slate-700">Description / Instructions</label>
                        <textarea v-model="form.description" rows="5" placeholder="Describe the assignment, instructions, or reference material…"></textarea>
                        <div v-if="form.errors.description" class="text-xs text-red-500 mt-1">{{ form.errors.description }}</div>
                    </div>

                    <!-- Attachments -->
                    <div>
                        <label class="block mb-1 font-semibold text-slate-700">Attachments (Optional)</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-indigo-400 transition-colors cursor-pointer relative">
                            <input type="file" multiple @change="handleFiles" accept=".pdf,.ppt,.pptx,.doc,.docx,.jpg,.jpeg,.png,.zip" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                            <div class="space-y-1 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600 justify-center">
                                    <span class="font-medium text-indigo-600">Upload files</span>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">PDF, PPT, DOC, PNG, JPG, ZIP up to 10MB each</p>
                            </div>
                        </div>
                        <div v-if="form.attachments.length > 0" class="mt-3 space-y-2">
                            <div v-for="(file, i) in form.attachments" :key="i" class="flex items-center gap-2 text-xs text-slate-600 bg-slate-50 p-2 rounded">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                {{ file.name }} ({{ (file.size / 1024 / 1024).toFixed(2) }} MB)
                            </div>
                        </div>
                        <div v-if="form.errors.attachments" class="text-xs text-red-500 mt-1">{{ form.errors.attachments }}</div>
                    </div>

                    <div class="pt-4">
                        <Button type="submit" :loading="form.processing">
                            <span v-if="form.processing">Saving…</span>
                            <span v-else>Create Assignment</span>
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </SchoolLayout>
</template>
