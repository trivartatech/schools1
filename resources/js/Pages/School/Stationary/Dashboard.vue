<script setup>
import { Link, Head } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    stats:         Object,
    lowStockItems: Array,
});

function fmt(n) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 }).format(Number(n || 0));
}
</script>

<template>
    <Head title="Stationary Dashboard" />
    <SchoolLayout title="Stationary Dashboard">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">📚 Stationary Module</h1>
                <p class="page-header-sub">Items, allocations, issuances, returns, and fee collection at a glance.</p>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-[11px] uppercase tracking-wider text-gray-500">Today's Collection</p>
                <p class="text-xl font-bold text-green-600 mt-1">{{ fmt(stats.today_collection) }}</p>
                <p v-if="stats.today_refunds > 0" class="text-xs text-rose-500 mt-1">- Refunds {{ fmt(stats.today_refunds) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-[11px] uppercase tracking-wider text-gray-500">This Month</p>
                <p class="text-xl font-bold text-green-600 mt-1">{{ fmt(stats.month_collection) }}</p>
                <p v-if="stats.month_refunds > 0" class="text-xs text-rose-500 mt-1">- Refunds {{ fmt(stats.month_refunds) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-[11px] uppercase tracking-wider text-gray-500">Total Outstanding</p>
                <p class="text-xl font-bold text-rose-600 mt-1">{{ fmt(stats.total_outstanding) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-[11px] uppercase tracking-wider text-gray-500">Active Items</p>
                <p class="text-xl font-bold text-indigo-600 mt-1">{{ stats.active_items }} <span class="text-sm text-gray-400">/ {{ stats.item_count }}</span></p>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <Link href="/school/stationary/items" class="bg-white rounded-xl border border-gray-200 p-4 hover:border-indigo-400 transition">
                <p class="text-xs text-gray-500">Manage</p>
                <p class="font-bold">📦 Items</p>
            </Link>
            <Link href="/school/stationary/allocations" class="bg-white rounded-xl border border-gray-200 p-4 hover:border-indigo-400 transition">
                <p class="text-xs text-gray-500">Allocations</p>
                <p class="font-bold">📋 {{ stats.allocations }} kits</p>
            </Link>
            <Link href="/school/stationary/fees" class="bg-white rounded-xl border border-gray-200 p-4 hover:border-indigo-400 transition">
                <p class="text-xs text-gray-500">Fee Collection</p>
                <p class="font-bold">💰 {{ stats.unpaid_count + stats.partial_count }} pending</p>
            </Link>
            <Link href="/school/stationary/reports/collection-pending" class="bg-white rounded-xl border border-gray-200 p-4 hover:border-indigo-400 transition">
                <p class="text-xs text-gray-500">Collection pending</p>
                <p class="font-bold">📦 {{ stats.collection_pending }} students</p>
            </Link>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl border border-gray-200">
                <h3 class="px-4 py-3 border-b text-sm font-bold flex items-center justify-between">
                    ⚠️ Low Stock Alerts
                    <Link href="/school/stationary/items?status=active" class="text-xs text-indigo-600 font-normal">All items →</Link>
                </h3>
                <div v-if="!lowStockItems?.length" class="p-6 text-center text-gray-400 text-sm">No items below minimum stock — all good. 🎉</div>
                <div v-for="i in lowStockItems" :key="i.id" class="px-4 py-2 border-b last:border-b-0 flex justify-between text-sm">
                    <span class="font-semibold">{{ i.name }} <small class="text-gray-400 font-mono">{{ i.code }}</small></span>
                    <span class="text-rose-600 font-bold">{{ i.current_stock }} / min {{ i.min_stock }}</span>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200">
                <h3 class="px-4 py-3 border-b text-sm font-bold">Quick Reports</h3>
                <Link href="/school/stationary/reports/fee-defaulters" class="block px-4 py-3 border-b hover:bg-gray-50 text-sm">
                    💰 Fee Defaulters <span class="float-right text-rose-600">{{ stats.unpaid_count + stats.partial_count }}</span>
                </Link>
                <Link href="/school/stationary/reports/collection-pending" class="block px-4 py-3 border-b hover:bg-gray-50 text-sm">
                    📦 Collection Pending <span class="float-right text-amber-600">{{ stats.collection_pending }}</span>
                </Link>
                <Link href="/school/stationary/reports/returns" class="block px-4 py-3 hover:bg-gray-50 text-sm">
                    ↩ Returns Report
                </Link>
            </div>
        </div>
    </SchoolLayout>
</template>
