<script setup>
/**
 * UI Sandbox — manual visual QA for the shared layout primitives.
 *
 * Exercises every variant of every shared UI component built in Phase 1
 * of the layout-standardization migration. Use this page to spot-check
 * visual regressions before sweeping the rest of the codebase.
 *
 * URL: /school/_ui-sandbox
 */
import { ref } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Modal from '@/Components/ui/Modal.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Button from '@/Components/ui/Button.vue';
import Table from '@/Components/ui/Table.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';

const confirm = useConfirm();
const toast = useToast();

// ── Modal state ─────────────────────────────────────────────────
const showSm = ref(false);
const showMd = ref(false);
const showLg = ref(false);
const showXl = ref(false);
const showPersistent = ref(false);
const showNoFooter = ref(false);
const showNoHeader = ref(false);

// ── Tabs state ──────────────────────────────────────────────────
const activeTab = ref('overview');
const activeTabFluid = ref('a');

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
</script>

<template>
    <SchoolLayout title="UI Sandbox">

        <!-- ─── PageHeader variants ─────────────────────────────────────── -->
        <PageHeader
            title="UI Sandbox"
            subtitle="Visual QA for shared layout primitives — Phase 1 of the standardization migration."
        >
            <template #actions>
                <Button variant="secondary" size="sm" as="link" href="/school">Back to dashboard</Button>
                <Button size="sm" @click="toast.success('Hello from the sandbox')">Trigger toast</Button>
            </template>
        </PageHeader>

        <h2 class="section-heading">PageHeader variants</h2>

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
        </div>

        <!-- ─── StatsRow variants ───────────────────────────────────────── -->
        <h2 class="section-heading">StatsRow — 4 cols (default)</h2>
        <StatsRow :cols="4" :stats="[
            { label: 'Total Students', value: 1247, color: 'accent', sub: '↑ 24 this month' },
            { label: 'Active Staff', value: 89, color: 'success', trend: 4 },
            { label: 'Defaulters', value: 12, color: 'danger', trend: -2 },
            { label: 'Pending Apps', value: 6, color: 'warning' },
        ]" />

        <h2 class="section-heading">StatsRow — 3 cols</h2>
        <StatsRow :cols="3" :stats="[
            { label: 'Total Debit', value: '₹12,45,300', color: 'accent', sub: '24 accounts' },
            { label: 'Total Credit', value: '₹8,90,150', color: 'success', sub: '18 accounts' },
            { label: 'Net Position', value: '₹3,55,150', color: 'info', sub: 'Surplus' },
        ]" />

        <h2 class="section-heading">StatsRow — 2 cols</h2>
        <StatsRow :cols="2" :stats="[
            { label: 'Today\'s Attendance', value: '94.2%', color: 'success', trend: 1 },
            { label: 'Late Arrivals', value: 17, color: 'warning' },
        ]" />

        <!-- ─── Tabs variants ───────────────────────────────────────────── -->
        <h2 class="section-heading">Tabs — with counts</h2>
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

        <h2 class="section-heading">Tabs — fluid (full-width)</h2>
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

        <!-- ─── EmptyState variants ─────────────────────────────────────── -->
        <h2 class="section-heading">EmptyState — default with action</h2>
        <div class="card" style="margin-bottom:20px;">
            <EmptyState
                title="No students found"
                description="Try adjusting your search filters, or admit your first student to get started."
                action-label="+ New Admission"
                action-href="/school/students/create"
            />
        </div>

        <h2 class="section-heading">EmptyState — compact (inline within tables)</h2>
        <div class="card" style="margin-bottom:20px;">
            <EmptyState
                variant="compact"
                title="No records"
                description="Nothing matches your current filter."
            />
        </div>

        <h2 class="section-heading">EmptyState — muted tone, no action</h2>
        <div class="card" style="margin-bottom:20px;">
            <EmptyState
                tone="muted"
                title="Quiet here"
                description="No activity yet today."
            />
        </div>

        <!-- ─── Table reference (for context) ───────────────────────────── -->
        <h2 class="section-heading">Existing Table component (for reference)</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table>
                <thead>
                    <tr><th>Name</th><th>Class</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <tr><td>Aanya Sharma</td><td>Grade 7-A</td><td><span class="badge badge-green">Active</span></td></tr>
                    <tr><td>Rohan Mehta</td><td>Grade 9-B</td><td><span class="badge badge-amber">Pending</span></td></tr>
                </tbody>
            </Table>
        </div>

        <!-- ─── Modal variants ──────────────────────────────────────────── -->
        <h2 class="section-heading">Modal sizes &amp; variants</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;">
            <Button @click="showSm = true" size="sm">Open sm (400px)</Button>
            <Button @click="showMd = true" size="sm">Open md (520px)</Button>
            <Button @click="showLg = true" size="sm">Open lg (720px)</Button>
            <Button @click="showXl = true" size="sm">Open xl (960px)</Button>
            <Button @click="showPersistent = true" size="sm" variant="secondary">Open persistent (no ESC/backdrop)</Button>
            <Button @click="showNoFooter = true" size="sm" variant="secondary">Open no-footer</Button>
            <Button @click="showNoHeader = true" size="sm" variant="secondary">Open no-header</Button>
        </div>

        <!-- ─── ConfirmDialog variants ──────────────────────────────────── -->
        <h2 class="section-heading">ConfirmDialog (replaces native confirm())</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;">
            <Button @click="tryConfirmSimple" size="sm">Simple (string arg)</Button>
            <Button @click="tryConfirmDanger" size="sm" variant="danger">Danger (delete style)</Button>
            <Button @click="tryConfirmCustom" size="sm" variant="secondary">Custom labels</Button>
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
</style>
