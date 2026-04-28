<script setup>
import { ref, computed } from 'vue';
import { Link, Head } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({ pending: Array });

const search = ref('');

const filtered = computed(() => {
    if (!search.value.trim()) return props.pending;
    const q = search.value.toLowerCase().trim();
    return props.pending.filter(p => {
        const name = studentName(p);
        const adm  = p?.student?.admission_no || '';
        return name.toLowerCase().includes(q) || adm.toLowerCase().includes(q);
    });
});

function studentName(a) {
    return a?.student?.user?.name
        || [a?.student?.first_name, a?.student?.last_name].filter(Boolean).join(' ')
        || '—';
}

function classSection(a) {
    const h = a?.student?.current_academic_history;
    if (!h) return '—';
    return [h.course_class?.name, h.section?.name].filter(Boolean).join(' - ') || '—';
}

function totalEntitled(a)  { return (a.line_items || []).reduce((s, l) => s + (l.qty_entitled  || 0), 0); }
function totalCollected(a) { return (a.line_items || []).reduce((s, l) => s + (l.qty_collected || 0), 0); }
function pctCollected(a) {
    const e = totalEntitled(a);
    if (!e) return 0;
    return Math.round((totalCollected(a) / e) * 100);
}

const statusBadge = (s) => ({
    none: 'bg-rose-100 text-rose-700',
    partial: 'bg-amber-100 text-amber-700',
    complete: 'bg-green-100 text-green-700',
})[s] || 'bg-gray-100 text-gray-600';
</script>

<template>
    <Head title="Stationary Collection Pending" />
    <SchoolLayout title="Stationary Collection Pending">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">📦 Collection Pending</h1>
                <p class="page-header-sub">Students who haven't fully picked up their stationary kit</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-3 mb-4">
            <input v-model="search" type="text" placeholder="Search by name or admission no…"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <Table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Class</th>
                        <th class="text-right">Items Entitled</th>
                        <th class="text-right">Items Collected</th>
                        <th class="text-right">% Collected</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in filtered" :key="a.id">
                        <td>
                            <div class="font-semibold">{{ studentName(a) }}</div>
                            <div class="text-xs text-gray-400 font-mono">{{ a.student?.admission_no }}</div>
                        </td>
                        <td>{{ classSection(a) }}</td>
                        <td class="text-right">{{ totalEntitled(a) }}</td>
                        <td class="text-right">{{ totalCollected(a) }}</td>
                        <td class="text-right font-bold" :class="pctCollected(a) === 0 ? 'text-rose-600' : 'text-amber-600'">{{ pctCollected(a) }}%</td>
                        <td><span class="px-2 py-0.5 rounded-full text-xs" :class="statusBadge(a.collection_status)">{{ a.collection_status }}</span></td>
                        <td><Link :href="`/school/stationary/allocations/${a.id}`" class="text-indigo-600 text-sm">Issue →</Link></td>
                    </tr>
                    <tr v-if="!filtered.length">
                        <td colspan="7" class="text-center py-8 text-gray-400">All allocations fully collected. 🎉</td>
                    </tr>
                </tbody>
            </Table>
        </div>
    </SchoolLayout>
</template>
