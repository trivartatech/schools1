<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

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

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' }) : '—';
</script>

<template>
    <SchoolLayout title="Alumni">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Alumni Directory</h1>
                <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Passout students and their current profiles</p>
            </div>
            <Button @click="openGraduate">
                <svg style="width:16px;height:16px;margin-right:6px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Graduate Students
            </Button>
        </div>

        <!-- Stats -->
        <div class="alumni-stats">
            <div class="card stat-card">
                <div class="card-body" style="padding:16px;display:flex;align-items:center;gap:12px;">
                    <div class="stat-icon" style="background:#ede9fe;color:#7c3aed;">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                    </div>
                    <div>
                        <div class="stat-number" style="color:#7c3aed;">{{ alumni.total }}</div>
                        <div class="stat-label">Total Alumni</div>
                    </div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="card-body" style="padding:16px;display:flex;align-items:center;gap:12px;">
                    <div class="stat-icon" style="background:#dbeafe;color:#1d4ed8;">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <div class="stat-number" style="color:#1d4ed8;">{{ years.length }}</div>
                        <div class="stat-label">Graduation Years</div>
                    </div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="card-body" style="padding:16px;display:flex;align-items:center;gap:12px;">
                    <div class="stat-icon" style="background:#dcfce7;color:#16a34a;">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <div>
                        <div class="stat-number" style="color:#16a34a;">{{ classes.length }}</div>
                        <div class="stat-label">Classes Represented</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom:16px;">
            <div class="filter-bar">
                <div class="filter-search">
                    <svg class="filter-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input v-model="search" placeholder="Search by name or admission no..." class="filter-input" />
                </div>
                <select v-model="passoutYear" class="filter-select">
                    <option value="">All Batches</option>
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
                <select v-model="finalClass" class="filter-select">
                    <option value="">All Classes</option>
                    <option v-for="c in classes" :key="c" :value="c">{{ c }}</option>
                </select>
            </div>
        </div>

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
                                    <Button size="xs" variant="danger" @click="$inertia.delete(`/school/alumni/${a.id}`, { preserveScroll: true })">Remove</Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!alumni.data?.length">
                            <td colspan="8" style="padding:48px;text-align:center;color:#94a3b8;">
                                <svg style="width:40px;height:40px;margin:0 auto 12px;display:block;opacity:.3;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                                No alumni records found.
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

        <!-- ── Graduate Modal ── -->
        <Teleport to="body">
            <div v-if="showGraduate" class="modal-backdrop" @click.self="showGraduate = false">
                <div class="grad-modal">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title">Graduate Students</h3>
                            <p style="font-size:.8rem;color:#64748b;margin:2px 0 0;">Select students and fill in graduation details</p>
                        </div>
                        <button @click="showGraduate = false" class="modal-close">&times;</button>
                    </div>

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
                                <svg style="width:13px;height:13px;display:inline;margin-right:4px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                These details will apply to all selected students. Student status will be updated to "graduated".
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <Button variant="secondary" type="button" @click="showGraduate = false">Cancel</Button>
                        <Button @click="graduate" :loading="gradForm.processing"
                                :disabled="selectedStudents.length === 0">
                            Graduate {{ selectedStudents.length > 0 ? selectedStudents.length + ' Student(s)' : '' }}
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Edit Alumni Modal ── -->
        <Teleport to="body">
            <div v-if="showEdit" class="modal-backdrop" @click.self="showEdit = false">
                <div class="modal" style="max-width:540px;width:100%;">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title">Update Alumni Profile</h3>
                            <p v-if="editTarget" style="font-size:.8rem;color:#64748b;margin:2px 0 0;">
                                {{ editTarget.student?.first_name }} {{ editTarget.student?.last_name }}
                            </p>
                        </div>
                        <button @click="showEdit = false" class="modal-close">&times;</button>
                    </div>
                    <form @submit.prevent="saveEdit">
                        <div class="modal-body" style="display:flex;flex-direction:column;gap:10px;">
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
                        <div class="modal-footer">
                            <Button variant="secondary" type="button" @click="showEdit = false">Cancel</Button>
                            <Button type="submit" :loading="editForm.processing">Save Changes</Button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

    </SchoolLayout>
</template>

<style scoped>
/* Stats */
.alumni-stats { display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px; }
.stat-card .card-body { display:flex;align-items:center;gap:12px; }
.stat-icon { width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.stat-icon svg { width:22px;height:22px; }
.stat-number { font-size:1.6rem;font-weight:700;line-height:1; }
.stat-label { font-size:.75rem;color:#64748b;margin-top:2px; }

/* Filters */
.filter-bar { padding:12px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center; }
.filter-search { position:relative;flex:1;min-width:220px; }
.filter-icon { position:absolute;left:10px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:#94a3b8; }
.filter-input { width:100%;padding:8px 12px 8px 32px;border:1px solid #e2e8f0;border-radius:7px;font-size:.875rem;outline:none;box-sizing:border-box; }
.filter-input:focus { border-color:#6366f1;box-shadow:0 0 0 2px rgba(99,102,241,.12); }
.filter-select { padding:8px 12px;border:1px solid #e2e8f0;border-radius:7px;font-size:.875rem;outline:none;background:#fff;min-width:130px; }
.filter-select:focus { border-color:#6366f1; }

/* Table */
.table-row:hover { background:#fafbff; }
.avatar-circle { width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:700;flex-shrink:0; }
.badge-purple { background:#ede9fe;color:#7c3aed;padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:600; }

/* Graduate Modal */
.grad-modal {
    background:#fff;border-radius:14px;
    box-shadow:0 25px 50px -12px rgba(0,0,0,.2);
    width:100%;max-width:860px;max-height:90vh;
    display:flex;flex-direction:column;
}
.grad-body {
    display:grid;grid-template-columns:1fr 1fr;
    overflow:hidden;flex:1;min-height:0;
}
.grad-left {
    padding:20px;border-right:1px solid #e2e8f0;
    display:flex;flex-direction:column;overflow:hidden;
}
.grad-right { padding:20px;overflow-y:auto; }

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

/* Modals */
.modal-backdrop { position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,.5);backdrop-filter:blur(2px);display:flex;align-items:center;justify-content:center;z-index:1000;padding:16px; }
.modal { background:#fff;border-radius:12px;box-shadow:0 20px 25px -5px rgba(0,0,0,.15); }
.modal-header { padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:flex-start; }
.modal-title { font-size:1rem;font-weight:700;color:#1e293b;margin:0; }
.modal-close { background:none;border:none;font-size:1.5rem;line-height:1;color:#94a3b8;cursor:pointer;padding:0;flex-shrink:0; }
.modal-close:hover { color:#0f172a; }
.modal-body { padding:20px; }
.modal-footer { padding:14px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;border-radius:0 0 12px 12px;display:flex;justify-content:flex-end;gap:10px; }

@media (max-width:640px) {
    .alumni-stats { grid-template-columns:1fr; }
    .grad-body { grid-template-columns:1fr; }
    .grad-left { border-right:none;border-bottom:1px solid #e2e8f0; }
}
</style>
