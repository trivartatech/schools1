<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Table from '@/Components/ui/Table.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    issues: Object,
    books: Array,
    students: Array,
    staff: Array,
    settings: Object,
    filters: Object,
});

// ── Filters ───────────────────────────────────────────────────────
const statusFilter = ref(props.filters?.status ?? '');
const typeFilter   = ref(props.filters?.borrower_type ?? '');

const applyFilters = () => {
    router.get('/school/library/issues', { status: statusFilter.value, borrower_type: typeFilter.value }, { preserveScroll: true, replace: true });
};

const clearFilters = () => {
    statusFilter.value = '';
    typeFilter.value = '';
    applyFilters();
};

// ── Issue Book Modal ──────────────────────────────────────────────
const showIssue = ref(false);

const issueForm = useForm({
    book_id: '', borrower_type: 'student', student_id: '', staff_id: '',
    issue_date: new Date().toISOString().split('T')[0], notes: '',
});

const submitIssue = () => {
    issueForm.post('/school/library/issues', { preserveScroll: true, onSuccess: () => { showIssue.value = false; issueForm.reset(); } });
};

// ── Return Modal ──────────────────────────────────────────────────
const returnIssue = ref(null);
const showReturn = ref(false);
const returnForm  = useForm({ return_date: new Date().toISOString().split('T')[0], notes: '' });

const openReturn = (issue) => {
    returnIssue.value = issue;
    returnForm.return_date = new Date().toISOString().split('T')[0];
    returnForm.notes = '';
    showReturn.value = true;
};

const submitReturn = () => {
    returnForm.patch(`/school/library/issues/${returnIssue.value.id}/return`, {
        preserveScroll: true,
        onSuccess: () => { showReturn.value = false; returnIssue.value = null; },
    });
};

const markFinePaid = (id) => {
    router.patch(`/school/library/issues/${id}/fine-paid`, {}, { preserveScroll: true });
};

import { useFormat } from '@/Composables/useFormat';
const { formatDate: fmtDate } = useFormat();
</script>

<template>
    <SchoolLayout title="Library — Issues">
        <PageHeader title="Book Issues" subtitle="Track issued, overdue, and returned library books.">
            <template #actions>
                <Button @click="showIssue = true">Issue Book</Button>
            </template>
        </PageHeader>

        <!-- Filters -->
        <FilterBar :active="!!(statusFilter || typeFilter)" @clear="clearFilters">
            <select v-model="statusFilter" @change="applyFilters" style="width:160px;">
                <option value="">All Statuses</option>
                <option value="issued">Issued</option>
                <option value="overdue">Overdue</option>
                <option value="returned">Returned</option>
                <option value="lost">Lost</option>
            </select>
            <select v-model="typeFilter" @change="applyFilters" style="width:160px;">
                <option value="">All Types</option>
                <option value="student">Students</option>
                <option value="staff">Staff</option>
            </select>
        </FilterBar>

        <!-- Issues Table -->
        <div class="card">
            <Table :empty="!issues.data?.length">
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Borrower</th>
                        <th>Issued</th>
                        <th>Due</th>
                        <th>Returned</th>
                        <th>Status</th>
                        <th>Fine</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="iss in issues.data" :key="iss.id">
                        <td>
                            <div style="font-weight:500;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ iss.book?.title }}</div>
                        </td>
                        <td>
                            <div>{{ iss.borrower_type === 'student' ? (iss.student?.first_name + ' ' + iss.student?.last_name) : iss.staff?.user?.name }}</div>
                            <div style="font-size:.7rem;color:#94a3b8;text-transform:capitalize;">{{ iss.borrower_type }}</div>
                        </td>
                        <td style="white-space:nowrap;">{{ fmtDate(iss.issue_date) }}</td>
                        <td style="white-space:nowrap;" :style="{ color: iss.status === 'overdue' ? '#dc2626' : undefined }">{{ fmtDate(iss.due_date) }}</td>
                        <td style="white-space:nowrap;">{{ fmtDate(iss.return_date) }}</td>
                        <td>
                            <span class="badge" :class="{
                                'badge-green': iss.status === 'returned',
                                'badge-amber': iss.status === 'issued',
                                'badge-red':   iss.status === 'overdue',
                                'badge-gray':  iss.status === 'lost',
                            }">{{ iss.status }}</span>
                        </td>
                        <td>
                            <span v-if="iss.fine_amount > 0">
                                <span :style="{ color: iss.fine_paid ? '#16a34a' : '#dc2626', fontWeight: 600 }">₹{{ iss.fine_amount }}</span>
                                <span v-if="!iss.fine_paid" style="font-size:.7rem;color:#94a3b8;"> unpaid</span>
                            </span>
                            <span v-else>—</span>
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                <Button v-if="iss.status !== 'returned' && iss.status !== 'lost'" variant="success" size="xs" @click="openReturn(iss)">Return</Button>
                                <Button v-if="iss.fine_amount > 0 && !iss.fine_paid" variant="secondary" size="xs" @click="markFinePaid(iss.id)">Fine Paid</Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <template #empty>
                    <EmptyState
                        title="No issues found"
                        description="Issue books to students or staff to see records here."
                        action-label="Issue Book"
                        @action="showIssue = true"
                    />
                </template>
            </Table>
        </div>

        <!-- Issue Book Modal -->
        <Modal v-model:open="showIssue" title="Issue Book" size="md">
            <form @submit.prevent="submitIssue" id="issue-form">
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <div class="form-field">
                        <label>Book *</label>
                        <select v-model="issueForm.book_id" required>
                            <option value="">Select book</option>
                            <option v-for="b in books" :key="b.id" :value="b.id">{{ b.title }} ({{ b.available_copies }} left)</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Borrower Type *</label>
                        <select v-model="issueForm.borrower_type">
                            <option value="student">Student</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div v-if="issueForm.borrower_type === 'student'" class="form-field">
                        <label>Student *</label>
                        <select v-model="issueForm.student_id" required>
                            <option value="">Select student</option>
                            <option v-for="s in students" :key="s.id" :value="s.id">{{ s.first_name }} {{ s.last_name }} ({{ s.admission_no }})</option>
                        </select>
                    </div>
                    <div v-else class="form-field">
                        <label>Staff *</label>
                        <select v-model="issueForm.staff_id" required>
                            <option value="">Select staff</option>
                            <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.user?.name }} ({{ s.employee_id }})</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Issue Date *</label>
                        <input v-model="issueForm.issue_date" type="date" required />
                    </div>
                    <div class="form-field">
                        <label>Notes</label>
                        <input v-model="issueForm.notes" />
                    </div>
                    <div style="font-size:.8rem;color:#64748b;background:#f1f5f9;padding:8px 12px;border-radius:6px;">
                        Due date will be {{ settings?.max_issue_days ?? 14 }} days from issue date. Fine: ₹{{ settings?.fine_per_day ?? 1 }}/day.
                    </div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showIssue = false">Cancel</Button>
                <Button type="submit" form="issue-form" :loading="issueForm.processing">Issue Book</Button>
            </template>
        </Modal>

        <!-- Return Modal -->
        <Modal v-model:open="showReturn" title="Return Book" size="sm">
            <form @submit.prevent="submitReturn" id="return-form" v-if="returnIssue">
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <div style="background:#f1f5f9;padding:10px 14px;border-radius:8px;font-size:.875rem;">
                        <strong>{{ returnIssue.book?.title }}</strong><br/>
                        <span style="color:#64748b;">Due: {{ fmtDate(returnIssue.due_date) }}</span>
                    </div>
                    <div class="form-field">
                        <label>Return Date *</label>
                        <input v-model="returnForm.return_date" type="date" required />
                    </div>
                    <div class="form-field">
                        <label>Notes</label>
                        <input v-model="returnForm.notes" />
                    </div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showReturn = false">Cancel</Button>
                <Button variant="success" type="submit" form="return-form" :loading="returnForm.processing">Confirm Return</Button>
            </template>
        </Modal>
    </SchoolLayout>
</template>

<style scoped>
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
