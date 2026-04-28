<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    classes: Array,
});

const form = useForm({
    class_id:   '',
    subject_id: '',
    book_name:  '',
    publisher:  '',
    author:     '',
    isbn:       '',
});

const loadingSubjects = ref(false);
const dynamicSubjects = ref([]);

watch(() => form.class_id, async (classId) => {
    form.subject_id   = '';
    dynamicSubjects.value = [];
    if (!classId) return;
    loadingSubjects.value = true;
    try {
        const { data } = await axios.get(route('school.academic.book-list.subjects-for-class', classId));
        dynamicSubjects.value = data;
    } catch {
        dynamicSubjects.value = [];
    } finally {
        loadingSubjects.value = false;
    }
});

const submit = () => {
    form.post(route('school.academic.book-list.store'), {
        onSuccess: () => router.visit(route('school.academic.book-list.index')),
    });
};
</script>

<template>
    <SchoolLayout title="Add Book">
        <PageHeader title="Add Book to List" subtitle="Add a required or recommended book to a class's reading list">
            <template #actions>
                <Button variant="secondary" as="link" :href="route('school.academic.book-list.index')">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                Back to Book List
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
                            <select v-model="form.subject_id" required :disabled="!form.class_id || loadingSubjects">
                                <option value="">{{ loadingSubjects ? 'Loading…' : 'Select Subject' }}</option>
                                <option v-for="s in dynamicSubjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <div v-if="form.errors.subject_id" class="text-xs text-red-500 mt-1">{{ form.errors.subject_id }}</div>
                        </div>
                    </div>

                    <!-- Book Name -->
                    <div>
                        <label class="block mb-1 font-semibold text-slate-700">Book Name <span class="text-red-500">*</span></label>
                        <input type="text" v-model="form.book_name" placeholder="e.g. NCERT Mathematics Part I" required />
                        <div v-if="form.errors.book_name" class="text-xs text-red-500 mt-1">{{ form.errors.book_name }}</div>
                    </div>

                    <!-- Publisher + Author -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 font-semibold text-slate-700">Publisher</label>
                            <input type="text" v-model="form.publisher" placeholder="e.g. NCERT" />
                            <div v-if="form.errors.publisher" class="text-xs text-red-500 mt-1">{{ form.errors.publisher }}</div>
                        </div>

                        <div>
                            <label class="block mb-1 font-semibold text-slate-700">Author</label>
                            <input type="text" v-model="form.author" placeholder="e.g. R.D. Sharma" />
                            <div v-if="form.errors.author" class="text-xs text-red-500 mt-1">{{ form.errors.author }}</div>
                        </div>
                    </div>

                    <!-- ISBN -->
                    <div>
                        <label class="block mb-1 font-semibold text-slate-700">ISBN (Optional)</label>
                        <input type="text" v-model="form.isbn" placeholder="e.g. 978-81-7450-284-6" maxlength="20" />
                        <div v-if="form.errors.isbn" class="text-xs text-red-500 mt-1">{{ form.errors.isbn }}</div>
                    </div>

                    <div class="pt-4">
                        <Button type="submit" :loading="form.processing">
                            <span v-if="form.processing">Saving…</span>
                            <span v-else>Add Book</span>
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </SchoolLayout>
</template>
