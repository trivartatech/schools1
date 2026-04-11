<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    types: Array,
});

// ── Add / Edit modal ────────────────────────────────────────────────────────
const showModal = ref(false);
const editing   = ref(null);

const blankForm = {
    name:        '',
    description: '',
    is_active:   true,
};
const form = useForm({ ...blankForm });

const openAdd = () => {
    editing.value = null;
    form.reset();
    Object.assign(form, { ...blankForm });
    showModal.value = true;
};

const openEdit = (t) => {
    editing.value = t;
    form.name        = t.name;
    form.description = t.description ?? '';
    form.is_active   = t.is_active;
    showModal.value = true;
};

const save = () => {
    if (editing.value) {
        form.put(`/school/fee/concession-types/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; },
        });
    } else {
        form.post('/school/fee/concession-types', {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    }
};

const toggleActive = (t) => {
    router.patch(`/school/fee/concession-types/${t.id}/toggle`, {}, { preserveScroll: true });
};

const destroy = (t) => {
    if (!confirm(`Delete concession type "${t.name}"?`)) return;
    router.delete(`/school/fee/concession-types/${t.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Concession Types" />
    <SchoolLayout>
        <div class="max-w-4xl mx-auto space-y-6">

            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-header-title">Predefined Concession Types</h1>
                    <p class="page-header-sub">Manage the list of pre-configured concession names and defaults.</p>
                </div>
                <div class="flex gap-2">
                    <Button variant="secondary" as="link" href="/school/fee/concessions">
                        &larr; Back to Concessions
                    </Button>
                    <Button @click="openAdd" class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Type
                    </Button>
                </div>
            </div>

            <!-- Table -->
            <div class="card overflow-hidden">
                <Table>
                    <thead>
                        <tr>
                            <th>Type Name</th>
                            <th>Default Description</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!types || types.length === 0">
                            <td colspan="4" class="py-12 text-center text-gray-400">
                                <div class="text-3xl mb-2">🔖</div>
                                <p>No concession types found. Add one to get started.</p>
                            </td>
                        </tr>
                        <tr v-for="t in types" :key="t.id">
                            <td class="font-medium text-gray-800">{{ t.name }}</td>
                            <td class="text-gray-500 text-xs max-w-sm truncate">{{ t.description || '—' }}</td>
                            <td>
                                <button @click="toggleActive(t)"
                                        :class="t.is_active ? 'badge-green' : 'badge-gray'"
                                        class="badge transition">
                                    {{ t.is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Button variant="secondary" size="xs" @click="openEdit(t)">Edit</Button>
                                    <Button variant="danger" size="xs" @click="destroy(t)">Delete</Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <!-- Add / Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="card w-full max-w-md shadow-2xl">
                <div class="card-header">
                    <h2 class="card-title">{{ editing ? 'Edit Type' : 'Add Concession Type' }}</h2>
                    <button @click="showModal = false" class="p-1 text-gray-400 hover:text-gray-600 rounded">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="save" class="card-body space-y-4">
                    <div class="form-field">
                        <label>Type Name <span class="text-red-500">*</span></label>
                        <input v-model="form.name" type="text" placeholder="e.g. Sibling Discount…">
                        <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                    </div>

                    <div class="form-field">
                        <label>Default Description</label>
                        <textarea v-model="form.description" rows="2" placeholder="Brief reason, will auto-fill in concession form…" class="form-field"></textarea>
                    </div>

                    <div class="flex items-center gap-3 bg-gray-50 border border-gray-100 rounded-lg p-4">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input v-model="form.is_active" type="checkbox" class="sr-only peer">
                            <div class="w-10 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:bg-indigo-600 transition"></div>
                            <div class="absolute left-0.5 top-0.5 bg-white w-4 h-4 rounded-full shadow transition peer-checked:translate-x-5"></div>
                        </label>
                        <span class="text-sm text-gray-700 font-medium">Available in dropdown</span>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                        <Button type="submit" :loading="form.processing">
                            Save Type
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </SchoolLayout>
</template>
