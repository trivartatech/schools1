<script setup>
import Button from '@/Components/ui/Button.vue';
import { router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref } from 'vue';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    tcs:     Object,   // paginated
    counts:  Object,   // { requested: N, approved: N, issued: N, rejected: N }
    classes: Array,
    filters: Object,
});

const filterForm = ref({
    status:   props.filters?.status   || '',
    search:   props.filters?.search   || '',
    class_id: props.filters?.class_id || '',
});

const applyFilters = () => {
    router.get(route('school.transfer-certificates.index'), filterForm.value, { preserveState: true });
};

const statusTabs = [
    { key: '',          label: 'All',       color: 'slate'   },
    { key: 'requested', label: 'Requested', color: 'amber'   },
    { key: 'approved',  label: 'Approved',  color: 'blue'    },
    { key: 'issued',    label: 'Issued',    color: 'emerald' },
    { key: 'rejected',  label: 'Rejected',  color: 'red'     },
];

const statusBadge = (s) => ({
    requested: 'badge-amber',
    approved:  'badge-blue',
    issued:    'badge-emerald',
    rejected:  'badge-red',
}[s] ?? 'badge-gray');

const statusLabel = (s) => s.charAt(0).toUpperCase() + s.slice(1);

const totalCount = () => Object.values(props.counts).reduce((a, b) => a + b, 0);
</script>

<template>
    <SchoolLayout title="Transfer Certificates">
        <div class="page-header">
            <div>
                <h2 class="page-header-title">Transfer Certificates</h2>
                <p class="page-header-sub">Manage student TC requests, approvals, and issuance</p>
            </div>
            <Button as="link" :href="route('school.transfer-certificates.create')">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New TC Request
            </Button>
        </div>

        <!-- Status Tabs -->
        <div class="flex gap-1 mb-6 overflow-x-auto">
            <button v-for="tab in statusTabs" :key="tab.key"
                    @click="filterForm.status = tab.key; applyFilters()"
                    :class="[
                        'px-4 py-2 rounded-lg text-sm font-semibold whitespace-nowrap transition-all border',
                        filterForm.status === tab.key
                            ? 'bg-indigo-600 text-white border-indigo-600 shadow'
                            : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-300 hover:text-indigo-600'
                    ]">
                {{ tab.label }}
                <span class="ml-1.5 text-xs font-bold opacity-80">
                    {{ tab.key === '' ? totalCount() : (counts[tab.key] || 0) }}
                </span>
            </button>
        </div>

        <!-- Filters -->
        <div class="card mb-6">
            <div class="card-body">
                <div class="flex gap-3 flex-wrap items-end">
                    <div class="form-field min-w-[200px]">
                        <label>Search Student</label>
                        <input v-model="filterForm.search" @keyup.enter="applyFilters"
                               type="text" placeholder="Name or admission no…" class="input">
                    </div>
                    <div class="form-field min-w-[160px]">
                        <label>Class</label>
                        <select v-model="filterForm.class_id" @change="applyFilters">
                            <option value="">All Classes</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <Button size="sm" @click="applyFilters">Filter</Button>
                    <Button variant="secondary" size="sm" @click="filterForm = { status: '', search: '', class_id: '' }; applyFilters()">Reset</Button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <Table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Leaving Date</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Certificate No</th>
                            <th>Requested By</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="tc in tcs.data" :key="tc.id">
                            <!-- Student -->
                            <td>
                                <div class="flex items-center gap-3">
                                    <img v-if="tc.student?.photo_url"
                                         :src="tc.student.photo_url"
                                         class="w-8 h-8 rounded-full object-cover">
                                    <div v-else class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">
                                        {{ tc.student?.first_name?.[0] }}
                                    </div>
                                    <div>
                                        <div class="font-medium text-slate-800 text-sm">
                                            {{ tc.student?.first_name }} {{ tc.student?.last_name }}
                                        </div>
                                        <div class="text-xs text-slate-400">{{ tc.student?.admission_no }}</div>
                                    </div>
                                </div>
                            </td>
                            <!-- Class -->
                            <td class="text-sm text-slate-600">
                                <span v-if="tc.student?.current_academic_history">
                                    {{ tc.student.current_academic_history.course_class?.name }}
                                    {{ tc.student.current_academic_history.section ? '/ ' + tc.student.current_academic_history.section.name : '' }}
                                </span>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <!-- Leaving Date -->
                            <td class="text-sm text-slate-600">
                                {{ tc.leaving_date ? school.fmtDate(tc.leaving_date) : '—' }}
                            </td>
                            <!-- Reason -->
                            <td class="text-sm text-slate-500 max-w-[200px] truncate">
                                {{ tc.reason || '—' }}
                            </td>
                            <!-- Status -->
                            <td>
                                <span :class="['badge text-xs', statusBadge(tc.status)]">
                                    {{ statusLabel(tc.status) }}
                                </span>
                            </td>
                            <!-- Certificate No -->
                            <td class="font-mono text-sm text-slate-700">
                                {{ tc.certificate_no || '—' }}
                            </td>
                            <!-- Requested By -->
                            <td class="text-xs text-slate-500">{{ tc.requested_by?.name }}</td>
                            <!-- Actions -->
                            <td class="text-right">
                                <Button variant="secondary" size="xs" as="link" :href="route('school.transfer-certificates.show', tc.id)">
                                    View →
                                </Button>
                            </td>
                        </tr>

                        <tr v-if="tcs.data.length === 0">
                            <td colspan="8" class="py-14 text-center text-slate-400">
                                No transfer certificates found.
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>

            <!-- Pagination -->
            <div v-if="tcs.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
                <span class="text-xs text-slate-400">
                    Showing {{ tcs.from }}–{{ tcs.to }} of {{ tcs.total }} records
                </span>
                <div class="flex gap-1">
                    <Link v-for="link in tcs.links" :key="link.label"
                          :href="link.url || '#'"
                          :class="[
                              'px-3 py-1 rounded text-xs font-medium border transition-colors',
                              link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-300',
                              !link.url ? 'opacity-40 pointer-events-none' : ''
                          ]"
                          v-html="link.label" preserve-scroll />
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>
