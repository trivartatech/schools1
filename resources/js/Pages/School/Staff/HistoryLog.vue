<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    events: Object,
});

const params     = new URLSearchParams(window.location.search);
const eventType  = ref(params.get('event_type') ?? '');
const fromDate   = ref(params.get('from_date')  ?? '');

const applyFilters = () => {
    router.get('/school/staff-history', {
        event_type: eventType.value || undefined,
        from_date:  fromDate.value  || undefined,
    }, { preserveState: true, preserveScroll: true });
};

let fTimer;
watch([eventType, fromDate], () => { clearTimeout(fTimer); fTimer = setTimeout(applyFilters, 400); });

const eventTypeColor = {
    joining:             'badge-green',
    promotion:           'badge-blue',
    transfer:            'badge-amber',
    demotion:            'badge-red',
    salary_revision:     'badge-green',
    department_change:   'badge-amber',
    designation_change:  'badge-blue',
    increment:           'badge-green',
    confirmation:        'badge-green',
    termination:         'badge-red',
    other:               'badge-gray',
};

import { useFormat } from '@/Composables/useFormat';
const { formatDate: fmt } = useFormat();
const fmtSal = (n) => n ? '₹' + Number(n).toLocaleString('en-IN') : null;
</script>

<template>
    <SchoolLayout title="Staff History Log">
        <PageHeader title="Staff Career History Log" />

        <!-- Filters -->
        <div class="card" style="margin-bottom:16px;padding:12px 16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <select v-model="eventType" style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:.9rem;">
                <option value="">All Events</option>
                <option value="promotion">Promotion</option>
                <option value="transfer">Transfer</option>
                <option value="salary_revision">Salary Revision</option>
                <option value="increment">Increment</option>
                <option value="department_change">Department Change</option>
                <option value="designation_change">Designation Change</option>
                <option value="termination">Termination</option>
                <option value="joining">Joining</option>
            </select>
            <div class="form-field" style="margin:0;display:flex;align-items:center;gap:8px;">
                <label style="font-size:.85rem;color:#64748b;white-space:nowrap;">From:</label>
                <input v-model="fromDate" type="date" style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:.9rem;" />
            </div>
        </div>

        <div class="card">
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Event</th>
                            <th>Change</th>
                            <th>Effective Date</th>
                            <th>Order No</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in events.data" :key="e.id">
                            <td>
                                <Link :href="`/school/staff/${e.staff_id}/history`" style="font-weight:500;color:#3b82f6;">
                                    {{ e.staff?.user?.name ?? `${e.staff?.user?.first_name} ${e.staff?.user?.last_name}` }}
                                </Link>
                            </td>
                            <td>
                                <span class="badge" :class="eventTypeColor[e.event_type]" style="font-size:.75rem;text-transform:capitalize;">{{ e.event_type.replace('_', ' ') }}</span>
                            </td>
                            <td style="font-size:.85rem;">
                                <div v-if="e.from_designation || e.to_designation">
                                    <span v-if="e.from_designation" style="color:#94a3b8;">{{ e.from_designation.name }}</span>
                                    <span v-if="e.from_designation && e.to_designation"> → </span>
                                    <span v-if="e.to_designation" style="font-weight:600;">{{ e.to_designation.name }}</span>
                                </div>
                                <div v-if="e.from_department || e.to_department">
                                    <span v-if="e.from_department" style="color:#94a3b8;">{{ e.from_department.name }}</span>
                                    <span v-if="e.from_department && e.to_department"> → </span>
                                    <span v-if="e.to_department" style="font-weight:600;">{{ e.to_department.name }}</span>
                                </div>
                                <div v-if="fmtSal(e.from_salary) || fmtSal(e.to_salary)">
                                    <span v-if="fmtSal(e.from_salary)" style="color:#94a3b8;">{{ fmtSal(e.from_salary) }}</span>
                                    <span v-if="fmtSal(e.from_salary) && fmtSal(e.to_salary)"> → </span>
                                    <span v-if="fmtSal(e.to_salary)" style="font-weight:600;color:#10b981;">{{ fmtSal(e.to_salary) }}</span>
                                </div>
                            </td>
                            <td>{{ fmt(e.effective_date) }}</td>
                            <td style="font-size:.8rem;color:#64748b;">{{ e.order_no ?? '—' }}</td>
                            <td style="font-size:.8rem;color:#64748b;max-width:200px;white-space:normal;">{{ e.remarks ?? '—' }}</td>
                        </tr>
                        <tr v-if="!events.data?.length">
                            <td colspan="6" style="text-align:center;padding:32px;color:#94a3b8;">No history events found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div v-if="events.last_page > 1" style="display:flex;justify-content:center;gap:4px;padding:16px;">
                <a v-for="p in events.links" :key="p.label" :href="p.url ?? '#'"
                   v-html="p.label"
                   :style="{ padding:'6px 12px', borderRadius:'6px', fontSize:'.85rem', background: p.active ? '#3b82f6' : '#f1f5f9', color: p.active ? '#fff' : '#475569', textDecoration:'none', pointerEvents: p.url ? 'auto' : 'none', opacity: p.url ? 1 : 0.4 }">
                </a>
            </div>
        </div>
    </SchoolLayout>
</template>
