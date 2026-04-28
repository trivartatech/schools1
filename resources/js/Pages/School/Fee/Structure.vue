<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useDelete } from '@/Composables/useDelete';

const props = defineProps({
    classes:    Array,
    feeHeads:   Array,
    structures: Array,
});

const form = reactive({
    class_id: '',
    fee_head_id: '',
    term: 'annual',
    amount: '',
    late_fee_per_day: '',
    due_date: '',
    is_optional: false,
    student_type: 'all',
    gender: 'all',
    change_reason: '',
});

const submit = () => {
    router.post('/school/fee/structure', form, { preserveScroll: true,
        onSuccess: () => {
            form.amount = ''; form.late_fee_per_day = ''; form.due_date = '';
            form.is_optional = false; form.student_type = 'all'; form.gender = 'all';
            form.change_reason = '';
            isEditing.value = false;
        }
    });
};

const isEditing = ref(false);

const edit = (s) => {
    form.class_id = s.class_id;
    form.fee_head_id = s.fee_head_id;
    form.term = s.term;
    form.amount = s.amount;
    form.late_fee_per_day = s.late_fee_per_day || '';
    form.due_date = s.due_date || '';
    form.is_optional = !!s.is_optional;
    form.student_type = s.student_type || 'all';
    form.gender = s.gender || 'all';
    isEditing.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const cancelEdit = () => {
    form.class_id = '';
    form.fee_head_id = '';
    form.term = 'annual';
    form.amount = '';
    form.late_fee_per_day = '';
    form.due_date = '';
    form.is_optional = false;
    form.student_type = 'all';
    form.gender = 'all';
    form.change_reason = '';
    isEditing.value = false;
};

const { del } = useDelete();
const remove = (id) => {
    del(`/school/fee/structure/${id}`, 'Remove this entry?');
};

const terms = [
    'annual', 'term1', 'term2', 'term3', 'monthly', 'quarterly', 'half_yearly',
    ...Array.from({length: 12}, (_, i) => `Installment ${i + 1}`)
];

// Group structures by class for display
import { computed } from 'vue';
import Table from '@/Components/ui/Table.vue';
const grouped = computed(() => {
    const map = {};
    (props.structures || []).forEach(s => {
        const label = s.course_class?.name || s.class_id;
        if (!map[label]) map[label] = [];
        map[label].push(s);
    });
    return map;
});
</script>

<template>
    <SchoolLayout title="Fee Structure Builder">
        <div class="max-w-5xl mx-auto space-y-6">

            <PageHeader title="Fee Structure" subtitle="Define how much to charge per class and term">
                <template #actions>
                    <Button variant="secondary" as="a" href="/school/fee/structure/history">History</Button>
                    <Button variant="secondary" as="a" href="/school/fee/groups">← Fee Groups</Button>

                </template>
            </PageHeader>

            <!-- Add / Edit entry -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ isEditing ? 'Update Fee Entry' : 'Add Fee Entry' }}</h3>
                    <Button variant="secondary" size="xs" v-if="isEditing" @click="cancelEdit">Cancel Edit</Button>
                </div>
                <div class="card-body">
                    <div class="form-row-3">
                        <div class="form-field">
                            <label>Class *</label>
                            <select v-model="form.class_id">
                                <option value="">Select Class</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Fee Head *</label>
                            <select v-model="form.fee_head_id">
                                <option value="">Select Head</option>
                                <optgroup v-for="group in feeHeads?.reduce((acc, h) => { const g = h.fee_group?.name||'Other'; if (!acc[g]) acc[g] = []; acc[g].push(h); return acc; }, {})" :key="'g'" :label="'Group'">
                                </optgroup>
                                <option v-for="h in feeHeads" :key="h.id" :value="h.id">{{ h.fee_group?.name }} — {{ h.name }}</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Term *</label>
                            <select v-model="form.term">
                                <option v-for="t in terms" :key="t" :value="t">{{ t.startsWith('Installment') ? t : t.charAt(0).toUpperCase() + t.slice(1).replace('_', ' ') }}</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Amount ({{ $page.props.school.currency }}) *</label>
                            <input v-model="form.amount" type="number" step="0.01" min="0" placeholder="e.g. 5000">
                        </div>
                        <div class="form-field">
                            <label>Late Fee / Day ({{ $page.props.school.currency }})</label>
                            <input v-model="form.late_fee_per_day" type="number" step="0.01" min="0" placeholder="0">
                        </div>
                        <div class="form-field">
                            <label>Due Date</label>
                            <input v-model="form.due_date" type="date">
                        </div>
                    </div>

                    <div class="mt-4 px-4 py-3 bg-blue-50 rounded-lg border border-blue-100 flex flex-wrap items-center gap-6">
                        <div class="flex items-center gap-2">
                            <input v-model="form.is_optional" type="checkbox" id="optionalFee"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <label for="optionalFee" class="text-sm font-medium text-blue-800">Optional Fee</label>
                        </div>
                        <div class="form-field" style="margin:0">
                            <label class="text-blue-600">Student Type</label>
                            <select v-model="form.student_type" class="w-32">
                                <option value="all">All</option>
                                <option value="new">New Only</option>
                                <option value="old">Old Only</option>
                            </select>
                        </div>
                        <div class="form-field" style="margin:0">
                            <label class="text-blue-600">Gender Limit</label>
                            <select v-model="form.gender" class="w-32">
                                <option value="all">All</option>
                                <option value="male">Male Only</option>
                                <option value="female">Female Only</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-field mt-4" v-if="isEditing">
                        <label>Reason for Change <span style="color:#94a3b8;font-size:.8rem;">(optional, for audit trail)</span></label>
                        <input v-model="form.change_reason" type="text" placeholder="e.g. Annual revision 2026-27, fee hike approved by management" maxlength="255" />
                    </div>

                    <div class="mt-4">
                        <Button @click="submit">
                            {{ isEditing ? 'Update Entry' : 'Save Entry' }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Structure by class -->
            <div v-for="(rows, className) in grouped" :key="className" class="card overflow-hidden">
                <div class="card-header">
                    <h3 class="card-title">Class {{ className }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <Table>
                        <thead>
                            <tr>
                                <th>Fee Head</th>
                                <th>Group</th>
                                <th class="text-center">Term</th>
                                <th class="text-right">Amount</th>
                                <th class="text-right">Late/Day</th>
                                <th class="text-center">Due</th>
                                <th class="w-16"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in rows" :key="s.id">
                                <td>
                                    <p class="text-sm font-medium text-gray-800">{{ s.fee_head?.name }}</p>
                                    <div class="flex items-center gap-1 mt-0.5" v-if="s.is_optional || s.student_type !== 'all' || s.gender !== 'all'">
                                        <span v-if="s.is_optional" class="badge badge-purple">Optional</span>
                                        <span v-if="s.student_type !== 'all'" class="badge badge-indigo">{{ s.student_type === 'new' ? 'New Students' : 'Old Students' }}</span>
                                        <span v-if="s.gender !== 'all'" class="badge badge-gray capitalize">{{ s.gender }} Only</span>
                                    </div>
                                </td>
                                <td class="text-sm text-gray-500">{{ s.fee_head?.fee_group?.name }}</td>
                                <td class="text-center">
                                    <span class="badge badge-blue">{{ s.term }}</span>
                                </td>
                                <td class="text-right text-sm font-semibold text-gray-900">{{ $page.props.school.currency }}{{ Number(s.amount).toLocaleString('en-IN') }}</td>
                                <td class="text-right text-sm text-gray-500">{{ s.late_fee_per_day > 0 ? $page.props.school.currency+s.late_fee_per_day : '—' }}</td>
                                <td class="text-center text-xs text-gray-500">{{ s.due_date || '—' }}</td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button @click="edit(s)" class="p-1 text-gray-400 hover:text-blue-500 rounded transition" title="Edit">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.586-6.586a2 2 0 112.826 2.826L11.828 13.828H9V11z" /></svg>
                                        </button>
                                        <button @click="remove(s.id)" class="p-1 text-gray-300 hover:text-red-500 rounded transition" title="Delete">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
            </div>

            <div v-if="!structures?.length" class="card text-center py-12 text-gray-400">
                No fee structure defined yet. Use the form above to add entries.
            </div>
        </div>
    </SchoolLayout>
</template>
