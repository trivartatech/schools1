<script setup>
import { ref, reactive, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Table from '@/Components/ui/Table.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useConfirm } from '@/Composables/useConfirm';

const { canDo } = usePermissions();
const confirm = useConfirm();

const props = defineProps({
    house:              { type: Object, required: true },
    houseStudents:      { type: Array,  default: () => [] },
    points:             { type: Array,  default: () => [] },
    total_points:       { type: Number, default: 0 },
    unassignedStudents: { type: Array,  default: () => [] },
});

const activeTab = ref('students');

// ── Assign Students Modal ─────────────────────────────────────────────────────
const showAssignModal = ref(false);
const assignLoading   = ref(false);
const assignErrors    = ref({});
const selectedIds     = ref([]);
const searchQuery     = ref('');

const filteredUnassigned = computed(() => {
    const q = searchQuery.value.toLowerCase();
    if (!q) return props.unassignedStudents;
    return props.unassignedStudents.filter(s =>
        `${s.first_name} ${s.last_name}`.toLowerCase().includes(q) ||
        s.admission_no.toLowerCase().includes(q)
    );
});

function openAssignModal() {
    selectedIds.value  = [];
    searchQuery.value  = '';
    assignErrors.value = {};
    showAssignModal.value = true;
}

function assignStudents() {
    if (!selectedIds.value.length) return;
    assignLoading.value = true;
    router.post(`/school/houses/${props.house.id}/students`, { student_ids: selectedIds.value }, {
        onSuccess: () => { showAssignModal.value = false; },
        onError:   (e) => { assignErrors.value = e; },
        onFinish:  () => { assignLoading.value = false; },
    });
}

async function removeStudent(studentId) {
    const ok = await confirm({
        title: 'Remove from house?',
        message: 'The student will be moved out of this house.',
        confirmLabel: 'Remove',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/houses/${props.house.id}/students/${studentId}`);
}

// ── Points Modal ──────────────────────────────────────────────────────────────
const showPointsModal = ref(false);
const pointsLoading   = ref(false);
const pointsErrors    = ref({});
const pointsForm      = reactive({ category: 'general', points: '', description: '' });

function openPointsModal() {
    Object.assign(pointsForm, { category: 'general', points: '', description: '' });
    pointsErrors.value = {};
    showPointsModal.value = true;
}

function savePoints() {
    pointsLoading.value = true;
    router.post(`/school/houses/${props.house.id}/points`, { ...pointsForm }, {
        onSuccess: () => { showPointsModal.value = false; },
        onError:   (e) => { pointsErrors.value = e; },
        onFinish:  () => { pointsLoading.value = false; },
    });
}

async function deletePoint(pointId) {
    const ok = await confirm({
        title: 'Delete points entry?',
        message: 'This will adjust the house total. This cannot be undone.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/houses/${props.house.id}/points/${pointId}`);
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const CATEGORY_LABELS = {
    sports: 'Sports', academic: 'Academic', cultural: 'Cultural',
    discipline: 'Discipline', general: 'General',
};
const CATEGORY_COLORS = {
    sports: '#22c55e', academic: '#6366f1', cultural: '#f59e0b',
    discipline: '#ef4444', general: '#94a3b8',
};

function studentName(hs) {
    const s = hs.student;
    return s ? `${s.first_name} ${s.last_name}` : '—';
}
function classSection(hs) {
    const h = hs.student?.current_academic_history;
    if (!h) return '—';
    return [h.course_class?.name, h.section?.name].filter(Boolean).join(' – ');
}
import { useFormat } from '@/Composables/useFormat';
const { formatDate } = useFormat();

const tabsConfig = computed(() => [
    { key: 'students', label: 'Students',    count: props.houseStudents.length },
    { key: 'points',   label: 'Points Log',  count: props.points.length },
]);

function toggleStudent(id) {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) selectedIds.value.push(id);
    else selectedIds.value.splice(idx, 1);
}
</script>

<template>
<SchoolLayout :title="house.name">
    <PageHeader>
        <template #title>
            <div style="display:flex;align-items:center;gap:12px;">
                <div class="house-color-blob" :style="{ background: house.color }"></div>
                <h1 class="page-header-title">{{ house.name }}</h1>
            </div>
        </template>
        <template #subtitle>
            <p class="page-header-sub" style="margin-left:56px;">
                Incharge: <strong>{{ house.incharge?.name || '—' }}</strong>
                &nbsp;·&nbsp;
                Captain: <strong>{{ house.captain ? `${house.captain.first_name} ${house.captain.last_name}` : '—' }}</strong>
            </p>
        </template>
        <template #actions>
            <div class="points-badge" :style="{ background: house.color }">{{ total_points }} pts</div>
            <Button as="link" variant="secondary" href="/school/houses">← Houses</Button>
            <Button as="link" variant="secondary" href="/school/houses/leaderboard">Leaderboard</Button>
        </template>
    </PageHeader>

    <Tabs v-model="activeTab" :tabs="tabsConfig">
        <!-- Students Tab -->
        <template #tab-students>
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 class="card-title">House Members</h3>
                    <Button v-if="canDo('edit','houses')" @click="openAssignModal" size="sm">+ Assign Students</Button>
                </div>
                <Table :empty="!houseStudents.length">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Admission No.</th>
                            <th>Class &amp; Section</th>
                            <th>Gender</th>
                            <th v-if="canDo('edit','houses')" style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(hs, i) in houseStudents" :key="hs.id">
                            <td style="color:var(--text-muted);">{{ i + 1 }}</td>
                            <td style="font-weight:600;">{{ studentName(hs) }}</td>
                            <td>{{ hs.student?.admission_no || '—' }}</td>
                            <td>{{ classSection(hs) }}</td>
                            <td>{{ hs.student?.gender || '—' }}</td>
                            <td v-if="canDo('edit','houses')" style="text-align:right;">
                                <Button variant="danger" size="xs" @click="removeStudent(hs.student_id)">Remove</Button>
                            </td>
                        </tr>
                    </tbody>
                    <template #empty>
                        <EmptyState
                            variant="compact"
                            title="No students assigned yet"
                            description="Use 'Assign Students' to add unassigned students to this house."
                        />
                    </template>
                </Table>
            </div>
        </template>

        <!-- Points Tab -->
        <template #tab-points>
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 class="card-title">Points Log</h3>
                    <Button v-if="canDo('edit','houses')" @click="openPointsModal" size="sm">+ Award / Deduct</Button>
                </div>
                <Table :empty="!points.length">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Points</th>
                            <th>Description</th>
                            <th>By</th>
                            <th v-if="canDo('delete','houses')" style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="pt in points" :key="pt.id">
                            <td style="color:var(--text-muted);font-size:.8rem;">{{ formatDate(pt.created_at) }}</td>
                            <td>
                                <span class="cat-badge" :style="{ background: CATEGORY_COLORS[pt.category] + '22', color: CATEGORY_COLORS[pt.category] }">
                                    {{ CATEGORY_LABELS[pt.category] }}
                                </span>
                            </td>
                            <td>
                                <span class="pts-value" :style="{ color: pt.points > 0 ? '#22c55e' : '#ef4444' }">
                                    {{ pt.points > 0 ? '+' : '' }}{{ pt.points }}
                                </span>
                            </td>
                            <td>{{ pt.description }}</td>
                            <td style="color:var(--text-muted);font-size:.8rem;">{{ pt.awarded_by?.name || '—' }}</td>
                            <td v-if="canDo('delete','houses')" style="text-align:right;">
                                <Button variant="danger" size="xs" @click="deletePoint(pt.id)">Delete</Button>
                            </td>
                        </tr>
                    </tbody>
                    <template #empty>
                        <EmptyState
                            variant="compact"
                            title="No points recorded yet"
                            description="Award or deduct points to track this house's performance."
                        />
                    </template>
                </Table>
            </div>
        </template>
    </Tabs>

    <!-- Assign Students Modal -->
    <Modal v-model:open="showAssignModal" :title="`Assign Students to ${house.name}`" size="md">
        <div v-if="Object.keys(assignErrors).length" class="form-errors">
            <div v-for="(msg, f) in assignErrors" :key="f">{{ msg }}</div>
        </div>

        <input
            v-model="searchQuery"
            class="search-input"
            placeholder="Search by name or admission no..."
            style="margin-bottom:12px;"
        >

        <div class="student-list">
            <label
                v-for="s in filteredUnassigned"
                :key="s.id"
                class="student-item"
                :class="{ selected: selectedIds.includes(s.id) }"
            >
                <input type="checkbox" :value="s.id" v-model="selectedIds" style="display:none;">
                <div class="student-check">
                    <svg v-if="selectedIds.includes(s.id)" viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </div>
                <div>
                    <div style="font-weight:600;font-size:.85rem;">{{ s.first_name }} {{ s.last_name }}</div>
                    <div style="font-size:.75rem;color:var(--text-muted);">{{ s.admission_no }}</div>
                </div>
            </label>
            <p v-if="!filteredUnassigned.length" style="text-align:center;color:var(--text-muted);padding:1.5rem;font-size:.85rem;">
                {{ searchQuery ? 'No matching students.' : 'All students are already assigned to a house.' }}
            </p>
        </div>

        <template #footer>
            <span style="font-size:.8rem;color:var(--text-muted);margin-right:auto;">{{ selectedIds.length }} selected</span>
            <Button variant="secondary" @click="showAssignModal = false">Cancel</Button>
            <Button @click="assignStudents" :loading="assignLoading" :disabled="!selectedIds.length">Assign</Button>
        </template>
    </Modal>

    <!-- Points Modal -->
    <Modal v-model:open="showPointsModal" title="Award / Deduct Points" size="sm">
        <div v-if="Object.keys(pointsErrors).length" class="form-errors">
            <div v-for="(msg, f) in pointsErrors" :key="f">{{ msg }}</div>
        </div>

        <form @submit.prevent="savePoints" id="points-form">
            <div class="form-row form-row-2">
                <div class="form-field">
                    <label>Category <span class="required">*</span></label>
                    <select v-model="pointsForm.category" required>
                        <option value="sports">Sports</option>
                        <option value="academic">Academic</option>
                        <option value="cultural">Cultural</option>
                        <option value="discipline">Discipline</option>
                        <option value="general">General</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Points <span class="required">*</span></label>
                    <input v-model.number="pointsForm.points" type="number" required placeholder="e.g. 10 or -5" min="-999" max="999">
                    <p class="form-hint">Positive = award, negative = deduction</p>
                </div>
            </div>
            <div class="form-field" style="margin-top:12px;">
                <label>Description <span class="required">*</span></label>
                <input v-model="pointsForm.description" required placeholder="e.g. Won inter-house cricket match" maxlength="255">
            </div>
        </form>
        <template #footer>
            <Button variant="secondary" type="button" @click="showPointsModal = false">Cancel</Button>
            <Button type="submit" form="points-form" :loading="pointsLoading">Save</Button>
        </template>
    </Modal>
</SchoolLayout>
</template>

<style scoped>
.house-color-blob {
    width: 44px; height: 44px; border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,.15); flex-shrink: 0;
}
.points-badge {
    padding: 5px 14px; border-radius: 20px; color: #fff;
    font-weight: 800; font-size: .85rem;
}

.cat-badge { padding:2px 9px; border-radius:20px; font-size:.72rem; font-weight:700; }
.pts-value  { font-weight:800; font-size:.95rem; }

.form-errors { background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;font-size:.82rem;color:#dc2626;margin-bottom:14px; }
.required { color:#ef4444; }
.form-hint { font-size:.72rem;color:var(--text-muted);margin-top:3px; }

.search-input { width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:.84rem; }
.student-list { max-height:320px;overflow-y:auto;border:1px solid var(--border);border-radius:8px; }
.student-item {
    display:flex;align-items:center;gap:10px;padding:9px 12px;cursor:pointer;
    border-bottom:1px solid #f1f5f9;transition:background .1s;
}
.student-item:last-child { border-bottom:none; }
.student-item:hover { background:#f8fafc; }
.student-item.selected { background:#eef2ff; }
.student-check {
    width:18px;height:18px;border:1.5px solid var(--border);border-radius:4px;
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
    background:#fff;color:#6366f1;
}
.student-item.selected .student-check { background:#6366f1;border-color:#6366f1;color:#fff; }
</style>
