<script setup>
/**
 * PageHeader — the unified page header primitive.
 *
 * Codifies the existing `.page-header` div pattern as a component (already
 * 98% consistent across pages — this just locks it in). Title on the left,
 * optional subtitle/breadcrumbs underneath, action buttons slot on the right.
 *
 * @prop {string} title          — main heading (h1)
 * @prop {string} subtitle       — small grey text under the title
 * @prop {string} backHref       — render a "← Back" link before the title
 * @prop {string} backLabel      — link label (default '← Back')
 * @prop {Array<{label:string,href?:string}>} breadcrumbs — optional breadcrumb trail
 * @prop {boolean} compact       — tighter spacing (default false)
 *
 * Slots:
 *   title    — override the title node entirely (e.g. with extra badges)
 *   subtitle — override the subtitle node
 *   meta     — extra row of meta info under the title (badges, status, etc.)
 *   actions  — right-side action buttons / dropdowns
 *
 * Examples:
 *   <PageHeader title="Students Directory" subtitle="Manage student admissions and records.">
 *     <template #actions>
 *       <Button>+ New Admission</Button>
 *     </template>
 *   </PageHeader>
 *
 *   <PageHeader title="Edit Student" back-href="/school/students/123" back-label="← Back to profile" />
 *
 *   <PageHeader title="Cash Account" :breadcrumbs="[
 *     { label: 'Finance', href: '/school/finance' },
 *     { label: 'Ledgers', href: '/school/finance/ledgers' },
 *     { label: 'Cash Account' },
 *   ]" />
 */
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    backHref: { type: String, default: '' },
    backLabel: { type: String, default: '← Back' },
    breadcrumbs: { type: Array, default: () => [] },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div :class="['ui-page-header', { 'ui-page-header--compact': compact }]">
        <div class="ui-page-header__left">
            <nav v-if="breadcrumbs.length" class="ui-page-header__crumbs" aria-label="Breadcrumb">
                <template v-for="(c, i) in breadcrumbs" :key="i">
                    <Link
                        v-if="c.href"
                        :href="c.href"
                        class="ui-page-header__crumb ui-page-header__crumb--link"
                    >{{ c.label }}</Link>
                    <span v-else class="ui-page-header__crumb">{{ c.label }}</span>
                    <span v-if="i < breadcrumbs.length - 1" class="ui-page-header__crumb-sep" aria-hidden="true">/</span>
                </template>
            </nav>

            <Link
                v-if="backHref"
                :href="backHref"
                class="ui-page-header__back"
            >{{ backLabel }}</Link>

            <slot name="title">
                <h1 v-if="title" class="page-header-title">{{ title }}</h1>
            </slot>

            <slot name="subtitle">
                <p v-if="subtitle" class="page-header-sub">{{ subtitle }}</p>
            </slot>

            <div v-if="$slots.meta" class="ui-page-header__meta">
                <slot name="meta" />
            </div>
        </div>

        <div v-if="$slots.actions" class="ui-page-header__actions">
            <slot name="actions" />
        </div>
    </div>
</template>

<style>
/* Reuses the global .page-header-title / .page-header-sub from
   SchoolLayout.vue so visual matches the rest of the app exactly. */

.ui-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 22px;
    gap: 16px;
    flex-wrap: wrap;
}
.ui-page-header--compact { margin-bottom: 14px; }

.ui-page-header__left {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
    flex: 1;
}

.ui-page-header__crumbs {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    color: var(--text-muted, #94a3b8);
    margin-bottom: 4px;
}
.ui-page-header__crumb {
    color: var(--text-muted, #94a3b8);
    text-decoration: none;
    font-weight: 500;
}
.ui-page-header__crumb--link {
    transition: color 0.15s;
}
.ui-page-header__crumb--link:hover {
    color: var(--accent, #6366f1);
}
.ui-page-header__crumb-sep {
    color: var(--border, #e2e8f0);
    font-weight: 700;
}

.ui-page-header__back {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-secondary, #475569);
    text-decoration: none;
    margin-bottom: 6px;
    transition: color 0.15s;
    width: fit-content;
}
.ui-page-header__back:hover {
    color: var(--accent, #6366f1);
}

.ui-page-header__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
    align-items: center;
}

.ui-page-header__actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    flex-shrink: 0;
}
</style>
