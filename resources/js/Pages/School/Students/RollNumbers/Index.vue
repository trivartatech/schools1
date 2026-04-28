<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Table from '@/Components/ui/Table.vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref, computed, watch } from 'vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    academicYears: Array,
    classes:       Array,
    sections:      Array,
    students:      Array,   // [{ history_id, student_id, name, first_name, last_name, admission_no, gender, photo_url, roll_no }]
    filters:       Object,
});

// ── Filters ───────────────────────────────────────────────────
const filters = ref({
    academic_year_id: props.filters?.academic_year_id || '',
    class_id:         props.filters?.class_id         || '',
    section_id:       props.filters?.section_id       || '',
});

const applyFilters = () => {
    router.get(route('school.roll-numbers.index'), filters.value, { preserveState: true });
};

// When class changes, clear section and reload
watch(() => filters.value.class_id, () => {
    filters.value.section_id = '';
    applyFilters();
});

// ── Editable student rows ─────────────────────────────────────
const rows = ref(props.students.map(s => ({ ...s })));
const isDirty = ref(false);

watch(() => props.students, (val) => {
    rows.value = val.map(s => ({ ...s }));
    isDirty.value = false;
}, { deep: true });

const markDirty = () => { isDirty.value = true; };

// ── Auto-assign ───────────────────────────────────────────────
const autoStart  = ref(1);
const autoPad    = ref(2);
const autoSort   = ref('name');

const sortOptions = [
    { value: 'name',        label: 'Name (A → Z)' },
    { value: 'name_desc',   label: 'Name (Z → A)' },
    { value: 'admission',   label: 'Admission No' },
    { value: 'boys_first',  label: 'Boys first, then Girls (A→Z)' },
    { value: 'girls_first', label: 'Girls first, then Boys (A→Z)' },
    { value: 'current',     label: 'Keep current order' },
];

const doAutoAssign = () => {
    const sorted = [...rows.value];
    const sortFn = {
        name:        (a, b) => a.name.localeCompare(b.name),
        name_desc:   (a, b) => b.name.localeCompare(a.name),
        admission:   (a, b) => a.admission_no.localeCompare(b.admission_no),
        boys_first:  (a, b) => {
            const ga = a.gender === 'Male' ? 0 : 1;
            const gb = b.gender === 'Male' ? 0 : 1;
            return ga !== gb ? ga - gb : a.name.localeCompare(b.name);
        },
        girls_first: (a, b) => {
            const ga = a.gender === 'Female' ? 0 : 1;
            const gb = b.gender === 'Female' ? 0 : 1;
            return ga !== gb ? ga - gb : a.name.localeCompare(b.name);
        },
        current: () => 0,
    }[autoSort.value] ?? ((a, b) => a.name.localeCompare(b.name));

    sorted.sort(sortFn);

    const pad = parseInt(autoPad.value) || 2;
    const start = parseInt(autoStart.value) || 1;
    sorted.forEach((s, i) => {
        s.roll_no = String(start + i).padStart(pad, '0');
    });

    rows.value = sorted;
    isDirty.value = true;
    showAutoModal.value = false;
};

const showAutoModal = ref(false);

// ── Duplicate detection ───────────────────────────────────────
const duplicateRolls = computed(() => {
    const seen = new Map();
    rows.value.forEach((r, idx) => {
        const key = r.roll_no?.trim();
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

const hasDuplicates = computed(() => duplicateRolls.value.size > 0);

// ── Save ──────────────────────────────────────────────────────
const isSaving = ref(false);

const save = () => {
    if (hasDuplicates.value) return;
    isSaving.value = true;
    router.post(route('school.roll-numbers.save'), {
        academic_year_id: filters.value.academic_year_id,
        class_id:         filters.value.class_id,
        section_id:       filters.value.section_id || null,
        assignments: rows.value.map(r => ({
            history_id: r.history_id,
            student_id: r.student_id,
            roll_no:    r.roll_no || null,
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

// ── Print ─────────────────────────────────────────────────────
const showPrintPanel = ref(false);

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

const genderDot = (g) => g === 'Male' ? '🔵' : g === 'Female' ? '🔴' : '⚪';

const doPrint = () => {
    const title = `Roll List — ${yearLabel.value} · ${classLabel.value}${sectionLabel.value ? ' / ' + sectionLabel.value : ''}`;
    const tableRows = rows.value.map((r, i) => `
        <tr>
            <td style="text-align:center;color:#64748b">${i + 1}</td>
            <td style="font-weight:700;font-family:monospace">${r.roll_no || '—'}</td>
            <td style="font-weight:600">${r.name}</td>
            <td style="font-family:monospace;color:#6b7280;font-size:0.82em">${r.admission_no}</td>
            <td>${r.gender || '—'}</td>
        </tr>`).join('');

    const html = `<!DOCTYPE html><html><head><meta charset="UTF-8">
        <title>${title}</title>
        <style>
            body{font-family:'Times New Roman',Times,serif;margin:32px;color:#1a1a1a}
            h2{text-align:center;font-size:1.3rem;margin-bottom:4px}
            h3{text-align:center;font-size:1rem;color:#475569;margin-bottom:20px;font-weight:normal}
            table{width:100%;border-collapse:collapse;font-size:0.9rem}
            th{background:#1e3a8a;color:white;padding:8px 10px;text-align:left;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px}
            td{padding:7px 10px;border-bottom:1px solid #e2e8f0}
            tr:nth-child(even) td{background:#f8fafc}
            .footer{text-align:center;margin-top:40px;color:#94a3b8;font-size:0.75rem}
            @media print{@page{margin:1.5cm}body{margin:0}}
        <\/style></head><body>
        <h2>${title}</h2>
        <h3>Total: ${rows.value.length} &nbsp;|&nbsp; Boys: ${rows.value.filter(r => r.gender === 'Male').length} &nbsp;|&nbsp; Girls: ${rows.value.filter(r => r.gender === 'Female').length}</h3>
        <table><thead><tr><th>#</th><th>Roll No</th><th>Student Name</th><th>Admission No</th><th>Gender</th></tr></thead>
        <tbody>${tableRows}</tbody></table>
        <div class="footer">Generated on ${school.fmtDate(school.today())}</div>
        <script>window.onload=()=>window.print();<\/script></body></html>`;

    const url = URL.createObjectURL(new Blob([html], { type: 'text/html' }));
    window.open(url, '_blank');
    showPrintPanel.value = false;
};
</script>

<template>
    <SchoolLayout title="Roll Number Management">
        <!-- ── Unsaved-changes sticky bar ── -->
        <div v-if="isDirty"
             class="fixed top-0 left-0 right-0 z-50 bg-amber-500 text-white flex items-center justify-between px-6 py-2.5 shadow-lg text-sm font-semibold">
            <span>⚠ You have unsaved changes</span>
            <div class="flex gap-3">
                <button @click="resetChanges" class="bg-white/20 hover:bg-white/30 px-3 py-1 rounded-lg transition">Discard</button>
                <Button variant="secondary" @click="save" :disabled="hasDuplicates || isSaving" class="text-amber-700">
                    {{ isSaving ? 'Saving…' : 'Save All' }}
                </Button>
            </div>
        </div>

        <div :class="isDirty ? 'mt-12' : ''">
            <PageHeader title="Roll Number Management" subtitle="Assign and manage roll numbers per class, section, and academic year">
                <template #actions>
                    <template v-if="rows.length > 0">
                        <Button variant="secondary" @click="showAutoModal = true">⚡ Auto Assign</Button>
                        <Button variant="secondary" @click="showPrintPanel = true">🖨 Print List</Button>
                        <Button @click="save" :disabled="!isDirty || hasDuplicates || isSaving">
                            {{ isSaving ? 'Saving…' : '💾 Save' }}
                        </Button>
                    </template>
                </template>
            </PageHeader>

            <!-- ── Filters ── -->
            <div class="card mb-6">
                <div class="card-body">
                    <div class="flex gap-4 flex-wrap items-end">
                        <div class="form-field min-w-[180px]">
                            <label>Academic Year</label>
                            <select v-model="filters.academic_year_id" @change="applyFilters">
                                <option value="">— Select year —</option>
                                <option v-for="y in academicYears" :key="y.id" :value="y.id">
                                    {{ y.name }}{{ y.is_current ? ' (Current)' : '' }}
                                </option>
                            </select>
                        </div>
                        <div class="form-field min-w-[150px]">
                            <label>Class</label>
                            <select v-model="filters.class_id" :disabled="!filters.academic_year_id">
                                <option value="">— Select class —</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="form-field min-w-[140px]">
                            <label>Section</label>
                            <select v-model="filters.section_id" @change="applyFilters"
                                    :disabled="!filters.class_id || sections.length === 0">
                                <option value="">All Sections</option>
                                <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── No class selected ── -->
            <div v-if="!filters.class_id" class="card">
                <EmptyState
                    title="Select a class to begin"
                    description="Pick an academic year and class above to manage roll numbers for those students."
                />
            </div>

            <!-- ── Empty result ── -->
            <div v-else-if="rows.length === 0" class="card">
                <EmptyState
                    title="No students found"
                    description="No students are enrolled in this class/section for the selected academic year."
                />
            </div>

            <!-- ── Student Table ── -->
            <div v-else class="card overflow-hidden">
                <!-- Header stats -->
                <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 bg-slate-50">
                    <div class="text-sm font-semibold text-slate-700">
                        {{ yearLabel }} · {{ classLabel }}{{ sectionLabel ? ' / ' + sectionLabel : '' }}
                        <span class="ml-2 text-xs font-normal text-slate-400">{{ rows.length }} student{{ rows.length !== 1 ? 's' : '' }}</span>
                    </div>
                    <div v-if="hasDuplicates" class="text-xs font-bold text-red-600 flex items-center gap-1">
                        ⚠ {{ duplicateRolls.size }} duplicate roll numbers — fix before saving
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
                            <th>Gender</th>
                            <th class="w-40">
                                Roll Number
                                <span class="text-xs font-normal text-slate-400 ml-1">(editable)</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in rows" :key="row.history_id"
                            :class="[
                                duplicateRolls.has(idx) ? 'bg-red-50' : '',
                            ]">
                            <td class="text-slate-400 text-xs text-center">{{ idx + 1 }}</td>
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <img v-if="row.photo_url" :src="row.photo_url"
                                         class="w-7 h-7 rounded-full object-cover border border-slate-200">
                                    <div v-else class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">
                                        {{ row.first_name?.[0] }}
                                    </div>
                                    <span class="text-sm font-medium text-slate-800">{{ row.name }}</span>
                                </div>
                            </td>
                            <td class="font-mono text-xs text-slate-500">{{ row.admission_no }}</td>
                            <td class="text-sm">
                                <span>{{ genderDot(row.gender) }} {{ row.gender || '—' }}</span>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    <input
                                        v-model="row.roll_no"
                                        @input="markDirty"
                                        type="text"
                                        maxlength="20"
                                        :class="[
                                            'w-28 text-center font-mono font-bold text-sm border rounded-lg px-2 py-1 transition-all focus:outline-none focus:ring-2',
                                            duplicateRolls.has(idx)
                                                ? 'border-red-400 bg-red-50 text-red-700 focus:ring-red-300'
                                                : 'border-slate-300 bg-white text-slate-800 focus:ring-indigo-300 focus:border-indigo-400'
                                        ]"
                                        :placeholder="`e.g. ${String(idx + 1).padStart(2, '0')}`"
                                    >
                                    <span v-if="duplicateRolls.has(idx)" class="text-red-500 text-xs">dup</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </Table>

                <!-- Footer summary -->
                <div class="px-5 py-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
                    <div class="flex gap-4">
                        <span>🔵 {{ rows.filter(r => r.gender === 'Male').length }} Boys</span>
                        <span>🔴 {{ rows.filter(r => r.gender === 'Female').length }} Girls</span>
                        <span>⚪ {{ rows.filter(r => !r.gender).length }} Not set</span>
                    </div>
                    <div>
                        {{ rows.filter(r => r.roll_no).length }} of {{ rows.length }} assigned
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Auto Assign Modal ── -->
        <Modal v-model:open="showAutoModal" title="⚡ Auto Assign Roll Numbers" size="md">
            <p class="text-sm text-slate-500 mb-5">
                Automatically assign sequential roll numbers based on your chosen sort order.
                You can review and adjust before saving.
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
                        {{ String(parseInt(autoStart) || 1).padStart(parseInt(autoPad) || 2, '0') }},
                        {{ String((parseInt(autoStart) || 1) + 1).padStart(parseInt(autoPad) || 2, '0') }},
                        {{ String((parseInt(autoStart) || 1) + 2).padStart(parseInt(autoPad) || 2, '0') }}, …
                    </div>
                    <div class="text-slate-400 mt-1">Total: {{ rows.length }} students</div>
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showAutoModal = false">Cancel</Button>
                <Button @click="doAutoAssign">Assign {{ rows.length }} Students</Button>
            </template>
        </Modal>

        <!-- ── Print Panel ── -->
        <Modal v-model:open="showPrintPanel" title="🖨 Print Roll List" size="sm">
            <p class="text-sm text-slate-500 mb-4">
                A print-ready view will open in a new tab. Make sure roll numbers are saved before printing.
            </p>
            <div class="bg-slate-50 rounded-lg px-4 py-3 border border-slate-200 text-sm space-y-1">
                <div><span class="text-slate-400">Year:</span> <strong>{{ yearLabel }}</strong></div>
                <div><span class="text-slate-400">Class:</span> <strong>{{ classLabel }}{{ sectionLabel ? ' / ' + sectionLabel : '' }}</strong></div>
                <div><span class="text-slate-400">Students:</span> <strong>{{ rows.length }}</strong></div>
            </div>
            <template #footer>
                <Button variant="secondary" @click="showPrintPanel = false">Cancel</Button>
                <Button @click="doPrint">Open Print View</Button>
            </template>
        </Modal>
    </SchoolLayout>
</template>
