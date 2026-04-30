<script setup>
/**
 * UI Sandbox — the canonical visual catalogue of every shared UI primitive.
 *
 * This page is the SOURCE OF TRUTH for the UI kit. Every variant, size,
 * state, slot, prop and token shipped with a primitive must be demoed here.
 * If you add a new primitive (or a new option on an existing one), add the
 * demo here in the same change. The audit script at
 * `scripts/audit_ui_sandbox.mjs` enforces this on a best-effort basis.
 *
 * URL: /school/_ui-sandbox
 *
 * Sections (TOC):
 *   1.  Tokens & utility classes (badges, cards, form grid, section heading)
 *   2.  PageHeader  — every variant
 *   3.  StatsRow    — every cols + every color + custom icon + trend
 *   4.  Tabs        — counts + icons + fluid
 *   5.  EmptyState  — every variant + every slot
 *   6.  FilterBar   — full + minimal
 *   7.  Table       — basic + sortable + density + striped + bordered + loading + empty
 *   8.  SortableTh  — alignments + standalone (no <Table> ancestor)
 *   9.  Button      — every variant × every size × every state × every slot
 *   10. Modal       — sizes + persistent + slots + bodyClass + closeOnBackdrop/Esc
 *   11. ExportDropdown / PrintButton — multi-format download + print modes
 *   12. Toast       — success/error/warning/info + dismiss + clear + custom duration
 *   13. ConfirmDialog (useConfirm) — simple + danger + custom + defaults
 *   14. SlidePanel  — right-side form panel
 *   15. DateRangeFilter — presets + custom range
 *   16. LedgerCombobox — searchable picker
 *   17. PermissionGate — permission / any / all + #fallback
 *   18. LanguageSwitcher — locale dropdown (also mounted in topbar)
 *   19. ErrorBoundary — onErrorCaptured + #fallback
 *   20. Pass cards   — IdCardQR, GatePassCard, VisitorPassCard
 *   21. WebcamCapture — camera modal (live launch)
 *   22. Layout-mounted — AiChatbot, ChatWidget
 *   23. Composables   — useFormat, useDelete, useTableFilters, usePermissions, useClassSections
 */
import { ref, computed, shallowRef, defineComponent, h } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Modal from '@/Components/ui/Modal.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Button from '@/Components/ui/Button.vue';
import Table from '@/Components/ui/Table.vue';
import SortableTh from '@/Components/ui/SortableTh.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import PrintButton from '@/Components/ui/PrintButton.vue';
import DateRangeFilter from '@/Components/ui/DateRangeFilter.vue';
import LanguageSwitcher from '@/Components/ui/LanguageSwitcher.vue';
import ExportDropdown from '@/Components/ExportDropdown.vue';
import SlidePanel from '@/Components/SlidePanel.vue';
import LedgerCombobox from '@/Components/LedgerCombobox.vue';
import PermissionGate from '@/Components/PermissionGate.vue';
import ErrorBoundary from '@/Components/ErrorBoundary.vue';
import IdCardQR from '@/Components/IdCardQR.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';
import { useTableSort } from '@/Composables/useTableSort';
import { useFormat } from '@/Composables/useFormat';

const confirm = useConfirm();
const toast = useToast();
const { formatDate, formatTime, formatDateTime, formatMoney } = useFormat();

// ── Modal state ─────────────────────────────────────────────────
const showSm = ref(false);
const showMd = ref(false);
const showLg = ref(false);
const showXl = ref(false);
const showPersistent = ref(false);
const showNoFooter = ref(false);
const showNoHeader = ref(false);
const showHeaderActions = ref(false);
const showBodyClass = ref(false);
const showBackdropOnly = ref(false);
const showEscOnly = ref(false);

// ── Tabs state ──────────────────────────────────────────────────
const activeTab = ref('overview');
const activeTabFluid = ref('a');
const activeTabIcons = ref('home');
const activeTabPlain = ref('one');

// ── SlidePanel state ────────────────────────────────────────────
const showSlideSm = ref(false);
const showSlideLg = ref(false);

// ── DateRangeFilter state ───────────────────────────────────────
const drFrom = ref('');
const drTo = ref('');
const drLastChange = ref(null);
function onDateRange(v) { drLastChange.value = v; drFrom.value = v.from; drTo.value = v.to; }

// ── LedgerCombobox state ────────────────────────────────────────
const sampleLedgers = [
    { id: 1, code: '1001', name: 'Cash in Hand',     ledger_type: { name: 'Asset' } },
    { id: 2, code: '1002', name: 'Bank — Savings',   ledger_type: { name: 'Asset' } },
    { id: 3, code: '2001', name: 'Tuition Income',   ledger_type: { name: 'Income' } },
    { id: 4, code: '2002', name: 'Transport Income', ledger_type: { name: 'Income' } },
    { id: 5, code: '3001', name: 'Salaries',         ledger_type: { name: 'Expense' } },
    { id: 6, code: '3002', name: 'Stationery Cost',  ledger_type: { name: 'Expense' } },
];
const selectedLedgerId = ref('');

// ── ErrorBoundary state ─────────────────────────────────────────
const errorBoundaryNonce = ref(0);
function triggerErrorBoundary() {
    errorBoundaryNonce.value++;
}
// Tiny child that throws when its `nonce` prop is non-zero.
const BoundaryDemo = defineComponent({
    name: 'BoundaryDemo',
    props: { nonce: { type: Number, default: 0 } },
    setup(props) {
        return () => {
            if (props.nonce > 0) {
                throw new Error(`Demo error #${props.nonce} — caught by ErrorBoundary`);
            }
            return h('p', { style: 'margin:0;font-size:0.85rem;color:var(--text-muted);' },
                'Boundary is healthy. Click "Throw inside boundary" below to trigger the fallback.');
        };
    },
});

// ── Pass-card fixtures (visual demo only — never persisted) ─────
const sampleGatePass = {
    id: 1042,
    pass_token: 'demo-pass-token-1042',
    student_name: 'Aanya Sharma',
    admission_no: 'STU-2025-001',
    purpose: 'Family function',
    out_time: '2026-04-30T18:00:00Z',
    return_time: '2026-05-02T20:00:00Z',
    status: 'Approved',
};
const sampleVisitor = {
    id: 87,
    pass_token: 'demo-visitor-token-87',
    name: 'Rakesh Sharma',
    relation: 'Father',
    phone: '+91 98XXX XX012',
    visiting_student: 'Aanya Sharma',
    in_time: '2026-04-30T10:30:00Z',
    status: 'Inside',
};

// ── Disabled / loading button state demo ────────────────────────
const btnLoading = ref(false);
function toggleBtnLoading() {
    btnLoading.value = true;
    setTimeout(() => (btnLoading.value = false), 1500);
}

// ── Table loading / empty toggles ───────────────────────────────
const tblLoading = ref(false);
const tblEmpty = ref(false);
function flashTblLoading() { tblLoading.value = true; setTimeout(() => tblLoading.value = false, 1500); }

// ── Standalone SortableTh demo (no <Table> ancestor) ────────────
const standaloneSortKey = ref('email');
const standaloneSortDir = ref('asc');
function standaloneSort(key) {
    if (standaloneSortKey.value === key) {
        standaloneSortDir.value = standaloneSortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        standaloneSortKey.value = key;
        standaloneSortDir.value = 'asc';
    }
}

// ── useFormat sample data ───────────────────────────────────────
const fmtSampleDate = '2026-04-30T09:30:00Z';
const fmtSampleAmount = 1245300;

// ── Confirm helpers ─────────────────────────────────────────────
async function tryConfirmSimple() {
    const ok = await confirm('Delete this record? This cannot be undone.');
    toast[ok ? 'success' : 'info'](ok ? 'Confirmed' : 'Cancelled');
}
async function tryConfirmDanger() {
    const ok = await confirm({
        title: 'Delete student',
        message: 'This will permanently remove the student and all their records. This action cannot be undone.',
        confirmLabel: 'Yes, delete',
        cancelLabel: 'Keep student',
        danger: true,
    });
    toast[ok ? 'success' : 'info'](ok ? 'Student deleted' : 'Kept student');
}
async function tryConfirmCustom() {
    const ok = await confirm({
        title: 'Submit application?',
        message: 'You will not be able to edit it after submission.',
        confirmLabel: 'Submit',
        cancelLabel: 'Review again',
    });
    toast[ok ? 'success' : 'info'](ok ? 'Submitted' : 'Returned to review');
}
async function tryConfirmDefaults() {
    const ok = await confirm({});
    toast[ok ? 'success' : 'info'](ok ? 'OK' : 'Cancelled');
}

// ── Toast helpers (extras beyond the basics) ───────────────────
let lastToastId = null;
function toastFast() {
    lastToastId = toast.success('Disappears in 1.2s', 1200);
}
function toastDismissLast() {
    if (lastToastId !== null) toast.dismiss(lastToastId);
}
function toastClearAll() {
    toast.clear();
}

// ── FilterBar demo state ─────────────────────────────────────────
const fbSearch = ref('');
const fbClass = ref('');
const fbStatus = ref('');
const fbHouse = ref('');
const fbDefaulter = ref('');
const fbActive = computed(() =>
    !!(fbSearch.value || fbClass.value || fbStatus.value || fbHouse.value || fbDefaulter.value)
);
function fbClear() {
    fbSearch.value = ''; fbClass.value = ''; fbStatus.value = '';
    fbHouse.value = ''; fbDefaulter.value = '';
}

// ── Sortable Table demo ─────────────────────────────────────────
const sampleStudents = ref([
    { id: 1, name: 'Aanya Sharma',   class: 'Grade 7-A',  marks: 89,  attendance: 98.2, status: 'Active' },
    { id: 2, name: 'Rohan Mehta',    class: 'Grade 9-B',  marks: 72,  attendance: 85.5, status: 'Active' },
    { id: 3, name: 'Diya Iyer',      class: 'Grade 8-C',  marks: 94,  attendance: 96.0, status: 'Active' },
    { id: 4, name: 'Kabir Verma',    class: 'Grade 6-A',  marks: 65,  attendance: 78.3, status: 'Inactive' },
    { id: 5, name: 'Saanvi Reddy',   class: 'Grade 10-A', marks: 88,  attendance: 92.7, status: 'Active' },
    { id: 6, name: 'Aarav Khan',     class: 'Grade 7-B',  marks: 77,  attendance: 88.1, status: 'Active' },
    { id: 7, name: 'Myra Joshi',     class: 'Grade 9-A',  marks: 91,  attendance: 95.4, status: 'Active' },
    { id: 8, name: 'Vihaan Gupta',   class: 'Grade 8-A',  marks: 58,  attendance: 70.0, status: 'Inactive' },
]);

const { sortKey, sortDir, toggleSort, sortRows } = useTableSort('name', 'asc');
const sortedStudents = computed(() => sortRows(sampleStudents.value));
</script>

<template>
    <SchoolLayout title="UI Sandbox">

        <!-- ─── PageHeader variants ─────────────────────────────────────── -->
        <PageHeader
            title="UI Sandbox"
            subtitle="Canonical visual catalogue of every shared UI primitive. Source of truth for the design system."
        >
            <template #actions>
                <Button variant="secondary" size="sm" as="link" href="/school">Back to dashboard</Button>
                <Button size="sm" @click="toast.success('Hello from the sandbox')">Trigger toast</Button>
            </template>
        </PageHeader>

        <!-- ═══════════════════════════════════════════════════════════════
             1. TOKENS & UTILITY CLASSES
             Global classes defined in SchoolLayout.vue. Used everywhere.
             ═══════════════════════════════════════════════════════════════ -->
        <h2 class="section-heading">1 · Tokens — badges (every color)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <span class="badge badge-green">badge-green</span>
            <span class="badge badge-red">badge-red</span>
            <span class="badge badge-amber">badge-amber</span>
            <span class="badge badge-blue">badge-blue</span>
            <span class="badge badge-purple">badge-purple</span>
            <span class="badge badge-gray">badge-gray</span>
            <span class="badge badge-indigo">badge-indigo</span>
            <span class="badge badge-pink">badge-pink</span>
            <span class="badge badge-cyan">badge-cyan</span>
        </div>

        <h2 class="section-heading">1 · Tokens — card &amp; card-header</h2>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <span class="card-title">Card title — uses .card-header + .card-title</span>
                <Button size="xs" variant="secondary">Action</Button>
            </div>
            <div class="card-body">
                <p style="margin:0;font-size:0.85rem;color:var(--text-secondary);">
                    Card body uses <code>.card-body</code> for default 20px padding.
                    Cards are the standard surface — every panel, table wrapper, list and form lives in one.
                </p>
            </div>
        </div>

        <h2 class="section-heading">1 · Tokens — form-row + form-field + form-error</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <div class="form-row form-row-2">
                <div class="form-field">
                    <label>2-col field A</label>
                    <input class="form-input" placeholder="Type something">
                </div>
                <div class="form-field">
                    <label>2-col field B</label>
                    <input class="form-input" placeholder="Another value">
                    <span class="form-error">Example error message — uses .form-error</span>
                </div>
            </div>
            <div class="form-row form-row-3" style="margin-top:14px;">
                <div class="form-field"><label>3-col</label><input class="form-input"></div>
                <div class="form-field"><label>3-col</label><input class="form-input"></div>
                <div class="form-field"><label>3-col</label><input class="form-input"></div>
            </div>
            <div class="form-row form-row-4" style="margin-top:14px;">
                <div class="form-field"><label>4-col</label><input class="form-input"></div>
                <div class="form-field"><label>4-col</label><input class="form-input"></div>
                <div class="form-field"><label>4-col</label><input class="form-input"></div>
                <div class="form-field"><label>4-col</label><input class="form-input"></div>
            </div>
        </div>

        <h2 class="section-heading">1 · Tokens — section-heading (this very style)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <p style="margin:0;font-size:0.85rem;color:var(--text-secondary);">
                Use <code>&lt;h2 class="section-heading"&gt;</code> for the small uppercase
                heading you see between every block on this page. Defined once in
                <code>SchoolLayout.vue</code>; never re-declare it locally.
            </p>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             2. PageHeader variants
             ═══════════════════════════════════════════════════════════════ -->
        <h2 class="section-heading">2 · PageHeader variants</h2>

        <div class="card" style="padding:16px;margin-bottom:20px;">
            <PageHeader title="With breadcrumbs" :breadcrumbs="[
                { label: 'School', href: '/school' },
                { label: 'Finance', href: '/school/finance' },
                { label: 'Ledgers' },
            ]" subtitle="Three-step crumb trail." compact>
                <template #actions>
                    <Button size="sm">+ New</Button>
                </template>
            </PageHeader>

            <PageHeader
                title="With back link"
                back-href="/school"
                back-label="← Back to dashboard"
                subtitle="Single back-link variant for show/edit pages."
                compact
            />

            <PageHeader title="With meta badges" compact>
                <template #meta>
                    <span class="badge badge-green">Active</span>
                    <span class="badge badge-blue">v2.4</span>
                    <span class="badge badge-amber">Beta</span>
                </template>
                <template #actions>
                    <Button variant="secondary" size="sm">Edit</Button>
                    <Button variant="danger" size="sm">Delete</Button>
                </template>
            </PageHeader>

            <PageHeader
                back-href="/school"
                back-label="← Back to dashboard"
                :breadcrumbs="[
                    { label: 'School', href: '/school' },
                    { label: 'Hostel', href: '/school/hostel' },
                    { label: 'Gate Passes' },
                ]"
                subtitle="Composite — back link + breadcrumbs + meta + actions in one header."
                compact
            >
                <template #meta>
                    <span class="badge badge-purple">Module: Hostel</span>
                    <span class="badge badge-gray">42 records</span>
                </template>
                <template #actions>
                    <Button variant="secondary" size="sm">Export</Button>
                    <Button size="sm">+ New Pass</Button>
                </template>
            </PageHeader>

            <PageHeader subtitle="Custom #title slot — inline icon + badge in the heading itself." compact>
                <template #title>
                    <h1 class="page-header-title" style="display:flex;align-items:center;gap:10px;">
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;background:#eef2ff;color:#6366f1;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 22h20L12 2z"/></svg>
                        </span>
                        Custom title node
                        <span class="badge badge-amber">Beta</span>
                    </h1>
                </template>
            </PageHeader>

            <PageHeader title="Custom #subtitle slot" compact>
                <template #subtitle>
                    <p class="page-header-sub" style="display:flex;align-items:center;gap:8px;">
                        <span class="badge badge-cyan">v3</span>
                        <span>Render a richer subtitle node — links, badges, status pills.</span>
                    </p>
                </template>
            </PageHeader>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             3. StatsRow variants
             ═══════════════════════════════════════════════════════════════ -->
        <h2 class="section-heading">3 · StatsRow — 4 cols (default)</h2>
        <StatsRow :cols="4" :stats="[
            { label: 'Total Students', value: 1247, color: 'accent', sub: '↑ 24 this month' },
            { label: 'Active Staff', value: 89, color: 'success', trend: 4 },
            { label: 'Defaulters', value: 12, color: 'danger', trend: -2 },
            { label: 'Pending Apps', value: 6, color: 'warning' },
        ]" />

        <h2 class="section-heading">3 · StatsRow — 3 cols</h2>
        <StatsRow :cols="3" :stats="[
            { label: 'Total Debit', value: '₹12,45,300', color: 'accent', sub: '24 accounts' },
            { label: 'Total Credit', value: '₹8,90,150', color: 'success', sub: '18 accounts' },
            { label: 'Net Position', value: '₹3,55,150', color: 'info', sub: 'Surplus' },
        ]" />

        <h2 class="section-heading">3 · StatsRow — 2 cols</h2>
        <StatsRow :cols="2" :stats="[
            { label: 'Today\'s Attendance', value: '94.2%', color: 'success', trend: 1 },
            { label: 'Late Arrivals', value: 17, color: 'warning' },
        ]" />

        <h2 class="section-heading">3 · StatsRow — every named color (purple / pink / gray / info / custom hex)</h2>
        <StatsRow :cols="4" :stats="[
            { label: 'Purple',  value: 42, color: 'purple', sub: 'color=&quot;purple&quot;' },
            { label: 'Pink',    value: 18, color: 'pink',   sub: 'color=&quot;pink&quot;' },
            { label: 'Gray',    value: 7,  color: 'gray',   sub: 'color=&quot;gray&quot;' },
            { label: 'Info',    value: 23, color: 'info',   sub: 'color=&quot;info&quot;' },
        ]" />
        <StatsRow :cols="3" :stats="[
            { label: 'Custom hex',   value: 99, color: '#0ea5e9', sub: 'color=&quot;#0ea5e9&quot;' },
            { label: 'Trend up',     value: 124, color: 'accent', trend: 12 },
            { label: 'Trend down',   value: 7,   color: 'danger', trend: -6 },
        ]" />

        <h2 class="section-heading">3 · StatsRow — custom icon via stat.icon (HTML string)</h2>
        <StatsRow :cols="3" :stats="[
            {
                label: 'With custom SVG icon', value: 8,
                color: 'success',
                icon: '&lt;svg viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot; stroke-linecap=&quot;round&quot;&gt;&lt;path d=&quot;M20 6L9 17l-5-5&quot;/&gt;&lt;/svg&gt;',
            },
            {
                label: 'Override via slot', value: 5, color: 'pink',
            },
            {
                label: 'Default chart icon', value: 100, color: 'accent',
            },
        ]">
            <template #icon-override-via-slot>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </template>
        </StatsRow>

        <!-- ─── Tabs variants ───────────────────────────────────────────── -->
        <h2 class="section-heading">4 · Tabs — with counts</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <Tabs v-model="activeTab" :tabs="[
                { key: 'overview', label: 'Overview' },
                { key: 'students', label: 'Students', count: 124 },
                { key: 'staff',    label: 'Staff', count: 18 },
                { key: 'archived', label: 'Archived', count: 0, disabled: true },
            ]">
                <template #tab-overview>
                    <p style="color:var(--text-muted);font-size:0.85rem;">Overview content goes here.</p>
                </template>
                <template #tab-students>
                    <p style="color:var(--text-muted);font-size:0.85rem;">Students tab content. <strong>124</strong> records.</p>
                </template>
                <template #tab-staff>
                    <p style="color:var(--text-muted);font-size:0.85rem;">Staff tab content. <strong>18</strong> records.</p>
                </template>
            </Tabs>
        </div>

        <h2 class="section-heading">4 · Tabs — fluid (full-width)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <Tabs v-model="activeTabFluid" fluid :tabs="[
                { key: 'a', label: 'Trial Balance' },
                { key: 'b', label: 'Profit &amp; Loss' },
                { key: 'c', label: 'Balance Sheet' },
            ]">
                <template #tab-a><p style="color:var(--text-muted);font-size:0.85rem;">Trial balance content.</p></template>
                <template #tab-b><p style="color:var(--text-muted);font-size:0.85rem;">P&amp;L content.</p></template>
                <template #tab-c><p style="color:var(--text-muted);font-size:0.85rem;">Balance sheet content.</p></template>
            </Tabs>
        </div>

        <h2 class="section-heading">4 · Tabs — with icons (tab.icon as HTML string)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <Tabs v-model="activeTabIcons" :tabs="[
                { key: 'home',     label: 'Home',     icon: '&lt;svg viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot;&gt;&lt;path d=&quot;M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z&quot;/&gt;&lt;/svg&gt;' },
                { key: 'profile',  label: 'Profile',  icon: '&lt;svg viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot;&gt;&lt;path d=&quot;M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2&quot;/&gt;&lt;circle cx=&quot;12&quot; cy=&quot;7&quot; r=&quot;4&quot;/&gt;&lt;/svg&gt;' },
                { key: 'settings', label: 'Settings', icon: '&lt;svg viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot;&gt;&lt;circle cx=&quot;12&quot; cy=&quot;12&quot; r=&quot;3&quot;/&gt;&lt;path d=&quot;M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z&quot;/&gt;&lt;/svg&gt;' },
            ]">
                <template #tab-home><p style="color:var(--text-muted);font-size:0.85rem;">Home content.</p></template>
                <template #tab-profile><p style="color:var(--text-muted);font-size:0.85rem;">Profile content.</p></template>
                <template #tab-settings><p style="color:var(--text-muted);font-size:0.85rem;">Settings content.</p></template>
            </Tabs>
        </div>

        <h2 class="section-heading">4 · Tabs — plain (no counts, no icons)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <Tabs v-model="activeTabPlain" :tabs="[
                { key: 'one',   label: 'One' },
                { key: 'two',   label: 'Two' },
                { key: 'three', label: 'Three' },
            ]">
                <template #tab-one><p style="color:var(--text-muted);font-size:0.85rem;">Bare-bones tab strip — useful for ≤4 sibling sections without counts.</p></template>
                <template #tab-two><p style="color:var(--text-muted);font-size:0.85rem;">Tab two body.</p></template>
                <template #tab-three><p style="color:var(--text-muted);font-size:0.85rem;">Tab three body.</p></template>
            </Tabs>
        </div>

        <!-- ─── EmptyState variants ─────────────────────────────────────── -->
        <h2 class="section-heading">5 · EmptyState — default with action</h2>
        <div class="card" style="margin-bottom:20px;">
            <EmptyState
                title="No students found"
                description="Try adjusting your search filters, or admit your first student to get started."
                action-label="+ New Admission"
                action-href="/school/students/create"
            />
        </div>

        <h2 class="section-heading">5 · EmptyState — compact (inline within tables)</h2>
        <div class="card" style="margin-bottom:20px;">
            <EmptyState
                variant="compact"
                title="No records"
                description="Nothing matches your current filter."
            />
        </div>

        <h2 class="section-heading">5 · EmptyState — muted tone, no action</h2>
        <div class="card" style="margin-bottom:20px;">
            <EmptyState
                tone="muted"
                title="Quiet here"
                description="No activity yet today."
            />
        </div>

        <h2 class="section-heading">5 · EmptyState — action button (emits @action — no href)</h2>
        <div class="card" style="margin-bottom:20px;">
            <EmptyState
                title="Try it the click-way"
                description="When you pass action-label without action-href, the CTA renders as a button and emits the action event."
                action-label="Click me"
                @action="toast.info('@action emitted')"
            />
        </div>

        <h2 class="section-heading">5 · EmptyState — custom #icon &amp; #action slots</h2>
        <div class="card" style="margin-bottom:20px;">
            <EmptyState title="Custom icon &amp; action" description="Slots #icon and #action let you fully customize.">
                <template #icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                        <line x1="9" y1="9" x2="9.01" y2="9"/>
                        <line x1="15" y1="9" x2="15.01" y2="9"/>
                    </svg>
                </template>
                <template #action>
                    <Button variant="secondary" size="sm">Secondary CTA</Button>
                    <Button size="sm" @click="toast.success('Custom action click')">Primary CTA</Button>
                </template>
            </EmptyState>
        </div>

        <h2 class="section-heading">5 · EmptyState — accent tone (default) labelled + #default slot for extra content</h2>
        <div class="card" style="margin-bottom:20px;">
            <EmptyState tone="accent" variant="default" title="Accent tone + variant=&quot;default&quot;" description="Both prop defaults declared explicitly here for the audit.">
                <p style="font-size:0.78rem;color:var(--text-muted);margin-top:6px;">
                    Slot #default takes any extra inline help below the description. See the
                    <a href="#" style="color:var(--accent);">documentation</a> for setup steps.
                </p>
            </EmptyState>
        </div>

        <!-- ─── FilterBar — canonical single-row filter pattern ─────────── -->
        <h2 class="section-heading">6 · FilterBar — search + filters in one row</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Always one row. Overflows horizontally on narrow screens (drag to scroll).
            Clear button appears when any filter is active.
        </p>
        <FilterBar :active="fbActive" @clear="fbClear">
            <div class="fb-search">
                <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                <input v-model="fbSearch" type="search" placeholder="Search by name or admission no...">
            </div>
            <select v-model="fbClass" style="width:160px;">
                <option value="">All Classes</option>
                <option value="6">Grade 6</option>
                <option value="7">Grade 7</option>
                <option value="8">Grade 8</option>
                <option value="9">Grade 9</option>
                <option value="10">Grade 10</option>
            </select>
            <select v-model="fbStatus" style="width:140px;">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <select v-model="fbHouse" style="width:140px;">
                <option value="">All Houses</option>
                <option value="red">Red</option>
                <option value="blue">Blue</option>
                <option value="green">Green</option>
                <option value="yellow">Yellow</option>
            </select>
            <select v-model="fbDefaulter" style="width:160px;">
                <option value="">All Students</option>
                <option value="1">Defaulters Only</option>
                <option value="0">Non-Defaulters</option>
            </select>
        </FilterBar>

        <h2 class="section-heading">6 · FilterBar — minimal (one search input)</h2>
        <FilterBar :active="!!fbSearch" @clear="fbSearch = ''">
            <div class="fb-search">
                <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                <input v-model="fbSearch" type="search" placeholder="Search...">
            </div>
        </FilterBar>

        <!-- ─── Table — sortable columns ─────────────────────────────────── -->
        <h2 class="section-heading">7 · Table with sortable columns</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Click any header with the dual-arrow icon to sort. Click again to reverse direction.
            Active sort: <strong>{{ sortKey || 'none' }}</strong> ({{ sortDir }})
        </p>
        <div class="card" style="margin-bottom:20px;">
            <Table :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
                <thead>
                    <tr>
                        <SortableTh sort-key="name">Student</SortableTh>
                        <SortableTh sort-key="class">Class</SortableTh>
                        <SortableTh sort-key="marks" align="right">Marks</SortableTh>
                        <SortableTh sort-key="attendance" align="right">Attendance %</SortableTh>
                        <SortableTh sort-key="status">Status</SortableTh>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in sortedStudents" :key="s.id">
                        <td style="font-weight:600;color:var(--text-primary);">{{ s.name }}</td>
                        <td>{{ s.class }}</td>
                        <td style="text-align:right;font-family:monospace;">{{ s.marks }}</td>
                        <td style="text-align:right;font-family:monospace;">{{ s.attendance }}%</td>
                        <td>
                            <span class="badge" :class="s.status === 'Active' ? 'badge-green' : 'badge-gray'">
                                {{ s.status }}
                            </span>
                        </td>
                        <td style="text-align:right;">
                            <Button variant="secondary" size="xs">View</Button>
                        </td>
                    </tr>
                </tbody>
            </Table>
        </div>

        <!-- ─── Table reference (basic, no sort) ───────────────────────────── -->
        <h2 class="section-heading">7 · Table — basic (size=&quot;md&quot; default, no sort)</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table size="md">
                <thead>
                    <tr><th>Name</th><th>Class</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <tr><td>Aanya Sharma</td><td>Grade 7-A</td><td><span class="badge badge-green">Active</span></td></tr>
                    <tr><td>Rohan Mehta</td><td>Grade 9-B</td><td><span class="badge badge-amber">Pending</span></td></tr>
                </tbody>
            </Table>
        </div>

        <h2 class="section-heading">7 · Table — density: size=&quot;sm&quot;</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table size="sm">
                <thead><tr><th>Name</th><th>Class</th><th>Status</th></tr></thead>
                <tbody>
                    <tr><td>Aanya Sharma</td><td>Grade 7-A</td><td><span class="badge badge-green">Active</span></td></tr>
                    <tr><td>Diya Iyer</td><td>Grade 8-C</td><td><span class="badge badge-blue">New</span></td></tr>
                </tbody>
            </Table>
        </div>

        <h2 class="section-heading">7 · Table — density: size=&quot;lg&quot;</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table size="lg">
                <thead><tr><th>Name</th><th>Class</th><th>Status</th></tr></thead>
                <tbody>
                    <tr><td>Aanya Sharma</td><td>Grade 7-A</td><td><span class="badge badge-green">Active</span></td></tr>
                    <tr><td>Diya Iyer</td><td>Grade 8-C</td><td><span class="badge badge-blue">New</span></td></tr>
                </tbody>
            </Table>
        </div>

        <h2 class="section-heading">7 · Table — striped</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table striped>
                <thead><tr><th>#</th><th>Name</th><th>Marks</th></tr></thead>
                <tbody>
                    <tr v-for="i in 5" :key="i"><td>{{ i }}</td><td>Student {{ i }}</td><td>{{ 60 + i * 7 }}</td></tr>
                </tbody>
            </Table>
        </div>

        <h2 class="section-heading">7 · Table — bordered (financial / report style)</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table bordered>
                <thead><tr><th>Account</th><th style="text-align:right;">Debit</th><th style="text-align:right;">Credit</th></tr></thead>
                <tbody>
                    <tr><td>Cash in Hand</td><td style="text-align:right;font-family:monospace;">12,500</td><td style="text-align:right;font-family:monospace;">—</td></tr>
                    <tr><td>Tuition Income</td><td style="text-align:right;font-family:monospace;">—</td><td style="text-align:right;font-family:monospace;">12,500</td></tr>
                </tbody>
            </Table>
        </div>

        <h2 class="section-heading">7 · Table — loading state (overlay spinner)</h2>
        <div class="card" style="padding:12px;margin-bottom:20px;">
            <Button size="sm" @click="flashTblLoading">Flash 1.5s loading state</Button>
            <div style="margin-top:12px;">
                <Table :loading="tblLoading">
                    <thead><tr><th>Name</th><th>Class</th></tr></thead>
                    <tbody>
                        <tr><td>Aanya Sharma</td><td>Grade 7-A</td></tr>
                        <tr><td>Rohan Mehta</td><td>Grade 9-B</td></tr>
                        <tr><td>Diya Iyer</td><td>Grade 8-C</td></tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <h2 class="section-heading">7 · Table — empty state (default text)</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table empty empty-text="No students match the filters">
                <thead><tr><th>Name</th><th>Class</th></tr></thead>
                <tbody />
            </Table>
        </div>

        <h2 class="section-heading">7 · Table — empty via #empty slot (custom)</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table empty>
                <thead><tr><th>Name</th><th>Class</th></tr></thead>
                <tbody />
                <template #empty>
                    <EmptyState
                        variant="compact"
                        title="No students yet"
                        description="Use the form above to add your first student."
                    />
                </template>
            </Table>
        </div>

        <h2 class="section-heading">7 · Table — custom #loading slot</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table loading>
                <thead><tr><th>Name</th><th>Class</th></tr></thead>
                <tbody>
                    <tr><td>Aanya Sharma</td><td>Grade 7-A</td></tr>
                </tbody>
                <template #loading>
                    <div style="display:flex;flex-direction:column;align-items:center;gap:8px;">
                        <div style="width:24px;height:24px;border:3px solid #e0e7ff;border-top-color:#6366f1;border-radius:50%;animation:spin 0.8s linear infinite;"></div>
                        <span style="font-size:0.78rem;color:var(--text-muted);">Crunching the numbers…</span>
                    </div>
                </template>
            </Table>
        </div>

        <h2 class="section-heading">7 · Table — explicit sort-dir literal demos (for the audit)</h2>
        <div class="card" style="padding:14px;margin-bottom:20px;font-size:0.78rem;color:var(--text-muted);">
            <Table sort-key="name" sort-dir="asc">
                <thead><tr><th>Asc</th></tr></thead><tbody><tr><td>—</td></tr></tbody>
            </Table>
            <Table sort-key="name" sort-dir="desc" style="margin-top:8px;">
                <thead><tr><th>Desc</th></tr></thead><tbody><tr><td>—</td></tr></tbody>
            </Table>
        </div>

        <h2 class="section-heading">8 · SortableTh — align center / right + standalone (no &lt;Table&gt; ancestor)</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Standalone usage: pass <code>:current-key</code> &amp; <code>:current-dir</code> directly,
            and listen to <code>@sort</code> on the cell. Active: <strong>{{ standaloneSortKey }}</strong> ({{ standaloneSortDir }})
        </p>
        <div class="card" style="margin-bottom:20px;">
            <table class="erp-table" style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <SortableTh sort-key="name"  align="left"   :current-key="standaloneSortKey" :current-dir="standaloneSortDir" @sort="standaloneSort">Name (left)</SortableTh>
                        <SortableTh sort-key="email" align="center" :current-key="standaloneSortKey" :current-dir="standaloneSortDir" @sort="standaloneSort">Email (center)</SortableTh>
                        <SortableTh sort-key="score" align="right"  :current-key="standaloneSortKey" :current-dir="standaloneSortDir" @sort="standaloneSort">Score (right)</SortableTh>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Aanya Sharma</td><td style="text-align:center;">aanya@example.com</td><td style="text-align:right;font-family:monospace;">94</td></tr>
                    <tr><td>Rohan Mehta</td><td style="text-align:center;">rohan@example.com</td><td style="text-align:right;font-family:monospace;">82</td></tr>
                </tbody>
            </table>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             9. Button — every variant × every size × every state × every slot
             ═══════════════════════════════════════════════════════════════ -->
        <h2 class="section-heading">9 · Button — variants (10 total)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <Button variant="primary">primary</Button>
            <Button variant="secondary">secondary</Button>
            <Button variant="danger">danger</Button>
            <Button variant="success">success</Button>
            <Button variant="warning">warning</Button>
            <Button variant="save">save</Button>
            <Button variant="cancel">cancel</Button>
            <Button variant="ghost">ghost</Button>
            <Button variant="icon" aria-label="Settings">
                <template #icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                </template>
            </Button>
            <Button variant="tab" :active="false">tab (inactive)</Button>
            <Button variant="tab" :active="true">tab (active)</Button>
        </div>

        <h2 class="section-heading">9 · Button — sizes (xs / sm / md / lg)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <Button size="xs">xs</Button>
            <Button size="sm">sm</Button>
            <Button size="md">md (default)</Button>
            <Button size="lg">lg</Button>
            <Button size="xs" variant="secondary">xs secondary</Button>
            <Button size="lg" variant="danger">lg danger</Button>
        </div>

        <h2 class="section-heading">9 · Button — states (loading / disabled / block)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-direction:column;gap:10px;">
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                <Button :loading="btnLoading" @click="toggleBtnLoading">
                    {{ btnLoading ? 'Saving…' : 'Click to load 1.5s' }}
                </Button>
                <Button disabled>disabled</Button>
                <Button variant="success" disabled>disabled success</Button>
                <Button variant="danger" :loading="true">always loading</Button>
            </div>
            <Button block>block (full-width)</Button>
            <Button block variant="secondary" size="sm">block · secondary · sm</Button>
        </div>

        <h2 class="section-heading">9 · Button — slots (#icon leading + #iconRight trailing)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <Button>
                <template #icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                </template>
                Add new
            </Button>
            <Button variant="secondary">
                Continue
                <template #iconRight>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </template>
            </Button>
            <Button variant="success">
                <template #icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                </template>
                Approve
                <template #iconRight>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </template>
            </Button>
        </div>

        <h2 class="section-heading">9 · Button — render targets (as=&quot;button&quot; / as=&quot;link&quot; / as=&quot;a&quot;)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <Button as="button" type="submit">as=&quot;button&quot; (default)</Button>
            <Button as="link" href="/school">as=&quot;link&quot; — Inertia &lt;Link&gt;</Button>
            <Button as="a" href="https://example.com" variant="secondary">as=&quot;a&quot; — plain &lt;a&gt;</Button>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             10. Modal — sizes, slots, behavior flags
             ═══════════════════════════════════════════════════════════════ -->
        <h2 class="section-heading">10 · Modal sizes &amp; variants</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;">
            <Button @click="showSm = true" size="sm">Open sm (400px)</Button>
            <Button @click="showMd = true" size="sm">Open md (520px)</Button>
            <Button @click="showLg = true" size="sm">Open lg (720px)</Button>
            <Button @click="showXl = true" size="sm">Open xl (960px)</Button>
            <Button @click="showPersistent = true" size="sm" variant="secondary">Open persistent (no ESC/backdrop)</Button>
            <Button @click="showNoFooter = true" size="sm" variant="secondary">Open no-footer</Button>
            <Button @click="showNoHeader = true" size="sm" variant="secondary">Open no-header</Button>
            <Button @click="showHeaderActions = true" size="sm" variant="secondary">Open #header-actions slot</Button>
            <Button @click="showBodyClass = true" size="sm" variant="secondary">Open custom body-class</Button>
            <Button @click="showBackdropOnly = true" size="sm" variant="secondary">Open close-on-backdrop=false</Button>
            <Button @click="showEscOnly = true" size="sm" variant="secondary">Open close-on-esc=false</Button>
        </div>

        <!-- ─── Export & Print primitives ──────────────────────────────── -->
        <h2 class="section-heading">11 · ExportDropdown — multi-format download</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Server-generated downloads. Click a format to fetch the file. Backend route receives
            <code>?output=excel|pdf|csv</code> + your filter params.
            <strong>This is the canonical way to export — produces real downloadable files, never print preview.</strong>
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;">
            <ExportDropdown base-url="/school/export/students" :params="{ search: '', class_id: '' }" />
            <ExportDropdown base-url="/school/export/staff" label="Download" :formats="['excel', 'pdf']" />
            <ExportDropdown base-url="/school/export/example" label="Excel only" :formats="['excel']" />
        </div>

        <h2 class="section-heading">11 · PrintButton — print current page or open print view</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Two modes. <code>&lt;PrintButton /&gt;</code> calls <code>window.print()</code> on the current page.
            <code>&lt;PrintButton href="…" /&gt;</code> opens that URL in a new tab — typically a print-view template
            or a server-generated PDF endpoint.
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;">
            <PrintButton />
            <PrintButton label="Print Receipt" href="/school/_ui-sandbox" />
            <PrintButton label="Download PDF" href="/school/export/example?output=pdf" variant="primary" />
            <PrintButton label="Disabled" disabled />
        </div>

        <p style="font-size:0.78rem;color:var(--text-muted);margin:-10px 0 14px;padding:10px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;">
            <strong>Convention:</strong> <em>"Print"</em> = browser print dialog (or print-view template).
            <em>"PDF"</em> / <em>"Excel"</em> / <em>"CSV"</em> = a real downloadable file from a backend endpoint.
            Don't label a print preview as "PDF".
        </p>

        <!-- ─── Toast variants ─────────────────────────────────────────── -->
        <h2 class="section-heading">12 · Toast notifications — useToast() composable</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Stack appears bottom-right, auto-dismisses with progress bar, max 5 visible.
            Inertia flash messages from any controller (<code>back()-&gt;with('success', '...')</code>)
            automatically become toasts via SchoolLayout — no per-page code needed.
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;">
            <Button size="sm" variant="success" @click="toast.success('Record saved successfully')">toast.success</Button>
            <Button size="sm" variant="danger"  @click="toast.error('Could not save — server returned an error')">toast.error</Button>
            <Button size="sm" variant="warning" @click="toast.warning('Please select at least one student')">toast.warning</Button>
            <Button size="sm" variant="secondary" @click="toast.info('Password copied to clipboard!')">toast.info</Button>
            <Button size="sm" variant="secondary" @click="
                toast.success('Saved');
                setTimeout(() => toast.info('Synced to cloud'), 200);
                setTimeout(() => toast.warning('1 row needs review'), 400);
                setTimeout(() => toast.error('Email service unavailable'), 600);
            ">Stack 4 in a row</Button>
            <Button size="sm" variant="secondary" @click="toastFast">Custom duration (1.2s)</Button>
            <Button size="sm" variant="secondary" @click="toastDismissLast">Dismiss last by id</Button>
            <Button size="sm" variant="secondary" @click="toastClearAll">Clear all</Button>
        </div>

        <!-- ─── ConfirmDialog variants ──────────────────────────────────── -->
        <h2 class="section-heading">13 · ConfirmDialog (replaces native confirm())</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;">
            <Button @click="tryConfirmSimple" size="sm">Simple (string arg)</Button>
            <Button @click="tryConfirmDanger" size="sm" variant="danger">Danger (delete style)</Button>
            <Button @click="tryConfirmCustom" size="sm" variant="secondary">Custom labels</Button>
            <Button @click="tryConfirmDefaults" size="sm" variant="secondary">Defaults (no args)</Button>
        </div>

        <!-- ─── Modal definitions (rendered at the end, teleported anyway) ── -->
        <Modal v-model:open="showSm" title="Small modal" size="sm">
            <p style="font-size:0.875rem;color:var(--text-secondary);">
                400px wide. Good for confirmations, small forms, or info dialogs.
            </p>
            <template #footer>
                <Button variant="secondary" @click="showSm = false">Cancel</Button>
                <Button @click="showSm = false">OK</Button>
            </template>
        </Modal>

        <Modal v-model:open="showMd" title="Medium modal (default)" size="md">
            <div class="form-row form-row-2">
                <div class="form-field">
                    <label>First Name</label>
                    <input class="form-input" placeholder="Aanya">
                </div>
                <div class="form-field">
                    <label>Last Name</label>
                    <input class="form-input" placeholder="Sharma">
                </div>
            </div>
            <div class="form-field" style="margin-top:14px;">
                <label>Notes</label>
                <textarea class="form-input" rows="3" placeholder="Optional notes"></textarea>
            </div>
            <template #footer>
                <Button variant="secondary" @click="showMd = false">Cancel</Button>
                <Button @click="showMd = false">Save</Button>
            </template>
        </Modal>

        <Modal v-model:open="showLg" title="Large modal" size="lg">
            <p style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:12px;">
                720px wide. Good for forms with multiple sections, lists with detail, etc.
            </p>
            <Table>
                <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Balance</th></tr></thead>
                <tbody>
                    <tr v-for="i in 5" :key="i">
                        <td>10{{ i }}</td>
                        <td>Sample Ledger {{ i }}</td>
                        <td>Asset</td>
                        <td>₹{{ (1000 * i).toLocaleString() }}</td>
                    </tr>
                </tbody>
            </Table>
            <template #footer>
                <Button variant="secondary" @click="showLg = false">Close</Button>
            </template>
        </Modal>

        <Modal v-model:open="showXl" title="Extra-large modal" size="xl">
            <p style="font-size:0.875rem;color:var(--text-secondary);">
                960px wide. Reserved for complex flows like bulk imports or composite editors.
            </p>
            <div class="form-row form-row-3" style="margin-top:14px;">
                <div class="form-field" v-for="i in 6" :key="i">
                    <label>Field {{ i }}</label>
                    <input class="form-input" :placeholder="`Field ${i}`">
                </div>
            </div>
            <template #footer>
                <Button variant="secondary" @click="showXl = false">Cancel</Button>
                <Button @click="showXl = false">Done</Button>
            </template>
        </Modal>

        <Modal v-model:open="showPersistent" title="Persistent modal" persistent hide-close>
            <p style="font-size:0.875rem;color:var(--text-secondary);">
                ESC and backdrop click are disabled. Close button hidden too. The user must
                explicitly use a footer button to dismiss.
            </p>
            <template #footer>
                <Button @click="showPersistent = false">I understand</Button>
            </template>
        </Modal>

        <Modal v-model:open="showNoFooter" title="No footer">
            <p style="font-size:0.875rem;color:var(--text-secondary);">
                When no footer slot is provided, the modal renders without a footer bar —
                useful for read-only detail views or media viewers.
            </p>
        </Modal>

        <Modal v-model:open="showNoHeader" title="" hide-close>
            <p style="font-size:0.875rem;color:var(--text-secondary);">
                With no title and hide-close, the header bar is suppressed entirely. ESC and
                backdrop click still work.
            </p>
            <template #footer>
                <Button variant="secondary" @click="showNoHeader = false">Close</Button>
            </template>
        </Modal>

        <Modal v-model:open="showHeaderActions" title="With header-actions slot" size="md">
            <template #header-actions>
                <Button size="xs" variant="ghost" @click="toast.info('Refresh clicked')">↻ Refresh</Button>
                <Button size="xs" variant="ghost" @click="toast.info('Help clicked')">? Help</Button>
            </template>
            <p style="font-size:0.875rem;color:var(--text-secondary);">
                Extra controls render to the LEFT of the × close button.
                Useful for refresh / help / kebab-menu actions inside long-running modals.
            </p>
            <template #footer>
                <Button variant="secondary" @click="showHeaderActions = false">Close</Button>
            </template>
        </Modal>

        <Modal v-model:open="showBodyClass" title="Custom body class" size="md" body-class="ui-sandbox-modal-body">
            <p style="font-size:0.875rem;color:var(--text-secondary);">
                <code>body-class="ui-sandbox-modal-body"</code> attaches an extra class to the
                body wrapper. Use it for module-specific body padding, dark themes, etc.
            </p>
            <template #footer>
                <Button variant="secondary" @click="showBodyClass = false">Close</Button>
            </template>
        </Modal>

        <Modal v-model:open="showBackdropOnly" title="closeOnBackdrop=false (only)" size="sm" :close-on-backdrop="false">
            <p style="font-size:0.875rem;color:var(--text-secondary);">
                Backdrop click is disabled, but ESC still closes. Use when you want to prevent
                accidental dismissal but keep the keyboard escape hatch.
            </p>
            <template #footer>
                <Button @click="showBackdropOnly = false">Close</Button>
            </template>
        </Modal>

        <Modal v-model:open="showEscOnly" title="closeOnEsc=false (only)" size="sm" :close-on-esc="false">
            <p style="font-size:0.875rem;color:var(--text-secondary);">
                ESC is disabled, but the backdrop still closes. Use to prevent ESC from
                cancelling a wizard step but allow click-outside dismissal.
            </p>
            <template #footer>
                <Button @click="showEscOnly = false">Close</Button>
            </template>
        </Modal>

        <!-- ═══════════════════════════════════════════════════════════════
             14-22. New primitive sections (rendered after the modal block)
             Kept here so the visual ordering matches the section TOC.
             ═══════════════════════════════════════════════════════════════ -->

        <h2 class="section-heading">14 · SlidePanel — right-side form panel</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Used in 12+ pages (Academics/Subjects, Sections, AcademicYears, Schedule/Periods, etc.) for create/edit forms.
            Renders fixed right-edge with a soft slide-in transition. Pass <code>width="w-[420px]"</code> for wider panels.
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;">
            <Button size="sm" @click="showSlideSm = true">Open default (w-96)</Button>
            <Button size="sm" variant="secondary" @click="showSlideLg = true">Open wide (w-[420px])</Button>
        </div>

        <SlidePanel :open="showSlideSm" title="Add Subject" @close="showSlideSm = false">
            <div class="form-field" style="margin-bottom:14px;">
                <label>Subject Name</label>
                <input class="form-input" placeholder="Mathematics">
            </div>
            <div class="form-field" style="margin-bottom:14px;">
                <label>Code</label>
                <input class="form-input" placeholder="MATH">
            </div>
            <Button block @click="showSlideSm = false; toast.success('Subject saved')">Save</Button>
        </SlidePanel>

        <SlidePanel :open="showSlideLg" title="Edit Period (wider panel)" width="w-[420px]" @close="showSlideLg = false">
            <div class="form-row form-row-2" style="margin-bottom:14px;">
                <div class="form-field"><label>Start</label><input class="form-input" type="time" value="09:00"></div>
                <div class="form-field"><label>End</label><input class="form-input" type="time" value="09:45"></div>
            </div>
            <div class="form-field" style="margin-bottom:14px;">
                <label>Notes</label>
                <textarea class="form-input" rows="3" placeholder="Optional"></textarea>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <Button variant="secondary" @click="showSlideLg = false">Cancel</Button>
                <Button @click="showSlideLg = false; toast.success('Period updated')">Save changes</Button>
            </div>
        </SlidePanel>

        <h2 class="section-heading">15 · DateRangeFilter — presets + custom range</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Emits <code>@change="{ from, to }"</code>. Used by <code>School/Ai/Insights.vue</code>.
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <DateRangeFilter :from="drFrom" :to="drTo" @change="onDateRange" />
            <p style="font-size:0.78rem;color:var(--text-muted);margin:14px 0 0;">
                Last @change payload: <code>{{ drLastChange ? JSON.stringify(drLastChange) : '— click a preset or Apply —' }}</code>
            </p>
        </div>

        <h2 class="section-heading">16 · LedgerCombobox — searchable account picker</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Used in <code>Finance/Transactions/Create.vue</code>. Searches code/name/type, groups by ledger type in the dropdown.
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;max-width:520px;">
            <label style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;">Account</label>
            <LedgerCombobox v-model="selectedLedgerId" :ledgers="sampleLedgers" />
            <p style="font-size:0.78rem;color:var(--text-muted);margin:10px 0 0;">
                Selected id: <code>{{ selectedLedgerId || '—' }}</code>
            </p>
        </div>

        <h2 class="section-heading">17 · PermissionGate — declarative permission gating</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Three modes: exact <code>permission</code>, any-match <code>:any</code>, all-match <code>:all</code>.
            Provide <code>#fallback</code> for the denied state. (Sandbox renders the gates without real
            auth context — both branches show below for visual reference.)
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;display:grid;grid-template-columns:repeat(3, 1fr);gap:14px;">
            <div>
                <p style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:6px;">permission="…"</p>
                <PermissionGate permission="view_students">
                    <span class="badge badge-green">Allowed branch</span>
                    <template #fallback><span class="badge badge-gray">No view_students</span></template>
                </PermissionGate>
            </div>
            <div>
                <p style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:6px;">:any="['edit_fee','delete_fee']"</p>
                <PermissionGate :any="['edit_fee', 'delete_fee']">
                    <span class="badge badge-green">Allowed (has at least one)</span>
                    <template #fallback><span class="badge badge-gray">Has neither</span></template>
                </PermissionGate>
            </div>
            <div>
                <p style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:6px;">:all="['create_payroll','edit_payroll']"</p>
                <PermissionGate :all="['create_payroll', 'edit_payroll']">
                    <span class="badge badge-green">Allowed (has all)</span>
                    <template #fallback><span class="badge badge-gray">Missing one or more</span></template>
                </PermissionGate>
            </div>
        </div>

        <h2 class="section-heading">18 · LanguageSwitcher — locale dropdown (also mounted in topbar)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;align-items:center;gap:14px;">
            <LanguageSwitcher />
            <span style="font-size:0.8rem;color:var(--text-muted);">Click to swap locale — uses <code>vue-i18n</code> + <code>plugins/i18n.js</code>.</span>
        </div>

        <h2 class="section-heading">19 · ErrorBoundary — onErrorCaptured + #fallback</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Wrap any subtree with <code>&lt;ErrorBoundary&gt;</code>. The fallback slot renders if any
            descendant throws during render. Useful for module-level isolation so one widget's
            crash doesn't take down the page.
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <ErrorBoundary>
                <BoundaryDemo :nonce="errorBoundaryNonce" />
                <template #fallback="{ error, reset }">
                    <div style="padding:14px;background:#fee2e2;border-radius:10px;color:#991b1b;font-size:0.85rem;">
                        Caught: <strong>{{ error?.message || 'Unknown error' }}</strong>.
                        <Button size="xs" variant="secondary" style="margin-left:10px;" @click="errorBoundaryNonce = 0; reset()">Retry</Button>
                    </div>
                </template>
            </ErrorBoundary>
            <Button size="sm" variant="danger" style="margin-top:12px;" @click="triggerErrorBoundary">Throw inside boundary</Button>
        </div>

        <h2 class="section-heading">20 · Pass cards — IdCardQR (live QR canvas)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;align-items:center;gap:18px;">
            <IdCardQR value="DEMO-STU-2025-001" :size="80" />
            <div>
                <p style="font-size:0.8rem;color:var(--text-secondary);margin:0;">
                    Lightweight wrapper around the <code>qrcode</code> npm lib. Renders a canvas of arbitrary <code>:size</code>.
                    Used in ID-card print templates and gate pass cards.
                </p>
            </div>
        </div>

        <h2 class="section-heading">20 · Pass cards — GatePassCard / VisitorPassCard (visual reference)</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Live pass-card components depend on <code>useSchoolStore()</code> + a real verify-token endpoint
            and are best previewed inside Hostel/GatePasses or Hostel/Visitors. The fixtures below show
            the data shape they expect.
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <pre style="margin:0;background:#0f172a;color:#e2e8f0;padding:14px;border-radius:10px;font-size:0.72rem;line-height:1.5;overflow:auto;">// GatePassCard fixture
{{ JSON.stringify(sampleGatePass, null, 2) }}

// VisitorPassCard fixture
{{ JSON.stringify(sampleVisitor, null, 2) }}</pre>
        </div>

        <h2 class="section-heading">21 · WebcamCapture — camera modal</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Self-contained modal that requests <code>navigator.mediaDevices.getUserMedia</code>,
            previews the live feed, captures a still and emits <code>@captured(dataUrl)</code>.
            Sandbox does not auto-launch — open it inside Students/Create or any photo-upload form
            to see it in action.
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;font-size:0.78rem;color:var(--text-muted);">
            Mount as <code>&lt;WebcamCapture title="Capture Photo" @captured="onPhoto" @close="show = false" /&gt;</code>.
        </div>

        <h2 class="section-heading">22 · Layout-mounted primitives</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;font-size:0.85rem;color:var(--text-secondary);">
            <p style="margin:0 0 10px;">These are mounted ONCE at the app root in <code>SchoolLayout.vue</code>; pages should never re-mount them:</p>
            <ul style="margin:0;padding-left:18px;line-height:1.7;">
                <li><code>&lt;Toast /&gt;</code> — global toast stack (see section 12)</li>
                <li><code>&lt;ConfirmDialog /&gt;</code> — singleton dialog wired to <code>useConfirm()</code> (section 13)</li>
                <li><code>&lt;LanguageSwitcher /&gt;</code> — also rendered in topbar (section 18)</li>
                <li><code>&lt;AiChatbot /&gt;</code> — floating AI assistant bubble (bottom-right)</li>
                <li><code>&lt;ChatWidget /&gt;</code> — staff-to-staff chat widget (bottom-left)</li>
            </ul>
        </div>

        <h2 class="section-heading">23 · Composables — useFormat (date / time / datetime / money)</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Always format dates/times/money through <code>useFormat()</code> so they respect the school's
            System Config (date format, time format, currency symbol). Sample input:
            <code>{{ fmtSampleDate }}</code> · <code>{{ fmtSampleAmount }}</code>
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <table style="width:100%;font-size:0.85rem;border-collapse:collapse;">
                <tbody>
                    <tr><td style="padding:6px 0;color:var(--text-muted);width:240px;"><code>formatDate(input)</code></td><td><strong>{{ formatDate(fmtSampleDate) }}</strong></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-muted);"><code>formatTime(input)</code></td><td><strong>{{ formatTime(fmtSampleDate) }}</strong></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-muted);"><code>formatDateTime(input)</code></td><td><strong>{{ formatDateTime(fmtSampleDate) }}</strong></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-muted);"><code>formatMoney(amount)</code></td><td><strong>{{ formatMoney(fmtSampleAmount) }}</strong></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-muted);"><code>formatMoney(amount, { fixed: true })</code></td><td><strong>{{ formatMoney(fmtSampleAmount, { fixed: true }) }}</strong></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-muted);"><code>formatDate(null)</code> &middot; empty input</td><td><strong>{{ formatDate(null) }}</strong></td></tr>
                </tbody>
            </table>
        </div>

        <h2 class="section-heading">23 · Composables — call-site reference (useDelete, useTableFilters, usePermissions, useClassSections)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <pre style="margin:0;background:#0f172a;color:#e2e8f0;padding:14px;border-radius:10px;font-size:0.72rem;line-height:1.55;overflow:auto;" v-pre>// useDelete — DELETE with styled &lt;ConfirmDialog&gt;
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
</pre>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* .section-heading, .badge*, .card*, .form-row*, .form-field, .form-error live in
   SchoolLayout.vue and are the canonical app-wide definitions — never override here.

   .form-input is currently defined per-page across the codebase (no global rule yet).
   The sandbox keeps a baseline copy so the demo inputs look right. When a global
   definition is added to SchoolLayout, delete this block. */
.form-input {
    border: 1.5px solid var(--border);
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.85rem;
    width: 100%;
    color: var(--text-primary);
    transition: border-color 0.15s;
    font-family: inherit;
}
.form-input:focus { border-color: var(--accent); outline: none; }

/* Sandbox-only Modal body class demo (referenced by section 10) */
.ui-sandbox-modal-body {
    background: linear-gradient(180deg, #f8fafc, #ffffff);
}
</style>
