<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, reactive, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import Table from '@/Components/ui/Table.vue';
import axios from 'axios';

const props = defineProps({
    allocations: Object, // paginator
    items:       Array,
    classes:     Array,
    filters:     Object,
});

const { can } = usePermissions();

const search = ref(props.filters?.q ?? '');
const status = ref(props.filters?.payment_status ?? '');

const showModal = ref(false);
const saving    = ref(false);
const errors    = ref({});

const form = reactive({
    class_id: '',
    section_id: '',
    student_ids: [],
    lines: [],
    remarks: '',
    status: 'active',
});

const studentsInClass = ref([]);
const loadingStudents = ref(false);

function applyFilters() {
    router.get('/school/stationary/allocations',
        { q: search.value, payment_status: status.value },
        { preserveState: true, preserveScroll: true, replace: true });
}

function openModal() {
    Object.assign(form, { class_id: '', section_id: '', student_ids: [], lines: [{ item_id: '', qty: 1 }], remarks: '', status: 'active' });
    studentsInClass.value = [];
    errors.value = {};
    showModal.value = true;
}

async function loadStudents() {
    if (!form.class_id) { studentsInClass.value = []; return; }
    loadingStudents.value = true;
    try {
        const { data } = await axios.get('/school/stationary/allocations/students-by-class', {
            params: { class_id: form.class_id, section_id: form.section_id || undefined },
        });
        studentsInClass.value = data;
    } finally {
        loadingStudents.value = false;
    }
}

function addLine()  { form.lines.push({ item_id: '', qty: 1 }); }
function removeLine(i) { form.lines.splice(i, 1); }

const total = computed(() => {
    return form.lines.reduce((sum, line) => {
        const item = props.items.find(i => i.id == line.item_id);
        if (!item) return sum;
        return sum + (parseFloat(item.unit_price) * (parseInt(line.qty) || 0));
    }, 0);
});

function save() {
    saving.value = true;
    errors.value = {};
    router.post('/school/stationary/allocations', { ...form }, {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false; },
        onError:   (e) => { errors.value = e; },
        onFinish:  () => { saving.value = false; },
    });
}

function destroy(a) {
    if (!confirm('Remove this allocation? Receipts and issuances will block deletion if they exist.')) return;
    router.delete(`/school/stationary/allocations/${a.id}`, { preserveScroll: true });
}

function fmt(n) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 }).format(n ?? 0);
}

function studentName(a) {
    return a?.student?.user?.name
        || [a?.student?.first_name, a?.student?.last_name].filter(Boolean).join(' ')
        || '—';
}

const statusBadge = (s) => ({
    paid: 'badge-green', partial: 'badge-amber', unpaid: 'badge-red', waived: 'badge-gray',
})[s] || 'badge-gray';

const collectionBadge = (s) => ({
    complete: 'badge-green', partial: 'badge-amber', none: 'badge-red',
})[s] || 'badge-gray';
</script>

<template>
    <SchoolLayout title="Stationary Allocations">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Stationary Allocations</h1>
                <p class="page-header-sub">Assign stationary kits to students</p>
            </div>
            <Button v-if="can('create_stationary_allocations')" @click="openModal">+ New Allocation</Button>
        </div>

        <div class="card" style="margin-bottom: 16px;">
            <div class="card-body" style="padding: 12px 16px; display: flex; gap: 10px; align-items: center;">
                <input v-model="search" @keydown.enter="applyFilters" type="text"
                       placeholder="Search by name or admission no..."
                       style="flex: 1; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.86rem;" />
                <select v-model="status" @change="applyFilters"
                        style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.86rem;">
                    <option value="">All Payment Status</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                    <option value="waived">Waived</option>
                </select>
                <Button variant="secondary" @click="applyFilters">Apply</Button>
            </div>
        </div>

        <div class="card">
            <Table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Adm No.</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Balance</th>
                        <th>Payment</th>
                        <th>Items</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in allocations.data" :key="a.id">
                        <td>{{ studentName(a) }}</td>
                        <td><span style="font-family: monospace;">{{ a.student?.admission_no || '—' }}</span></td>
                        <td class="text-right">{{ fmt(a.total_amount) }}</td>
                        <td class="text-right">{{ fmt(a.amount_paid) }}</td>
                        <td class="text-right" :style="parseFloat(a.balance) > 0 ? 'color:#dc2626;font-weight:600;' : ''">{{ fmt(a.balance) }}</td>
                        <td><span :class="['badge', statusBadge(a.payment_status)]">{{ a.payment_status }}</span></td>
                        <td><span :class="['badge', collectionBadge(a.collection_status)]">{{ a.collection_status }}</span></td>
                        <td>
                            <Link :href="`/school/stationary/allocations/${a.id}`" class="btn-link">View</Link>
                            <Button v-if="can('delete_stationary_allocations')" size="sm" variant="danger" @click="destroy(a)">Delete</Button>
                        </td>
                    </tr>
                    <tr v-if="!allocations.data.length">
                        <td colspan="8" style="text-align: center; padding: 28px; color: #94a3b8;">
                            No allocations yet.
                        </td>
                    </tr>
                </tbody>
            </Table>
            <div v-if="allocations.last_page > 1" style="padding: 12px 16px; display: flex; gap: 6px; flex-wrap: wrap;">
                <a v-for="link in allocations.links" :key="link.label"
                   :href="link.url || '#'" v-html="link.label"
                   :class="link.active ? 'pgn pgn-active' : 'pgn'"
                   :style="!link.url ? 'pointer-events:none;opacity:0.4' : ''"></a>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
            <div class="modal-card" style="max-width: 720px;">
                <div class="modal-header">
                    <h3>New Allocation</h3>
                    <button class="modal-close" @click="showModal = false">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-row-2">
                        <div>
                            <label>Class *</label>
                            <select v-model="form.class_id" @change="loadStudents" class="form-input">
                                <option value="">— Select Class —</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label>Section</label>
                            <input v-model="form.section_id" @change="loadStudents" type="text" class="form-input" placeholder="(optional, not implemented yet)" />
                        </div>
                    </div>

                    <div class="form-row" v-if="form.class_id">
                        <label>Students <small>({{ form.student_ids.length }} selected of {{ studentsInClass.length }})</small></label>
                        <div v-if="loadingStudents" style="font-size: 0.8rem; color: #94a3b8;">Loading…</div>
                        <div v-else style="max-height: 180px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px;">
                            <label v-for="s in studentsInClass" :key="s.id" style="display: flex; gap: 8px; padding: 4px 0; font-size: 0.85rem; cursor: pointer;">
                                <input type="checkbox" :value="s.id" v-model="form.student_ids" />
                                <span>{{ s.name }} <small style="color:#94a3b8;">({{ s.admission_no }})</small></span>
                            </label>
                            <p v-if="!studentsInClass.length" style="text-align: center; color: #94a3b8; font-size: 0.82rem; padding: 8px;">
                                No eligible students in this class (or all already have an allocation).
                            </p>
                        </div>
                        <p v-if="errors.student_ids" class="form-err">{{ errors.student_ids }}</p>
                    </div>

                    <div class="form-row">
                        <label>Kit Items *</label>
                        <div v-for="(line, i) in form.lines" :key="i" style="display: grid; grid-template-columns: 1fr 100px 90px 32px; gap: 8px; align-items: end; margin-bottom: 6px;">
                            <select v-model="line.item_id" class="form-input">
                                <option value="">— Select Item —</option>
                                <option v-for="it in items" :key="it.id" :value="it.id">
                                    {{ it.name }} ({{ fmt(it.unit_price) }}, stock {{ it.current_stock }})
                                </option>
                            </select>
                            <input v-model.number="line.qty" type="number" min="1" class="form-input" placeholder="Qty" />
                            <div style="font-size: 0.8rem; color: #475569; padding: 6px;">
                                {{ fmt((items.find(i => i.id == line.item_id)?.unit_price || 0) * (line.qty || 0)) }}
                            </div>
                            <button v-if="form.lines.length > 1" type="button" @click="removeLine(i)" style="background:#fee2e2;color:#dc2626;border:0;border-radius:6px;padding:6px;cursor:pointer;">×</button>
                        </div>
                        <Button variant="secondary" size="sm" type="button" @click="addLine">+ Add Line</Button>
                        <p v-if="errors.lines" class="form-err">{{ errors.lines }}</p>
                    </div>

                    <div style="background:#f8fafc;padding:10px 14px;border-radius:8px;display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:0.86rem;color:#475569;">Per-student total</span>
                        <span style="font-size:1.1rem;font-weight:700;color:#1e293b;">{{ fmt(total) }}</span>
                    </div>

                    <div class="form-row">
                        <label>Remarks</label>
                        <textarea v-model="form.remarks" rows="2" class="form-input"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <Button variant="secondary" @click="showModal = false">Cancel</Button>
                    <Button :loading="saving" @click="save" :disabled="!form.student_ids.length || !form.lines.length">
                        Allocate to {{ form.student_ids.length }} student(s)
                    </Button>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.text-right { text-align: right; }
.badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
.badge-green { background: #d1fae5; color: #059669; }
.badge-amber { background: #fef3c7; color: #b45309; }
.badge-red   { background: #fee2e2; color: #dc2626; }
.badge-gray  { background: #f1f5f9; color: #94a3b8; }
.btn-link { color: #6366f1; font-size: 0.84rem; padding: 4px 8px; text-decoration: none; }
.btn-link:hover { background: #eef2ff; border-radius: 4px; }

.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); display: flex; align-items: flex-start; justify-content: center; z-index: 50; padding: 40px 20px; overflow-y: auto; }
.modal-card { background: white; border-radius: 12px; width: 100%; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 22px; border-bottom: 1px solid #e2e8f0; }
.modal-header h3 { font-size: 1.05rem; font-weight: 700; color: #1e293b; margin: 0; }
.modal-close { background: none; border: 0; font-size: 1.4rem; color: #94a3b8; cursor: pointer; }
.modal-body { padding: 18px 22px; display: flex; flex-direction: column; gap: 14px; max-height: 70vh; overflow-y: auto; }
.modal-footer { padding: 14px 22px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; }

.form-row { display: flex; flex-direction: column; gap: 4px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-row label, .form-row-2 label { font-size: 0.78rem; font-weight: 600; color: #475569; }
.form-input { border: 1px solid #cbd5e1; border-radius: 8px; padding: 7px 10px; font-size: 0.86rem; outline: none; width: 100%; }
.form-input:focus { border-color: #6366f1; }
.form-err { font-size: 0.74rem; color: #dc2626; }

.pgn { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.78rem; color: #475569; text-decoration: none; background: white; }
.pgn-active { background: #6366f1; color: white; border-color: #6366f1; }
</style>
