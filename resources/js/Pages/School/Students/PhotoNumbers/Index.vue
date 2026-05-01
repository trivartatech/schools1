<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Table from '@/Components/ui/Table.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useToast } from '@/Composables/useToast';
import axios from 'axios';

const toast = useToast();

const props = defineProps({
    academicYears: Array,
    classes:       Array,
    sections:      Array,
    students:      Array,
    filters:       Object,
});

// ── Filters ───────────────────────────────────────────────────
const filters = ref({
    academic_year_id: props.filters?.academic_year_id || '',
    class_id:         props.filters?.class_id         || '',
    section_id:       props.filters?.section_id       || '',
});

const applyFilters = () => {
    router.get(route('school.photo-numbers.index'), filters.value, { preserveState: true });
};

watch(() => filters.value.class_id, () => {
    filters.value.section_id = '';
    applyFilters();
});

// ── Editable rows ─────────────────────────────────────────────
const rows = ref(props.students.map(s => ({ ...s })));
const isDirty = ref(false);

watch(() => props.students, (val) => {
    rows.value = val.map(s => ({ ...s }));
    isDirty.value = false;
}, { deep: true });

const markDirty = () => { isDirty.value = true; };

// ── Show details toggle (persisted) ───────────────────────────
const showDetails = ref(true);
const STORAGE_KEY = 'photo_numbers_show_details';

onMounted(() => {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved !== null) showDetails.value = saved === '1';
});

watch(showDetails, (v) => {
    localStorage.setItem(STORAGE_KEY, v ? '1' : '0');
});

// ── Auto-assign ───────────────────────────────────────────────
const showAutoModal = ref(false);
const autoStart = ref(1);
const autoPad   = ref(3);
const autoSort  = ref('name');

const sortOptions = [
    { value: 'name',      label: 'Name (A → Z)' },
    { value: 'name_desc', label: 'Name (Z → A)' },
    { value: 'admission', label: 'Admission No' },
    { value: 'current',   label: 'Keep current order' },
];

const doAutoAssign = () => {
    const sorted = [...rows.value];
    const sortFn = {
        name:      (a, b) => a.name.localeCompare(b.name),
        name_desc: (a, b) => b.name.localeCompare(a.name),
        admission: (a, b) => (a.admission_no ?? '').localeCompare(b.admission_no ?? ''),
        current:   () => 0,
    }[autoSort.value] ?? ((a, b) => a.name.localeCompare(b.name));

    sorted.sort(sortFn);

    const pad = parseInt(autoPad.value) || 1;
    const start = parseInt(autoStart.value) || 1;
    sorted.forEach((s, i) => {
        s.photo_number = String(start + i).padStart(pad, '0');
    });

    rows.value = sorted;
    isDirty.value = true;
    showAutoModal.value = false;
};

// ── Duplicate detection ───────────────────────────────────────
const duplicatePhotoNumbers = computed(() => {
    const seen = new Map();
    rows.value.forEach((r, idx) => {
        const key = (r.photo_number ?? '').toString().trim();
        if (!key) return;
        if (!seen.has(key)) seen.set(key, []);
        seen.get(key).push(idx);
    });
    const dupes = new Set();
    seen.forEach((indices) => {
        if (indices.length > 1) indices.forEach(i => dupes.add(i));
    });
    return dupes;
});

const hasDuplicates = computed(() => duplicatePhotoNumbers.value.size > 0);

// ── Save photo numbers ────────────────────────────────────────
const isSaving = ref(false);

const save = () => {
    if (hasDuplicates.value) return;
    isSaving.value = true;
    router.post(route('school.photo-numbers.save'), {
        assignments: rows.value.map(r => ({
            student_id:   r.student_id,
            photo_number: r.photo_number || null,
        })),
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSaving.value = false;
            isDirty.value  = false;
        },
    });
};

const resetChanges = () => {
    rows.value = props.students.map(s => ({ ...s }));
    isDirty.value = false;
};

// ── Inline edit modal (queues an EditRequest) ─────────────────
const editModalOpen = ref(false);
const editingRowIdx = ref(null);
const editForm = ref({});
const editErrors = ref({});
const editingMessage = ref('');
const isSubmittingEdit = ref(false);

const openEdit = async (idx) => {
    const r = rows.value[idx];
    editingRowIdx.value = idx;
    editForm.value = {
        first_name:     r.first_name ?? '',
        last_name:      r.last_name ?? '',
        address:        r.student_address ?? '',
        primary_phone:  r.primary_phone ?? '',
        father_name:    r.father_name ?? '',
        mother_name:    r.mother_name ?? '',
        father_phone:   r.father_phone ?? '',
        mother_phone:   r.mother_phone ?? '',
        parent_address: r.parent_address ?? '',
        class_id:       r.class_id ?? '',
        section_id:     r.section_id ?? '',
        reason:         '',
    };
    editErrors.value = {};
    editingMessage.value = '';
    // Preload sections for the row's current class so the dropdown isn't empty
    // when the modal opens. The web filter bar already loads sections only for
    // the filtered class, but we may be editing a student in a class outside
    // that filter (e.g. after a class transfer is approved on another row).
    editSections.value = props.sections;
    if (r.class_id && (props.sections.length === 0 || props.sections[0]?.course_class_id !== r.class_id)) {
        await loadSectionsForEdit(r.class_id);
    }
    editModalOpen.value = true;
};

// Sections cache used by the edit modal's section dropdown.
const editSections = ref([]);
const loadSectionsForEdit = async (classId) => {
    if (!classId) {
        editSections.value = [];
        return;
    }
    try {
        // Existing endpoint returns the sections array directly, not wrapped.
        const { data } = await axios.get(route('school.classes.sections', classId));
        editSections.value = Array.isArray(data) ? data : (data.sections ?? []);
    } catch {
        // Fallback: at minimum keep the page-level filter sections so the user
        // can still pick a section (won't be filtered to the right class).
        editSections.value = props.sections;
    }
};

// Reload section list whenever the modal's class selection changes. Clear the
// chosen section so the user can't accidentally submit a section that doesn't
// belong to the new class (the backend would reject it anyway).
watch(() => editForm.value.class_id, (newClassId, oldClassId) => {
    if (!editModalOpen.value) return;
    if (newClassId !== oldClassId) {
        editForm.value.section_id = '';
        loadSectionsForEdit(newClassId);
    }
});

const submitEdit = async () => {
    if (editingRowIdx.value === null) return;
    const row = rows.value[editingRowIdx.value];
    isSubmittingEdit.value = true;
    editErrors.value = {};
    editingMessage.value = '';

    try {
        const { data } = await axios.post(
            route('school.photo-numbers.request-edit', row.student_id),
            editForm.value
        );
        const fieldCount = Object.keys(data.pending_changes ?? {}).length;
        rows.value[editingRowIdx.value].pending_changes_count =
            (rows.value[editingRowIdx.value].pending_changes_count ?? 0) + fieldCount;
        editModalOpen.value = false;
        toast.success(`${fieldCount} change${fieldCount === 1 ? '' : 's'} queued for approval.`);
    } catch (err) {
        if (err.response?.status === 422) {
            // Either field-level validation errors (Laravel `errors` shape) or
            // the controller's "no actual changes detected" message.
            if (err.response.data?.errors) {
                editErrors.value = err.response.data.errors;
            } else {
                editingMessage.value = err.response.data?.message ?? 'Validation failed.';
            }
        } else {
            toast.error(err.response?.data?.message ?? 'Could not submit change request.');
        }
    } finally {
        isSubmittingEdit.value = false;
    }
};

// ── Export pending (split button) ─────────────────────────────
const showExportMenu = ref(false);

const totalPending = computed(() =>
    rows.value.reduce((sum, r) => sum + (r.pending_changes_count ?? 0), 0)
);

const exportUrl = (format) => {
    const params = new URLSearchParams({ format });
    if (filters.value.class_id)   params.set('class_id',   filters.value.class_id);
    if (filters.value.section_id) params.set('section_id', filters.value.section_id);
    return `${route('school.photo-numbers.export-pending')}?${params.toString()}`;
};

const downloadExport = (format) => {
    showExportMenu.value = false;
    window.location.href = exportUrl(format);
};

// ── Export roster (current state, all editable fields) ─────────
const showRosterMenu = ref(false);

const rosterUrl = (format) => {
    const params = new URLSearchParams({ format });
    if (filters.value.academic_year_id) params.set('academic_year_id', filters.value.academic_year_id);
    if (filters.value.class_id)         params.set('class_id',         filters.value.class_id);
    if (filters.value.section_id)       params.set('section_id',       filters.value.section_id);
    return `${route('school.photo-numbers.export-roster')}?${params.toString()}`;
};

const downloadRoster = (format) => {
    showRosterMenu.value = false;
    window.location.href = rosterUrl(format);
};

const closeAllExportMenus = (e) => {
    if (!e.target.closest('.export-split-button')) showExportMenu.value = false;
    if (!e.target.closest('.roster-split-button')) showRosterMenu.value = false;
};

onMounted(() => document.addEventListener('click', closeAllExportMenus));
onBeforeUnmount(() => document.removeEventListener('click', closeAllExportMenus));

// ── Photographer credential management ───────────────────────
const photographerModalOpen = ref(false);
const photographerCredential = ref({
    configured: false,
    username: null,
    created_at: null,
});
const photographerLastGenerated = ref(null); // { username, password } shown ONCE
const isLoadingCredential = ref(false);
const isMutatingCredential = ref(false);

const loadPhotographerCredential = async () => {
    isLoadingCredential.value = true;
    try {
        const { data } = await axios.get(route('school.photo-numbers.photographer-credential.show'));
        photographerCredential.value = data;
    } catch {
        toast.error('Could not load photographer credential.');
    } finally {
        isLoadingCredential.value = false;
    }
};

const openPhotographerModal = async () => {
    photographerLastGenerated.value = null;
    photographerModalOpen.value = true;
    await loadPhotographerCredential();
};

const generatePhotographerCredential = async () => {
    if (photographerCredential.value.configured) {
        if (!confirm('Regenerating will revoke the existing photographer login. Anyone using the old credentials will be signed out. Continue?')) {
            return;
        }
    }
    isMutatingCredential.value = true;
    try {
        const { data } = await axios.post(route('school.photo-numbers.photographer-credential.generate'));
        photographerLastGenerated.value = data;
        await loadPhotographerCredential();
        toast.success('Photographer login generated. Copy it now — the password is shown only once.');
    } catch {
        toast.error('Could not generate photographer login.');
    } finally {
        isMutatingCredential.value = false;
    }
};

const clearPhotographerCredential = async () => {
    if (!confirm('Clear the photographer login? Anyone using these credentials will be signed out. You can generate a new one later.')) {
        return;
    }
    isMutatingCredential.value = true;
    try {
        await axios.delete(route('school.photo-numbers.photographer-credential.clear'));
        photographerLastGenerated.value = null;
        await loadPhotographerCredential();
        toast.success('Photographer login cleared.');
    } catch {
        toast.error('Could not clear photographer login.');
    } finally {
        isMutatingCredential.value = false;
    }
};

const copyToClipboard = async (text, label) => {
    try {
        await navigator.clipboard.writeText(text);
        toast.success(`${label} copied.`);
    } catch {
        toast.error('Could not copy. Long-press to select instead.');
    }
};

// Eager-load credential state on mount so the header dot is accurate without
// waiting for the user to open the modal.
onMounted(() => {
    loadPhotographerCredential();
});

// ── Helpers ───────────────────────────────────────────────────
const classLabel = computed(() => {
    const c = props.classes.find(c => c.id === parseInt(filters.value.class_id));
    return c?.name ?? '';
});
const sectionLabel = computed(() => {
    const s = props.sections.find(s => s.id === parseInt(filters.value.section_id));
    return s?.name ?? '';
});
const yearLabel = computed(() => {
    const y = props.academicYears.find(y => y.id === parseInt(filters.value.academic_year_id));
    return y?.name ?? '';
});
</script>

<template>
    <SchoolLayout title="Photo Numbers">
        <!-- ── Unsaved-changes sticky bar ── -->
        <div v-if="isDirty"
             class="fixed top-0 left-0 right-0 z-50 bg-amber-500 text-white flex items-center justify-between px-6 py-2.5 shadow-lg text-sm font-semibold">
            <span>⚠ You have unsaved photo numbers</span>
            <div class="flex gap-3">
                <button @click="resetChanges" class="bg-white/20 hover:bg-white/30 px-3 py-1 rounded-lg transition">Discard</button>
                <Button variant="secondary" @click="save" :disabled="hasDuplicates || isSaving" class="text-amber-700">
                    {{ isSaving ? 'Saving…' : 'Save All' }}
                </Button>
            </div>
        </div>

        <div :class="isDirty ? 'mt-12' : ''">
            <PageHeader
                title="Photo Numbers"
                subtitle="Record the photographer's sequential photo number against each student during an ID-card session.">
                <template #actions>
                    <!-- Always available — photographer login can be generated even when no class is selected. -->
                    <Button variant="secondary" @click="openPhotographerModal" class="!flex items-center gap-1.5">
                        <span :class="['w-2 h-2 rounded-full inline-block',
                                       photographerCredential.configured ? 'bg-emerald-500' : 'bg-slate-300']"></span>
                        Photographer Login
                    </Button>

                    <template v-if="rows.length > 0">
                        <label class="flex items-center gap-2 text-xs text-slate-600 select-none cursor-pointer mr-2">
                            <input type="checkbox" v-model="showDetails" class="accent-indigo-600">
                            Show details
                        </label>

                        <Button variant="secondary" @click="showAutoModal = true">⚡ Auto Assign</Button>

                        <!-- Export roster split button (current state with all editable fields) -->
                        <div class="roster-split-button relative inline-flex">
                            <Button variant="secondary" @click="downloadRoster('xlsx')">
                                📋 Export roster
                            </Button>
                            <button type="button"
                                    class="ml-1 px-2 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 text-xs"
                                    @click.stop="showRosterMenu = !showRosterMenu">
                                ▼
                            </button>
                            <div v-if="showRosterMenu"
                                 class="absolute right-0 top-full mt-1 w-44 bg-white border border-slate-200 rounded-lg shadow-lg z-30 overflow-hidden">
                                <button @click="downloadRoster('xlsx')" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 flex items-center gap-2">
                                    <span>📊</span> Export as Excel
                                </button>
                                <button @click="downloadRoster('pdf')" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 flex items-center gap-2 border-t border-slate-100">
                                    <span>📄</span> Export as PDF
                                </button>
                            </div>
                        </div>

                        <!-- Export pending split button (only pending edit-requests, for approval review) -->
                        <div class="export-split-button relative inline-flex">
                            <Button variant="secondary"
                                    :disabled="totalPending === 0"
                                    @click="downloadExport('xlsx')">
                                ⬇ Export pending
                                <span v-if="totalPending > 0" class="ml-1.5 inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold">
                                    {{ totalPending }}
                                </span>
                            </Button>
                            <button type="button"
                                    :disabled="totalPending === 0"
                                    class="ml-1 px-2 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 text-xs disabled:opacity-50 disabled:cursor-not-allowed"
                                    @click.stop="showExportMenu = !showExportMenu">
                                ▼
                            </button>
                            <div v-if="showExportMenu"
                                 class="absolute right-0 top-full mt-1 w-44 bg-white border border-slate-200 rounded-lg shadow-lg z-30 overflow-hidden">
                                <button @click="downloadExport('xlsx')" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 flex items-center gap-2">
                                    <span>📊</span> Export as Excel
                                </button>
                                <button @click="downloadExport('pdf')" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 flex items-center gap-2 border-t border-slate-100">
                                    <span>📄</span> Export as PDF
                                </button>
                            </div>
                        </div>

                        <Button @click="save" :disabled="!isDirty || hasDuplicates || isSaving">
                            {{ isSaving ? 'Saving…' : '💾 Save' }}
                        </Button>
                    </template>
                </template>
            </PageHeader>

            <!-- ── Filters ── -->
            <FilterBar :active="!!(filters.academic_year_id || filters.class_id || filters.section_id)" @clear="filters.academic_year_id = ''; filters.class_id = ''; filters.section_id = ''; applyFilters()">
                <div class="form-field">
                    <label>Academic Year</label>
                    <select v-model="filters.academic_year_id" @change="applyFilters" style="width:180px;">
                        <option value="">— Select year —</option>
                        <option v-for="y in academicYears" :key="y.id" :value="y.id">
                            {{ y.name }}{{ y.is_current ? ' (Current)' : '' }}
                        </option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Class</label>
                    <select v-model="filters.class_id" :disabled="!filters.academic_year_id" style="width:160px;">
                        <option value="">— Select class —</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Section</label>
                    <select v-model="filters.section_id" @change="applyFilters"
                            :disabled="!filters.class_id || sections.length === 0" style="width:140px;">
                        <option value="">All Sections</option>
                        <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
            </FilterBar>

            <!-- ── No class selected ── -->
            <div v-if="!filters.class_id" class="card">
                <EmptyState
                    title="Select a class to begin"
                    description="Pick an academic year and class above to start assigning photo numbers." />
            </div>

            <!-- ── Empty result ── -->
            <div v-else-if="rows.length === 0" class="card">
                <EmptyState
                    title="No students found"
                    description="No students are enrolled in this class/section for the selected academic year." />
            </div>

            <!-- ── Student Table ── -->
            <div v-else class="card overflow-hidden">
                <!-- Header stats -->
                <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 bg-slate-50">
                    <div class="text-sm font-semibold text-slate-700">
                        {{ yearLabel }} · {{ classLabel }}{{ sectionLabel ? ' / ' + sectionLabel : '' }}
                        <span class="ml-2 text-xs font-normal text-slate-400">{{ rows.length }} student{{ rows.length !== 1 ? 's' : '' }}</span>
                    </div>
                    <div v-if="hasDuplicates" class="text-xs font-bold text-red-600">
                        ⚠ {{ duplicatePhotoNumbers.size }} duplicate photo numbers — fix before saving
                    </div>
                    <div v-else-if="isDirty" class="text-xs text-amber-600 font-medium">
                        Unsaved changes
                    </div>
                </div>

                <Table>
                    <thead>
                        <tr>
                            <th class="w-10">#</th>
                            <th>Student</th>
                            <th>Admission No</th>
                            <th class="w-44">
                                Photo Number
                                <span class="text-xs font-normal text-slate-400 ml-1">(editable)</span>
                            </th>
                            <th class="w-16 text-center">Edit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in rows" :key="row.student_id"
                            :class="duplicatePhotoNumbers.has(idx) ? 'bg-red-50' : ''">
                            <td class="text-slate-400 text-xs text-center align-top pt-3">{{ idx + 1 }}</td>
                            <td>
                                <div class="flex items-start gap-2.5 py-1">
                                    <img v-if="row.photo_url" :src="row.photo_url"
                                         class="w-9 h-9 rounded-full object-cover border border-slate-200 flex-shrink-0">
                                    <div v-else class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600 flex-shrink-0">
                                        {{ row.first_name?.[0] }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-slate-800 flex items-center gap-2 flex-wrap">
                                            <span>{{ row.name }}</span>
                                            <span v-if="row.class_name" class="text-xs font-normal text-slate-500">
                                                · {{ row.class_name }}{{ row.section_name ? ' / ' + row.section_name : '' }}
                                            </span>
                                            <span v-if="row.pending_changes_count > 0"
                                                  class="text-[10px] font-bold uppercase tracking-wide bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded">
                                                Pending edit ({{ row.pending_changes_count }})
                                            </span>
                                        </div>
                                        <div v-if="showDetails" class="mt-1 space-y-0.5 text-xs text-slate-500">
                                            <div v-if="row.primary_phone">
                                                <span class="text-slate-400">Primary phone:</span>
                                                <a :href="`tel:${row.primary_phone}`" class="text-indigo-600 hover:underline ml-1">{{ row.primary_phone }}</a>
                                            </div>
                                            <div v-if="row.father_name || row.father_phone">
                                                <span class="text-slate-400">Father:</span>
                                                <span v-if="row.father_name" class="text-slate-600">{{ row.father_name }}</span>
                                                <span v-if="row.father_phone">
                                                    · <a :href="`tel:${row.father_phone}`" class="text-indigo-600 hover:underline">{{ row.father_phone }}</a>
                                                </span>
                                            </div>
                                            <div v-if="row.mother_name || row.mother_phone">
                                                <span class="text-slate-400">Mother:</span>
                                                <span v-if="row.mother_name" class="text-slate-600">{{ row.mother_name }}</span>
                                                <span v-if="row.mother_phone">
                                                    · <a :href="`tel:${row.mother_phone}`" class="text-indigo-600 hover:underline">{{ row.mother_phone }}</a>
                                                </span>
                                            </div>
                                            <div v-if="row.student_address || row.parent_address">
                                                <span class="text-slate-400">Address:</span>
                                                <span class="text-slate-600">{{ row.student_address || row.parent_address }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="font-mono text-xs text-slate-500 align-top pt-3">
                                <div>{{ row.admission_no }}</div>
                                <div v-if="row.erp_no" class="text-[10px] text-slate-400 mt-0.5">ERP: {{ row.erp_no }}</div>
                            </td>
                            <td class="align-top pt-2">
                                <div class="flex items-center gap-1.5">
                                    <input
                                        v-model="row.photo_number"
                                        @input="markDirty"
                                        type="text"
                                        maxlength="50"
                                        :class="[
                                            'w-32 text-center font-mono font-bold text-sm border rounded-lg px-2 py-1.5 transition-all focus:outline-none focus:ring-2',
                                            duplicatePhotoNumbers.has(idx)
                                                ? 'border-red-400 bg-red-50 text-red-700 focus:ring-red-300'
                                                : 'border-slate-300 bg-white text-slate-800 focus:ring-indigo-300 focus:border-indigo-400'
                                        ]"
                                        :placeholder="`e.g. ${String(idx + 1).padStart(3, '0')}`">
                                    <span v-if="duplicatePhotoNumbers.has(idx)" class="text-red-500 text-xs">dup</span>
                                </div>
                            </td>
                            <td class="text-center align-top pt-2">
                                <button @click="openEdit(idx)"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition"
                                        title="Request profile changes">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </Table>

                <!-- Footer summary -->
                <div class="px-5 py-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
                    <div>{{ rows.filter(r => r.photo_number).length }} of {{ rows.length }} assigned</div>
                    <div v-if="totalPending > 0" class="text-amber-700">
                        {{ totalPending }} pending edit{{ totalPending === 1 ? '' : 's' }} awaiting approval
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Auto Assign Modal ── -->
        <Modal v-model:open="showAutoModal" title="⚡ Auto Assign Photo Numbers" size="md">
            <p class="text-sm text-slate-500 mb-5">
                Sequentially fill photo numbers for every student. Useful when the photographer's
                camera assigns sequential file numbers and you want to mirror that order.
            </p>

            <div class="space-y-4">
                <div class="form-field">
                    <label>Sort Students By</label>
                    <select v-model="autoSort">
                        <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </div>

                <div class="flex gap-4">
                    <div class="form-field flex-1">
                        <label>Starting Number</label>
                        <input v-model="autoStart" type="number" min="1" class="font-mono">
                    </div>
                    <div class="form-field flex-1">
                        <label>Digits (padding)</label>
                        <select v-model="autoPad" class="font-mono">
                            <option value="1">1 → 1, 2, 3</option>
                            <option value="2">2 → 01, 02, 03</option>
                            <option value="3">3 → 001, 002, 003</option>
                            <option value="4">4 → 0001, 0002</option>
                        </select>
                    </div>
                </div>

                <div class="bg-slate-50 rounded-xl px-4 py-3 border border-slate-200 text-xs">
                    <div class="font-semibold text-slate-600 mb-1">Preview (first 3):</div>
                    <div class="font-mono text-indigo-700 font-bold">
                        {{ String(parseInt(autoStart) || 1).padStart(parseInt(autoPad) || 1, '0') }},
                        {{ String((parseInt(autoStart) || 1) + 1).padStart(parseInt(autoPad) || 1, '0') }},
                        {{ String((parseInt(autoStart) || 1) + 2).padStart(parseInt(autoPad) || 1, '0') }}, …
                    </div>
                    <div class="text-slate-400 mt-1">Total: {{ rows.length }} students</div>
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showAutoModal = false">Cancel</Button>
                <Button @click="doAutoAssign">Assign {{ rows.length }} Students</Button>
            </template>
        </Modal>

        <!-- ── Inline Edit Modal (creates a pending EditRequest) ── -->
        <Modal v-model:open="editModalOpen" title="Request profile changes" size="lg" persistent>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-xs text-amber-900">
                <strong>Heads up:</strong> these changes will not appear on the student's profile until an
                administrator approves them at <code class="px-1 bg-amber-100 rounded">/school/edit-requests</code>.
            </div>

            <div v-if="editingMessage" class="bg-slate-100 border border-slate-200 rounded-lg p-2.5 mb-3 text-xs text-slate-700">
                {{ editingMessage }}
            </div>

            <div class="space-y-4">
                <!-- Student name -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="form-field">
                        <label>First Name</label>
                        <input v-model="editForm.first_name" type="text" maxlength="255">
                        <p v-if="editErrors.first_name" class="text-red-600 text-xs mt-1">{{ editErrors.first_name[0] }}</p>
                    </div>
                    <div class="form-field">
                        <label>Last Name</label>
                        <input v-model="editForm.last_name" type="text" maxlength="255">
                        <p v-if="editErrors.last_name" class="text-red-600 text-xs mt-1">{{ editErrors.last_name[0] }}</p>
                    </div>
                </div>

                <!-- Class & Section (current academic year) -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="form-field">
                        <label>Class</label>
                        <select v-model="editForm.class_id">
                            <option value="">— Select class —</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="editErrors.class_id" class="text-red-600 text-xs mt-1">{{ editErrors.class_id[0] }}</p>
                    </div>
                    <div class="form-field">
                        <label>Section</label>
                        <select v-model="editForm.section_id" :disabled="!editForm.class_id">
                            <option value="">— Select section —</option>
                            <option v-for="s in editSections" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <p v-if="editErrors.section_id" class="text-red-600 text-xs mt-1">{{ editErrors.section_id[0] }}</p>
                    </div>
                </div>

                <!-- Student address -->
                <div class="form-field">
                    <label>Student Address</label>
                    <textarea v-model="editForm.address" rows="2" maxlength="500"></textarea>
                    <p v-if="editErrors.address" class="text-red-600 text-xs mt-1">{{ editErrors.address[0] }}</p>
                </div>

                <hr class="border-slate-200">

                <!-- Primary phone (family contact, separate from individual parent phones) -->
                <div class="form-field">
                    <label>Primary Phone <span class="text-slate-400 text-xs">(family's main contact)</span></label>
                    <input v-model="editForm.primary_phone" type="tel" maxlength="20">
                    <p v-if="editErrors.primary_phone" class="text-red-600 text-xs mt-1">{{ editErrors.primary_phone[0] }}</p>
                </div>

                <!-- Father -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="form-field">
                        <label>Father Name</label>
                        <input v-model="editForm.father_name" type="text" maxlength="255">
                        <p v-if="editErrors.father_name" class="text-red-600 text-xs mt-1">{{ editErrors.father_name[0] }}</p>
                    </div>
                    <div class="form-field">
                        <label>Father Phone</label>
                        <input v-model="editForm.father_phone" type="text" maxlength="20">
                        <p v-if="editErrors.father_phone" class="text-red-600 text-xs mt-1">{{ editErrors.father_phone[0] }}</p>
                    </div>
                </div>

                <!-- Mother -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="form-field">
                        <label>Mother Name</label>
                        <input v-model="editForm.mother_name" type="text" maxlength="255">
                        <p v-if="editErrors.mother_name" class="text-red-600 text-xs mt-1">{{ editErrors.mother_name[0] }}</p>
                    </div>
                    <div class="form-field">
                        <label>Mother Phone</label>
                        <input v-model="editForm.mother_phone" type="text" maxlength="20">
                        <p v-if="editErrors.mother_phone" class="text-red-600 text-xs mt-1">{{ editErrors.mother_phone[0] }}</p>
                    </div>
                </div>

                <!-- Parent address -->
                <div class="form-field">
                    <label>Parent Address (if different from student)</label>
                    <textarea v-model="editForm.parent_address" rows="2" maxlength="500"></textarea>
                    <p v-if="editErrors.parent_address" class="text-red-600 text-xs mt-1">{{ editErrors.parent_address[0] }}</p>
                </div>

                <!-- Reason -->
                <div class="form-field">
                    <label>Reason for change <span class="text-slate-400">(optional)</span></label>
                    <textarea v-model="editForm.reason" rows="2" maxlength="1000"
                              placeholder="e.g. Father changed phone number, parent confirmed at photoshoot"></textarea>
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="editModalOpen = false" :disabled="isSubmittingEdit">Cancel</Button>
                <Button @click="submitEdit" :disabled="isSubmittingEdit">
                    {{ isSubmittingEdit ? 'Submitting…' : 'Submit for approval' }}
                </Button>
            </template>
        </Modal>

        <!-- ── Photographer Login Modal ── -->
        <Modal v-model:open="photographerModalOpen" title="Photographer Login" size="md">
            <p class="text-sm text-slate-500 mb-4">
                Hand these credentials to your photographer. They'll log into the school mobile app
                with this username and password and only see the Photo Numbers screen — they can't
                access fees, attendance, or anything else. The login is shared per school; rotate it
                if it's leaked or after a photoshoot ends.
            </p>

            <!-- Just-generated state: show plaintext password ONCE -->
            <div v-if="photographerLastGenerated"
                 class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-4 space-y-3">
                <div class="text-xs font-bold text-emerald-800 uppercase tracking-wide">
                    ✓ New credential ready — copy it now
                </div>
                <p class="text-xs text-emerald-700">
                    The password below is shown <strong>only this once</strong>. Once you close this dialog,
                    it's gone forever — you'll have to regenerate.
                </p>

                <div class="bg-white rounded-lg p-3 border border-emerald-200 space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-[10px] uppercase font-semibold text-slate-400">Username</div>
                            <div class="font-mono text-sm font-bold text-slate-800 truncate">{{ photographerLastGenerated.username }}</div>
                        </div>
                        <button @click="copyToClipboard(photographerLastGenerated.username, 'Username')"
                                class="px-2 py-1 text-xs bg-slate-100 hover:bg-slate-200 rounded text-slate-700 flex-shrink-0">
                            Copy
                        </button>
                    </div>
                    <div class="flex items-center justify-between gap-3 pt-2 border-t border-slate-100">
                        <div class="min-w-0">
                            <div class="text-[10px] uppercase font-semibold text-slate-400">Password</div>
                            <div class="font-mono text-sm font-bold text-slate-800 truncate">{{ photographerLastGenerated.password }}</div>
                        </div>
                        <button @click="copyToClipboard(photographerLastGenerated.password, 'Password')"
                                class="px-2 py-1 text-xs bg-slate-100 hover:bg-slate-200 rounded text-slate-700 flex-shrink-0">
                            Copy
                        </button>
                    </div>
                </div>
            </div>

            <!-- Configured (but no plaintext available — only username) -->
            <div v-else-if="photographerCredential.configured"
                 class="bg-slate-50 border border-slate-200 rounded-lg p-4 mb-4 space-y-2">
                <div class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Currently configured</div>
                <div class="flex items-center justify-between gap-3 bg-white p-2.5 rounded border border-slate-200">
                    <div class="min-w-0">
                        <div class="text-[10px] uppercase font-semibold text-slate-400">Username</div>
                        <div class="font-mono text-sm font-bold text-slate-800 truncate">{{ photographerCredential.username }}</div>
                    </div>
                    <button @click="copyToClipboard(photographerCredential.username, 'Username')"
                            class="px-2 py-1 text-xs bg-slate-100 hover:bg-slate-200 rounded text-slate-700 flex-shrink-0">
                        Copy
                    </button>
                </div>
                <p class="text-xs text-slate-500">
                    The password isn't retrievable. If your photographer has lost it, click <strong>Regenerate</strong>
                    to issue a new one.
                </p>
            </div>

            <!-- Not configured -->
            <div v-else-if="!isLoadingCredential"
                 class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4 text-sm text-amber-900">
                No photographer login is currently configured for this school. Click <strong>Generate</strong> to create one.
            </div>

            <div v-if="isLoadingCredential" class="text-center py-6 text-sm text-slate-400">
                Loading…
            </div>

            <template #footer>
                <Button variant="secondary" @click="photographerModalOpen = false" :disabled="isMutatingCredential">
                    Close
                </Button>
                <Button v-if="photographerCredential.configured" variant="danger"
                        @click="clearPhotographerCredential" :disabled="isMutatingCredential">
                    Clear
                </Button>
                <Button @click="generatePhotographerCredential" :disabled="isMutatingCredential">
                    {{ isMutatingCredential ? 'Working…' : (photographerCredential.configured ? 'Regenerate' : 'Generate') }}
                </Button>
            </template>
        </Modal>
    </SchoolLayout>
</template>
