<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    current: {
        type: String,
        required: true,
    },
});

const tabs = [
    { key: 'trial-balance', label: 'Trial Balance', href: '/school/finance/statements/trial-balance' },
    { key: 'profit-loss',   label: 'Profit & Loss',  href: '/school/finance/statements/profit-loss' },
    { key: 'balance-sheet', label: 'Balance Sheet',  href: '/school/finance/statements/balance-sheet' },
];
</script>

<template>
    <div class="stmt-tabs">
        <Link
            v-for="t in tabs"
            :key="t.key"
            :href="t.href"
            class="stmt-tab"
            :class="{ 'stmt-tab--active': current === t.key }"
        >
            {{ t.label }}
        </Link>
    </div>
</template>

<style scoped>
.stmt-tabs {
    display: flex;
    gap: 4px;
    background: #f1f5f9;
    border-radius: 10px;
    padding: 4px;
    margin-bottom: 18px;
    width: fit-content;
}
.stmt-tab {
    padding: 8px 18px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #64748b;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.15s;
    cursor: pointer;
}
.stmt-tab:hover { color: #1e293b; background: #e2e8f0; }
.stmt-tab--active {
    background: #fff;
    color: #4338ca;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.stmt-tab--active:hover { background: #fff; }
</style>
