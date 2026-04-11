<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    concessions:     Object,
    feeHeads:        Array,
    concessionTypes: Array,
    filters:         Object,
});

// ── Search ─────────────────────────────────────────────────────────────────
const search = ref(props.filters?.search ?? '');
let searchTimer = null;
watch(search, (v) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => router.get('/school/fee/concessions', { search: v }, { preserveState: true }), 350);
});

// ── Add / Edit modal ────────────────────────────────────────────────────────
const showModal = ref(false);
const editing   = ref(null);

const blankForm = {
    student_search:         '',
    student_id:             '',
    student_label:          '',
    name:                   '',
    description:            '',
    type:                   'percentage',
    value:                  '',
    is_active:              true,
    is_one_time:            false,
};
const form = useForm({ ...blankForm });

const openAdd = () => {
    editing.value = null;
    form.reset();
    Object.assign(form, { ...blankForm });
    showModal.value = true;
};

const openEdit = (c) => {
    editing.value = c;
    form.student_id            = c.student?.id ?? '';
    form.student_label         = c.student ? `${c.student.first_name} ${c.student.last_name ?? ''} — ${c.student.admission_no}` : '';
    form.name                  = c.name;
    form.description           = c.description ?? '';
    form.type                  = c.type;
    form.value                 = c.value;
    form.is_active             = c.is_active;
    form.is_one_time           = c.is_one_time;
    showModal.value = true;
};

// Auto-fill description based on preloaded types
watch(() => form.name, (newName) => {
    if (editing.value) return; // Don't auto-fill while editing
    const foundType = props.concessionTypes?.find(t => t.name === newName);
    if (foundType && !form.description) {
        form.description = foundType.description || '';
    }
});

const save = () => {
    if (editing.value) {
        form.put(`/school/fee/concessions/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; },
        });
    } else {
        form.post('/school/fee/concessions', {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    }
};

const toggleActive = (c) => {
    router.patch(`/school/fee/concessions/${c.id}/toggle`, {}, { preserveScroll: true });
};
const destroy = (c) => {
    if (!confirm(`Delete concession "${c.name}" for ${c.student?.first_name}?`)) return;
    router.delete(`/school/fee/concessions/${c.id}`, { preserveScroll: true });
};

// ── Student search (type-ahead) ─────────────────────────────────────────────
const studentResults = ref([]);
let stuTimer = null;
const onStudentInput = async (e) => {
    const q = e.target.value;
    form.student_search = q;
    form.student_id = '';
    form.student_label = '';
    clearTimeout(stuTimer);
    if (q.length < 2) { studentResults.value = []; return; }
    stuTimer = setTimeout(async () => {
        try {
            const r = await fetch(`/school/students/search?q=${encodeURIComponent(q)}`);
            if (r.ok) { 
                studentResults.value = await r.json(); 
            }
        } catch (err) {
            console.error('Search failed', err);
        }
    }, 300);
};
const pickStudent = (s) => {
    form.student_id    = s.id;
    form.student_label = `${s.first_name} ${s.last_name ?? ''} — ${s.admission_no}`;
    form.student_search = form.student_label;
    studentResults.value = [];
};

// ── Preview computed ────────────────────────────────────────────────────────
const previewLabel = computed(() => {
    if (!form.value) return '';
    return form.type === 'percentage' ? `${form.value}% off fee amount` : `₹${form.value} flat discount`;
});

// Type badge
const typeBadge = (c) => c.type === 'percentage'
    ? { text: `${c.value}%`, cls: 'bg-blue-100 text-blue-700' }
    : { text: `₹${parseFloat(c.value).toLocaleString()}`, cls: 'bg-green-100 text-green-700' };
</script>

<template>
    <Head title="Fee Concessions" />
    <SchoolLayout>
        <div class="max-w-6xl mx-auto space-y-6">

            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-header-title">Fee Concessions</h1>
                    <p class="page-header-sub">Assign discounts &amp; scholarships to students</p>
                </div>
                <div class="flex gap-2">
                    <Button variant="secondary" as="link" href="/school/fee/concession-types">
                        Manage Preset Types
                    </Button>
                    <Button @click="openAdd" class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Concession
                    </Button>
                </div>
            </div>

            <!-- Search -->
            <div class="card">
                <div class="card-body">
                    <input v-model="search" type="text" placeholder="Search student name, admission no, or concession name…"
                           class="form-field w-full">
                </div>
            </div>

            <!-- Table -->
            <div class="card overflow-hidden">
                <Table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Concession</th>
                            <th>Discount</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!concessions.data || concessions.data.length === 0">
                            <td colspan="6" class="py-12 text-center text-gray-400">
                                <div class="text-3xl mb-2">🎟️</div>
                                <p>No concessions found. Add one to get started.</p>
                            </td>
                        </tr>
                        <tr v-for="c in concessions.data" :key="c.id">
                            <td>
                                <p class="font-medium text-gray-900">{{ c.student?.first_name }} {{ c.student?.last_name }}</p>
                                <p class="text-xs text-gray-400">{{ c.student?.admission_no }}</p>
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <p class="font-medium text-gray-800">{{ c.name }}</p>
                                    <span v-if="c.is_one_time" class="badge badge-amber">One-time</span>
                                </div>
                                <p v-if="c.description" class="text-xs text-gray-500 truncate max-w-xs">{{ c.description }}</p>
                            </td>
                            <td>
                                <span :class="typeBadge(c).cls" class="badge">
                                    {{ typeBadge(c).text }}
                                </span>
                            </td>
                            <td>
                                <button @click="toggleActive(c)"
                                        :class="c.is_active ? 'badge-green' : 'badge-gray'"
                                        class="badge transition">
                                    {{ c.is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Button variant="secondary" size="xs" @click="openEdit(c)">Edit</Button>
                                    <Button variant="danger" size="xs" @click="destroy(c)">Delete</Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </Table>

                <!-- Pagination -->
                <div v-if="concessions.last_page > 1" class="flex justify-end gap-2 p-4 border-t">
                    <Link v-for="link in concessions.links" :key="link.label"
                          :href="link.url ?? '#'"
                          :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                          class="px-3 py-1.5 text-xs rounded-lg border border-gray-200 transition"
                          v-html="link.label" />
                </div>
            </div>
        </div>

        <!-- Slide-over panel -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-if="showModal" class="fixed inset-0 z-50 bg-gray-900/50 backdrop-blur-sm" @click="showModal = false"></div>
            </Transition>

            <Transition
                enter-active-class="transform transition ease-out duration-300 sm:duration-500"
                enter-from-class="translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transform transition ease-in duration-200 sm:duration-500"
                leave-from-class="translate-x-0"
                leave-to-class="translate-x-full">

                <div v-if="showModal" class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-white shadow-2xl flex flex-col">
                    <div class="card-header flex-shrink-0 border-b px-5 py-4">
                        <h2 class="card-title">{{ editing ? 'Edit Concession' : 'Add Concession' }}</h2>
                        <Button variant="secondary" @click="showModal = false">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </Button>
                    </div>

                    <form @submit.prevent="save" class="flex flex-col flex-1 overflow-hidden">
                        <div class="flex-1 overflow-y-auto p-6 space-y-5">
                            <!-- Student Search (only when adding) -->
                            <div v-if="!editing" class="relative">
                                <div class="form-field">
                                    <label>Student <span class="text-red-500">*</span></label>
                                    <input :value="form.student_search" @input="onStudentInput"
                                           type="text" placeholder="Type name or admission no…">
                                </div>
                                <div v-if="studentResults.length" class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                    <button v-for="s in studentResults" :key="s.id" type="button"
                                            @click="pickStudent(s)"
                                            class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 flex flex-col gap-0.5">
                                        <span class="font-medium text-gray-900">{{ s.first_name }} {{ s.last_name }}</span>
                                        <div class="flex items-center gap-2 text-gray-500 text-xs">
                                            <span class="font-semibold text-indigo-600">{{ s.admission_no }}</span>
                                            <span v-if="s.class_section" class="badge badge-gray">{{ s.class_section }}</span>
                                        </div>
                                    </button>
                                </div>
                                <p v-if="form.errors.student_id" class="form-error">{{ form.errors.student_id }}</p>
                                <p v-if="form.student_id" class="mt-1 text-xs text-green-600 font-medium">✓ {{ form.student_label }}</p>
                            </div>
                            <div v-else class="text-sm text-gray-600 bg-gray-50 rounded-lg p-3">
                                Editing concession for: <strong>{{ editing.student?.first_name }} {{ editing.student?.last_name }}</strong>
                            </div>

                            <!-- Name (with datalist) -->
                            <div class="form-field">
                                <label>Concession Name <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" list="concession-types-list" placeholder="Select or type e.g. Sibling Discount…">
                                <datalist id="concession-types-list">
                                    <option v-for="t in concessionTypes" :key="t.id" :value="t.name"></option>
                                </datalist>
                                <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                            </div>

                            <!-- Description -->
                            <div class="form-field">
                                <label>Description / Reason</label>
                                <textarea v-model="form.description" rows="2" placeholder="Brief reason for this concession…" class="form-field"></textarea>
                            </div>

                            <!-- Type + Value -->
                            <div class="form-row-2">
                                <div class="form-field">
                                    <label>Discount Type <span class="text-red-500">*</span></label>
                                    <select v-model="form.type">
                                        <option value="percentage">Percentage (%)</option>
                                        <option value="fixed">Fixed Amount (₹)</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label>
                                        Value <span class="text-red-500">*</span>
                                        <span class="text-gray-400 text-xs ml-1">({{ form.type === 'percentage' ? 'max 100' : '₹ flat' }})</span>
                                    </label>
                                    <input v-model="form.value" type="number" step="0.01" :max="form.type === 'percentage' ? 100 : undefined" min="0.01"
                                           placeholder="e.g. 25 or 5000">
                                    <p v-if="form.errors.value" class="form-error">{{ form.errors.value }}</p>
                                </div>
                            </div>

                            <!-- Live preview -->
                            <div v-if="form.value" class="rounded-lg bg-indigo-50 border border-indigo-200 px-3 py-2 text-sm text-indigo-700 font-medium">
                                🎟️ This concession will apply <strong>{{ previewLabel }}</strong>
                            </div>

                            <!-- Status & Type toggles -->
                            <div class="grid grid-cols-2 gap-4 mt-2">
                                <div class="flex items-center gap-3 bg-gray-50 border border-gray-100 rounded-lg p-3">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input v-model="form.is_active" type="checkbox" class="sr-only peer">
                                        <div class="w-9 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:bg-indigo-600 transition text-[10px]"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-4 h-4 rounded-full shadow transition peer-checked:translate-x-4"></div>
                                    </label>
                                    <span class="text-xs font-semibold text-gray-700">Active</span>
                                </div>
                                <div class="flex items-center gap-3 bg-gray-50 border border-gray-100 rounded-lg p-3">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input v-model="form.is_one_time" type="checkbox" class="sr-only peer">
                                        <div class="w-9 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-amber-300 rounded-full peer peer-checked:bg-amber-600 transition text-[10px]"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-4 h-4 rounded-full shadow transition peer-checked:translate-x-4"></div>
                                    </label>
                                    <span class="text-xs font-semibold text-gray-700">One-Time Only</span>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex justify-end gap-3 p-5 border-t bg-gray-50 flex-shrink-0">
                            <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                            <Button type="submit" :loading="form.processing">
                                {{ (editing ? 'Update Concession' : 'Add Concession') }}
                            </Button>
                        </div>
                    </form>
                </div>
            </Transition>
        </Teleport>
    </SchoolLayout>
</template>
