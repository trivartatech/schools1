<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useDelete } from '@/Composables/useDelete';
import { useClassSections } from '@/Composables/useClassSections';
import { useTableSort } from '@/Composables/useTableSort';
import ExportDropdown from '@/Components/ExportDropdown.vue';
import debounce from 'lodash/debounce';
import Table from '@/Components/ui/Table.vue';
import SortableTh from '@/Components/ui/SortableTh.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const { canDo } = usePermissions();
const { del } = useDelete();
const { sections, fetchSections } = useClassSections();
const school = useSchoolStore();
const formatDate = (value) => value ? school.fmtDate(value) : '—';

const deleteStudent = (id) => del(
    `/school/students/${id}`,
    'Are you sure you want to delete this student? This action cannot be undone.'
);

const props = defineProps({
    students: { type: Object, required: true },
    classes:  { type: Array, required: true },
    houses:   { type: Array, default: () => [] },
    filters:  { type: Object, default: () => ({}) },
});

const viewMode = ref('card');
const search = ref(props.filters.search ?? '');
const selectedClass = ref(props.filters.class_id ?? '');
const selectedSection = ref(props.filters.section_id ?? '');
const selectedHouse = ref(props.filters.house_id ?? '');
const defaulterFilter = ref(props.filters.defaulter ?? '');
const selectedStudentType = ref(props.filters.student_type ?? '');
const perPage = ref(Number(props.filters.per_page) || 20);
const PER_PAGE_OPTIONS = [20, 40, 60, 100];

// Server-side sort. Backend allowlist: name, erp_no, admission_no, gender, dob, status.
const { sortKey, sortDir, toggleSort } = useTableSort(
    props.filters.sort ?? null,
    props.filters.dir ?? 'asc',
);

// Pre-load sections if class is already filtered
if (selectedClass.value) {
    fetchSections(selectedClass.value);
}

// Watch filters and debounce navigation. Always resets to page 1 so the user
// doesn't land on a page that no longer exists after filtering.
watch([search, selectedClass, selectedSection, selectedHouse, defaulterFilter, selectedStudentType, perPage, sortKey, sortDir], debounce(function ([s, cls, sec, house, def, stype, pp, sk, sd]) {
    router.get('/school/students', {
        search: s,
        class_id: cls,
        section_id: sec,
        house_id: house,
        defaulter: def,
        student_type: stype,
        per_page: pp,
        sort: sk,
        dir: sd,
        page: 1,
    }, {
        preserveState: true,
        replace: true,
    });
}, 300));
</script>

<template>
    <SchoolLayout title="Students Directory">

        <!-- Page Header -->
        <PageHeader title="Students Directory" subtitle="Manage student admissions, profiles, and records.">
            <template #actions>
                <!-- View toggle -->
                <div class="view-toggle" role="group" aria-label="View mode">
                    <button @click="viewMode = 'card'" :class="viewMode === 'card' ? 'view-toggle-btn--active' : ''" class="view-toggle-btn" title="Card View" :aria-pressed="viewMode === 'card'" aria-label="Card View">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    </button>
                    <button @click="viewMode = 'list'" :class="viewMode === 'list' ? 'view-toggle-btn--active' : ''" class="view-toggle-btn" title="List View" :aria-pressed="viewMode === 'list'" aria-label="List View">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    </button>
                </div>
                <ExportDropdown
                    base-url="/school/export/students"
                    :params="{ search, class_id: selectedClass, section_id: selectedSection, student_type: selectedStudentType }"
                />
                <Button variant="secondary" size="sm" as="a" :href="`/school/students/export-qr-pdf?class_id=${selectedClass}&section_id=${selectedSection}`" target="_blank" title="Print-ready PDF — 8 badges per A4 page">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m0 14v1m8-9h-1M5 12H4m11.314-6.314l-.707.707M6.393 17.607l-.707.707M17.607 17.607l-.707-.707M6.393 6.393l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    QR Badges PDF
                </Button>
                <Button variant="secondary" size="sm" as="a" :href="`/school/students/export-qr?class_id=${selectedClass}&section_id=${selectedSection}`" title="Export QR codes to Excel">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    QR Excel
                </Button>
                <Button variant="secondary" size="sm" as="link" v-if="canDo('create', 'students')" href="/school/bulk-import?type=students">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Bulk Import
                </Button>
                <Button variant="secondary" size="sm" as="link" v-if="canDo('create', 'students')" href="/school/students/bulk-photo">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h14a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Bulk Photos
                </Button>
                <Button variant="secondary" size="sm" as="link" href="/school/students/scanner" title="Scan a student ID QR to open their profile">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                    Scan QR
                </Button>
                <Button as="link" v-if="canDo('create', 'students')" href="/school/students/create">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Admission
                </Button>

            </template>
        </PageHeader>

        <!-- Filters -->
        <FilterBar :active="!!(search || selectedClass || selectedSection || selectedHouse || defaulterFilter !== '' || selectedStudentType)" @clear="search = ''; selectedClass = ''; selectedSection = ''; selectedHouse = ''; defaulterFilter = ''; selectedStudentType = '';">
            <div class="fb-search">
                <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                <input v-model="search" type="search" placeholder="Search by name or admission no...">
            </div>
            <select v-model="selectedClass" @change="fetchSections(selectedClass); selectedSection = ''" style="width:160px;">
                <option value="">All Classes</option>
                <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
            </select>
            <select v-model="selectedSection" :disabled="!selectedClass" style="width:140px;">
                <option value="">All Sections</option>
                <option v-for="sec in sections" :key="sec.id" :value="sec.id">{{ sec.name }}</option>
            </select>
            <select v-if="houses.length" v-model="selectedHouse" style="width:140px;">
                <option value="">All Houses</option>
                <option v-for="h in houses" :key="h.id" :value="h.id">{{ h.name }}</option>
            </select>
            <div class="form-field">
                <label>Defaulter</label>
                <select v-model="defaulterFilter" style="width:150px;">
                    <option value="">All</option>
                    <option value="1">Defaulters Only</option>
                    <option value="0">Non-Defaulters</option>
                </select>
            </div>
            <div class="form-field">
                <label>Student Type</label>
                <select v-model="selectedStudentType" style="width:160px;">
                    <option value="">All</option>
                    <option value="new">New Students</option>
                    <option value="old">Old Students</option>
                </select>
            </div>
        </FilterBar>

        <!-- ── List View ── -->
        <div v-if="viewMode === 'list'" class="card" style="overflow:hidden;margin-bottom:20px;">
            <Table v-if="students.data.length > 0" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
                <thead>
                    <tr>
                        <SortableTh sort-key="name">Student</SortableTh>
                        <SortableTh sort-key="erp_no">ERP No</SortableTh>
                        <SortableTh sort-key="admission_no">Admission #</SortableTh>
                        <th>Class / Section</th>
                        <th>House</th>
                        <SortableTh sort-key="gender">Gender</SortableTh>
                        <SortableTh sort-key="dob">Date of Birth</SortableTh>
                        <th>Parent</th>
                        <th>Contact</th>
                        <SortableTh sort-key="status">Status</SortableTh>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="student in students.data" :key="student.id">
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="student-avatar-sm">
                                    <img v-if="student.photo" :src="`/storage/${student.photo}`" style="width:100%;height:100%;object-fit:cover;border-radius:8px;" />
                                    <span v-else>{{ student.first_name?.charAt(0) }}{{ student.last_name?.charAt(0) }}</span>
                                </div>
                                <span style="font-weight:600;color:#0f172a;white-space:nowrap;">{{ student.first_name }} {{ student.last_name }}</span>
                            </div>
                        </td>
                        <td><span class="erp-no-badge">{{ student.erp_no || '—' }}</span></td>
                        <td><span style="font-family:monospace;font-size:0.8rem;color:#6366f1;font-weight:600;">{{ student.admission_no || '—' }}</span></td>
                        <td>
                            <div style="font-weight:600;color:#1e293b;">{{ student.current_academic_history?.course_class?.name || 'Unassigned' }}</div>
                            <div style="font-size:0.75rem;color:#94a3b8;">{{ student.current_academic_history?.section?.name }}</div>
                        </td>
                        <td>
                            <span v-if="student.current_house_assignment?.house" class="house-pill" :style="{ background: student.current_house_assignment.house.color + '22', color: student.current_house_assignment.house.color, borderColor: student.current_house_assignment.house.color + '55' }">
                                {{ student.current_house_assignment.house.name }}
                            </span>
                            <span v-else style="color:var(--text-muted);">—</span>
                        </td>
                        <td>{{ student.gender || '—' }}</td>
                        <td style="white-space:nowrap;">{{ formatDate(student.dob) }}</td>
                        <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ student.student_parent?.father_name || student.student_parent?.mother_name || student.student_parent?.guardian_name || '—' }}</td>
                        <td style="white-space:nowrap;">{{ student.student_parent?.primary_phone || '—' }}</td>
                        <td>
                            <span class="badge" :class="student.status === 'Active' ? 'badge-green' : 'badge-gray'">{{ student.status || 'Active' }}</span>
                            <span v-if="student.is_defaulter" class="defaulter-pill" title="Fee defaulter" style="margin-left:6px;">Defaulter</span>
                        </td>
                        <td>
                            <Button variant="secondary" size="xs" as="link" :href="`/school/students/${student.id}`">View</Button>
                        </td>
                    </tr>
                </tbody>
            </Table>
            <EmptyState
                v-else
                title="No students found"
                description="Try adjusting your search or filters."
            />
        </div>

        <!-- ── Card View ── -->
        <div v-if="viewMode === 'card' && students.data.length === 0" class="card" style="margin-bottom:20px;">
            <EmptyState
                title="No students found"
                description="Try adjusting your search or filters."
            />
        </div>
        <div v-else-if="viewMode === 'card'" class="students-card-grid" style="margin-bottom:20px;">
            <Link v-for="student in students.data" :key="student.id" v-memo="[student.id, student.status, student.photo, student.is_defaulter]" :href="`/school/students/${student.id}`" class="student-card">
                <!-- Status dot -->
                <div class="student-card-status" :class="student.status === 'Active' ? 'student-card-status--active' : 'student-card-status--inactive'"></div>

                <!-- Defaulter ribbon -->
                <span v-if="student.is_defaulter" class="student-card-defaulter" title="Fee defaulter">Defaulter</span>

                <!-- Avatar -->
                <div class="student-card-avatar">
                    <img v-if="student.photo" :src="`/storage/${student.photo}`" class="student-avatar-img" />
                    <span v-else class="student-avatar-initials">{{ student.first_name?.charAt(0) }}{{ student.last_name?.charAt(0) }}</span>
                </div>

                <!-- Info -->
                <div class="student-card-body">
                    <div class="student-card-name">{{ student.first_name }} {{ student.last_name }}</div>
                    <div v-if="student.erp_no" class="student-card-erp">{{ student.erp_no }}</div>
                    <div class="student-card-adm">{{ student.admission_no || '—' }}</div>

                    <div class="student-card-meta">
                        <div class="student-meta-item">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                            <span>{{ student.current_academic_history ? `${student.current_academic_history.course_class?.name} · ${student.current_academic_history.section?.name || ''}` : 'Unassigned' }}</span>
                        </div>
                        <div class="student-meta-item">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span>{{ student.student_parent?.father_name || student.student_parent?.mother_name || student.student_parent?.guardian_name || '—' }}</span>
                        </div>
                        <div v-if="student.current_house_assignment?.house" class="student-meta-item">
                            <span class="house-dot-sm" :style="{ background: student.current_house_assignment.house.color }"></span>
                            <span style="font-weight:600;" :style="{ color: student.current_house_assignment.house.color }">{{ student.current_house_assignment.house.name }}</span>
                        </div>
                        <div class="student-meta-item">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <span>{{ student.student_parent?.primary_phone || '—' }}</span>
                        </div>
                    </div>
                </div>
            </Link>
        </div>

        <!-- Pagination -->
        <div v-if="students.total > 0" class="card">
            <div class="pg-footer">
                <p class="pg-summary">
                    Showing <strong>{{ students.from }}</strong>–<strong>{{ students.to }}</strong> of <strong>{{ students.total }}</strong> students
                </p>

                <!-- Per-page selector -->
                <label class="pg-per-page">
                    <span class="pg-per-page-label">Show</span>
                    <select v-model.number="perPage" class="pg-per-page-select">
                        <option v-for="n in PER_PAGE_OPTIONS" :key="n" :value="n">{{ n }}</option>
                    </select>
                    <span class="pg-per-page-label">per page</span>
                </label>

                <nav v-if="students.links.length > 3" class="pg-nav">
                    <Link v-for="(link, k) in students.links" :key="k"
                        :href="link.url || '#'"
                        v-html="link.label"
                        :class="['pg-btn', link.active ? 'pg-btn--active' : '', !link.url ? 'pg-btn--disabled' : '']" />
                </nav>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* View toggle */
.view-toggle {
    display: flex;
    gap: 2px;
    background: #f1f5f9;
    padding: 3px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.view-toggle-btn {
    display: flex; align-items: center; justify-content: center;
    width: 30px; height: 30px;
    border-radius: 6px;
    border: none; background: transparent;
    color: #94a3b8; cursor: pointer;
    transition: all 0.14s;
}
.view-toggle-btn:hover { color: #475569; }
.view-toggle-btn--active { background: #fff; color: #6366f1; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }

/* Student card grid */
.students-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 16px;
}

/* Student card */
.student-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 12px;
    position: relative;
    overflow: hidden;
    text-decoration: none;
    transition: all 0.18s;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.student-card:hover {
    border-color: #a5b4fc;
    box-shadow: 0 6px 24px rgba(99,102,241,0.12);
    transform: translateY(-2px);
}
.student-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
    opacity: 0;
    transition: opacity 0.18s;
}
.student-card:hover::before { opacity: 1; }

/* House badges */
.house-pill {
    display: inline-block; padding: 2px 9px; border-radius: 20px;
    font-size: .72rem; font-weight: 700; border: 1px solid transparent;
}
.house-dot-sm {
    display: inline-block; width: 8px; height: 8px;
    border-radius: 50%; flex-shrink: 0;
}

/* Status dot */
.student-card-status {
    position: absolute;
    top: 14px; right: 14px;
    width: 8px; height: 8px;
    border-radius: 50%;
}
.student-card-status--active { background: #10b981; box-shadow: 0 0 0 2px #d1fae5; }
.student-card-status--inactive { background: #ef4444; box-shadow: 0 0 0 2px #fee2e2; }

/* Defaulter ribbon (card view) — sits top-left opposite the status dot */
.student-card-defaulter {
    position: absolute;
    top: 10px; left: 10px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.35);
    z-index: 1;
}

/* Defaulter pill (list view) */
.defaulter-pill {
    display: inline-block;
    background: #fee2e2;
    color: #b91c1c;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    padding: 2px 8px;
    border-radius: 10px;
    border: 1px solid #fecaca;
}

/* Avatar */
.student-card-avatar {
    width: 64px; height: 64px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #f0f0ff;
    box-shadow: 0 2px 10px rgba(99,102,241,0.15);
    flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #e0e7ff, #ede9fe);
}
.student-avatar-img { width: 100%; height: 100%; object-fit: cover; }
.student-avatar-initials { font-size: 1.125rem; font-weight: 800; color: #6366f1; }

/* Card body */
.student-card-body { width: 100%; }
.student-card-name { font-size: 0.9375rem; font-weight: 700; color: #0f172a; line-height: 1.3; }
.student-card-erp { font-size: 0.65rem; font-family: monospace; color: #0d9488; font-weight: 700; margin-top: 3px; background: #ccfbf1; padding: 1px 8px; border-radius: 10px; display: inline-block; letter-spacing: 0.02em; }
.student-card-adm { font-size: 0.7rem; font-family: monospace; color: #6366f1; font-weight: 600; margin-top: 2px; background: #e0e7ff; padding: 1px 8px; border-radius: 10px; display: inline-block; }
.erp-no-badge { font-family: monospace; font-size: 0.78rem; font-weight: 700; color: #0d9488; background: #ccfbf1; padding: 2px 8px; border-radius: 5px; white-space: nowrap; }
.student-card-meta { margin-top: 10px; display: flex; flex-direction: column; gap: 5px; text-align: left; }
.student-meta-item { display: flex; align-items: center; gap: 7px; font-size: 0.775rem; color: #64748b; }
.student-meta-item svg { flex-shrink: 0; color: #a5b4fc; }
.student-meta-item span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Small list avatar */
.student-avatar-sm {
    width: 34px; height: 34px;
    border-radius: 8px;
    background: linear-gradient(135deg, #e0e7ff, #ede9fe);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 700; color: #6366f1;
    flex-shrink: 0; overflow: hidden;
}

/* Pagination footer */
.pg-footer {
    padding: 12px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.pg-summary {
    font-size: 0.8125rem;
    color: #64748b;
    margin: 0;
}
.pg-nav {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}

/* Per-page selector */
.pg-per-page {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8125rem;
    color: #64748b;
    cursor: pointer;
    user-select: none;
}
.pg-per-page-label { white-space: nowrap; }
.pg-per-page-select {
    height: 32px;
    padding: 0 26px 0 10px;
    border-radius: 7px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #1e293b;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.13s, box-shadow 0.13s;
    /* custom caret */
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%2394a3b8'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.19l3.71-3.96a.75.75 0 111.08 1.04l-4.25 4.54a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z' clip-rule='evenodd'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 6px center;
    background-size: 14px;
}
.pg-per-page-select:hover { border-color: #a5b4fc; }
.pg-per-page-select:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.pg-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 32px; height: 32px;
    padding: 0 8px;
    border-radius: 7px;
    font-size: 0.8125rem; font-weight: 500;
    border: 1px solid #e2e8f0;
    background: #fff; color: #475569;
    text-decoration: none;
    transition: all 0.13s;
}
.pg-btn:hover { border-color: #6366f1; color: #6366f1; background: #f5f5ff; }
.pg-btn--active { background: #6366f1; border-color: #6366f1; color: #fff; font-weight: 700; }
.pg-btn--disabled { opacity: 0.4; pointer-events: none; }
</style>
