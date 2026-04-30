<script setup>
/**
 * UI Sandbox · Composables
 * Code-snippet reference for useFormat, useDelete, useTableFilters,
 * usePermissions, useClassSections.
 * URL: /school/_ui-sandbox/composables
 */
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useFormat } from '@/Composables/useFormat';

const { formatDate, formatTime, formatDateTime, formatMoney } = useFormat();

const fmtSampleDate = '2026-04-30T09:30:00Z';
const fmtSampleAmount = 1245300;
</script>

<template>
    <SchoolLayout title="UI Sandbox · Composables">

        <PageHeader
            title="Composables"
            subtitle="Reusable Vue composables — call sites and live formatters."
            back-href="/school/_ui-sandbox"
            back-label="← Back to sandbox"
        />

        <!-- ── useFormat — live ────────────────────────────────────── -->
        <h2 class="section-heading">useFormat — live formatting via System Config</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Always format dates / times / money through <code>useFormat()</code> so they respect the school's
            System Config (date format, time format, currency symbol).
            Sample input: <code>{{ fmtSampleDate }}</code> · <code>{{ fmtSampleAmount }}</code>
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <table style="width:100%;font-size:0.85rem;border-collapse:collapse;">
                <tbody>
                    <tr><td style="padding:6px 0;color:var(--text-muted);width:280px;"><code>formatDate(input)</code></td><td><strong>{{ formatDate(fmtSampleDate) }}</strong></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-muted);"><code>formatTime(input)</code></td><td><strong>{{ formatTime(fmtSampleDate) }}</strong></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-muted);"><code>formatDateTime(input)</code></td><td><strong>{{ formatDateTime(fmtSampleDate) }}</strong></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-muted);"><code>formatMoney(amount)</code></td><td><strong>{{ formatMoney(fmtSampleAmount) }}</strong></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-muted);"><code>formatMoney(amount, { fixed: true })</code></td><td><strong>{{ formatMoney(fmtSampleAmount, { fixed: true }) }}</strong></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-muted);"><code>formatDate(null)</code> &middot; empty input</td><td><strong>{{ formatDate(null) }}</strong></td></tr>
                </tbody>
            </table>
        </div>

        <!-- ── Code reference ──────────────────────────────────────── -->
        <h2 class="section-heading">Call-site reference (useDelete, useTableFilters, usePermissions, useClassSections)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <pre style="margin:0;background:#0f172a;color:#e2e8f0;padding:14px;border-radius:10px;font-size:0.72rem;line-height:1.55;overflow:auto;" v-pre>// useDelete — DELETE with styled confirm dialog
import { useDelete } from '@/Composables/useDelete';
const { del } = useDelete();
del('/school/students/123');                              // default confirm
del('/school/students/123', 'Permanent — sure?');         // custom message
del('/school/students/123', null);                        // skip confirm

// useTableFilters — debounced router.get for index pages
import { reactive, watch } from 'vue';
import { useTableFilters } from '@/Composables/useTableFilters';
const filters = reactive({ search: '', status: 'active' });
const { navigate } = useTableFilters('/school/students', filters);
watch(filters, navigate);

// usePermissions — auth/role/permission helpers
import { usePermissions } from '@/Composables/usePermissions';
const { can, canDo, canAny, canAll, hasRole, isAdmin, canAccess } = usePermissions();
v-if="can('view_students')"
v-if="canDo('edit', 'students')"
v-if="canAccess.finance.value"

// useClassSections — fetch sections when a class is picked
import { useClassSections } from '@/Composables/useClassSections';
const { sections, isFetching, fetchError, fetchSections, reset } = useClassSections();
@change="fetchSections(form.class_id)"

// useTableSort — client-side table sorting helpers
import { useTableSort } from '@/Composables/useTableSort';
const { sortKey, sortDir, toggleSort, sortRows } = useTableSort('name', 'asc');
const sortedRows = computed(() => sortRows(rawRows.value));
</pre>
        </div>

    </SchoolLayout>
</template>

<style scoped>
.section-heading {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    margin: 24px 0 10px;
}
</style>
