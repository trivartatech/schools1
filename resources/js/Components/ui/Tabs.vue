<script setup>
/**
 * Tabs — the unified tabbed-navigation primitive.
 *
 * Replaces the ad-hoc `.tabs` / `.tab-btn` patterns scattered across show
 * pages (Houses/Show, Inventory/Show, Admin/Impersonation, etc.). The
 * visual baseline matches Houses/Show — underlined tab strip, accent
 * underline + colour for the active tab.
 *
 * @prop {Array<{key:string,label:string,count?:number,icon?:any,disabled?:boolean}>} tabs
 * @prop {string} modelValue       — currently active tab key (v-model)
 * @prop {boolean} fluid           — stretch tabs to fill width (default false)
 *
 * Emits: update:modelValue
 *
 * Slots:
 *   #tab-{key}      — render content for that tab (only the active one shown)
 *   #default        — fallback when no per-tab slot is defined
 *
 * Example:
 *   <Tabs v-model="activeTab" :tabs="[
 *     { key: 'students', label: 'Students', count: 12 },
 *     { key: 'points',   label: 'Points Log', count: 34 },
 *   ]">
 *     <template #tab-students>...</template>
 *     <template #tab-points>...</template>
 *   </Tabs>
 */
import { computed } from 'vue';

const props = defineProps({
    tabs: {
        type: Array,
        required: true,
        validator: (arr) => Array.isArray(arr) && arr.every((t) => t && typeof t.key === 'string' && typeof t.label === 'string'),
    },
    modelValue: { type: String, default: '' },
    fluid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const activeKey = computed(() => {
    if (props.modelValue && props.tabs.some((t) => t.key === props.modelValue)) {
        return props.modelValue;
    }
    return props.tabs[0]?.key ?? '';
});

function select(tab) {
    if (tab.disabled) return;
    if (tab.key === activeKey.value) return;
    emit('update:modelValue', tab.key);
}
</script>

<template>
    <div class="ui-tabs-root">
        <div :class="['ui-tabs', { 'ui-tabs--fluid': fluid }]" role="tablist">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                role="tab"
                :aria-selected="activeKey === tab.key"
                :tabindex="activeKey === tab.key ? 0 : -1"
                :disabled="tab.disabled || undefined"
                :class="['ui-tab', { 'ui-tab--active': activeKey === tab.key }]"
                @click="select(tab)"
            >
                <span v-if="tab.icon" class="ui-tab__icon" aria-hidden="true">
                    <component :is="tab.icon" v-if="typeof tab.icon === 'object'" />
                    <span v-else v-html="tab.icon" />
                </span>
                <span class="ui-tab__label">{{ tab.label }}</span>
                <span v-if="tab.count !== undefined && tab.count !== null" class="ui-tab__count">{{ tab.count }}</span>
            </button>
        </div>

        <div class="ui-tabs-panel" role="tabpanel" :aria-labelledby="activeKey">
            <slot :name="`tab-${activeKey}`">
                <slot />
            </slot>
        </div>
    </div>
</template>

<style>
.ui-tabs-root { display: flex; flex-direction: column; }

.ui-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 16px;
    border-bottom: 2px solid var(--border, #e2e8f0);
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}
.ui-tabs--fluid .ui-tab { flex: 1; }

.ui-tab {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 18px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-muted, #94a3b8);
    background: none;
    border: none;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
    white-space: nowrap;
    font-family: inherit;
}
.ui-tab:hover:not(:disabled):not(.ui-tab--active) {
    color: var(--text-primary, #0f172a);
}
.ui-tab--active {
    color: var(--accent, #6366f1);
    border-bottom-color: var(--accent, #6366f1);
}
.ui-tab:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.ui-tab__icon {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
}
.ui-tab__icon :deep(svg),
.ui-tab__icon svg {
    width: 1em;
    height: 1em;
    font-size: 1.05em;
}

.ui-tab__count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 10px;
    background: var(--border-light, #f1f5f9);
    color: var(--text-secondary, #475569);
    font-size: 0.7rem;
    font-weight: 700;
    line-height: 1;
}
.ui-tab--active .ui-tab__count {
    background: var(--accent-subtle, rgba(99,102,241,0.12));
    color: var(--accent, #6366f1);
}

.ui-tabs-panel { min-width: 0; }
</style>
