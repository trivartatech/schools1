<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';

const confirm = useConfirm();
const toast = useToast();

const props = defineProps({
    terms: Array
});

const isFormOpen = ref(false);
const isEditing = ref(false);

const form = useForm({
    id: null,
    name: '',
    display_name: '',
    sort_order: null,
});

const openCreateForm = () => {
    isEditing.value = false;
    form.reset();
    isFormOpen.value = true;
};

const editTerm = (t) => {
    isEditing.value = true;
    form.id = t.id;
    form.name = t.name;
    form.display_name = t.display_name;
    form.sort_order = t.sort_order;
    isFormOpen.value = true;
};

const closeForm = () => {
    isFormOpen.value = false;
    form.reset();
};

const submit = () => {
    if (form.processing) return; // guard against double-submit
    if (isEditing.value) {
        form.put(`/school/exam-terms/${form.id}`, {
            onSuccess: () => closeForm(),
            onError: () => toast.error('Please fix the highlighted fields and try again.'),
        });
    } else {
        form.post('/school/exam-terms', {
            onSuccess: () => closeForm(),
            onError: () => toast.error('Please fix the highlighted fields and try again.'),
        });
    }
};

const deleteTerm = async (id) => {
    const ok = await confirm({
        title: 'Delete Exam Term?',
        message: 'This cannot be undone.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/exam-terms/${id}`, {
        preserveScroll: true,
        onError: () => toast.error('Could not delete exam term.'),
    });
};

// ── Drag-to-reorder ──────────────────────────────────────────────────────
// Local mirror of the prop so the table updates instantly while the POST
// is in flight. Re-syncs whenever the parent prop changes (e.g. after the
// server reload that follows the reorder POST).
const localTerms = ref([...props.terms]);
watch(() => props.terms, (val) => { localTerms.value = [...val]; });

const dragSrcId = ref(null);
const dragOverId = ref(null);

const onDragStart = (id, e) => {
    dragSrcId.value = id;
    e.dataTransfer.effectAllowed = 'move';
};
const onDragOver  = (id, e) => { e.preventDefault(); dragOverId.value = id; };
const onDragLeave = ()        => { dragOverId.value = null; };
const onDragEnd   = ()        => { dragSrcId.value = null; dragOverId.value = null; };

const onDrop = (targetId, e) => {
    e.preventDefault();
    const fromId = dragSrcId.value;
    dragSrcId.value = null;
    dragOverId.value = null;
    if (!fromId || fromId === targetId) return;

    const list = [...localTerms.value];
    const fromIdx = list.findIndex(t => t.id === fromId);
    const toIdx   = list.findIndex(t => t.id === targetId);
    if (fromIdx === -1 || toIdx === -1) return;

    const [moved] = list.splice(fromIdx, 1);
    list.splice(toIdx, 0, moved);
    localTerms.value = list;

    router.post('/school/exam-terms/reorder', {
        order: list.map(t => t.id),
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            toast.error('Could not save the new order.');
            localTerms.value = [...props.terms]; // roll back on failure
        },
    });
};
</script>

<template>
    <SchoolLayout title="Exam Terms">
        <PageHeader title="Exam Terms" subtitle="Manage academic examination terms (e.g. Term 1, Term 2)">
            <template #actions>
                <Button @click="openCreateForm">
                                <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Add Term
                            </Button>
            </template>
        </PageHeader>

        <div class="card">
            <div class="overflow-x-auto">
                <Table>
                    <thead>
                        <tr>
                            <th class="w-8"></th>
                            <th class="w-16">Order</th>
                            <th>Term Name</th>
                            <th>Display Name</th>
                            <th class="w-24 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="localTerms.length === 0">
                            <td colspan="5" class="text-center py-8 text-gray-500">No exam terms created yet.</td>
                        </tr>
                        <tr v-for="t in localTerms" :key="t.id"
                            draggable="true"
                            @dragstart="onDragStart(t.id, $event)"
                            @dragover="onDragOver(t.id, $event)"
                            @dragleave="onDragLeave"
                            @drop="onDrop(t.id, $event)"
                            @dragend="onDragEnd"
                            :class="{
                                'row-dragging': dragSrcId === t.id,
                                'row-drop-target': dragOverId === t.id && dragSrcId !== t.id,
                            }">
                            <td class="drag-handle" title="Drag to reorder">
                                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
                                </svg>
                            </td>
                            <td>
                                <span class="badge badge-blue">{{ t.sort_order ?? 0 }}</span>
                                <span class="text-gray-400 font-mono text-[10px] ml-1.5">#{{ t.id }}</span>
                            </td>
                            <td class="font-medium text-gray-900">{{ t.name }}</td>
                            <td class="text-gray-600">{{ t.display_name || '-' }}</td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="editTerm(t)" class="text-blue-600 hover:text-blue-800 p-1" title="Edit">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button @click="deleteTerm(t.id)" class="text-red-500 hover:text-red-700 p-1" title="Delete">
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
                    <h2 class="text-lg font-bold text-gray-800">{{ isEditing ? 'Edit Exam Term' : 'Add Exam Term' }}</h2>
                    <Button variant="secondary" @click="closeForm">
                        <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </Button>
                </div>

                <!-- Body -->
                <div class="p-6 flex-1 overflow-y-auto">
                    <form @submit.prevent="submit" class="flex flex-col gap-5">
                        <div class="form-field">
                            <label>Term Name <span class="text-red-500">*</span></label>
                            <input v-model="form.name" type="text" placeholder="e.g., Term 1" required />
                            <span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
                            <div class="text-xs text-gray-500 mt-1">Internal reference name for the term.</div>
                        </div>

                        <div class="form-field">
                            <label>Display Name</label>
                            <input v-model="form.display_name" type="text" placeholder="e.g., First Term Examination" />
                            <span v-if="form.errors.display_name" class="form-error">{{ form.errors.display_name }}</span>
                            <div class="text-xs text-gray-500 mt-1">How it should appear on report cards.</div>
                        </div>

                        <div class="form-field">
                            <label>Order</label>
                            <input v-model.number="form.sort_order" type="number" min="0" placeholder="Auto-assigned if blank" />
                            <span v-if="form.errors.sort_order" class="form-error">{{ form.errors.sort_order }}</span>
                            <div class="text-xs text-gray-500 mt-1">
                                Rank used to sort terms in lists (lowest first). Leave blank when adding to append at the end.
                            </div>
                        </div>

                        <div class="mt-6">
                            <Button type="submit" :loading="form.processing" class="w-full justify-center">
                                Save Exam Term
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

<style scoped>
.drag-handle {
    cursor: grab;
    user-select: none;
    text-align: center;
}
.drag-handle:active { cursor: grabbing; }

/* Visual cues during a drag */
.row-dragging    { opacity: 0.4; background: #f1f5f9 !important; }
.row-drop-target { background: #eef2ff !important; box-shadow: inset 0 -2px 0 #6366f1; }
</style>
