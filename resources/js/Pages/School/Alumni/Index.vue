<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { useConfirm } from '@/Composables/useConfirm';

const confirm = useConfirm();

const props = defineProps({
    alumni:        Object,
    years:         Array,
    classes:       Array,
    schoolClasses: Array,
});

// ── Directory Filters ─────────────────────────────────────────────────────────
const params      = new URLSearchParams(window.location.search);
const search      = ref(params.get('search') ?? '');
const passoutYear = ref(params.get('passout_year') ?? '');
const finalClass  = ref(params.get('final_class') ?? '');

const applyFilters = () => router.get('/school/alumni', {
    search:       search.value || undefined,
    passout_year: passoutYear.value || undefined,
    final_class:  finalClass.value || undefined,
}, { preserveState: true, preserveScroll: true });

const clearFilters = () => {
    search.value = '';
    passoutYear.value = '';
    finalClass.value = '';
    applyFilters();
};

let filterTimer;
watch([search, passoutYear, finalClass], () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(applyFilters, 400);
});

// ── Graduate Modal ────────────────────────────────────────────────────────────
const showGraduate     = ref(false);
const classFilter      = ref('');
const studentSearch    = ref('');
const studentResults   = ref([]);
const selectedStudents = ref([]);
const loadingStudents  = ref(false);

const gradForm = useForm({
    student_ids:      [],
    final_class:      '',
    passout_year:     '',
    final_percentage: '',
    final_grade:      '',
    graduated_on:     new Date().toISOString().slice(0, 10),
    notes:            '',
});

let searchDebounce;
const fetchStudents = () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(async () => {
        loadingStudents.value = true;
        try {
            const p = new URLSearchParams();
            if (studentSearch.value) p.set('q', studentSearch.value);
            if (classFilter.value)   p.set('class_id', classFilter.value);
            const res = await fetch(`/school/alumni/search-students?${p}`);
            studentResults.value = await res.json();
        } finally {
            loadingStudents.value = false;
        }
    }, 300);
};

watch([studentSearch, classFilter], fetchStudents);

const isSelected  = (id) => selectedStudents.value.some(s => s.id === id);
const toggleAll   = () => {
    const unselected = studentResults.value.filter(s => !isSelected(s.id));
    if (unselected.length) {
        unselected.forEach(s => selectedStudents.value.push({
            id: s.id,
            name: `${s.first_name} ${s.last_name}`,
            admission_no: s.admission_no,
            className: s.current_academic_history?.course_class?.name ?? '',
        }));
    } else {
        const ids = new Set(studentResults.value.map(s => s.id));
        selectedStudents.value = selectedStudents.value.filter(s => !ids.has(s.id));
    }
};
const allCurrentSelected = computed(() =>
    studentResults.value.length > 0 && studentResults.value.every(s => isSelected(s.id))
);

const toggleStudent = (s) => {
    if (isSelected(s.id)) {
        selectedStudents.value = selectedStudents.value.filter(x => x.id !== s.id);
    } else {
        selectedStudents.value.push({
            id: s.id,
            name: `${s.first_name} ${s.last_name}`,
            admission_no: s.admission_no,
            className: s.current_academic_history?.course_class?.name ?? '',
        });
    }
};

const openGraduate = () => {
    showGraduate.value     = true;
    classFilter.value      = '';
    studentSearch.value    = '';
    selectedStudents.value = [];
    gradForm.reset();
    gradForm.graduated_on  = new Date().toISOString().slice(0, 10);
    fetchStudents();
};

const graduate = () => {
    gradForm.student_ids = selectedStudents.value.map(s => s.id);
    gradForm.post('/school/alumni/graduate', {
        preserveScroll: true,
        onSuccess: () => {
            showGraduate.value     = false;
            selectedStudents.value = [];
            studentResults.value   = [];
        },
    });
};

// ── Edit Modal ────────────────────────────────────────────────────────────────
const showEdit   = ref(false);
const editTarget = ref(null);
const editForm   = useForm({
    current_occupation: '', current_employer: '',
    current_city: '',       current_state: '',
    personal_email: '',     personal_phone: '',
    linkedin_url: '',       achievements: '',
    notes: '',              final_percentage: '',  final_grade: '',
});

const openEdit = (a) => {
    editTarget.value = a;
    editForm.current_occupation = a.current_occupation ?? '';
    editForm.current_employer   = a.current_employer   ?? '';
    editForm.current_city       = a.current_city       ?? '';
    editForm.current_state      = a.current_state      ?? '';
    editForm.personal_email     = a.personal_email     ?? '';
    editForm.personal_phone     = a.personal_phone     ?? '';
    editForm.linkedin_url       = a.linkedin_url       ?? '';
    editForm.achievements       = a.achievements       ?? '';
    editForm.notes              = a.notes              ?? '';
    editForm.final_percentage   = a.final_percentage   ?? '';
    editForm.final_grade        = a.final_grade        ?? '';
    showEdit.value = true;
};

const saveEdit = () => {
    editForm.put(`/school/alumni/${editTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { showEdit.value = false; },
    });
};

async function removeAlumni(a) {
    const ok = await confirm({
        title: 'Remove alumni record?',
        message: 'This alumni profile will be removed.',
        confirmLabel: 'Remove',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/alumni/${a.id}`, { preserveScroll: true });
}

import { useFormat } from '@/Composables/useFormat';
const { formatDate: fmt } = useFormat();

const statCards = computed(() => [
    { label: 'Total Alumni',         value: props.alumni.total,    color: 'purple' },
    { label: 'Graduation Years',     value: props.years.length,    color: 'accent' },
    { label: 'Classes Represented',  value: props.classes.length,  color: 'success' },
]);
</script>

<template>
    <SchoolLayout title="Alumni">

        <!-- Header -->
        <PageHeader title="Alumni Directory" subtitle="Passout students and their current profiles">
            <template #actions>
                <Button @click="openGraduate">+ Graduate Students</Button>
            </template>
        </PageHeader>

        <!-- Stats -->
        <StatsRow :cols="3" :stats="statCards" />

        <!-- Filters -->
        <FilterBar :active="!!(search || passoutYear || finalClass)" @clear="clearFilters">
            <div class="fb-search">
                <svg class="fb-search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <input v-model="search" placeholder="Search by name or admission no..." />
            </div>
            <select v-model="passoutYear">
                <option value="">All Batches</option>
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
            <select v-model="finalClass">
                <option value="">All Classes</option>
                <option v-for="c in classes" :key="c" :value="c">{{ c }}</option>
            </select>
        </FilterBar>

        <!-- Table -->
        <div class="card">
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.8125rem;">
                    <thead>
                        <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                            <th style="padding:10px 16px;text-align:left;color:#475569;font-weight:600;">Student</th>
                            <th style="padding:10px 16px;text-align:left;color:#475569;font-weight:600;">Batch</th>
                            <th style="padding:10px 16px;text-align:left;color:#475569;font-weight:600;">Final Class</th>
                            <th style="padding:10px 16px;text-align:left;color:#475569;font-weight:600;">Grade / %</th>
                            <th style="padding:10px 16px;text-align:left;color:#475569;font-weight:600;">Current Occupation</th>
                            <th style="padding:10px 16px;text-align:left;color:#475569;font-weight:600;">Location</th>
                            <th style="padding:10px 16px;text-align:left;color:#475569;font-weight:600;">Graduated On</th>
                            <th style="padding:10px 16px;text-align:left;color:#475569;font-weight:600;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in alumni.data" :key="a.id" style="border-bottom:1px solid #f1f5f9;" class="table-row">
                            <td style="padding:10px 16px;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="avatar-circle">{{ a.student?.first_name?.charAt(0) }}</div>
                                    <div>
                                        <div style="font-weight:600;color:#1e293b;">{{ a.student?.first_name }} {{ a.student?.last_name }}</div>
                                        <div style="font-size:.75rem;color:#94a3b8;">{{ a.student?.admission_no }}</div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding:10px 16px;">
                                <span class="badge badge-purple">{{ a.passout_year ?? '—' }}</span>
                            </td>
                            <td style="padding:10px 16px;color:#475569;">{{ a.final_class ?? '—' }}</td>
                            <td style="padding:10px 16px;">
                                <span v-if="a.final_grade" class="badge badge-green">{{ a.final_grade }}</span>
                                <span v-if="a.final_percentage" style="font-size:.8rem;color:#64748b;margin-left:4px;">{{ a.final_percentage }}%</span>
                                <span v-if="!a.final_grade && !a.final_percentage" style="color:#94a3b8;">—</span>
                            </td>
                            <td style="padding:10px 16px;color:#475569;">{{ a.current_occupation ?? '—' }}</td>
                            <td style="padding:10px 16px;color:#475569;">
                                {{ a.current_city ?? '—' }}<span v-if="a.current_state">, {{ a.current_state }}</span>
                            </td>
                            <td style="padding:10px 16px;color:#475569;">{{ fmt(a.graduated_on) }}</td>
                            <td style="padding:10px 16px;">
                                <div style="display:flex;gap:6px;">
                                    <Button size="xs" variant="secondary" @click="openEdit(a)">Edit</Button>
                                    <Button size="xs" variant="danger" @click="removeAlumni(a)">Remove</Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!alumni.data?.length">
                            <td colspan="8" style="padding:0;">
                                <EmptyState
                                    title="No alumni records found"
                                    description="Graduate students to start building your alumni directory."
                                    action-label="+ Graduate Students"
                                    @action="openGraduate"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="alumni.last_page > 1" style="display:flex;justify-content:center;gap:4px;padding:16px;flex-wrap:wrap;">
                <a v-for="p in alumni.links" :key="p.label" :href="p.url ?? '#'" v-html="p.label"
                   :style="{ padding:'6px 12px', borderRadius:'6px', fontSize:'.8rem', fontWeight:'500',
                             background: p.active ? '#6366f1' : '#f1f5f9',
                             color: p.active ? '#fff' : '#475569',
                             textDecoration:'none', pointerEvents: p.url ? 'auto' : 'none', opacity: p.url ? 1 : 0.4 }">
                </a>
            </div>
        </div>

        <!-- Graduate Modal -->
        <Modal v-model:open="showGraduate" title="Graduate Students" size="xl">
            <div class="grad-body">
                <!-- Left: Student Selection -->
                <div class="grad-left">
                    <div style="font-size:.8rem;font-weight:600;color:#475569;margin-bottom:8px;text-transform:uppercase;letter-spacing:.04em;">
                        Select Students
                    </div>

                    <!-- Class + Search filters -->
                    <div style="display:flex;gap:8px;margin-bottom:10px;">
                        <select v-model="classFilter" class="modal-select" style="flex:1;">
                            <option value="">All Classes</option>
                            <option v-for="c in schoolClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div style="position:relative;margin-bottom:10px;">
                        <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;color:#94a3b8;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input v-model="studentSearch" placeholder="Search by name or admission no..." class="modal-input" style="padding-left:32px;" />
                    </div>

                    <!-- Select All -->
                    <div v-if="studentResults.length" style="display:flex;align-items:center;justify-content:space-between;padding:6px 10px;background:#f8fafc;border-radius:6px;margin-bottom:6px;font-size:.8rem;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:500;color:#475569;">
                            <input type="checkbox" :checked="allCurrentSelected" @change="toggleAll" style="width:15px;height:15px;accent-color:#6366f1;" />
                            Select all ({{ studentResults.length }})
                        </label>
                        <span style="color:#94a3b8;">{{ selectedStudents.length }} selected</span>
                    </div>

                    <!-- Student List -->
                    <div class="student-list">
                        <div v-if="loadingStudents" style="padding:20px;text-align:center;color:#94a3b8;font-size:.85rem;">
                            Loading...
                        </div>
                        <div v-else-if="!studentResults.length" style="padding:20px;text-align:center;color:#94a3b8;font-size:.85rem;">
                            No eligible students found.
                        </div>
                        <label v-else v-for="s in studentResults" :key="s.id"
                               class="student-item" :class="{ selected: isSelected(s.id) }">
                            <input type="checkbox" :checked="isSelected(s.id)" @change="toggleStudent(s)"
                                   style="width:15px;height:15px;accent-color:#6366f1;flex-shrink:0;" />
                            <div class="s-avatar">{{ s.first_name?.charAt(0) }}</div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:500;font-size:.8125rem;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    {{ s.first_name }} {{ s.last_name }}
                                </div>
                                <div style="font-size:.72rem;color:#94a3b8;">
                                    {{ s.admission_no }}
                                    <span v-if="s.current_academic_history?.course_class"> · {{ s.current_academic_history.course_class.name }}</span>
                                    <span v-if="s.current_academic_history?.section"> {{ s.current_academic_history.section.name }}</span>
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- Selected chips -->
                    <div v-if="selectedStudents.length" style="margin-top:10px;">
                        <div style="font-size:.75rem;font-weight:600;color:#6366f1;margin-bottom:6px;">
                            {{ selectedStudents.length }} student(s) selected:
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:4px;max-height:80px;overflow-y:auto;">
                            <span v-for="s in selectedStudents" :key="s.id" class="student-chip">
                                {{ s.name }}
                                <button @click.prevent="selectedStudents = selectedStudents.filter(x => x.id !== s.id)" class="chip-remove">&times;</button>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right: Graduation Details -->
                <div class="grad-right">
                    <div style="font-size:.8rem;font-weight:600;color:#475569;margin-bottom:12px;text-transform:uppercase;letter-spacing:.04em;">
                        Graduation Details
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div class="form-field" style="margin:0;">
                            <label>Final Class</label>
                            <input v-model="gradForm.final_class" placeholder="e.g. Class XII" class="modal-input" />
                        </div>
                        <div class="form-field" style="margin:0;">
                            <label>Passout Year</label>
                            <input v-model="gradForm.passout_year" placeholder="e.g. 2025-26" class="modal-input" />
                        </div>
                        <div class="form-field" style="margin:0;">
                            <label>Final Percentage</label>
                            <input v-model="gradForm.final_percentage" type="number" min="0" max="100" step="0.01" placeholder="e.g. 87.50" class="modal-input" />
                        </div>
                        <div class="form-field" style="margin:0;">
                            <label>Grade</label>
                            <input v-model="gradForm.final_grade" placeholder="e.g. A+" maxlength="5" class="modal-input" />
                        </div>
                    </div>

                    <div class="form-field" style="margin:10px 0 0;">
                        <label>Graduated On</label>
                        <input v-model="gradForm.graduated_on" type="date" class="modal-input" />
                    </div>

                    <div class="form-field" style="margin:10px 0 0;">
                        <label>Notes</label>
                        <textarea v-model="gradForm.notes" rows="3" placeholder="Any remarks..." class="modal-input" style="resize:vertical;"></textarea>
                    </div>

                    <div v-if="gradForm.errors.student_ids" style="color:#ef4444;font-size:.8rem;margin-top:8px;">
                        {{ gradForm.errors.student_ids }}
                    </div>

                    <!-- Info note -->
                    <div style="margin-top:12px;padding:10px 12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:.78rem;color:#166534;">
                        These details will apply to all selected students. Student status will be updated to "graduated".
                    </div>
                </div>
            </div>
            <template #footer>
                <Button variant="secondary" type="button" @click="showGraduate = false">Cancel</Button>
                <Button @click="graduate" :loading="gradForm.processing"
                        :disabled="selectedStudents.length === 0">
                    Graduate {{ selectedStudents.length > 0 ? selectedStudents.length + ' Student(s)' : '' }}
                </Button>
            </template>
        </Modal>

        <!-- Edit Alumni Modal -->
        <Modal v-model:open="showEdit" title="Update Alumni Profile" size="md">
            <form @submit.prevent="saveEdit" id="alumni-edit-form">
                <p v-if="editTarget" style="font-size:.8rem;color:#64748b;margin:0 0 16px;">
                    {{ editTarget.student?.first_name }} {{ editTarget.student?.last_name }}
                </p>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div class="form-field" style="margin:0;"><label>Final %</label><input v-model="editForm.final_percentage" type="number" min="0" max="100" step="0.01" /></div>
                        <div class="form-field" style="margin:0;"><label>Grade</label><input v-model="editForm.final_grade" maxlength="5" /></div>
                        <div class="form-field" style="margin:0;"><label>Current Occupation</label><input v-model="editForm.current_occupation" /></div>
                        <div class="form-field" style="margin:0;"><label>Employer</label><input v-model="editForm.current_employer" /></div>
                        <div class="form-field" style="margin:0;"><label>City</label><input v-model="editForm.current_city" /></div>
                        <div class="form-field" style="margin:0;"><label>State</label><input v-model="editForm.current_state" /></div>
                        <div class="form-field" style="margin:0;"><label>Personal Email</label><input v-model="editForm.personal_email" type="email" /></div>
                        <div class="form-field" style="margin:0;"><label>Personal Phone</label><input v-model="editForm.personal_phone" /></div>
                    </div>
                    <div class="form-field"><label>LinkedIn URL</label><input v-model="editForm.linkedin_url" type="url" placeholder="https://linkedin.com/in/..." /></div>
                    <div class="form-field"><label>Achievements</label><textarea v-model="editForm.achievements" rows="2"></textarea></div>
                    <div class="form-field"><label>Notes</label><textarea v-model="editForm.notes" rows="2"></textarea></div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showEdit = false">Cancel</Button>
                <Button type="submit" form="alumni-edit-form" :loading="editForm.processing">Save Changes</Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
/* Table */
.table-row:hover { background:#fafbff; }
.avatar-circle { width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:700;flex-shrink:0; }
.badge-purple { background:#ede9fe;color:#7c3aed;padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:600; }

/* Graduate Modal */
.grad-body {
    display:grid;grid-template-columns:1fr 1fr;
    overflow:hidden;min-height:0;gap:0;
}
.grad-left {
    padding-right:20px;border-right:1px solid #e2e8f0;
    display:flex;flex-direction:column;overflow:hidden;
}
.grad-right { padding-left:20px;overflow-y:auto; }

.student-list {
    flex:1;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;
    min-height:180px;max-height:320px;
}
.student-item {
    display:flex;align-items:center;gap:10px;padding:9px 12px;
    cursor:pointer;border-bottom:1px solid #f8fafc;transition:background .1s;
}
.student-item:last-child { border-bottom:none; }
.student-item:hover { background:#f8fafc; }
.student-item.selected { background:#eef2ff; }
.s-avatar {
    width:28px;height:28px;border-radius:50%;
    background:linear-gradient(135deg,#6366f1,#8b5cf6);
    color:#fff;display:flex;align-items:center;justify-content:center;
    font-size:.75rem;font-weight:700;flex-shrink:0;
}

.student-chip {
    display:inline-flex;align-items:center;gap:4px;
    background:#eef2ff;color:#4338ca;
    padding:3px 8px;border-radius:20px;font-size:.72rem;font-weight:500;
}
.chip-remove {
    background:none;border:none;color:#6366f1;cursor:pointer;
    font-size:.9rem;line-height:1;padding:0;margin-left:2px;
}
.chip-remove:hover { color:#ef4444; }

.modal-select,.modal-input {
    width:100%;padding:8px 10px;border:1px solid #e2e8f0;
    border-radius:7px;font-size:.875rem;outline:none;
    background:#fff;box-sizing:border-box;
}
.modal-select:focus,.modal-input:focus { border-color:#6366f1;box-shadow:0 0 0 2px rgba(99,102,241,.1); }

@media (max-width:640px) {
    .grad-body { grid-template-columns:1fr; }
    .grad-left { padding-right:0;border-right:none;border-bottom:1px solid #e2e8f0;padding-bottom:20px;margin-bottom:20px; }
    .grad-right { padding-left:0; }
}

/* Form fields — Tailwind preflight workaround */
.form-field { display: flex; flex-direction: column; gap: 5px; }
.form-field label { font-size: 0.78rem; font-weight: 600; color: #374151; }
.form-field input,
.form-field select,
.form-field textarea {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    background: #fff;
    color: #111827;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
</style>
