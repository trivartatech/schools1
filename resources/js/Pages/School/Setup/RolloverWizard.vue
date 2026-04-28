<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import SchoolLayout from '@/Layouts/SchoolLayout.vue'
import axios from 'axios'
import { useFormat } from '@/Composables/useFormat';
import { useConfirm } from '@/Composables/useConfirm';

const { formatDateTime } = useFormat();
const confirm = useConfirm();

const props = defineProps({
    years:      { type: Array,  default: () => [] },
    runs:       { type: Array,  default: () => [] },
    allModules: { type: Array,  default: () => [] },
})

const MODULE_META = {
    fee_structures:  { name: 'Fee Structures', desc: 'Per-class fee amounts, terms and due dates.', icon: 'F', tone: 'module-icon--class' },
    exam_terms:      { name: 'Exam Terms & Types', desc: 'Terms and their weightage config.', icon: 'E', tone: 'module-icon--subject' },
    grading_systems: { name: 'Grading Systems', desc: 'Scholastic/co-scholastic scales and grades.', icon: 'G', tone: 'module-icon--dept' },
    fee_concessions: { name: 'Fee Concessions', desc: 'Student-level discounts (only if student rolls forward).', icon: 'C', tone: 'module-icon--class' },
}

const activeStep = ref(1)

const form = useForm({
    source_year_id: '',
    target_year_id: '',
    modules: [...props.allModules],
})

if (props.years && props.years.length >= 2) {
    form.source_year_id = props.years[1].id
    form.target_year_id = props.years[0].id
}

const sourceYearName = computed(() => props.years.find(y => y.id === form.source_year_id)?.name || '')
const targetYearName = computed(() => props.years.find(y => y.id === form.target_year_id)?.name || '')

const inProgressRun = computed(() =>
    props.runs.find(r => !['finalized', 'failed', 'cancelled'].includes(r.state))
)

const nextStep = () => { if (activeStep.value < 3) activeStep.value++ }
const prevStep = () => { if (activeStep.value > 1) activeStep.value-- }

const toggleModule = (mod) => {
    const idx = form.modules.indexOf(mod)
    if (idx > -1) form.modules.splice(idx, 1)
    else form.modules.push(mod)
}

const executeRollover = async () => {
    const ok = await confirm({
        title: 'Start rollover?',
        message: `Clone ${form.modules.length} modules from ${sourceYearName.value} → ${targetYearName.value}?`,
        confirmLabel: 'Start Rollover',
    });
    if (!ok) return;
    form.post('/school/settings/rollover', {
        onSuccess: () => {
            activeStep.value = 1
            form.reset()
        }
    })
}

// ─────────────── Phase 2+: operations on an existing run ───────────────
const runBusy = ref(false)
const dryRunResult = ref(null)

const goPromoteManual = (run) => {
    router.visit(`/school/settings/rollover/runs/${run.id}/promote-manual`)
}

const runCarryDryRun = async (run) => {
    runBusy.value = true
    dryRunResult.value = null
    try {
        const { data } = await axios.post(
            `/school/settings/rollover/runs/${run.id}/carry-forward`,
            { dry_run: true }
        )
        dryRunResult.value = { phase: 'fees', ...data.summary }
    } catch (e) {
        alert('Dry-run failed: ' + (e?.response?.data?.message || e.message))
    } finally {
        runBusy.value = false
    }
}

const runCarryExecute = async (run) => {
    const ok = await confirm({
        title: 'Carry forward unpaid balances?',
        message: 'Carry unpaid balances from source year into target year? This creates new fee_payment rows.',
        confirmLabel: 'Carry Forward',
    });
    if (!ok) return;
    router.post(`/school/settings/rollover/runs/${run.id}/carry-forward`, {}, {
        onSuccess: () => { dryRunResult.value = null }
    })
}

const runFinalize = async (run) => {
    const ok = await confirm({
        title: 'Finalize rollover?',
        message: 'Finalize this rollover run? The source year will be frozen (read-only).',
        confirmLabel: 'Finalize',
        danger: true,
    });
    if (!ok) return;
    router.post(`/school/settings/rollover/runs/${run.id}/finalize`, { freeze_source: true })
}

const phaseLabel = (state) => ({
    draft:              'Ready to start',
    structure_running:  'Cloning structure…',
    structure_done:     'Structure cloned',
    students_running:   'Promoting students…',
    students_done:      'Students promoted',
    fees_running:       'Carrying forward fees…',
    fees_done:          'Fees carried',
    finalized:          'Finalized',
    failed:             'Failed',
    cancelled:          'Cancelled',
}[state] || state)

const nextPhaseActionFor = (state) => {
    if (state === 'structure_done' || state === 'students_running') return 'promote'
    if (state === 'students_done')   return 'carry'
    if (state === 'fees_done')       return 'finalize'
    return null
}
</script>

<template>
    <SchoolLayout title="Academic Year Rollover">
        <PageHeader title="Academic Year Rollover" subtitle="Clone per-year configuration, promote students, and carry forward unpaid balances." />

        <!-- ─────────────── IN-PROGRESS RUN PANEL ─────────────── -->
        <div v-if="inProgressRun" class="run-panel">
            <div class="run-panel-header">
                <div>
                    <span class="run-chip">Run #{{ inProgressRun.id }}</span>
                    <span class="run-flow">
                        {{ inProgressRun.source_year?.name }}
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        {{ inProgressRun.target_year?.name }}
                    </span>
                </div>
                <span class="run-state" :data-state="inProgressRun.state">{{ phaseLabel(inProgressRun.state) }}</span>
            </div>

            <div v-if="inProgressRun.error" class="run-error">{{ inProgressRun.error }}</div>

            <div class="run-phases">
                <div class="run-phase" :class="{ 'run-phase--done': ['structure_done','students_done','fees_done','finalized'].includes(inProgressRun.state), 'run-phase--active': inProgressRun.state === 'structure_running' }">
                    <span class="run-phase-label">1. Structure</span>
                </div>
                <div class="run-phase" :class="{ 'run-phase--done': ['students_done','fees_done','finalized'].includes(inProgressRun.state), 'run-phase--active': ['structure_done','students_running'].includes(inProgressRun.state) }">
                    <span class="run-phase-label">2. Students</span>
                </div>
                <div class="run-phase" :class="{ 'run-phase--done': ['fees_done','finalized'].includes(inProgressRun.state), 'run-phase--active': ['students_done','fees_running'].includes(inProgressRun.state) }">
                    <span class="run-phase-label">3. Carry-Forward Fees</span>
                </div>
                <div class="run-phase" :class="{ 'run-phase--done': inProgressRun.state === 'finalized', 'run-phase--active': inProgressRun.state === 'fees_done' }">
                    <span class="run-phase-label">4. Finalize</span>
                </div>
            </div>

            <div class="run-actions">
                <template v-if="nextPhaseActionFor(inProgressRun.state) === 'promote'">
                    <Button @click="goPromoteManual(inProgressRun)">Open Promotion Wizard →</Button>
                    <span class="run-action-note run-action-note--inline">Pick students class-by-class, section-by-section. Fees carry automatically.</span>
                </template>
                <template v-else-if="nextPhaseActionFor(inProgressRun.state) === 'carry'">
                    <Button variant="secondary" :loading="runBusy" @click="runCarryDryRun(inProgressRun)">Preview (dry-run)</Button>
                    <Button @click="runCarryExecute(inProgressRun)">Carry Forward Dues</Button>
                </template>
                <template v-else-if="nextPhaseActionFor(inProgressRun.state) === 'finalize'">
                    <Button @click="runFinalize(inProgressRun)">Finalize (freeze source year)</Button>
                </template>
                <template v-else>
                    <span class="run-action-note">Waiting for current phase to complete…</span>
                </template>

                <a :href="`/school/settings/rollover/runs/${inProgressRun.id}`" class="run-link">View run log →</a>
            </div>

            <div v-if="dryRunResult" class="run-preview">
                <h4>Dry-run preview</h4>
                <pre>{{ JSON.stringify(dryRunResult, null, 2) }}</pre>
            </div>
        </div>

        <!-- ─────────────── NEW ROLLOVER WIZARD ─────────────── -->
        <div v-if="!inProgressRun" class="wizard-wrap">
            <div class="wizard-progress">
                <template v-for="(step, idx) in [
                    { n: 1, label: 'Select Years', desc: 'Source & target' },
                    { n: 2, label: 'Choose Modules', desc: 'What to clone' },
                    { n: 3, label: 'Review & Start', desc: 'Confirm & run' },
                ]" :key="step.n">
                    <div v-if="idx > 0" :class="['wiz-connector', activeStep >= step.n ? 'wiz-connector--done' : '']"></div>
                    <div :class="['wiz-step', activeStep === step.n ? 'wiz-step--active' : activeStep > step.n ? 'wiz-step--done' : 'wiz-step--pending']">
                        <div class="wiz-step-circle">
                            <svg v-if="activeStep > step.n" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span v-else>{{ step.n }}</span>
                        </div>
                        <div class="wiz-step-text">
                            <span class="wiz-step-label">{{ step.label }}</span>
                            <span class="wiz-step-desc">{{ step.desc }}</span>
                        </div>
                    </div>
                </template>
            </div>

            <div class="wizard-body">
                <form @submit.prevent>
                    <!-- STEP 1 -->
                    <div v-show="activeStep === 1" class="wiz-pane">
                        <div class="wiz-pane-header">
                            <div class="wiz-pane-icon wiz-pane-icon--blue">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <h3 class="wiz-pane-title">Choose Academic Years</h3>
                                <p class="wiz-pane-sub">Clone FROM the source year INTO the target year.</p>
                            </div>
                        </div>

                        <div class="year-selector">
                            <div class="year-card year-card--source">
                                <div class="year-card-header"><span>Copy From (Source)</span></div>
                                <select v-model="form.source_year_id">
                                    <option value="" disabled>Select Source Year</option>
                                    <option v-for="y in years" :key="y.id" :value="y.id">{{ y.name }}</option>
                                </select>
                            </div>
                            <div class="year-arrow-col">
                                <div class="year-arrow">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </div>
                            </div>
                            <div class="year-card year-card--target">
                                <div class="year-card-header year-card-header--target"><span>Paste To (Target)</span></div>
                                <select v-model="form.target_year_id">
                                    <option value="" disabled>Select Target Year</option>
                                    <option v-for="y in years" :key="y.id" :value="y.id" :disabled="y.id === form.source_year_id">{{ y.name }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2 -->
                    <div v-show="activeStep === 2" class="wiz-pane">
                        <div class="wiz-pane-header">
                            <div class="wiz-pane-icon wiz-pane-icon--violet">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6z"/></svg>
                            </div>
                            <div>
                                <h3 class="wiz-pane-title">Per-Year Config to Clone</h3>
                                <p class="wiz-pane-sub">School-wide tables (classes, sections, subjects) stay the same — only per-year config is cloned here.</p>
                            </div>
                        </div>

                        <div class="modules-grid">
                            <label v-for="mod in allModules" :key="mod"
                                class="module-card"
                                :class="form.modules.includes(mod) ? 'module-card--active' : ''">
                                <div class="module-check-wrap">
                                    <input type="checkbox" :checked="form.modules.includes(mod)" @change="toggleModule(mod)" class="module-check"/>
                                </div>
                                <div class="module-icon" :class="MODULE_META[mod]?.tone">{{ MODULE_META[mod]?.icon }}</div>
                                <div class="module-content">
                                    <p class="module-name">{{ MODULE_META[mod]?.name || mod }}</p>
                                    <p class="module-desc">{{ MODULE_META[mod]?.desc }}</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- STEP 3 -->
                    <div v-show="activeStep === 3" class="wiz-pane">
                        <div class="wiz-pane-header">
                            <div class="wiz-pane-icon wiz-pane-icon--amber">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <h3 class="wiz-pane-title">Review &amp; Start Rollover</h3>
                                <p class="wiz-pane-sub">This creates a tracked run. Students &amp; carry-forward happen in later steps.</p>
                            </div>
                        </div>

                        <div class="review-card">
                            <div class="review-year-row">
                                <div class="review-year">
                                    <span class="review-year-label">Source</span>
                                    <span class="review-year-name">{{ sourceYearName }}</span>
                                </div>
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                <div class="review-year review-year--target">
                                    <span class="review-year-label review-year-label--target">Target</span>
                                    <span class="review-year-name review-year-name--target">{{ targetYearName }}</span>
                                </div>
                            </div>
                            <div class="review-modules">
                                <p class="review-modules-title">Modules <span class="review-count">{{ form.modules.length }}</span></p>
                                <div class="review-chips">
                                    <span v-for="m in form.modules" :key="m" class="review-chip">{{ MODULE_META[m]?.name || m }}</span>
                                </div>
                            </div>
                            <div class="review-warning">
                                <span>Structure cloning is idempotent — rows that already exist in the target year are skipped.</span>
                            </div>
                        </div>
                    </div>

                    <div class="wiz-nav">
                        <Button variant="secondary" type="button" @click="prevStep" :disabled="activeStep === 1" :style="activeStep === 1 ? 'visibility:hidden' : ''">Back</Button>
                        <div class="wiz-dots">
                            <span v-for="n in 3" :key="n" :class="['wiz-dot', activeStep === n ? 'wiz-dot--active' : activeStep > n ? 'wiz-dot--done' : '']"></span>
                        </div>
                        <Button v-if="activeStep < 3" type="button" @click="nextStep" :disabled="!form.source_year_id || !form.target_year_id || (activeStep === 2 && form.modules.length === 0)">Continue</Button>
                        <button v-else type="button" @click="executeRollover" :disabled="form.processing || form.modules.length === 0" class="btn-execute">
                            {{ form.processing ? 'Starting…' : 'Start Rollover' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ─────────────── RECENT RUNS ─────────────── -->
        <div v-if="runs && runs.length" class="runs-list">
            <h3 class="runs-list-title">Recent Runs</h3>
            <table class="runs-table">
                <thead>
                    <tr><th>#</th><th>Source → Target</th><th>State</th><th>Started</th><th>By</th><th></th></tr>
                </thead>
                <tbody>
                    <tr v-for="r in runs" :key="r.id">
                        <td>{{ r.id }}</td>
                        <td>{{ r.source_year?.name }} → {{ r.target_year?.name }}</td>
                        <td><span class="run-state" :data-state="r.state">{{ phaseLabel(r.state) }}</span></td>
                        <td>{{ formatDateTime(r.started_at) }}</td>
                        <td>{{ r.started_by?.name || '—' }}</td>
                        <td><a :href="`/school/settings/rollover/runs/${r.id}`" class="run-link">View</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.page-header { margin-bottom: 1.5rem; }

.run-panel {
    max-width: 820px; margin: 0 auto 1.5rem;
    background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius-lg);
    box-shadow: 0 4px 20px rgba(15,23,42,0.07); padding: 1.25rem 1.5rem;
}
.run-panel-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 0.85rem; }
.run-chip { background: var(--accent); color: #fff; padding: 0.25rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 700; margin-right: 0.75rem; }
.run-flow { font-size: 0.95rem; font-weight: 700; color: #1e293b; display: inline-flex; align-items: center; gap: 0.5rem; }
.run-state {
    font-size: 0.72rem; font-weight: 700; padding: 0.25rem 0.7rem; border-radius: 12px;
    background: #e2e8f0; color: #334155;
}
.run-state[data-state="failed"]     { background: #fef2f2; color: #b91c1c; }
.run-state[data-state="finalized"]  { background: #dcfce7; color: #166534; }
.run-state[data-state="structure_running"],
.run-state[data-state="students_running"],
.run-state[data-state="fees_running"] { background: #fef3c7; color: #92400e; }
.run-error { background: #fef2f2; color: #b91c1c; padding: 0.6rem 0.85rem; border-radius: var(--radius); font-size: 0.85rem; margin-bottom: 0.85rem; }

.run-phases { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem; margin-bottom: 1rem; }
.run-phase {
    padding: 0.5rem 0.65rem; border: 1.5px solid var(--border); border-radius: var(--radius);
    background: #f8fafc; font-size: 0.78rem; font-weight: 600; color: #64748b; text-align: center;
}
.run-phase--active { border-color: var(--accent); background: #eef2ff; color: var(--accent); }
.run-phase--done   { border-color: #86efac; background: #dcfce7; color: #166534; }

.run-actions { display: flex; align-items: center; gap: 0.75rem; }
.run-action-note { font-size: 0.85rem; color: #64748b; flex: 1; }
.run-link { margin-left: auto; font-size: 0.82rem; font-weight: 700; color: var(--accent); text-decoration: none; }
.run-link:hover { text-decoration: underline; }

.run-preview { margin-top: 1rem; padding: 0.85rem 1rem; background: #f1f5f9; border-radius: var(--radius); font-size: 0.8rem; }
.run-preview h4 { margin: 0 0 0.5rem 0; font-size: 0.82rem; font-weight: 700; color: #1e293b; }
.run-preview pre { margin: 0; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.75rem; white-space: pre-wrap; }

.wizard-wrap {
    max-width: 820px; margin: 0 auto;
    background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius-lg);
    overflow: hidden; box-shadow: 0 4px 20px rgba(15,23,42,0.07);
}
.wizard-progress {
    display: flex; align-items: center; padding: 1.25rem 2rem;
    background: #f8fafc; border-bottom: 1.5px solid var(--border);
}
.wiz-connector { flex: 1; height: 2px; background: var(--border); margin: 0 0.75rem; border-radius: 1px; transition: background 0.35s; }
.wiz-connector--done { background: var(--accent); }
.wiz-step { display: flex; align-items: center; gap: 0.55rem; flex-shrink: 0; }
.wiz-step-circle { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; flex-shrink: 0; transition: all 0.25s; }
.wiz-step--pending .wiz-step-circle { background: #e2e8f0; color: #94a3b8; }
.wiz-step--active  .wiz-step-circle { background: var(--accent); color: #fff; box-shadow: 0 0 0 4px rgba(99,102,241,0.18); }
.wiz-step--done    .wiz-step-circle { background: var(--success); color: #fff; }
.wiz-step-text { display: flex; flex-direction: column; gap: 1px; }
.wiz-step-label { font-size: 0.82rem; font-weight: 700; white-space: nowrap; }
.wiz-step-desc  { font-size: 0.7rem; white-space: nowrap; }
.wiz-step--pending .wiz-step-label { color: #94a3b8; }
.wiz-step--active  .wiz-step-label { color: var(--accent); }
.wiz-step--done    .wiz-step-label { color: var(--success); }

.wizard-body { padding: 1.75rem 2rem; }
.wiz-pane { animation: fadeUp 0.22s ease-out; }

.wiz-pane-header { display: flex; align-items: flex-start; gap: 0.85rem; margin-bottom: 1.4rem; }
.wiz-pane-icon { display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: var(--radius); flex-shrink: 0; margin-top: 2px; }
.wiz-pane-icon--blue   { background: #eff6ff; color: #2563eb; }
.wiz-pane-icon--violet { background: #f5f3ff; color: #7c3aed; }
.wiz-pane-icon--amber  { background: #fffbeb; color: #d97706; }
.wiz-pane-title { font-size: 1rem; font-weight: 700; color: #1e293b; margin: 0 0 0.25rem 0; }
.wiz-pane-sub { font-size: 0.875rem; color: #64748b; margin: 0; line-height: 1.5; }

.year-selector { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 0.75rem; }
.year-card { border: 1.5px solid var(--border); border-radius: var(--radius); padding: 1rem; background: #f8fafc; display: flex; flex-direction: column; gap: 0.5rem; }
.year-card--target { background: #eff6ff; border-color: #bfdbfe; }
.year-card-header { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #94a3b8; }
.year-card-header--target { color: #3b82f6; }
.year-card select { border: 1.5px solid var(--border); border-radius: calc(var(--radius) - 2px); padding: 0.45rem 0.65rem; font-size: 0.875rem; background: var(--surface); outline: none; width: 100%; }
.year-card select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
.year-arrow-col { display: flex; align-items: center; justify-content: center; }
.year-arrow { width: 38px; height: 38px; border-radius: 50%; background: var(--surface); border: 1.5px solid var(--border); display: flex; align-items: center; justify-content: center; color: #94a3b8; }

.modules-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; }
.module-card { display: flex; align-items: flex-start; gap: 0.75rem; padding: 1rem; border: 1.5px solid var(--border); border-radius: var(--radius); cursor: pointer; transition: all 0.15s; background: var(--surface); position: relative; }
.module-card:hover { background: #fafbfe; border-color: #c7d2fe; }
.module-card--active { border-color: var(--accent); background: #eef2ff; }
.module-check-wrap { position: absolute; top: 0.8rem; right: 0.8rem; }
.module-check { width: 16px; height: 16px; accent-color: var(--accent); cursor: pointer; }
.module-icon { width: 40px; height: 40px; border-radius: var(--radius); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 800; font-size: 1rem; }
.module-icon--dept    { background: #fff7ed; color: #ea580c; }
.module-icon--class   { background: #f0fdf4; color: #16a34a; }
.module-icon--subject { background: #fdf4ff; color: #9333ea; }
.module-content { padding-right: 1.5rem; }
.module-name { font-size: 0.9rem; font-weight: 700; color: #1e293b; margin: 0 0 0.2rem 0; }
.module-desc { font-size: 0.78rem; color: #64748b; margin: 0; line-height: 1.4; }

.review-card { border: 1.5px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.review-year-row { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.2rem; background: #f8fafc; border-bottom: 1px solid var(--border); gap: 1rem; }
.review-year { display: flex; flex-direction: column; gap: 0.2rem; }
.review-year--target { align-items: flex-end; text-align: right; }
.review-year-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #94a3b8; }
.review-year-label--target { color: #3b82f6; }
.review-year-name { font-size: 1rem; font-weight: 700; color: #1e293b; }
.review-year-name--target { color: #1d4ed8; }
.review-modules { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
.review-modules-title { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; margin: 0 0 0.6rem 0; display: flex; align-items: center; gap: 0.5rem; }
.review-count { background: var(--accent); color: #fff; font-size: 0.69rem; width: 18px; height: 18px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; }
.review-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.review-chip { display: flex; align-items: center; gap: 0.3rem; padding: 0.28rem 0.8rem; background: var(--surface); border: 1.5px solid #c7d2fe; border-radius: 20px; font-size: 0.8rem; font-weight: 600; color: #4338ca; }
.review-warning { padding: 0.85rem 1.2rem; background: #fff7ed; color: #92400e; font-size: 0.82rem; line-height: 1.5; }

.wiz-nav { display: flex; align-items: center; justify-content: space-between; margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1.5px solid var(--border); }
.wiz-dots { display: flex; gap: 0.4rem; align-items: center; }
.wiz-dot { width: 8px; height: 8px; border-radius: 50%; background: #e2e8f0; transition: all 0.2s; }
.wiz-dot--active { background: var(--accent); width: 24px; border-radius: 4px; }
.wiz-dot--done   { background: var(--success); }
.btn-execute { display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.55rem 1.4rem; background: var(--accent); color: #fff; font-weight: 700; font-size: 0.875rem; border: none; border-radius: var(--radius); cursor: pointer; }
.btn-execute:disabled { opacity: 0.55; cursor: not-allowed; }

.runs-list { max-width: 820px; margin: 2rem auto 0; }
.runs-list-title { font-size: 1rem; font-weight: 700; color: #1e293b; margin: 0 0 0.85rem 0; }
.runs-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.runs-table th, .runs-table td { padding: 0.65rem 0.9rem; text-align: left; font-size: 0.82rem; border-bottom: 1px solid var(--border); }
.runs-table th { background: #f8fafc; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.7rem; }
.runs-table tbody tr:last-child td { border-bottom: none; }

@keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

@media (max-width: 600px) {
    .year-selector { grid-template-columns: 1fr; }
    .year-arrow-col { display: none; }
    .modules-grid { grid-template-columns: 1fr; }
    .wizard-body { padding: 1.25rem; }
    .run-phases { grid-template-columns: 1fr 1fr; }
}
</style>
