<script setup>
/**
 * StatsRow — the unified stats / KPI cards row.
 *
 * Replaces the two competing patterns (Staff `.stats-row` and Finance
 * `.ledger-summary`). Visual baseline matches Staff — icon-left in a
 * tinted square, label above value, optional sub-text. Pass `cols=3`
 * for Finance-style 3-up.
 *
 * @prop {Array<{
 *   label: string,
 *   value: string|number,
 *   sub?: string,
 *   icon?: any,        // SVG component or HTML string
 *   color?: string,    // accent | success | danger | warning | info | string (hex)
 *   trend?: number,    // signed delta % — green up, red down
 * }>} stats
 *
 * @prop {2|3|4} cols   — grid columns at desktop width (default 4)
 *
 * Slots:
 *   icon-{label-slug} — custom icon override per stat (label slugified to lowercase, dashes)
 *
 * Example:
 *   <StatsRow :cols="4" :stats="[
 *     { label: 'Total Staff', value: 124, color: 'accent' },
 *     { label: 'On Leave', value: 8, color: 'warning' },
 *     { label: 'Active', value: 116, color: 'success' },
 *     { label: 'Suspended', value: 0, color: 'danger' },
 *   ]" />
 */
const props = defineProps({
    stats: {
        type: Array,
        required: true,
        validator: (arr) => Array.isArray(arr),
    },
    cols: {
        type: Number,
        default: 4,
        validator: (v) => [2, 3, 4].includes(v),
    },
});

const NAMED_COLORS = {
    accent: { fg: '#6366f1', bg: '#eef2ff' },
    success: { fg: '#059669', bg: '#d1fae5' },
    danger: { fg: '#dc2626', bg: '#fee2e2' },
    warning: { fg: '#d97706', bg: '#fef3c7' },
    info: { fg: '#2563eb', bg: '#dbeafe' },
    purple: { fg: '#7c3aed', bg: '#ede9fe' },
    pink: { fg: '#db2777', bg: '#fce7f3' },
    gray: { fg: '#475569', bg: '#f1f5f9' },
};

function resolveColor(c) {
    if (!c) return NAMED_COLORS.accent;
    if (NAMED_COLORS[c]) return NAMED_COLORS[c];
    // Hex / arbitrary CSS color — derive a tinted bg by appending alpha hex
    return { fg: c, bg: `${c}1f` };
}

function slug(s) {
    return String(s).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}
</script>

<template>
    <div :class="['ui-stats-row', `ui-stats-row--cols-${cols}`]">
        <div
            v-for="(s, i) in stats"
            :key="i"
            class="ui-stat-card"
            :style="{ '--stat-fg': resolveColor(s.color).fg, '--stat-bg': resolveColor(s.color).bg }"
        >
            <div class="ui-stat-icon" aria-hidden="true">
                <slot :name="`icon-${slug(s.label)}`">
                    <component :is="s.icon" v-if="s.icon && typeof s.icon === 'object'" />
                    <span v-else-if="s.icon" v-html="s.icon" />
                    <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3v18h18"/>
                        <path d="M7 14l4-4 4 4 5-5"/>
                    </svg>
                </slot>
            </div>
            <div class="ui-stat-body">
                <div class="ui-stat-label">{{ s.label }}</div>
                <div class="ui-stat-value">{{ s.value }}</div>
                <div v-if="s.sub" class="ui-stat-sub">{{ s.sub }}</div>
                <div
                    v-if="typeof s.trend === 'number'"
                    class="ui-stat-trend"
                    :class="s.trend >= 0 ? 'ui-stat-trend--up' : 'ui-stat-trend--down'"
                >
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline v-if="s.trend >= 0" points="6 14 12 8 18 14"/>
                        <polyline v-else points="6 10 12 16 18 10"/>
                    </svg>
                    {{ s.trend >= 0 ? '+' : '' }}{{ s.trend }}%
                </div>
            </div>
        </div>
    </div>
</template>

<style>
.ui-stats-row {
    display: grid;
    gap: 14px;
    margin-bottom: 20px;
}
.ui-stats-row--cols-2 { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
.ui-stats-row--cols-3 { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
.ui-stats-row--cols-4 { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }

@media (min-width: 640px) {
    .ui-stats-row--cols-2 { grid-template-columns: repeat(2, 1fr); }
    .ui-stats-row--cols-3 { grid-template-columns: repeat(3, 1fr); }
    .ui-stats-row--cols-4 { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 1024px) {
    .ui-stats-row--cols-4 { grid-template-columns: repeat(4, 1fr); }
}

.ui-stat-card {
    display: flex;
    align-items: center;
    gap: 14px;
    background: var(--surface, #fff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: var(--radius-lg, 14px);
    padding: 16px 18px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
    transition: box-shadow 0.15s, border-color 0.15s, transform 0.15s;
}
.ui-stat-card:hover {
    border-color: var(--stat-fg);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
}

.ui-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--stat-bg);
    color: var(--stat-fg);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.ui-stat-icon :deep(svg),
.ui-stat-icon svg {
    width: 22px;
    height: 22px;
}

.ui-stat-body {
    flex: 1;
    min-width: 0;
}
.ui-stat-label {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--text-muted, #94a3b8);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    line-height: 1.2;
}
.ui-stat-value {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--text-primary, #0f172a);
    line-height: 1.2;
    margin-top: 4px;
    word-break: break-word;
}
.ui-stat-sub {
    font-size: 0.72rem;
    color: var(--text-muted, #94a3b8);
    margin-top: 3px;
    font-weight: 500;
}
.ui-stat-trend {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    margin-top: 4px;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 10px;
}
.ui-stat-trend--up   { background: #d1fae5; color: #059669; }
.ui-stat-trend--down { background: #fee2e2; color: #dc2626; }
</style>
