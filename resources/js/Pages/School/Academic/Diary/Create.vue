<script setup>
import Button from '@/Components/ui/Button.vue';
import { useForm, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    classes: Array,
});

const form = useForm({
    class_id: '',
    section_ids: [], // Array for multi-section selection
    subject_id: '',
    date: new Date().toISOString().split('T')[0],
    content: '',
    attachments: [],
});

const selectedClass = computed(() => {
    return props.classes.find(c => c.id === parseInt(form.class_id));
});

const sections = computed(() => {
    return selectedClass.value ? selectedClass.value.sections : [];
});

const selectedSections = computed(() => {
    return sections.value.filter(s => form.section_ids.includes(s.id));
});

const availableSubjects = computed(() => {
    const subjectMap = new Map();
    
    // Add class-level subjects
    if (selectedClass.value?.subjects) {
        selectedClass.value.subjects.forEach(s => subjectMap.set(s.id, s));
    }
    
    // Add section-level subjects from ALL selected sections
    selectedSections.value.forEach(section => {
        if (section.subjects) {
            section.subjects.forEach(s => subjectMap.set(s.id, s));
        }
    });
    
    return Array.from(subjectMap.values());
});

const handleFileChange = (e) => {
    form.attachments = Array.from(e.target.files);
};

const submit = () => {
    form.post(route('school.academic.diary.store'), {
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <SchoolLayout title="New Diary Entry">
        <div class="page-header">
            <div>
                <h2 class="page-header-title">New Diary Entry</h2>
                <p class="page-header-sub">Record homework or class activities for students</p>
            </div>
            <Button variant="secondary" as="link" :href="route('school.academic.diary.index')">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to List
            </Button>
        </div>

        <div class="max-w-3xl">
            <form @submit.prevent="submit" class="card">
                <div class="card-body space-y-5">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1">Target Class <span class="text-red-500">*</span></label>
                            <select v-model="form.class_id" required>
                                <option value="">Select Class</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <div v-if="form.errors.class_id" class="text-xs text-red-500 mt-1">{{ form.errors.class_id }}</div>
                        </div>

                        <div>
                            <label class="block mb-1 font-semibold text-slate-700">Sections (Select Multiple) <span class="text-red-500">*</span></label>
                            <div class="flex flex-wrap gap-x-6 gap-y-3 p-4 bg-slate-50 border border-slate-200 rounded-lg" :class="{'opacity-50 cursor-not-allowed': !form.class_id}">
                                <label v-for="s in sections" :key="s.id" class="flex items-center gap-2 cursor-pointer group">
                                    <input type="checkbox" :value="s.id" v-model="form.section_ids" :disabled="!form.class_id" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4.5 h-4.5" />
                                    <span class="text-sm font-medium text-slate-700 group-hover:text-indigo-600 transition-colors">{{ s.name }}</span>
                                </label>
                                <div v-if="sections.length === 0 && form.class_id" class="text-xs text-slate-400 italic">No sections found for this class.</div>
                                <div v-if="!form.class_id" class="text-xs text-slate-400 italic">Please select a class first.</div>
                            </div>
                            <div v-if="form.errors.section_ids" class="text-xs text-red-500 mt-1">{{ form.errors.section_ids }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 font-semibold text-slate-700">Subject <span class="text-red-500">*</span></label>
                            <select v-model="form.subject_id" required :disabled="!form.class_id || (sections.length > 0 && form.section_ids.length === 0)">
                                <option value="">Select Subject</option>
                                <option v-for="s in availableSubjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <div v-if="form.errors.subject_id" class="text-xs text-red-500 mt-1">{{ form.errors.subject_id }}</div>
                        </div>

                        <div>
                            <label class="block mb-1">Date <span class="text-red-500">*</span></label>
                            <input type="date" v-model="form.date" required />
                            <div v-if="form.errors.date" class="text-xs text-red-500 mt-1">{{ form.errors.date }}</div>
                        </div>
                    </div>

                    <div>
                        <label class="block mb-1">Note / Homework Content <span class="text-red-500">*</span></label>
                        <textarea v-model="form.content" rows="6" placeholder="Write classwork, homework, or important notes here..." required></textarea>
                        <div v-if="form.errors.content" class="text-xs text-red-500 mt-1">{{ form.errors.content }}</div>
                    </div>

                    <div>
                        <label class="block mb-1">Attachments (Optional)</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-indigo-400 transition-colors cursor-pointer relative">
                            <input type="file" multiple @change="handleFileChange" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                            <div class="space-y-1 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600">
                                    <span class="relative font-medium text-indigo-600">Upload files</span>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">PDF, PNG, JPG up to 10MB each</p>
                            </div>
                        </div>
                        <div v-if="form.attachments.length > 0" class="mt-3 space-y-2">
                            <div v-for="(file, index) in form.attachments" :key="index" class="flex items-center gap-2 text-xs text-slate-600 bg-slate-50 p-2 rounded">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                {{ file.name }} ({{ (file.size / 1024 / 1024).toFixed(2) }} MB)
                            </div>
                        </div>
                        <div v-if="form.errors.attachments" class="text-xs text-red-500 mt-1">{{ form.errors.attachments }}</div>
                    </div>

                    <div class="pt-4 flex items-center gap-3">
                        <Button type="submit" :loading="form.processing">
                            <span v-if="form.processing">Saving...</span>
                            <span v-else>Post to Diary</span>
                        </Button>
                        <label class="flex items-center gap-2 cursor-pointer ml-auto">
                            <input type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" />
                            <span class="text-xs font-semibold text-slate-600">Send WhatsApp Notification to Parents</span>
                        </label>
                    </div>
                </div>
            </form>
        </div>
    </SchoolLayout>
</template>
