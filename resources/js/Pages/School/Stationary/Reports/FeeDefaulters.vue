<script setup>
import { ref, computed } from 'vue';
import { Link, Head } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({ defaulters: Array });

const search = ref('');

const filtered = computed(() => {
    if (!search.value.trim()) return props.defaulters;
    const q = search.value.toLowerCase().trim();
    return props.defaulters.filter(d => {
        const name = studentName(d);
        const adm  = d?.student?.admission_no || '';
        return name.toLowerCase().includes(q) || adm.toLowerCase().includes(q);
    });
});

const totalDue = computed(() => filtered.value.reduce((s, d) => s + parseFloat(d.balance || 0), 0));

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

function fmt(n) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 }).format(Number(n || 0));
}
</script>

<template>
    <Head title="Stationary Fee Defaulters" />
    <SchoolLayout title="Stationary Fee Defaulters">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">📚 Stationary Fee Defaulters</h1>
                <p class="page-header-sub">Active allocations with outstanding balance</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs uppercase text-gray-500 font-semibold">Total Defaulters</p>
                <p class="text-2xl font-bold text-rose-600 mt-1">{{ filtered.length }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs uppercase text-gray-500 font-semibold">Total Amount Due</p>
                <p class="text-2xl font-bold text-rose-600 mt-1">{{ fmt(totalDue) }}</p>
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
                        <th class="text-right">Total</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Balance</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in filtered" :key="d.id">
                        <td>
                            <div class="font-semibold">{{ studentName(d) }}</div>
                            <div class="text-xs text-gray-400 font-mono">{{ d.student?.admission_no }}</div>
                        </td>
                        <td>{{ classSection(d) }}</td>
                        <td class="text-right">{{ fmt(d.total_amount) }}</td>
                        <td class="text-right">{{ fmt(d.amount_paid) }}</td>
                        <td class="text-right text-rose-600 font-bold">{{ fmt(d.balance) }}</td>
                        <td><span class="px-2 py-0.5 rounded-full text-xs bg-rose-100 text-rose-700">{{ d.payment_status }}</span></td>
                        <td><Link :href="`/school/stationary/fees/${d.id}`" class="text-indigo-600 text-sm">Collect →</Link></td>
                    </tr>
                    <tr v-if="!filtered.length">
                        <td colspan="7" class="text-center py-8 text-gray-400">No defaulters. 🎉</td>
                    </tr>
                </tbody>
            </Table>
        </div>
    </SchoolLayout>
</template>
