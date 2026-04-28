<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useDelete } from '@/Composables/useDelete';

const props = defineProps({
    groups: Array,
});

// Group form
const showNewHead = ref(null); // group_id to show add-head form for
const editGroup  = useForm({ id: '', name: '', description: '' });

const newGroup   = useForm({ name: '', description: '' });

const saveGroup = () => {
    newGroup.post('/school/fee/groups', {
        preserveScroll: true,
        onSuccess: () => { newGroup.reset(); },
    });
};

const updateGroup = () => {
    editGroup.transform((data) => ({ ...data, _method: 'put' })).post(`/school/fee/groups/${editGroup.id}`, {
        preserveScroll: true,
        onSuccess: () => { editGroup.reset(); },
    });
};

const { del } = useDelete();

const deleteGroup = (id) => {
    del(`/school/fee/groups/${id}`, 'Delete this fee group and all its heads?');
};

// Head form
const newHead = useForm({ fee_group_id: '', name: '', short_code: '', is_taxable: false, gst_percent: 0 });

const addHead = (groupId) => {
    newHead.fee_group_id = groupId;
    newHead.post('/school/fee/heads', {
        preserveScroll: true,
        onSuccess: () => { newHead.reset(); showNewHead.value = null; },
    });
};

const deleteHead = (id) => {
    del(`/school/fee/heads/${id}`, 'Remove this fee head?');
};
</script>

<template>
    <SchoolLayout title="Fee Groups & Heads">
        <div class="max-w-5xl mx-auto space-y-6">

            <PageHeader title="Fee Groups &amp;amp; Heads" subtitle="Organize fee categories and specific line items">
                <template #actions>
                    <Button variant="secondary" as="a" href="/school/fee/structure">Fee Structure →</Button>
                    <Button as="a" href="/school/fee/collect">Collect Fee →</Button>

                </template>
            </PageHeader>

            <!-- Add Group -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Add Fee Group</h3>
                </div>
                <div class="card-body">
                    <div class="flex items-start gap-3">
                        <div class="flex-1">
                            <input v-model="newGroup.name" type="text" placeholder="e.g. Tuition, Transport, Hostel"
                                   class="form-field w-full">
                            <p v-if="newGroup.errors.name" class="form-error">{{ newGroup.errors.name }}</p>
                        </div>
                        <input v-model="newGroup.description" type="text" placeholder="Description (optional)"
                               class="form-field flex-1">
                        <Button @click="saveGroup" :loading="newGroup.processing">Add Group</Button>
                    </div>
                </div>
            </div>

            <!-- Groups list -->
            <div v-for="group in groups" :key="group.id" class="card overflow-hidden">

                <!-- Group header -->
                <div class="card-header">
                    <div v-if="editGroup.id === group.id" class="flex flex-col gap-1 flex-1">
                        <div class="flex items-center gap-2">
                            <input v-model="editGroup.name" class="form-field text-sm w-48">
                            <Button size="sm" @click="updateGroup" :loading="editGroup.processing">Save</Button>
                            <Button variant="secondary" size="sm" @click="editGroup.reset(); editGroup.clearErrors(); editGroup.id = ''">Cancel</Button>
                        </div>
                        <p v-if="editGroup.errors.name" class="form-error">{{ editGroup.errors.name }}</p>
                    </div>
                    <div v-else class="flex items-center gap-2">
                        <h3 class="card-title">{{ group.name }}</h3>
                        <span class="badge badge-gray">{{ group.fee_heads?.length || 0 }} heads</span>
                    </div>
                    <div class="flex items-center gap-2 ml-auto">
                        <Button variant="secondary" size="sm" @click="showNewHead = showNewHead === group.id ? null : group.id">
                            + Add Head
                        </Button>
                        <button @click="editGroup.id = group.id; editGroup.name = group.name; editGroup.description = group.description; editGroup.clearErrors()" class="p-1.5 text-gray-400 hover:text-blue-600 rounded-md">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.586-6.586a2 2 0 112.826 2.826L11.828 13.828H9V11z" /></svg>
                        </button>
                        <button @click="deleteGroup(group.id)" class="p-1.5 text-gray-400 hover:text-red-600 rounded-md">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Add head form -->
                <div v-if="showNewHead === group.id" class="px-5 py-4 bg-blue-50 border-b border-blue-100 flex flex-wrap items-end gap-3">
                    <div class="form-field">
                        <label class="text-blue-700">Head Name *</label>
                        <input v-model="newHead.name" type="text" placeholder="e.g. Tuition Fee">
                        <p v-if="newHead.errors.name" class="form-error">{{ newHead.errors.name }}</p>
                    </div>
                    <div class="form-field">
                        <label class="text-blue-700">Short Code</label>
                        <input v-model="newHead.short_code" type="text" placeholder="TF" maxlength="10" class="w-24">
                        <p v-if="newHead.errors.short_code" class="form-error">{{ newHead.errors.short_code }}</p>
                    </div>
                    <div class="flex items-center gap-2 mt-4">
                        <input v-model="newHead.is_taxable" type="checkbox" class="rounded" id="taxable">
                        <label for="taxable" class="text-xs text-blue-700">Taxable (GST)</label>
                    </div>
                    <div v-if="newHead.is_taxable" class="form-field">
                        <label class="text-blue-700">GST %</label>
                        <input v-model="newHead.gst_percent" type="number" step="0.01" min="0" max="28" class="w-20">
                    </div>
                    <Button @click="addHead(group.id)" :loading="newHead.processing">Add</Button>
                    <Button variant="secondary" @click="showNewHead = null; newHead.reset(); newHead.clearErrors()">Cancel</Button>
                </div>

                <!-- Heads list -->
                <div v-if="group.fee_heads && group.fee_heads.length">
                    <div v-for="head in group.fee_heads" :key="head.id"
                         class="flex items-center justify-between px-5 py-2.5 border-b border-gray-100 last:border-0 hover:bg-gray-50 group transition">
                        <div class="flex items-center gap-2">
                            <span v-if="head.short_code" class="badge badge-gray font-mono">{{ head.short_code }}</span>
                            <span class="text-sm font-medium text-gray-800">{{ head.name }}</span>
                            <span v-if="head.is_taxable" class="badge badge-amber">GST {{ head.gst_percent }}%</span>
                        </div>
                        <button @click="deleteHead(head.id)"
                                class="opacity-0 group-hover:opacity-100 p-1.5 text-gray-400 hover:text-red-600 rounded-md transition">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>
                <p v-else class="px-5 py-4 text-sm text-gray-400 text-center">No fee heads yet. Click "+ Add Head".</p>
            </div>

            <div v-if="!groups?.length" class="card text-center py-12 text-gray-400">
                No fee groups yet. Add one above.
            </div>
        </div>
    </SchoolLayout>
</template>
