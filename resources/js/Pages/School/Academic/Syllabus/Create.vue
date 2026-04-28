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
    class_id:     '',
    subject_id:   '',
    chapter_name: '',
    topic_name:   '',
    sort_order:   1,
});

const selectedClass = computed(() =>
    props.classes.find(c => c.id === parseInt(form.class_id))
);

const availableSubjects = computed(() => {
    if (!selectedClass.value) return [];
    const map = new Map();
    (selectedClass.value.subjects || []).forEach(s => map.set(s.id, s));
    (selectedClass.value.sections || []).forEach(sec =>
        (sec.subjects || []).forEach(s => map.set(s.id, s))
    );
    return Array.from(map.values());
});

watch(() => form.class_id, () => { form.subject_id = ''; });

const submit = () => {
    form.post(route('school.academic.syllabus.store-topic'), {
        onSuccess: () => router.visit(route('school.academic.syllabus.index')),
    });
};
</script>

<template>
    <SchoolLayout title="Add Syllabus Topic">
        <PageHeader title="Add Syllabus Topic" subtitle="Add a new topic to a subject's syllabus">
            <template #actions>
                <Button variant="secondary" as="link" :href="route('school.academic.syllabus.index')">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                Back to Syllabus
                            </Button>
            </template>
        </PageHeader>

        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="card">
                <div class="card-body space-y-5">

                    <!-- Class + Subject -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 font-semibold text-slate-700">Class <span class="text-red-500">*</span></label>
                            <select v-model="form.class_id" required>
                                <option value="">Select Class</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <div v-if="form.errors.class_id" class="text-xs text-red-500 mt-1">{{ form.errors.class_id }}</div>
                        </div>

                        <div>
                            <label class="block mb-1 font-semibold text-slate-700">Subject <span class="text-red-500">*</span></label>
                            <select v-model="form.subject_id" required :disabled="!form.class_id">
                                <option value="">Select Subject</option>
                                <option v-for="s in availableSubjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <div v-if="form.errors.subject_id" class="text-xs text-red-500 mt-1">{{ form.errors.subject_id }}</div>
                        </div>
                    </div>

                    <!-- Chapter Name -->
                    <div>
                        <label class="block mb-1 font-semibold text-slate-700">Chapter Name <span class="text-red-500">*</span></label>
                        <input type="text" v-model="form.chapter_name" placeholder="e.g. Chapter 5 – Light and Optics" required />
                        <div v-if="form.errors.chapter_name" class="text-xs text-red-500 mt-1">{{ form.errors.chapter_name }}</div>
                    </div>

                    <!-- Topic Name -->
                    <div>
                        <label class="block mb-1 font-semibold text-slate-700">Topic Name <span class="text-red-500">*</span></label>
                        <input type="text" v-model="form.topic_name" placeholder="e.g. Reflection of Light" required />
                        <div v-if="form.errors.topic_name" class="text-xs text-red-500 mt-1">{{ form.errors.topic_name }}</div>
                    </div>

                    <!-- Sort Order -->
                    <div>
                        <label class="block mb-1 font-semibold text-slate-700">Sort Order</label>
                        <input type="number" v-model="form.sort_order" min="1" />
                        <p class="text-xs text-slate-400 mt-1">Lower numbers appear first in the syllabus list.</p>
                        <div v-if="form.errors.sort_order" class="text-xs text-red-500 mt-1">{{ form.errors.sort_order }}</div>
                    </div>

                    <div class="pt-4">
                        <Button type="submit" :loading="form.processing">
                            <span v-if="form.processing">Saving…</span>
                            <span v-else>Add Topic</span>
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </SchoolLayout>
</template>
