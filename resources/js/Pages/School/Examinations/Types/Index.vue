<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    types: Array,
    terms: Array
});

const isFormOpen = ref(false);
const isEditing = ref(false);

const form = useForm({
    id: null,
    exam_term_id: '',
    name: '',
    code: '',
    display_name: '',
    weightage: 100.00,
    classification: 'main'
});

const openCreateForm = () => {
    isEditing.value = false;
    form.reset();
    isFormOpen.value = true;
};

const editType = (t) => {
    isEditing.value = true;
    form.id = t.id;
    form.exam_term_id = t.exam_term_id;
    form.name = t.name;
    form.code = t.code;
    form.display_name = t.display_name;
    form.weightage = t.weightage;
    form.classification = t.classification;
    isFormOpen.value = true;
};

const closeForm = () => {
    isFormOpen.value = false;
    form.reset();
};

const submit = () => {
    if (isEditing.value) {
        form.put(`/school/exam-types/${form.id}`, {
            onSuccess: () => closeForm()
        });
    } else {
        form.post('/school/exam-types', {
            onSuccess: () => closeForm()
        });
    }
};

const deleteType = (id) => {
    if (confirm('Are you sure you want to delete this Exam Type?')) {
        router.delete(`/school/exam-types/${id}`);
    }
};

const getClassificationBadge = (val) => {
    const map = {
        'main': { label: 'Main Exam', class: 'badge-blue' },
        'periodic': { label: 'Periodic Test', class: 'badge-purple' },
        'unit_test': { label: 'Unit Test', class: 'badge-gray' }
    };
    return map[val] || { label: val, class: 'badge-gray' };
};
</script>

<template>
    <SchoolLayout title="Exam Types">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Exam Types</h1>
                <p class="page-header-sub">Manage exams within terms (e.g. Unit Test 1, Half Yearly)</p>
            </div>
            <Button @click="openCreateForm">
                <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Exam Type
            </Button>
        </div>

        <div v-if="terms.length === 0" class="mb-6 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded text-yellow-800 text-sm flex gap-3">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <p><strong>Warning:</strong> No Exam Terms found. You must create an Exam Term first before creating Exam Types.</p>
        </div>

        <div class="card">
            <div class="overflow-x-auto">
                <Table>
                    <thead>
                        <tr>
                            <th class="w-16">ID</th>
                            <th>Term</th>
                            <th>Exam Name</th>
                            <th>Code</th>
                            <th>Classification</th>
                            <th>Weightage</th>
                            <th class="w-24 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="types.length === 0">
                            <td colspan="7" class="text-center py-8 text-gray-500">No exam types created yet.</td>
                        </tr>
                        <tr v-for="t in types" :key="t.id">
                            <td class="text-gray-500 font-mono text-xs">#{{ t.id }}</td>
                            <td>
                                <span class="badge badge-gray">{{ t.exam_term?.name || 'Unknown' }}</span>
                            </td>
                            <td class="font-medium text-gray-900">
                                {{ t.name }}
                                <div v-if="t.display_name" class="text-xs text-gray-500 font-normal mt-0.5">Report Label: {{ t.display_name }}</div>
                            </td>
                            <td class="text-gray-600 font-mono text-xs">{{ t.code || '-' }}</td>
                            <td>
                                <span class="badge" :class="getClassificationBadge(t.classification).class">
                                    {{ getClassificationBadge(t.classification).label }}
                                </span>
                            </td>
                            <td class="font-medium text-gray-700">{{ t.weightage }}%</td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="editType(t)" class="text-blue-600 hover:text-blue-800 p-1" title="Edit">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button @click="deleteType(t.id)" class="text-red-500 hover:text-red-700 p-1" title="Delete">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <!-- Slide-over Panel -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <div v-if="isFormOpen" class="fixed inset-y-0 right-0 w-full md:w-[450px] bg-white shadow-2xl z-50 flex flex-col border-l border-gray-200 pt-16">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                    <h2 class="text-lg font-bold text-gray-800">{{ isEditing ? 'Edit Exam Type' : 'Add Exam Type' }}</h2>
                    <Button variant="secondary" @click="closeForm">
                        <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </Button>
                </div>

                <!-- Body -->
                <div class="p-6 flex-1 overflow-y-auto">
                    <form @submit.prevent="submit" class="flex flex-col gap-5">
                        <div class="form-field">
                            <label>Mapped Exam Term <span class="text-red-500">*</span></label>
                            <select v-model="form.exam_term_id" required>
                                <option value="" disabled>Select Term</option>
                                <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                            <span v-if="form.errors.exam_term_id" class="form-error">{{ form.errors.exam_term_id }}</span>
                        </div>

                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Exam Name <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" placeholder="e.g., Half Yearly" required />
                                <span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
                            </div>
                            <div class="form-field">
                                <label>Code</label>
                                <input v-model="form.code" type="text" placeholder="e.g., HY, UT1" />
                                <span v-if="form.errors.code" class="form-error">{{ form.errors.code }}</span>
                            </div>
                        </div>

                        <div class="form-field">
                            <label>Display Name</label>
                            <input v-model="form.display_name" type="text" placeholder="e.g., Mid Term Assessment" />
                            <span v-if="form.errors.display_name" class="form-error">{{ form.errors.display_name }}</span>
                            <div class="text-xs text-gray-500 mt-1">If empty, Exam Name will be used on reports.</div>
                        </div>

                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Classification <span class="text-red-500">*</span></label>
                                <select v-model="form.classification" required>
                                    <option value="main">Main Exam (Scholastic)</option>
                                    <option value="periodic">Periodic Test</option>
                                    <option value="unit_test">Unit Test</option>
                                </select>
                                <span v-if="form.errors.classification" class="form-error">{{ form.errors.classification }}</span>
                            </div>
                            <div class="form-field">
                                <label>Weightage (%) <span class="text-red-500">*</span></label>
                                <input v-model.number="form.weightage" type="number" step="0.01" min="0" max="100" required />
                                <span v-if="form.errors.weightage" class="form-error">{{ form.errors.weightage }}</span>
                            </div>
                        </div>

                        <div class="mt-6">
                            <Button type="submit" :loading="form.processing" class="w-full justify-center">
                                Save Exam Type
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>

        <!-- Backdrop -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-40"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-40"
            leave-to-class="opacity-0"
        >
            <div v-if="isFormOpen" class="fixed inset-0 bg-black z-40" @click="closeForm"></div>
        </Transition>
    </SchoolLayout>
</template>
