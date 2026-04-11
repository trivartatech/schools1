<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import SchoolLayout from '@/Layouts/SchoolLayout.vue'

const props = defineProps({
    years: Array
})

const activeStep = ref(1)

const form = useForm({
    source_year_id: '',
    target_year_id: '',
    modules: ['departments', 'classes', 'subjects'] // Default all
})

// Auto-select years based on chronological order if possible
if (props.years && props.years.length >= 2) {
    // Usually, you want to clone FROM the second newest TO the newest
    form.source_year_id = props.years[1].id
    form.target_year_id = props.years[0].id
}

const sourceYearName = computed(() => props.years.find(y => y.id === form.source_year_id)?.name || '')
const targetYearName = computed(() => props.years.find(y => y.id === form.target_year_id)?.name || '')

const nextStep = () => {
    if (activeStep.value < 3) activeStep.value++
}
const prevStep = () => {
    if (activeStep.value > 1) activeStep.value--
}

const toggleModule = (mod) => {
    const idx = form.modules.indexOf(mod)
    if (idx > -1) form.modules.splice(idx, 1)
    else form.modules.push(mod)
}

const executeRollover = () => {
    if (confirm(`Are you sure you want to clone ${form.modules.length} modules from ${sourceYearName.value} ➔ ${targetYearName.value}?`)) {
        form.post('/school/settings/rollover', {
            onSuccess: () => {
                // If successful, reset to step 1
                if (!form.hasErrors) {
                    activeStep.value = 1
                    form.reset()
                }
            }
        })
    }
}
</script>

<template>
    <SchoolLayout title="Academic Year Rollover">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Academic Year Rollover</h1>
                <p class="page-header-sub">Copy classes, subjects, and configurations from one academic year to another.</p>
            </div>
        </div>

        <div class="wizard-wrap">

            <!-- Step Progress Header -->
            <div class="wizard-progress">
                <template v-for="(step, idx) in [
                    { n: 1, label: 'Select Years', desc: 'Source & target' },
                    { n: 2, label: 'Choose Modules', desc: 'What to clone' },
                    { n: 3, label: 'Review & Execute', desc: 'Confirm & run' },
                ]" :key="step.n">
                    <!-- Connector before step (not first) -->
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

            <!-- Step Content -->
            <div class="wizard-body">
                <form @submit.prevent>

                    <!-- STEP 1: Select Years -->
                    <div v-show="activeStep === 1" class="wiz-pane">
                        <div class="wiz-pane-header">
                            <div class="wiz-pane-icon wiz-pane-icon--blue">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <h3 class="wiz-pane-title">Choose Academic Years</h3>
                                <p class="wiz-pane-sub">Select the source year to copy from, and the target year to populate.</p>
                            </div>
                        </div>

                        <div class="year-selector">
                            <div class="year-card year-card--source">
                                <div class="year-card-header">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    <span>Copy From (Source)</span>
                                </div>
                                <select v-model="form.source_year_id">
                                    <option value="" disabled>Select Source Year</option>
                                    <option v-for="y in years" :key="y.id" :value="y.id">
                                        {{ y.name }} {{ y.is_active ? '(Active)' : '' }}
                                    </option>
                                </select>
                                <transition name="slide">
                                    <span v-if="form.source_year_id" class="year-selected-name">{{ sourceYearName }}</span>
                                </transition>
                            </div>

                            <div class="year-arrow-col">
                                <div class="year-arrow">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </div>
                            </div>

                            <div class="year-card year-card--target">
                                <div class="year-card-header year-card-header--target">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    <span>Paste To (Target)</span>
                                </div>
                                <select v-model="form.target_year_id">
                                    <option value="" disabled>Select Target Year</option>
                                    <option v-for="y in years" :key="y.id" :value="y.id" :disabled="y.id === form.source_year_id">
                                        {{ y.name }} {{ y.is_active ? '(Active)' : '' }}
                                    </option>
                                </select>
                                <transition name="slide">
                                    <span v-if="form.target_year_id" class="year-selected-name year-selected-name--target">{{ targetYearName }}</span>
                                </transition>
                                <p class="year-card-note">Data will be written into this year.</p>
                            </div>
                        </div>

                        <p v-if="form.errors.source_year_id || form.errors.target_year_id" class="wiz-error">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            Please select valid and distinct academic years.
                        </p>
                    </div>

                    <!-- STEP 2: Select Modules -->
                    <div v-show="activeStep === 2" class="wiz-pane">
                        <div class="wiz-pane-header">
                            <div class="wiz-pane-icon wiz-pane-icon--violet">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                            </div>
                            <div>
                                <h3 class="wiz-pane-title">Select Modules to Clone</h3>
                                <p class="wiz-pane-sub">
                                    Choose which configurations to copy from
                                    <strong>{{ sourceYearName }}</strong> into <strong>{{ targetYearName }}</strong>.
                                </p>
                            </div>
                        </div>

                        <div class="modules-grid">
                            <label
                                class="module-card"
                                :class="form.modules.includes('departments') ? 'module-card--active' : ''"
                            >
                                <div class="module-check-wrap">
                                    <input
                                        type="checkbox"
                                        :checked="form.modules.includes('departments')"
                                        @change="toggleModule('departments')"
                                        class="module-check"
                                    />
                                </div>
                                <div class="module-icon module-icon--dept">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <div class="module-content">
                                    <p class="module-name">Departments</p>
                                    <p class="module-desc">Primary/Secondary wings, Humanities, Science, etc.</p>
                                </div>
                            </label>

                            <label
                                class="module-card"
                                :class="form.modules.includes('classes') ? 'module-card--active' : ''"
                            >
                                <div class="module-check-wrap">
                                    <input
                                        type="checkbox"
                                        :checked="form.modules.includes('classes')"
                                        @change="toggleModule('classes')"
                                        class="module-check"
                                    />
                                </div>
                                <div class="module-icon module-icon--class">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                </div>
                                <div class="module-content">
                                    <p class="module-name">Classes &amp; Sections</p>
                                    <p class="module-desc">Class 1 to 12 along with their sections (A, B, C).</p>
                                </div>
                            </label>

                            <label
                                class="module-card"
                                :class="form.modules.includes('subjects') ? 'module-card--active' : ''"
                            >
                                <div class="module-check-wrap">
                                    <input
                                        type="checkbox"
                                        :checked="form.modules.includes('subjects')"
                                        @change="toggleModule('subjects')"
                                        class="module-check"
                                    />
                                </div>
                                <div class="module-icon module-icon--subject">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                </div>
                                <div class="module-content">
                                    <p class="module-name">Subjects &amp; Assignments</p>
                                    <p class="module-desc">Core subjects and their mapping to classes.</p>
                                </div>
                            </label>
                        </div>

                        <p v-if="form.modules.length === 0" class="wiz-error">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            Please select at least one module to continue.
                        </p>
                    </div>

                    <!-- STEP 3: Review & Execute -->
                    <div v-show="activeStep === 3" class="wiz-pane">
                        <div class="wiz-pane-header">
                            <div class="wiz-pane-icon wiz-pane-icon--amber">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <h3 class="wiz-pane-title">Review &amp; Execute</h3>
                                <p class="wiz-pane-sub">Confirm the rollover configuration before executing. This action cannot be undone.</p>
                            </div>
                        </div>

                        <div class="review-card">
                            <!-- Year flow -->
                            <div class="review-year-row">
                                <div class="review-year">
                                    <span class="review-year-label">Source Year</span>
                                    <span class="review-year-name">{{ sourceYearName }}</span>
                                </div>
                                <div class="review-year-arrow">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </div>
                                <div class="review-year review-year--target">
                                    <span class="review-year-label review-year-label--target">Target Year</span>
                                    <span class="review-year-name review-year-name--target">{{ targetYearName }}</span>
                                </div>
                            </div>

                            <!-- Modules -->
                            <div class="review-modules">
                                <p class="review-modules-title">
                                    Modules to Clone
                                    <span class="review-count">{{ form.modules.length }}</span>
                                </p>
                                <div v-if="form.modules.length === 0" class="review-no-modules">No modules selected.</div>
                                <div class="review-chips">
                                    <span v-for="m in form.modules" :key="m" class="review-chip">
                                        <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        {{ m.replace('-', ' ') }}
                                    </span>
                                </div>
                            </div>

                            <!-- Warning banner -->
                            <div class="review-warning">
                                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span>Existing data in the target year for selected modules will be overwritten. This action cannot be reversed.</span>
                            </div>
                        </div>

                        <div v-if="form.errors.modules" class="wiz-error wiz-error--block">
                            {{ form.errors.modules }}
                        </div>
                    </div>

                    <!-- Navigation Footer -->
                    <div class="wiz-nav">
                        <Button variant="secondary" type="button" @click="prevStep" :loading="form.processing" :disabled="activeStep === 1" :style="activeStep === 1 ? 'visibility:hidden' : ''">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            Back
                        </Button>

                        <div class="wiz-dots">
                            <span v-for="n in 3" :key="n" :class="['wiz-dot', activeStep === n ? 'wiz-dot--active' : activeStep > n ? 'wiz-dot--done' : '']"></span>
                        </div>

                        <Button v-if="activeStep < 3" type="button" @click="nextStep" :disabled="!form.source_year_id || !form.target_year_id || (activeStep === 2 && form.modules.length === 0)">
                            Continue
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </Button>

                        <button
                            v-if="activeStep === 3"
                            type="button"
                            @click="executeRollover"
                            :disabled="form.processing || form.modules.length === 0"
                            class="btn-execute"
                        >
                            <svg v-if="form.processing" class="spin-icon" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <svg v-else width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            {{ form.processing ? 'Cloning Data…' : 'Execute Rollover' }}
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* ── Page header ── */
.page-header { margin-bottom: 1.75rem; }

/* ── Wizard shell ── */
.wizard-wrap {
    max-width: 820px;
    margin: 0 auto;
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(15,23,42,0.07);
}

/* ── Progress header ── */
.wizard-progress {
    display: flex;
    align-items: center;
    padding: 1.35rem 2rem;
    background: #f8fafc;
    border-bottom: 1.5px solid var(--border);
    gap: 0;
}
.wiz-connector {
    flex: 1;
    height: 2px;
    background: var(--border);
    margin: 0 0.85rem;
    border-radius: 1px;
    transition: background 0.35s;
}
.wiz-connector--done { background: var(--accent); }

.wiz-step {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-shrink: 0;
}
.wiz-step-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    flex-shrink: 0;
    transition: all 0.25s;
}
.wiz-step--pending .wiz-step-circle { background: #e2e8f0; color: #94a3b8; }
.wiz-step--active .wiz-step-circle {
    background: var(--accent);
    color: #fff;
    box-shadow: 0 0 0 4px rgba(99,102,241,0.18);
}
.wiz-step--done .wiz-step-circle { background: var(--success); color: #fff; }

.wiz-step-text { display: flex; flex-direction: column; gap: 1px; }
.wiz-step-label {
    font-size: 0.83rem;
    font-weight: 700;
    white-space: nowrap;
}
.wiz-step-desc {
    font-size: 0.7rem;
    white-space: nowrap;
}
.wiz-step--pending .wiz-step-label { color: #94a3b8; }
.wiz-step--pending .wiz-step-desc { color: #cbd5e1; }
.wiz-step--active .wiz-step-label { color: var(--accent); }
.wiz-step--active .wiz-step-desc { color: #a5b4fc; }
.wiz-step--done .wiz-step-label { color: var(--success); }
.wiz-step--done .wiz-step-desc { color: #6ee7b7; }

/* ── Step body ── */
.wizard-body { padding: 2rem; }

.wiz-pane { animation: fadeUp 0.22s ease-out; }

.wiz-pane-header {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    margin-bottom: 1.6rem;
}
.wiz-pane-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: var(--radius);
    flex-shrink: 0;
    margin-top: 2px;
}
.wiz-pane-icon--blue   { background: #eff6ff; color: #2563eb; }
.wiz-pane-icon--violet { background: #f5f3ff; color: #7c3aed; }
.wiz-pane-icon--amber  { background: #fffbeb; color: #d97706; }

.wiz-pane-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 0.25rem 0;
}
.wiz-pane-sub {
    font-size: 0.875rem;
    color: #64748b;
    margin: 0;
    line-height: 1.5;
}

/* ── Year selector ── */
.year-selector {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 0.75rem;
}
.year-card {
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 1.1rem;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    transition: border-color 0.15s;
}
.year-card--target {
    background: #eff6ff;
    border-color: #bfdbfe;
}
.year-card-header {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #94a3b8;
}
.year-card-header--target { color: #3b82f6; }
.year-card select {
    border: 1.5px solid var(--border);
    border-radius: calc(var(--radius) - 2px);
    padding: 0.48rem 0.7rem;
    font-size: 0.875rem;
    background: var(--surface);
    color: #1e293b;
    outline: none;
    width: 100%;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.year-card select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}
.year-selected-name {
    font-size: 0.875rem;
    font-weight: 700;
    color: #1e293b;
}
.year-selected-name--target { color: #1d4ed8; }
.year-card-note { font-size: 0.71rem; color: #3b82f6; margin: 0; }

.year-arrow-col {
    display: flex;
    align-items: center;
    justify-content: center;
}
.year-arrow {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--surface);
    border: 1.5px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    box-shadow: 0 1px 4px rgba(15,23,42,0.07);
}

/* ── Modules grid ── */
.modules-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.9rem;
}
.module-card {
    display: flex;
    align-items: flex-start;
    gap: 0.8rem;
    padding: 1.05rem 1.1rem;
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    cursor: pointer;
    transition: all 0.15s;
    background: var(--surface);
    position: relative;
}
.module-card:hover { background: #fafbfe; border-color: #c7d2fe; }
.module-card--active { border-color: var(--accent); background: #eef2ff; }

.module-check-wrap {
    position: absolute;
    top: 0.85rem;
    right: 0.85rem;
}
.module-check {
    width: 16px;
    height: 16px;
    accent-color: var(--accent);
    cursor: pointer;
}
.module-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.module-icon--dept    { background: #fff7ed; color: #ea580c; }
.module-icon--class   { background: #f0fdf4; color: #16a34a; }
.module-icon--subject { background: #fdf4ff; color: #9333ea; }

.module-content { padding-right: 1.5rem; }
.module-name {
    font-size: 0.9rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 0.2rem 0;
}
.module-desc {
    font-size: 0.78rem;
    color: #64748b;
    margin: 0;
    line-height: 1.4;
}

/* ── Review card ── */
.review-card {
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
}
.review-year-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.1rem 1.25rem;
    background: #f8fafc;
    border-bottom: 1px solid var(--border);
    gap: 1rem;
}
.review-year { display: flex; flex-direction: column; gap: 0.2rem; }
.review-year--target { align-items: flex-end; text-align: right; }
.review-year-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #94a3b8;
}
.review-year-label--target { color: #3b82f6; }
.review-year-name {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
}
.review-year-name--target { color: #1d4ed8; }
.review-year-arrow { color: #94a3b8; flex-shrink: 0; }

.review-modules {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
}
.review-modules-title {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #94a3b8;
    margin: 0 0 0.7rem 0;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.review-count {
    background: var(--accent);
    color: #fff;
    font-size: 0.69rem;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.review-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.review-chip {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.28rem 0.8rem;
    background: var(--surface);
    border: 1.5px solid #c7d2fe;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #4338ca;
    text-transform: capitalize;
}
.review-chip svg { color: var(--accent); }
.review-no-modules { font-size: 0.85rem; color: var(--danger); }

.review-warning {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.9rem 1.25rem;
    background: #fff7ed;
    color: #92400e;
    font-size: 0.82rem;
    line-height: 1.5;
}
.review-warning svg { flex-shrink: 0; margin-top: 1px; color: #f59e0b; }

/* ── Errors ── */
.wiz-error {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.82rem;
    color: var(--danger);
    margin-top: 0.85rem;
}
.wiz-error svg { flex-shrink: 0; }
.wiz-error--block {
    display: flex;
    padding: 0.65rem 0.9rem;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: var(--radius);
    margin-top: 0.9rem;
}

/* ── Navigation footer ── */
.wiz-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 2rem;
    padding-top: 1.25rem;
    border-top: 1.5px solid var(--border);
}
.wiz-dots {
    display: flex;
    gap: 0.4rem;
    align-items: center;
}
.wiz-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #e2e8f0;
    transition: all 0.2s;
}
.wiz-dot--active { background: var(--accent); width: 24px; border-radius: 4px; }
.wiz-dot--done { background: var(--success); }

/* Execute button */
.btn-execute {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 1.4rem;
    background: var(--danger);
    color: #fff;
    font-weight: 700;
    font-size: 0.875rem;
    border: none;
    border-radius: var(--radius);
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s, transform 0.1s;
    box-shadow: 0 2px 8px rgba(239,68,68,0.3);
}
.btn-execute:hover { background: #dc2626; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(239,68,68,0.35); }
.btn-execute:active { transform: translateY(0); }
.btn-execute:disabled { opacity: 0.55; cursor: not-allowed; box-shadow: none; transform: none; }

/* ── Animations ── */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
.spin-icon { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.slide-enter-active, .slide-leave-active { transition: all 0.2s; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-4px); }

/* ── Responsive ── */
@media (max-width: 600px) {
    .year-selector { grid-template-columns: 1fr; }
    .year-arrow-col { display: none; }
    .modules-grid { grid-template-columns: 1fr; }
    .wizard-body { padding: 1.25rem; }
    .wizard-progress { padding: 1rem 1.25rem; gap: 0; }
    .wiz-step-text { display: none; }
    .wiz-step-circle { width: 28px; height: 28px; font-size: 0.75rem; }
}
</style>
