<script setup>
/**
 * UI Sandbox · Components
 * StatsRow, Tabs, EmptyState, PageHeader extras, IdCardQR, PermissionGate,
 * pass cards, ExportDropdown, PrintButton, layout-mounted note.
 * URL: /school/_ui-sandbox/components
 */
import { ref } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PrintButton from '@/Components/ui/PrintButton.vue';
import ExportDropdown from '@/Components/ExportDropdown.vue';
// PermissionGate + IdCardQR live demos disabled here while bisecting a prod
// render-effect recursion. Both still ship and are used in real pages.
// import PermissionGate from '@/Components/PermissionGate.vue';
// import IdCardQR from '@/Components/IdCardQR.vue';
import { useToast } from '@/Composables/useToast';

const toast = useToast();

const activeTabPlain = ref('one');

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
</script>

<template>
    <SchoolLayout title="UI Sandbox · Components">

        <PageHeader
            title="Components"
            subtitle="Extras for StatsRow / Tabs / EmptyState / PageHeader, plus IdCardQR, PermissionGate, ExportDropdown, PrintButton, pass cards."
            back-href="/school/_ui-sandbox"
            back-label="← Back to sandbox"
        />

        <!-- ── Tokens — badges (every color) ───────────────────────── -->
        <h2 class="section-heading">Tokens — badges (every color)</h2>
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

        <h2 class="section-heading">Tokens — card &amp; card-header</h2>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <span class="card-title">Card title — uses .card-header + .card-title</span>
                <Button size="xs" variant="secondary">Action</Button>
            </div>
            <div class="card-body">
                <p style="margin:0;font-size:0.85rem;color:var(--text-secondary);">
                    Card body uses <code>.card-body</code> for default 20px padding.
                </p>
            </div>
        </div>

        <!-- ── PageHeader extras ───────────────────────────────────── -->
        <h2 class="section-heading">PageHeader — composite + slot variants</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;">
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

        <!-- ── StatsRow — every named color ────────────────────────── -->
        <h2 class="section-heading">StatsRow — every named color (purple / pink / gray / info / custom hex)</h2>
        <StatsRow :cols="4" :stats="[
            { label: 'Purple',  value: 42, color: 'purple', sub: 'color=&quot;purple&quot;' },
            { label: 'Pink',    value: 18, color: 'pink',   sub: 'color=&quot;pink&quot;' },
            { label: 'Gray',    value: 7,  color: 'gray',   sub: 'color=&quot;gray&quot;' },
            { label: 'Info',    value: 23, color: 'info',   sub: 'color=&quot;info&quot;' },
        ]" />
        <StatsRow :cols="3" :stats="[
            { label: 'Custom hex', value: 99,  color: '#0ea5e9', sub: 'color=&quot;#0ea5e9&quot;' },
            { label: 'Trend up',   value: 124, color: 'accent', trend: 12 },
            { label: 'Trend down', value: 7,   color: 'danger', trend: -6 },
        ]" />

        <!-- ── Tabs — code reference + plain live demo ─────────────── -->
        <h2 class="section-heading">Tabs — with icons (tab.icon API reference)</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Live demo of `tab.icon` (HTML string rendered via `v-html`) is intentionally
            omitted — that pattern combined with the array-literal `:tabs` prop has
            triggered a Vue 3.5.29 prod-bundle render-effect recursion in the past.
            The prop itself works correctly when used in real pages (e.g., Examinations).
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <pre style="margin:0;background:#0f172a;color:#e2e8f0;padding:14px;border-radius:10px;font-size:0.72rem;line-height:1.55;overflow:auto;" v-pre>&lt;Tabs v-model="active" :tabs="[
    { key: 'home',     label: 'Home',     icon: '&lt;svg ...&gt;...&lt;/svg&gt;' },
    { key: 'profile',  label: 'Profile',  icon: '&lt;svg ...&gt;...&lt;/svg&gt;' },
    { key: 'settings', label: 'Settings', icon: '&lt;svg ...&gt;...&lt;/svg&gt;' },
]"&gt;
    &lt;template #tab-home&gt;...&lt;/template&gt;
    &lt;template #tab-profile&gt;...&lt;/template&gt;
    &lt;template #tab-settings&gt;...&lt;/template&gt;
&lt;/Tabs&gt;</pre>
        </div>

        <h2 class="section-heading">Tabs — plain (no counts, no icons)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <Tabs v-model="activeTabPlain" :tabs="[
                { key: 'one',   label: 'One' },
                { key: 'two',   label: 'Two' },
                { key: 'three', label: 'Three' },
            ]">
                <template #tab-one><p style="color:var(--text-muted);font-size:0.85rem;">Bare-bones tab strip.</p></template>
                <template #tab-two><p style="color:var(--text-muted);font-size:0.85rem;">Tab two body.</p></template>
                <template #tab-three><p style="color:var(--text-muted);font-size:0.85rem;">Tab three body.</p></template>
            </Tabs>
        </div>

        <!-- ── EmptyState extras ───────────────────────────────────── -->
        <h2 class="section-heading">EmptyState — accent tone + variant=&quot;default&quot; (explicit defaults)</h2>
        <div class="card" style="margin-bottom:20px;">
            <EmptyState tone="accent" variant="default" title="Accent tone + variant=&quot;default&quot;" description="Both prop defaults declared explicitly here for the audit.">
                <p style="font-size:0.78rem;color:var(--text-muted);margin-top:6px;">
                    Slot #default takes any extra inline help below the description.
                </p>
            </EmptyState>
        </div>

        <h2 class="section-heading">EmptyState — action button (emits @action — no href)</h2>
        <div class="card" style="margin-bottom:20px;">
            <EmptyState
                title="Try it the click-way"
                description="When you pass action-label without action-href, the CTA renders as a button and emits the action event."
                action-label="Click me"
                @action="toast.info('@action emitted')"
            />
        </div>

        <h2 class="section-heading">EmptyState — custom #icon &amp; #action slots</h2>
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

        <!-- ── ExportDropdown / PrintButton ───────────────────────── -->
        <h2 class="section-heading">ExportDropdown — multi-format download</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;">
            <ExportDropdown base-url="/school/export/students" :params="{ search: '', class_id: '' }" />
            <ExportDropdown base-url="/school/export/staff" label="Download" :formats="['excel', 'pdf']" />
            <ExportDropdown base-url="/school/export/example" label="Excel only" :formats="['excel']" />
        </div>

        <h2 class="section-heading">PrintButton — print page or open print view</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;">
            <PrintButton />
            <PrintButton label="Print Receipt" href="/school/_ui-sandbox" />
            <PrintButton label="Download PDF" href="/school/export/example?output=pdf" variant="primary" />
            <PrintButton label="Disabled" disabled />
        </div>

        <!-- ── PermissionGate (code reference — live demo disabled while bisecting recursion) ── -->
        <h2 class="section-heading">PermissionGate — declarative permission gating (code reference)</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Live demo disabled here while we bisect a prod-bundle render-effect
            recursion. The primitive itself is in active use across many pages.
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <pre style="margin:0;background:#0f172a;color:#e2e8f0;padding:14px;border-radius:10px;font-size:0.72rem;line-height:1.55;overflow:auto;" v-pre>// Three modes: exact permission, any-match, all-match.
&lt;PermissionGate permission="view_students"&gt;
    &lt;ViewLink /&gt;
    &lt;template #fallback&gt;&lt;span&gt;No access&lt;/span&gt;&lt;/template&gt;
&lt;/PermissionGate&gt;

&lt;PermissionGate :any="['edit_fee', 'delete_fee']"&gt;
    &lt;FeeActions /&gt;
&lt;/PermissionGate&gt;

&lt;PermissionGate :all="['create_payroll', 'edit_payroll']"&gt;
    &lt;PayrollForm /&gt;
&lt;/PermissionGate&gt;</pre>
        </div>

        <!-- ── Pass cards / IdCardQR (code reference — live demo disabled) ── -->
        <h2 class="section-heading">IdCardQR — QR canvas (code reference)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <p style="font-size:0.8rem;color:var(--text-muted);margin:0 0 10px;">
                Lightweight wrapper around the <code>qrcode</code> npm lib. Used in
                ID-card print templates and gate-pass cards. Live demo disabled here
                while we bisect a prod-bundle render-effect recursion.
            </p>
            <pre style="margin:0;background:#0f172a;color:#e2e8f0;padding:14px;border-radius:10px;font-size:0.72rem;line-height:1.55;overflow:auto;" v-pre>import IdCardQR from '@/Components/IdCardQR.vue';

&lt;IdCardQR value="STU-2025-001" :size="80" /&gt;</pre>
        </div>

        <h2 class="section-heading">GatePassCard / VisitorPassCard — fixture reference</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Live pass-card components depend on <code>useSchoolStore()</code> + a real verify-token endpoint.
            Best previewed inside Hostel/GatePasses or Hostel/Visitors. Below are the data shapes they expect.
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <pre style="margin:0;background:#0f172a;color:#e2e8f0;padding:14px;border-radius:10px;font-size:0.72rem;line-height:1.5;overflow:auto;">{{ JSON.stringify({ GatePassCard: sampleGatePass, VisitorPassCard: sampleVisitor }, null, 2) }}</pre>
        </div>

        <!-- ── Layout-mounted ─────────────────────────────────────── -->
        <h2 class="section-heading">Layout-mounted primitives</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;font-size:0.85rem;color:var(--text-secondary);">
            <p style="margin:0 0 10px;">These are mounted ONCE at the app root in <code>SchoolLayout.vue</code>; pages should never re-mount them:</p>
            <ul style="margin:0;padding-left:18px;line-height:1.7;">
                <li><code>&lt;Toast /&gt;</code> — global toast stack (see Modals page §12)</li>
                <li><code>&lt;ConfirmDialog /&gt;</code> — singleton dialog wired to <code>useConfirm()</code></li>
                <li><code>&lt;LanguageSwitcher /&gt;</code> — locale picker rendered in topbar</li>
                <li><code>&lt;AiChatbot /&gt;</code> — floating AI assistant bubble (bottom-right)</li>
                <li><code>&lt;ChatWidget /&gt;</code> — staff-to-staff chat widget (bottom-left)</li>
                <li><code>&lt;ErrorBoundary /&gt;</code> — wrap any subtree to catch render errors via #fallback slot</li>
                <li><code>&lt;WebcamCapture /&gt;</code> — camera capture modal; mount per page where needed</li>
            </ul>
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
