<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import { ref, reactive, computed, watch } from 'vue';
import { router, Link, Head } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useConfirm } from '@/Composables/useConfirm';
import Table from '@/Components/ui/Table.vue';
import axios from 'axios';

const confirm = useConfirm();

const props = defineProps({
    allocations: Object, // paginator
    items:       Array,
    classes:     Array,
    filters:     Object,
});

const { can } = usePermissions();

// ── Top-level filters ────────────────────────────────────────────────────────
const search = ref(props.filters?.q ?? '');
const status = ref(props.filters?.payment_status ?? '');

function applyFilters() {
    router.get('/school/stationary/allocations',
        { q: search.value, payment_status: status.value },
        { preserveState: true, preserveScroll: true, replace: true });
}

function clearFilters() {
    search.value = '';
    status.value = '';
    applyFilters();
}

// ── New Allocation modal ─────────────────────────────────────────────────────
const showModal = ref(false);
const saving    = ref(false);
const formErrors = ref({});

const form = reactive({
    class_id: '',
    section_id: '',
    student_ids: [],
    lines: [{ item_id: '', qty: 1 }],
    remarks: '',
    status: 'active',
});

const sections = ref([]);
const studentsInClass = ref([]);
const fetchingStudents = ref(false);

async function fetchSections(classId) {
    sections.value = [];
    if (!classId) return;
    try {
        const res = await axios.get(`/school/classes/${classId}/sections`);
        sections.value = res.data || [];
    } catch (e) { console.error('Error fetching sections', e); }
}

async function fetchStudents() {
    studentsInClass.value = [];
    form.student_ids = [];
    if (!form.class_id) return;
    fetchingStudents.value = true;
    try {
        let url = `/school/stationary/allocations/students-by-class?class_id=${form.class_id}`;
        if (form.section_id) url += `&section_id=${form.section_id}`;
        const { data } = await axios.get(url);
        studentsInClass.value = data;
    } finally {
        fetchingStudents.value = false;
    }
}

watch(() => form.class_id, async (val) => {
    form.section_id = '';
    sections.value = [];
    await fetchSections(val);
    fetchStudents();
});
watch(() => form.section_id, () => fetchStudents());

function toggleAllStudents(event) {
    if (event.target.checked) {
        form.student_ids = studentsInClass.value.map(s => s.id);
    } else {
        form.student_ids = [];
    }
}

function openModal() {
    Object.assign(form, {
        class_id: '', section_id: '', student_ids: [],
        lines: [{ item_id: '', qty: 1 }], remarks: '', status: 'active',
    });
    sections.value = [];
    studentsInClass.value = [];
    formErrors.value = {};
    showModal.value = true;
}

function addLine()  { form.lines.push({ item_id: '', qty: 1 }); }
function removeLine(i) { form.lines.splice(i, 1); }

const perStudentTotal = computed(() => {
    return form.lines.reduce((sum, line) => {
        const item = props.items.find(i => i.id == line.item_id);
        if (!item) return sum;
        return sum + (parseFloat(item.unit_price) * (parseInt(line.qty) || 0));
    }, 0);
});

const validLines = computed(() => form.lines.filter(l => l.item_id && l.qty > 0));

function save() {
    saving.value = true;
    formErrors.value = {};
    router.post('/school/stationary/allocations', { ...form }, {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false; },
        onError:   (e) => { formErrors.value = e; },
        onFinish:  () => { saving.value = false; },
    });
}

async function destroy(a) {
    const ok = await confirm({
        title: 'Remove allocation?',
        message: 'Receipts and issuances will block deletion if they exist.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/stationary/allocations/${a.id}`, { preserveScroll: true });
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function fmt(n) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 }).format(n ?? 0);
}

function studentName(a) {
    return a?.student?.user?.name
        || [a?.student?.first_name, a?.student?.last_name].filter(Boolean).join(' ')
        || '—';
}

const paymentBadge = (s) => ({
    paid: 'badge-green', partial: 'badge-amber', unpaid: 'badge-red', waived: 'badge-gray',
})[s] || 'badge-gray';

const collectionBadge = (s) => ({
    complete: 'badge-green', partial: 'badge-amber', none: 'badge-red',
})[s] || 'badge-gray';
</script>

<template>
    <Head title="Stationary Allocations" />
    <SchoolLayout title="Stationary Allocations">
        <PageHeader title="Stationary Allocations" subtitle="Assign stationary kits to students by class & section.">
            <template #actions>
                <Button v-if="can('create_stationary_allocations')" @click="openModal">+ New Allocation</Button>
            </template>
        </PageHeader>

        <!-- Filters -->
        <FilterBar :active="!!(search || status)" @clear="clearFilters">
            <div class="fb-search">
                <svg class="fb-search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <input v-model="search" @keydown.enter="applyFilters" type="text" placeholder="Search by student name or admission no..." />
            </div>
            <select v-model="status" @change="applyFilters">
                <option value="">All Payment Status</option>
                <option value="unpaid">Unpaid</option>
                <option value="partial">Partial</option>
                <option value="paid">Paid</option>
                <option value="waived">Waived</option>
            </select>
            <Button variant="secondary" size="sm" @click="applyFilters">Apply</Button>
        </FilterBar>

        <!-- Table -->
        <div class="card">
            <Table :empty="!allocations.data.length">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Adm No.</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:right;">Paid</th>
                        <th style="text-align:right;">Balance</th>
                        <th>Payment</th>
                        <th>Items</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in allocations.data" :key="a.id">
                        <td style="font-weight:500;">{{ studentName(a) }}</td>
                        <td style="font-family:monospace;font-size:0.82rem;color:#64748b;">{{ a.student?.admission_no || '—' }}</td>
                        <td style="text-align:right;">{{ fmt(a.total_amount) }}</td>
                        <td style="text-align:right;color:var(--success, #059669);">{{ fmt(a.amount_paid) }}</td>
                        <td style="text-align:right;" :style="parseFloat(a.balance) > 0 ? 'color:#dc2626;font-weight:600;' : ''">{{ fmt(a.balance) }}</td>
                        <td><span :class="['badge', paymentBadge(a.payment_status)]">{{ a.payment_status }}</span></td>
                        <td><span :class="['badge', collectionBadge(a.collection_status)]">{{ a.collection_status }}</span></td>
                        <td style="text-align:right;">
                            <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.375rem;">
                                <Link :href="`/school/stationary/allocations/${a.id}`">
                                    <Button variant="secondary" size="xs">View · Issue · Return</Button>
                                </Link>
                                <Link :href="`/school/stationary/fees/${a.id}`">
                                    <Button size="xs">Pay</Button>
                                </Link>
                                <Button v-if="can('delete_stationary_allocations')" variant="danger" size="xs" @click="destroy(a)">×</Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <template #empty>
                    <EmptyState
                        title="No allocations yet"
                        description="Click '+ New Allocation' to assign kits to a class."
                        :action-label="can('create_stationary_allocations') ? '+ New Allocation' : ''"
                        @action="openModal"
                    />
                </template>
            </Table>

            <div v-if="allocations.last_page > 1" style="padding: 0.75rem 1rem; display: flex; gap: 0.375rem; flex-wrap: wrap; border-top: 1px solid var(--border, #e5e7eb);">
                <a v-for="link in allocations.links" :key="link.label"
                   :href="link.url || '#'" v-html="link.label"
                   :class="link.active ? 'pgn pgn-active' : 'pgn'"
                   :style="!link.url ? 'pointer-events:none;opacity:0.4' : ''"></a>
            </div>
        </div>

        <!-- New Allocation Modal -->
        <Modal v-model:open="showModal" title="New Stationary Allocation" size="lg">
            <form @submit.prevent="save" id="allocation-form">
                <!-- Validation errors -->
                <div v-if="Object.keys(formErrors).length" style="background:#fef2f2;border:1px solid #fecaca;border-radius:0.5rem;padding:0.75rem 1rem;margin-bottom:1rem;">
                    <p v-for="(msg, key) in formErrors" :key="key" style="font-size:0.8rem;color:#dc2626;margin:0.125rem 0;">
                        {{ Array.isArray(msg) ? msg[0] : msg }}
                    </p>
                </div>

                <!-- Class + Section -->
                <div class="form-row-2">
                    <div class="form-field">
                        <label>Class *</label>
                        <select v-model="form.class_id" required>
                            <option value="">— Select Class —</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Section</label>
                        <select v-model="form.section_id" :disabled="!form.class_id">
                            <option value="">— All Sections —</option>
                            <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- Student multi-select with select-all -->
                <div v-if="form.class_id" class="form-field" style="margin-bottom: 1rem;">
                    <label style="display:flex;align-items:center;justify-content:space-between;">
                        <span>Select Students ({{ studentsInClass.length }} eligible · {{ form.student_ids.length }} chosen) *</span>
                        <label v-if="studentsInClass.length > 0" style="display:flex;align-items:center;gap:0.375rem;cursor:pointer;font-weight:400;font-size:0.82rem;">
                            <input type="checkbox" @change="toggleAllStudents"
                                   :checked="studentsInClass.length > 0 && form.student_ids.length === studentsInClass.length">
                            <span>Select All</span>
                        </label>
                    </label>

                    <div v-if="fetchingStudents" style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:1rem;background:#f9fafb;text-align:center;font-size:0.82rem;color:#6b7280;">
                        Loading students…
                    </div>
                    <div v-else-if="studentsInClass.length === 0" style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:1rem;background:#f9fafb;text-align:center;font-size:0.82rem;color:#6b7280;">
                        No eligible students (all already have an allocation in this academic year).
                    </div>
                    <div v-else style="border:1px solid #e5e7eb;border-radius:0.5rem;max-height:14rem;overflow-y:auto;background:#fff;">
                        <label v-for="s in studentsInClass" :key="s.id"
                               style="display:flex;align-items:center;gap:0.625rem;padding:0.5rem 0.75rem;cursor:pointer;border-bottom:1px solid #f3f4f6;">
                            <input type="checkbox" v-model="form.student_ids" :value="s.id">
                            <div style="display:flex;flex-direction:column;flex:1;">
                                <span style="font-size:0.875rem;font-weight:500;color:#111827;">{{ s.name }}</span>
                                <span style="font-size:0.75rem;color:#9ca3af;font-family:monospace;">{{ s.admission_no }}</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Kit items -->
                <div class="form-field">
                    <label>Kit Items *</label>
                    <div style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.625rem;background:#fafafa;">
                        <div v-for="(line, i) in form.lines" :key="i"
                             style="display:grid;grid-template-columns:1fr 90px 100px 32px;gap:0.5rem;align-items:center;margin-bottom:0.375rem;">
                            <select v-model="line.item_id" required>
                                <option value="">— Select Item —</option>
                                <option v-for="it in items" :key="it.id" :value="it.id">
                                    {{ it.name }} — {{ fmt(it.unit_price) }} (stock {{ it.current_stock }})
                                </option>
                            </select>
                            <input v-model.number="line.qty" type="number" min="1" placeholder="Qty" />
                            <div style="text-align:right;font-size:0.82rem;color:#475569;font-weight:500;">
                                {{ fmt((items.find(i => i.id == line.item_id)?.unit_price || 0) * (line.qty || 0)) }}
                            </div>
                            <button v-if="form.lines.length > 1" type="button" @click="removeLine(i)"
                                    style="background:#fee2e2;color:#dc2626;border:0;border-radius:0.375rem;padding:0.375rem;cursor:pointer;font-weight:600;">×</button>
                        </div>
                        <Button variant="secondary" size="xs" type="button" @click="addLine">+ Add Line</Button>
                    </div>
                </div>

                <!-- Per-student total -->
                <div style="background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.2);border-radius:0.5rem;padding:0.625rem 0.875rem;display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                    <span style="font-size:0.82rem;color:#475569;">Per-student kit total</span>
                    <span style="font-size:1.05rem;font-weight:700;color:#1e293b;">{{ fmt(perStudentTotal) }}</span>
                </div>

                <div class="form-field">
                    <label>Remarks</label>
                    <textarea v-model="form.remarks" rows="2"></textarea>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showModal = false">Cancel</Button>
                <Button type="submit" form="allocation-form" :loading="saving"
                        :disabled="!form.student_ids.length || !validLines.length">
                    {{ saving ? 'Saving…' : `Allocate to ${form.student_ids.length} student(s)` }}
                </Button>
            </template>
        </Modal>
    </SchoolLayout>
</template>

<style scoped>
.badge {
    display: inline-block;
    padding: 0.25rem 0.625rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: capitalize;
}
.badge-green { background: #d1fae5; color: #059669; }
.badge-amber { background: #fef3c7; color: #b45309; }
.badge-red   { background: #fee2e2; color: #dc2626; }
.badge-gray  { background: #f1f5f9; color: #94a3b8; }

/* Form fields — Tailwind preflight workaround */
.form-field { display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: 0.875rem; }
.form-field label { font-size: 0.78rem; font-weight: 600; color: #475569; }
.form-field input, .form-field select, .form-field textarea {
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    outline: none;
    width: 100%;
    background: #fff;
}
.form-field input:focus, .form-field select:focus, .form-field textarea:focus {
    border-color: var(--accent, #6366f1);
}
.form-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.875rem;
    margin-bottom: 0.875rem;
}
@media (max-width: 640px) {
    .form-row-2 { grid-template-columns: 1fr; }
}

.pgn { padding: 0.25rem 0.625rem; border: 1px solid var(--border, #e5e7eb); border-radius: 0.375rem; font-size: 0.78rem; color: #475569; text-decoration: none; background: white; }
.pgn-active { background: var(--accent, #6366f1); color: white; border-color: var(--accent, #6366f1); }
</style>
