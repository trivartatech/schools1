<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Table from '@/Components/ui/Table.vue';
import SortableTh from '@/Components/ui/SortableTh.vue';
import { useTableSort } from '@/Composables/useTableSort';
import { Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    history:  { type: Array,  default: () => [] },
    classes:  { type: Array,  default: () => [] },
    feeHeads: { type: Array,  default: () => [] },
});

// Filter form state (mirror query params)
const classId   = ref(new URLSearchParams(window.location.search).get('class_id')   ?? '');
const feeHeadId = ref(new URLSearchParams(window.location.search).get('fee_head_id') ?? '');
const term      = ref(new URLSearchParams(window.location.search).get('term')        ?? '');

const terms = ['annual','term1','term2','term3','monthly','quarterly','half_yearly'];

const search = () => {
    if (!classId.value || !feeHeadId.value || !term.value) return;
    router.get('/school/fee/structure/history', {
        class_id:    classId.value,
        fee_head_id: feeHeadId.value,
        term:        term.value,
    }, { preserveState: true, preserveScroll: true });
};

import { useFormat } from '@/Composables/useFormat';
const { formatDate: fmt } = useFormat();

const currentEntry = computed(() => props.history.find(h => !h.effective_to));
const archivedEntries = computed(() => props.history.filter(h => h.effective_to));

const { sortKey, sortDir, toggleSort, sortRows } = useTableSort('effective_from', 'desc');
const sortedArchived = computed(() => sortRows(archivedEntries.value, {
    getValue: (row, key) => {
        if (key === 'amount') return Number(row.amount);
        if (key === 'late_fee_per_day') return Number(row.late_fee_per_day ?? 0);
        return row[key];
    },
}));
</script>

<template>
    <SchoolLayout title="Fee Structure History">
        <PageHeader back-href="/school/fee/structure" back-label="← Back to Fee Structure" title="Fee Structure Version History">
            <template #subtitle>
                <p style="color:#64748b;font-size:.9rem;margin-top:2px;">Audit trail of all fee amount changes with effective dates.</p>
            </template>
        </PageHeader>

        <!-- Filter -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><span class="card-title">Select Fee Structure</span></div>
            <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end;">
                <div class="form-field" style="margin:0;">
                    <label>Class</label>
                    <select v-model="classId">
                        <option value="">— Select Class —</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="form-field" style="margin:0;">
                    <label>Fee Head</label>
                    <select v-model="feeHeadId">
                        <option value="">— Select Fee Head —</option>
                        <option v-for="f in feeHeads" :key="f.id" :value="f.id">{{ f.name }}</option>
                    </select>
                </div>
                <div class="form-field" style="margin:0;">
                    <label>Term</label>
                    <select v-model="term">
                        <option value="">— Select Term —</option>
                        <option v-for="t in terms" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>
                <button class="btn btn-primary" @click="search" :disabled="!classId || !feeHeadId || !term">View History</button>
            </div>
        </div>

        <div v-if="history.length === 0 && (classId && feeHeadId && term)" style="text-align:center;padding:40px;color:#94a3b8;">
            No history found for this combination.
        </div>

        <div v-if="history.length > 0">
            <!-- Current Active Version -->
            <div v-if="currentEntry" class="card" style="margin-bottom:20px;border:2px solid #86efac;">
                <div class="card-header" style="background:#f0fdf4;">
                    <span class="card-title" style="color:#16a34a;">Current Active Version</span>
                    <span class="badge badge-green">Active</span>
                </div>
                <div style="padding:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;">
                    <div>
                        <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">Amount</div>
                        <div style="font-size:1.5rem;font-weight:700;color:#1e293b;">₹{{ Number(currentEntry.amount).toLocaleString('en-IN') }}</div>
                    </div>
                    <div>
                        <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">Effective From</div>
                        <div style="font-weight:600;">{{ fmt(currentEntry.effective_from) }}</div>
                    </div>
                    <div>
                        <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">Due Date</div>
                        <div style="font-weight:600;">{{ fmt(currentEntry.due_date) }}</div>
                    </div>
                    <div>
                        <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">Late Fee / Day</div>
                        <div style="font-weight:600;">₹{{ currentEntry.late_fee_per_day ?? 0 }}</div>
                    </div>
                    <div v-if="currentEntry.change_reason">
                        <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">Change Reason</div>
                        <div style="font-size:.85rem;color:#475569;font-style:italic;">{{ currentEntry.change_reason }}</div>
                    </div>
                </div>
            </div>

            <!-- History Timeline -->
            <div v-if="archivedEntries.length" class="card">
                <div class="card-header">
                    <span class="card-title">Previous Versions ({{ archivedEntries.length }})</span>
                </div>
                <Table :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
                    <thead>
                        <tr>
                            <th>#</th>
                            <SortableTh sort-key="amount" align="right">Amount</SortableTh>
                            <SortableTh sort-key="late_fee_per_day" align="right">Late Fee/Day</SortableTh>
                            <SortableTh sort-key="due_date">Due Date</SortableTh>
                            <SortableTh sort-key="effective_from">Effective From</SortableTh>
                            <SortableTh sort-key="effective_to">Effective To</SortableTh>
                            <SortableTh sort-key="change_reason">Change Reason</SortableTh>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(entry, idx) in sortedArchived" :key="entry.id" style="background:#fafafa;">
                            <td style="color:#94a3b8;font-size:.8rem;">v{{ sortedArchived.length - idx }}</td>
                            <td style="font-weight:600;text-align:right;">₹{{ Number(entry.amount).toLocaleString('en-IN') }}</td>
                            <td style="text-align:right;">₹{{ entry.late_fee_per_day ?? 0 }}</td>
                            <td>{{ fmt(entry.due_date) }}</td>
                            <td>{{ fmt(entry.effective_from) }}</td>
                            <td><span class="badge badge-gray">{{ fmt(entry.effective_to) }}</span></td>
                            <td style="color:#64748b;font-style:italic;">{{ entry.change_reason ?? '—' }}</td>
                            <td>
                                <span v-if="entry.effective_from && entry.effective_to">
                                    {{ Math.round((new Date(entry.effective_to) - new Date(entry.effective_from)) / (1000*60*60*24)) }} days
                                </span>
                                <span v-else>—</span>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>

            <div v-if="!archivedEntries.length && currentEntry" style="padding:16px;text-align:center;color:#94a3b8;font-size:.9rem;">
                No previous versions. This is the first and only entry.
            </div>
        </div>
    </SchoolLayout>
</template>
