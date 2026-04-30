<script setup>
/**
 * UI Sandbox · Modals & Toasts
 * <Modal> sizes/slots/behaviors + useConfirm + useToast.
 * URL: /school/_ui-sandbox/modals
 */
import { ref } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import Table from '@/Components/ui/Table.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';

const confirm = useConfirm();
const toast = useToast();

// Modal state
const showSm = ref(false);
const showMd = ref(false);
const showLg = ref(false);
const showXl = ref(false);
const showPersistent = ref(false);
const showNoFooter = ref(false);
const showNoHeader = ref(false);
const showHeaderActions = ref(false);
const showBackdropOnly = ref(false);
const showEscOnly = ref(false);

// Confirm helpers
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

// Toast helpers
let lastToastId = null;
function toastFast() { lastToastId = toast.success('Disappears in 1.2s', 1200); }
function toastDismissLast() { if (lastToastId !== null) toast.dismiss(lastToastId); }
function toastClearAll() { toast.clear(); }
</script>

<template>
    <SchoolLayout title="UI Sandbox · Modals & Toasts">

        <PageHeader
            title="Modals &amp; Toasts"
            subtitle="Modal sizes/slots/behaviors + useConfirm + useToast"
            back-href="/school/_ui-sandbox"
            back-label="← Back to sandbox"
        />

        <!-- ── Modal size triggers ─────────────────────────────────── -->
        <h2 class="section-heading">Modal sizes &amp; behaviors</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;">
            <Button @click="showSm = true" size="sm">Open sm (400px)</Button>
            <Button @click="showMd = true" size="sm">Open md (520px)</Button>
            <Button @click="showLg = true" size="sm">Open lg (720px)</Button>
            <Button @click="showXl = true" size="sm" variant="secondary">Open xl (960px)</Button>
            <Button @click="showPersistent = true" size="sm" variant="secondary">Persistent (no ESC/backdrop)</Button>
            <Button @click="showNoFooter = true" size="sm" variant="secondary">No footer</Button>
            <Button @click="showNoHeader = true" size="sm" variant="secondary">No header</Button>
            <Button @click="showHeaderActions = true" size="sm" variant="secondary">#header-actions slot</Button>
            <Button @click="showBackdropOnly = true" size="sm" variant="secondary">close-on-backdrop=false only</Button>
            <Button @click="showEscOnly = true" size="sm" variant="secondary">close-on-esc=false only</Button>
        </div>

        <!-- ── Toast ──────────────────────────────────────────────── -->
        <h2 class="section-heading">Toast notifications — useToast() composable</h2>
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

        <!-- ── Confirm ────────────────────────────────────────────── -->
        <h2 class="section-heading">ConfirmDialog (replaces native confirm())</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;">
            <Button @click="tryConfirmSimple" size="sm">Simple (string arg)</Button>
            <Button @click="tryConfirmDanger" size="sm" variant="danger">Danger (delete style)</Button>
            <Button @click="tryConfirmCustom" size="sm" variant="secondary">Custom labels</Button>
            <Button @click="tryConfirmDefaults" size="sm" variant="secondary">Defaults (no args)</Button>
        </div>

        <!-- ── Modal definitions (teleport to body) ──────────────── -->
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
