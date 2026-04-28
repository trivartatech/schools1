<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    categories: Array,
});

const form = useForm({
    name: '',
    description: '',
});

const isEditing = ref(false);
const editingId = ref(null);

const saveCategory = () => {
    if (isEditing.value) {
        form.put(route('school.expense-categories.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
        });
    } else {
        form.post(route('school.expense-categories.store'), {
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
        });
    }
};

const editCategory = (cat) => {
    isEditing.value = true;
    editingId.value = cat.id;
    form.name = cat.name;
    form.description = cat.description || '';
};

const cancelEdit = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
};
</script>

<template>
    <SchoolLayout>
        <PageHeader>
            <template #title>
                <h1 class="text-2xl font-bold text-gray-900">Expense Categories</h1>
            </template>
            <template #subtitle>
                <p class="text-sm text-gray-500 mt-1">Manage general expense types (e.g., Office Supplies, Maintenance)</p>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Form -->
            <div class="md:col-span-1">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">{{ isEditing ? 'Edit Category' : 'Add Category' }}</h2>
                    </div>
                    <form @submit.prevent="saveCategory" class="card-body space-y-4">
                        <div class="form-field">
                            <label>Category Name <span class="text-red-500">*</span></label>
                            <input type="text" v-model="form.name" required class="w-full" placeholder="e.g. Utilities" />
                            <div v-if="form.errors.name" class="form-error">{{ form.errors.name }}</div>
                        </div>

                        <div class="form-field">
                            <label>Description</label>
                            <textarea v-model="form.description" class="w-full" rows="3" placeholder="Optional description..."></textarea>
                            <div v-if="form.errors.description" class="form-error">{{ form.errors.description }}</div>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <Button type="submit" :loading="form.processing" class="w-full">
                                {{ isEditing ? 'Update' : 'Save' }}
                            </Button>
                            <Button variant="secondary" v-if="isEditing" type="button" @click="cancelEdit" class="w-full">
                                Cancel
                            </Button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- List -->
            <div class="md:col-span-2">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Existing Categories</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <Table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="cat in categories" :key="cat.id">
                                    <td class="font-medium text-gray-900">{{ cat.name }}</td>
                                    <td class="text-gray-500 text-sm truncate max-w-xs">{{ cat.description || '-' }}</td>
                                    <td class="text-right">
                                        <button @click="editCategory(cat)" class="text-blue-600 hover:text-blue-800 text-sm font-medium mr-3">Edit</button>
                                    </td>
                                </tr>
                                <tr v-if="categories.length === 0">
                                    <td colspan="3" class="text-center py-6 text-gray-500">No categories found. Add your first expense category.</td>
                                </tr>
                            </tbody>
                        </Table>
                    </div>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>
