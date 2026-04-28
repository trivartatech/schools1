<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { computed, watch } from 'vue';

const props = defineProps({
    courseClasses: Array,
});

const TYPES = [
    { value: 'pdf',   label: 'PDF' },
    { value: 'ppt',   label: 'Presentation (PPT)' },
    { value: 'doc',   label: 'Document (DOC)' },
    { value: 'video', label: 'Video' },
    { value: 'image', label: 'Image' },
    { value: 'link',  label: 'External Link' },
];

const form = useForm({
    class_id:     '',
    section_ids:  [],
    subject_id:   '',
    title:        '',
    description:  '',
    type:         'pdf',
    file:         null,
    external_url: '',
    chapter_name: '',
    is_published: true,
});

const selectedClass = computed(() =>
    props.courseClasses.find(c => c.id === parseInt(form.class_id))
);

const sections = computed(() =>
    selectedClass.value ? (selectedClass.value.sections || []) : []
);

const selectedSections = computed(() =>
    sections.value.filter(s => form.section_ids.includes(s.id))
);

const availableSubjects = computed(() => {
    if (!selectedClass.value) return [];
    const map = new Map();
    (selectedClass.value.subjects || []).forEach(s => map.set(s.id, s));
    selectedSections.value.forEach(sec =>
        (sec.subjects || []).forEach(s => map.set(s.id, s))
    );
    return Array.from(map.values());
});

watch(() => form.class_id, () => {
    form.section_ids = [];
    form.subject_id  = '';
});

watch(() => form.type, () => {
    form.file         = null;
    form.external_url = '';
});

const handleFile = (e) => {
    form.file = e.target.files[0] ?? null;
};

const submit = () => {
    form.post(route('school.academic.resources.store-material'), {
        forceFormData: true,
        onSuccess: () => router.visit(route('school.academic.resources.index')),
    });
};
</script>

<template>
    <SchoolLayout title="Upload Learning Material">
        <PageHeader title="Upload Learning Material" subtitle="Share files, videos, or links with students">
            <template #actions>
                <Button variant="secondary" as="link" :href="route('school.academic.resources.index')">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                Back to Resources
                            </Button>
            </template>
        </PageHeader>

        <div class="max-w-3xl">
            <form @submit.prevent="submit" class="card">
                <div class="card-body space-y-5">

                    <!-- Title + Type -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 font-semibold text-slate-700">Title <span class="text-red-500">*</span></label>
                            <input type="text" v-model="form.title" placeholder="e.g. Photosynthesis Notes" required />
                            <div v-if="form.errors.title" class="text-xs text-red-500 mt-1">{{ form.errors.title }}</div>
                        </div>

                        <div>
                            <label class="block mb-1 font-semibold text-slate-700">Material Type <span class="text-red-500">*</span></label>
                            <select v-model="form.type" required>
                                <option v-for="t in TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                            <div v-if="form.errors.type" class="text-xs text-red-500 mt-1">{{ form.errors.type }}</div>
                        </div>
                    </div>

                    <!-- Class + Sections -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 font-semibold text-slate-700">Target Class <span class="text-red-500">*</span></label>
                            <select v-model="form.class_id" required>
                                <option value="">Select Class</option>
                                <option v-for="c in courseClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
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

                    <!-- Subject + Chapter -->
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
                            <label class="block mb-1 font-semibold text-slate-700">Chapter / Unit (Optional)</label>
                            <input type="text" v-model="form.chapter_name" placeholder="e.g. Chapter 4 – Cell Biology" />
                            <div v-if="form.errors.chapter_name" class="text-xs text-red-500 mt-1">{{ form.errors.chapter_name }}</div>
                        </div>
                    </div>

                    <!-- File or External URL -->
                    <div v-if="form.type === 'link'">
                        <label class="block mb-1 font-semibold text-slate-700">External URL <span class="text-red-500">*</span></label>
                        <input type="url" v-model="form.external_url" placeholder="https://…" required />
                        <div v-if="form.errors.external_url" class="text-xs text-red-500 mt-1">{{ form.errors.external_url }}</div>
                    </div>
                    <div v-else>
                        <label class="block mb-1 font-semibold text-slate-700">Upload File <span class="text-red-500">*</span></label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-indigo-400 transition-colors cursor-pointer relative">
                            <input type="file" @change="handleFile" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                            <div class="space-y-1 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600 justify-center">
                                    <span class="font-medium text-indigo-600">Click to upload</span>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">PDF, PPT, DOC, MP4, JPG, PNG up to 20MB</p>
                            </div>
                        </div>
                        <div v-if="form.file" class="mt-2 text-xs text-slate-600 bg-slate-50 p-2 rounded">
                            {{ form.file.name }} ({{ (form.file.size / 1024 / 1024).toFixed(2) }} MB)
                        </div>
                        <div v-if="form.errors.file" class="text-xs text-red-500 mt-1">{{ form.errors.file }}</div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block mb-1 font-semibold text-slate-700">Description (Optional)</label>
                        <textarea v-model="form.description" rows="3" placeholder="Brief description of this material…"></textarea>
                        <div v-if="form.errors.description" class="text-xs text-red-500 mt-1">{{ form.errors.description }}</div>
                    </div>

                    <!-- Publish toggle -->
                    <div class="flex items-center gap-3">
                        <input type="checkbox" id="is_published" v-model="form.is_published" class="rounded border-gray-300 text-indigo-600 shadow-sm" />
                        <label for="is_published" class="text-sm font-medium text-slate-700 cursor-pointer">Publish immediately (visible to students)</label>
                    </div>

                    <div class="pt-4">
                        <Button type="submit" :loading="form.processing">
                            <span v-if="form.processing">Uploading…</span>
                            <span v-else>Upload Material</span>
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </SchoolLayout>
</template>
