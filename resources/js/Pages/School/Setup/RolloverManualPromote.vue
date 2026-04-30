<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import SchoolLayout from '@/Layouts/SchoolLayout.vue'
import Button from '@/Components/ui/Button.vue'
import PageHeader from '@/Components/ui/PageHeader.vue';
import Table from '@/Components/ui/Table.vue';
import axios from 'axios'
import { useConfirm } from '@/Composables/useConfirm'
import { useSchoolStore } from '@/stores/useSchoolStore';

const confirm = useConfirm()
const school = useSchoolStore()

const props = defineProps({
    run: { type: Object, required: true },
})

const base = `/school/settings/rollover/runs/${props.run.id}`

// ─── Source side ───
const sourceClasses = ref([])
const sourceSections = ref([])
const sourceClassId = ref('')
const sourceSectionId = ref('')
const students = ref([])
const selectedIds = ref(new Set())
const loadingSource = ref(false)
const loadingStudents = ref(false)

// ─── Target side ───
const targetClasses = ref([])
const targetSections = ref([])
const targetClassId = ref('')
const targetSectionId = ref('')
const loadingTarget = ref(false)
const loadingTargetSections = ref(false)

// ─── Action state ───
const submitting = ref(false)
const batches = ref([]) // { at, promoted, skipped, failed, fees_rows, fees_total }
const flash = ref(null)

// Cumulative totals pulled from run.stats so the page shows prior batches too.
const runStats = ref(props.run.stats || {})
const cumulativePromoted = computed(() => (runStats.value?.students_manual?.promoted) || 0)
const cumulativeCarriedRows = computed(() => (runStats.value?.fees_manual?.rows_created) || 0)
const cumulativeCarriedAmount = computed(() => runStats.value?.fees_manual?.total_amount || '0.00')

const allSelected = computed(() =>
    students.value.length > 0 && students.value.every(s => selectedIds.value.has(s.id))
)
const selectedCount = computed(() => selectedIds.value.size)
const selectedOutstanding = computed(() => {
    let total = 0
    for (const s of students.value) {
        if (selectedIds.value.has(s.id)) total += parseFloat(s.outstanding || 0)
    }
    return total.toFixed(2)
})

const canSubmit = computed(() =>
    selectedCount.value > 0 && targetClassId.value && targetSectionId.value && !submitting.value
)

// ─────────────── Loaders ───────────────

const loadSourceClasses = async () => {
    loadingSource.value = true
    try {
        const { data } = await axios.get(`${base}/classes`, {
            params: { year_id: props.run.source_year_id, only_with_students: 1 },
        })
        sourceClasses.value = data.classes
    } finally {
        loadingSource.value = false
    }
}

const loadSourceSections = async () => {
    sourceSections.value = []
    const prevSection = sourceSectionId.value
    sourceSectionId.value = ''
    students.value = []
    selectedIds.value = new Set()
    if (!sourceClassId.value) return
    const { data } = await axios.get(`${base}/sections`, {
        params: {
            class_id: sourceClassId.value,
            year_id:  props.run.source_year_id,
            only_with_students: 1,
        },
    })
    sourceSections.value = data.sections
    // If clearing the section above didn't trigger the student-load watcher
    // (because it was already empty), fetch the class-wide list now.
    if (!prevSection) {
        loadStudents()
    }
}

const loadStudents = async () => {
    students.value = []
    selectedIds.value = new Set()
    if (!sourceClassId.value) return
    loadingStudents.value = true
    try {
        const { data } = await axios.get(`${base}/eligible-students`, {
            params: {
                class_id:   sourceClassId.value,
                section_id: sourceSectionId.value || undefined,
            },
        })
        students.value = data.students
    } finally {
        loadingStudents.value = false
    }
}

const loadTargetClasses = async () => {
    loadingTarget.value = true
    try {
        const { data } = await axios.get(`${base}/classes`, {
            params: { year_id: props.run.target_year_id },
        })
        targetClasses.value = data.classes
    } finally {
        loadingTarget.value = false
    }
}

const loadTargetSections = async () => {
    targetSections.value = []
    targetSectionId.value = ''
    if (!targetClassId.value) return
    loadingTargetSections.value = true
    try {
        const { data } = await axios.get(`${base}/sections`, {
            params: {
                class_id: targetClassId.value,
                year_id:  props.run.target_year_id,
            },
        })
        targetSections.value = data.sections
    } finally {
        loadingTargetSections.value = false
    }
}

// ─── Init ───
loadSourceClasses()
loadTargetClasses()

watch(sourceClassId, loadSourceSections)
watch(sourceSectionId, loadStudents)
watch(targetClassId, loadTargetSections)

// ─── Selection helpers ───
const toggleAll = () => {
    if (allSelected.value) {
        selectedIds.value = new Set()
    } else {
        selectedIds.value = new Set(students.value.map(s => s.id))
    }
}
const toggleOne = (id) => {
    const s = new Set(selectedIds.value)
    s.has(id) ? s.delete(id) : s.add(id)
    selectedIds.value = s
}

// ─── Submit ───
const promote = async () => {
    if (!canSubmit.value) return
    const tgtClass = targetClasses.value.find(c => c.id === targetClassId.value)?.name || '?'
    const tgtSec = targetSections.value.find(s => s.id === targetSectionId.value)?.name || '?'
    const ok = await confirm({
        title: 'Promote students?',
        message: `Promote ${selectedCount.value} student(s) into ${tgtClass} — ${tgtSec} and carry ₹${selectedOutstanding.value} of dues?`,
        confirmLabel: 'Promote',
    })
    if (!ok) return
    submitting.value = true
    flash.value = null
    try {
        const { data } = await axios.post(`${base}/promote-manual`, {
            student_ids:       Array.from(selectedIds.value),
            target_class_id:   targetClassId.value,
            target_section_id: targetSectionId.value,
            carry_fees:        true,
        })
        batches.value.unshift({
            at: school.fmtTime(new Date()),
            target: `${tgtClass} — ${tgtSec}`,
            promoted: data.promotion.promoted,
            skipped:  data.promotion.skipped,
            failed:   data.promotion.failed,
            fees_rows:  data.fees?.rows_created ?? 0,
            fees_total: data.fees?.total_amount ?? '0.00',
        })
        flash.value = { kind: 'success', msg: `Promoted ${data.promotion.promoted} student(s). ${data.fees?.rows_created ?? 0} carry-forward fee row(s) created (₹${data.fees?.total_amount ?? '0.00'}).` }

        // Refresh run stats locally (best-effort — mirrors server side increments)
        const rs = { ...runStats.value }
        rs.students_manual = rs.students_manual || { promoted: 0, skipped: 0, failed: 0 }
        rs.students_manual.promoted += data.promotion.promoted
        rs.students_manual.skipped  += data.promotion.skipped
        rs.students_manual.failed   += data.promotion.failed
        if (data.fees) {
            rs.fees_manual = rs.fees_manual || { students_with_dues: 0, rows_created: 0, total_amount: '0.00', skipped: 0 }
            rs.fees_manual.rows_created += data.fees.rows_created
            rs.fees_manual.total_amount = (parseFloat(rs.fees_manual.total_amount) + parseFloat(data.fees.total_amount)).toFixed(2)
        }
        runStats.value = rs

        // Reload the student list so promoted students disappear.
        await loadStudents()
    } catch (e) {
        flash.value = { kind: 'error', msg: e?.response?.data?.message || e.message }
    } finally {
        submitting.value = false
    }
}

const markDone = async () => {
    const ok = await confirm({
        title: 'Mark promotion phase complete?',
        message: 'After this, any remaining source-year students without a target-year row will be treated as graduated. The next phase (carry-forward) becomes available.',
        confirmLabel: 'Mark Complete',
    })
    if (!ok) return
    router.post(`${base}/mark-students-done`)
}

const classLabel = (c) => c.numeric_value !== null && c.numeric_value !== undefined
    ? `${c.name}`
    : c.name
</script>

<template>
    <SchoolLayout title="Promote Students">
        <PageHeader title="Promote Students">
            <template #subtitle>
                <p class="page-header-sub">Run <strong>#{{ run.id }}</strong> ·
                    <strong>{{ run.source_year?.name }}</strong> → <strong>{{ run.target_year?.name }}</strong>
                    · State: <strong>{{ run.state }}</strong></p>
            </template>
            <template #actions>
                <a href="/school/settings/rollover" class="back-link">← Back to Wizard</a>
            </template>
        </PageHeader>

        <div v-if="flash" :class="['flash', flash.kind === 'success' ? 'flash--ok' : 'flash--err']">
            {{ flash.msg }}
        </div>

        <!-- Cumulative totals -->
        <div class="totals">
            <div class="total-card">
                <span class="total-label">Promoted so far</span>
                <span class="total-val">{{ cumulativePromoted }}</span>
            </div>
            <div class="total-card">
                <span class="total-label">Carry-forward rows</span>
                <span class="total-val">{{ cumulativeCarriedRows }}</span>
            </div>
            <div class="total-card">
                <span class="total-label">Amount carried</span>
                <span class="total-val">₹{{ cumulativeCarriedAmount }}</span>
            </div>
            <div class="total-spacer"></div>
            <Button variant="secondary" @click="markDone">Mark Students Phase Complete →</Button>
        </div>

        <!-- Dual picker -->
        <div class="pick-grid">
            <!-- SOURCE -->
            <div class="pick-card pick-card--source">
                <div class="pick-head">
                    <span class="pick-badge pick-badge--source">SOURCE</span>
                    <span>{{ run.source_year?.name }}</span>
                </div>

                <div class="pick-row">
                    <label>Class</label>
                    <select v-model="sourceClassId" :disabled="loadingSource">
                        <option value="" disabled>Select class…</option>
                        <option v-for="c in sourceClasses" :key="c.id" :value="c.id">{{ classLabel(c) }}</option>
                    </select>
                </div>

                <div class="pick-row">
                    <label>Section (optional)</label>
                    <select v-model="sourceSectionId" :disabled="!sourceClassId || sourceSections.length === 0">
                        <option value="">All sections</option>
                        <option v-for="s in sourceSections" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>

                <div class="student-head">
                    <label class="select-all">
                        <input type="checkbox" :checked="allSelected" :disabled="students.length === 0" @change="toggleAll" />
                        <span>Select all ({{ students.length }})</span>
                    </label>
                    <span class="counter">{{ selectedCount }} selected</span>
                </div>

                <div class="student-list">
                    <div v-if="loadingStudents" class="hint">Loading…</div>
                    <div v-else-if="!sourceClassId" class="hint">Pick a class to see students.</div>
                    <div v-else-if="students.length === 0" class="hint">No eligible students here (everyone may already be promoted).</div>

                    <label v-for="s in students" :key="s.id" class="student-row" :class="{ 'student-row--checked': selectedIds.has(s.id) }">
                        <input type="checkbox" :checked="selectedIds.has(s.id)" @change="toggleOne(s.id)" />
                        <div class="student-main">
                            <span class="student-name">{{ s.name }}</span>
                            <span class="student-meta">
                                {{ s.erp_no || s.admission_no || `#${s.id}` }}
                                <template v-if="s.roll_no"> · Roll {{ s.roll_no }}</template>
                                <template v-if="s.source_status && s.source_status !== 'current'"> · <em>{{ s.source_status }}</em></template>
                            </span>
                        </div>
                        <span class="student-due" :class="{ 'student-due--zero': parseFloat(s.outstanding) === 0 }">
                            ₹{{ s.outstanding }}
                        </span>
                    </label>
                </div>
            </div>

            <!-- TARGET -->
            <div class="pick-card pick-card--target">
                <div class="pick-head">
                    <span class="pick-badge pick-badge--target">TARGET</span>
                    <span>{{ run.target_year?.name }}</span>
                </div>

                <div class="pick-row">
                    <label>Class</label>
                    <select v-model="targetClassId" :disabled="loadingTarget">
                        <option value="" disabled>Select class…</option>
                        <option v-for="c in targetClasses" :key="c.id" :value="c.id">{{ classLabel(c) }}</option>
                    </select>
                </div>

                <div class="pick-row">
                    <label>Section</label>
                    <select v-model="targetSectionId" :disabled="!targetClassId || loadingTargetSections">
                        <option value="" disabled>Select section…</option>
                        <option v-for="s in targetSections" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>

                <div class="target-summary">
                    <div class="summary-row">
                        <span class="summary-label">Students to promote</span>
                        <span class="summary-val">{{ selectedCount }}</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Dues to carry</span>
                        <span class="summary-val">₹{{ selectedOutstanding }}</span>
                    </div>
                    <Button class="promote-btn" :disabled="!canSubmit" :loading="submitting" @click="promote">
                        Promote {{ selectedCount }} & Carry Fees
                    </Button>
                </div>
            </div>
        </div>

        <!-- Per-batch history (this session only) -->
        <div v-if="batches.length" class="batches">
            <h3 class="batches-title">This session</h3>
            <Table class="batch-table">
                <thead>
                    <tr>
                        <th>Time</th><th>Target</th>
                        <th class="num">Promoted</th><th class="num">Skipped</th><th class="num">Failed</th>
                        <th class="num">Fee rows</th><th class="num">₹ Carried</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(b, i) in batches" :key="i">
                        <td>{{ b.at }}</td>
                        <td>{{ b.target }}</td>
                        <td class="num">{{ b.promoted }}</td>
                        <td class="num">{{ b.skipped }}</td>
                        <td class="num">{{ b.failed }}</td>
                        <td class="num">{{ b.fees_rows }}</td>
                        <td class="num">₹{{ b.fees_total }}</td>
                    </tr>
                </tbody>
            </Table>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
.page-header-title { font-size: 1.2rem; font-weight: 700; color: #1e293b; margin: 0 0 0.25rem 0; }
.page-header-sub { font-size: 0.85rem; color: #64748b; margin: 0; }
.back-link { font-size: 0.85rem; color: var(--accent); font-weight: 600; text-decoration: none; }
.back-link:hover { text-decoration: underline; }

.flash { padding: 0.65rem 0.9rem; border-radius: var(--radius); font-size: 0.85rem; margin-bottom: 1rem; }
.flash--ok  { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.flash--err { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

.totals {
    display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;
    padding: 0.85rem 1rem; background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius);
}
.total-card { display: flex; flex-direction: column; gap: 2px; padding: 0 1rem; border-right: 1px solid var(--border); }
.total-card:last-of-type { border-right: none; }
.total-label { font-size: 0.68rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
.total-val { font-size: 1.05rem; font-weight: 700; color: #1e293b; }
.total-spacer { flex: 1; }

.pick-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.pick-card {
    background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius-lg);
    padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: 0.85rem;
    min-height: 480px;
}
.pick-card--source { border-color: #bfdbfe; }
.pick-card--target { border-color: #bbf7d0; }

.pick-head { display: flex; align-items: center; gap: 0.65rem; font-size: 0.95rem; font-weight: 700; color: #1e293b; }
.pick-badge { font-size: 0.65rem; font-weight: 800; padding: 0.2rem 0.55rem; border-radius: 10px; letter-spacing: 0.08em; }
.pick-badge--source { background: #dbeafe; color: #1d4ed8; }
.pick-badge--target { background: #dcfce7; color: #166534; }

.pick-row { display: flex; flex-direction: column; gap: 0.3rem; }
.pick-row label { font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
.pick-row select {
    border: 1.5px solid var(--border); border-radius: calc(var(--radius) - 2px);
    padding: 0.45rem 0.6rem; font-size: 0.88rem; background: var(--surface); outline: none; width: 100%;
}
.pick-row select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }

.student-head {
    display: flex; align-items: center; justify-content: space-between;
    padding-top: 0.35rem; border-top: 1px solid var(--border);
}
.select-all { display: flex; align-items: center; gap: 0.45rem; font-size: 0.82rem; font-weight: 600; color: #334155; cursor: pointer; }
.counter { font-size: 0.78rem; font-weight: 700; color: var(--accent); }

.student-list { flex: 1; overflow-y: auto; max-height: 420px; display: flex; flex-direction: column; gap: 4px; }
.hint { font-size: 0.85rem; color: #94a3b8; padding: 1rem 0.5rem; text-align: center; }

.student-row {
    display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 0.65rem;
    padding: 0.5rem 0.6rem; border: 1px solid var(--border); border-radius: calc(var(--radius) - 2px);
    background: #f8fafc; cursor: pointer; transition: background 0.15s;
}
.student-row:hover { background: #f1f5f9; }
.student-row--checked { background: #eef2ff; border-color: #c7d2fe; }
.student-main { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.student-name { font-size: 0.85rem; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.student-meta { font-size: 0.72rem; color: #64748b; }
.student-due { font-size: 0.82rem; font-weight: 700; color: #b91c1c; white-space: nowrap; }
.student-due--zero { color: #94a3b8; font-weight: 500; }

.target-summary {
    margin-top: auto;
    padding: 0.85rem; background: #f0fdf4; border: 1.5px solid #86efac; border-radius: var(--radius);
    display: flex; flex-direction: column; gap: 0.55rem;
}
.summary-row { display: flex; justify-content: space-between; font-size: 0.88rem; color: #166534; }
.summary-label { font-weight: 600; }
.summary-val { font-weight: 800; }
.promote-btn { margin-top: 0.4rem; width: 100%; }

.batches { margin-top: 1.5rem; background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 1rem 1.1rem; }
.batches-title { font-size: 0.95rem; font-weight: 700; color: #1e293b; margin: 0 0 0.6rem 0; }
.batch-table { width: 100%; border-collapse: collapse; }
.batch-table th, .batch-table td { padding: 0.5rem 0.7rem; font-size: 0.82rem; border-bottom: 1px solid var(--border); text-align: left; }
.batch-table th { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 700; background: #f8fafc; }
.batch-table tbody tr:last-child td { border-bottom: none; }
.batch-table .num { text-align: right; font-variant-numeric: tabular-nums; }

@media (max-width: 860px) {
    .pick-grid { grid-template-columns: 1fr; }
    .totals { flex-wrap: wrap; }
    .total-card { border-right: none; padding: 0.4rem 0.6rem; }
}
</style>
