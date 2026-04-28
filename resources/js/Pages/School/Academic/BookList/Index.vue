<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref, computed, watch } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import ExportDropdown from '@/Components/ExportDropdown.vue';
import axios from 'axios';
import Table from '@/Components/ui/Table.vue';
import { useConfirm } from '@/Composables/useConfirm';
import FilterBar from '@/Components/ui/FilterBar.vue';

const confirm = useConfirm();

const props = defineProps({
    books:   Array,
    classes: Array,
    filters: Object,
});

const { can } = usePermissions();

// ── Filter ───────────────────────────────────────────────
const filterClassId = ref(props.filters?.class_id || '');

const applyFilter = () => {
    router.get(route('school.academic.book-list.index'), { class_id: filterClassId.value }, { preserveState: true });
};

// ── Dynamic subjects for selected class (form) ───────────
const showAddModal      = ref(false);
const loadingSubjects   = ref(false);
const dynamicSubjects   = ref([]);

const form = useForm({
    class_id:   '',
    subject_id: '',
    book_name:  '',
    publisher:  '',
    author:     '',
    isbn:       '',
});

// When class changes in the form, fetch subjects for that class
watch(() => form.class_id, async (classId) => {
    form.subject_id  = '';
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
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
            dynamicSubjects.value = [];
        },
    });
};

const deleteBook = async (id) => {
    const ok = await confirm({
        title: 'Remove book?',
        message: 'Remove this book from the list?',
        confirmLabel: 'Remove',
        danger: true,
    });
    if (!ok) return;
    router.delete(route('school.academic.book-list.destroy', id));
};

// ── CSV Export ───────────────────────────────────────────
const exportCsv = () => {
    const params = filterClassId.value ? `?class_id=${filterClassId.value}` : '';
    window.location.href = route('school.academic.book-list.export') + params;
};

// ── Grouped display ───────────────────────────────────────
const grouped = computed(() => {
    const map = {};
    props.books.forEach(b => {
        const key = b.course_class?.name ?? 'Unknown';
        if (!map[key]) map[key] = [];
        map[key].push(b);
    });
    return map;
});
</script>

<template>
    <SchoolLayout title="Book List Management">
        <PageHeader title="Book List" subtitle="Manage required textbooks per class">
            <template #actions>
                <ExportDropdown
                    base-url="/school/export/book-list"
                    :params="{ class_id: filterClassId }"
                />
                <Button v-if="can('create_academic')" as="link" :href="route('school.academic.book-list.create')">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Book
                </Button>

            </template>
        </PageHeader>

        <!-- Filter -->
        <FilterBar :active="!!filterClassId" @clear="filterClassId = ''; applyFilter()">
            <div class="form-field">
                <label>Class</label>
                <select v-model="filterClassId" @change="applyFilter" style="width:200px;">
                    <option value="">All Classes</option>
                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
        </FilterBar>

        <!-- Books grouped by class -->
        <div v-if="books.length > 0" class="space-y-6">
            <div v-for="(classBooks, className) in grouped" :key="className" class="card overflow-hidden">
                <div class="card-header">
                    <h3 class="section-heading">{{ className }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <Table>
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Book Name</th>
                                <th>Publisher</th>
                                <th>Author</th>
                                <th>ISBN</th>
                                <th v-if="can('delete_academic')" class="w-20 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="book in classBooks" :key="book.id">
                                <td class="text-slate-500 text-xs font-semibold">{{ book.subject?.name ?? '—' }}</td>
                                <td class="font-medium text-slate-800">{{ book.book_name }}</td>
                                <td>{{ book.publisher || '—' }}</td>
                                <td>{{ book.author || '—' }}</td>
                                <td class="text-xs text-slate-500">{{ book.isbn || '—' }}</td>
                                <td v-if="can('delete_academic')" class="text-right">
                                    <Button variant="danger" size="xs" @click="deleteBook(book.id)">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
            </div>
        </div>

        <div v-else class="card py-16 text-center">
            <svg class="w-12 h-12 mx-auto text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <p class="text-slate-500 font-medium">No books added to the list yet.</p>
        </div>

        <!-- ── Add Book Modal ── -->
        <div v-if="showAddModal"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
                <div class="card-header flex justify-between items-center">
                    <h3 class="card-title">Add Book to List</h3>
                    <button @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">×</button>
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
                            <label>Subject <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <select v-model="form.subject_id" required :disabled="!form.class_id || loadingSubjects">
                                    <option value="">
                                        {{ loadingSubjects ? 'Loading subjects...' : 'Select Subject' }}
                                    </option>
                                    <option v-for="s in dynamicSubjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                                <div v-if="loadingSubjects"
                                     class="absolute inset-y-0 right-8 flex items-center">
                                    <svg class="animate-spin w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                    </svg>
                                </div>
                            </div>
                            <p v-if="!form.class_id" class="text-xs text-slate-400 mt-1">Select a class to load subjects.</p>
                            <p v-if="form.errors.subject_id" class="field-error">{{ form.errors.subject_id }}</p>
                        </div>
                    </div>

                    <div class="form-field">
                        <label>Book Name <span class="text-red-500">*</span></label>
                        <input type="text" v-model="form.book_name" required
                               placeholder="e.g. Mathematics for Class X" />
                        <p v-if="form.errors.book_name" class="field-error">{{ form.errors.book_name }}</p>
                    </div>

                    <div class="form-field">
                        <label>Publisher</label>
                        <input type="text" v-model="form.publisher" placeholder="e.g. NCERT" />
                    </div>

                    <div class="form-row-2">
                        <div class="form-field">
                            <label>Author</label>
                            <input type="text" v-model="form.author" />
                        </div>
                        <div class="form-field">
                            <label>ISBN</label>
                            <input type="text" v-model="form.isbn" placeholder="978-..." />
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4 border-t border-slate-100">
                        <Button variant="secondary" type="button" @click="showAddModal = false" class="flex-1">Cancel</Button>
                        <Button type="submit" :loading="form.processing" class="flex-1">
                            Save Book
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
