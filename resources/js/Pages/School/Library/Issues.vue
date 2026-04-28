<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
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
const returnForm  = useForm({ return_date: new Date().toISOString().split('T')[0], notes: '' });

const openReturn = (issue) => {
    returnIssue.value = issue;
    returnForm.return_date = new Date().toISOString().split('T')[0];
    returnForm.notes = '';
};

const submitReturn = () => {
    returnForm.patch(`/school/library/issues/${returnIssue.value.id}/return`, {
        preserveScroll: true,
        onSuccess: () => { returnIssue.value = null; },
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
        <div class="page-header">
            <h1 class="page-header-title">Book Issues</h1>
            <Button @click="showIssue = true">Issue Book</Button>
        </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom:16px;">
            <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;">
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
            </div>
        </div>

        <!-- Issues Table -->
        <div class="card">
            <div style="overflow-x:auto;">
                <table class="table">
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
                        <tr v-if="!issues.data?.length">
                            <td colspan="8" style="text-align:center;padding:32px;color:#94a3b8;">No issues found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Issue Book Modal -->
        <Teleport to="body">
            <div v-if="showIssue" class="modal-backdrop" @click.self="showIssue = false">
                <div class="modal" style="max-width:440px;width:100%;">
                    <div class="modal-header">
                        <h3 class="modal-title">Issue Book</h3>
                        <button @click="showIssue = false" class="modal-close">&times;</button>
                    </div>
                    <form @submit.prevent="submitIssue">
                        <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;">
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
                        <div class="modal-footer">
                            <Button variant="secondary" type="button" @click="showIssue = false">Cancel</Button>
                            <Button type="submit" :loading="issueForm.processing">Issue Book</Button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Return Modal -->
        <Teleport to="body">
            <div v-if="returnIssue" class="modal-backdrop" @click.self="returnIssue = null">
                <div class="modal" style="max-width:400px;width:100%;">
                    <div class="modal-header">
                        <h3 class="modal-title">Return Book</h3>
                        <button @click="returnIssue = null" class="modal-close">&times;</button>
                    </div>
                    <form @submit.prevent="submitReturn">
                        <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;">
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
                        <div class="modal-footer">
                            <Button variant="secondary" type="button" @click="returnIssue = null">Cancel</Button>
                            <Button variant="success" type="submit" :loading="returnForm.processing">Confirm Return</Button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </SchoolLayout>
</template>

<style scoped>
.modal-backdrop { position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,.5);backdrop-filter:blur(2px);display:flex;align-items:center;justify-content:center;z-index:1000; }
.modal { background:#fff;border-radius:12px;box-shadow:0 20px 25px -5px rgba(0,0,0,.1); }
.modal-header { padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center; }
.modal-title { font-size:1rem;font-weight:700;color:#1e293b; }
.modal-close { background:none;border:none;font-size:1.5rem;line-height:1;color:#94a3b8;cursor:pointer; }
.modal-body { padding:20px; }
.modal-footer { padding:16px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;border-radius:0 0 12px 12px;display:flex;justify-content:flex-end;gap:10px; }
</style>
