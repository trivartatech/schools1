<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';

const toast = useToast();
const confirm = useConfirm();

const props = defineProps({
    school: Object,
    classes: Array,
    periods: Array,
    timetables: Array,
    classSubjects: Array,
    selectedSectionId: [String, Number],
    filters: Object,
});

const selectedClassId = ref('');
const selectedSectionId = ref(props.selectedSectionId || '');

const weekdayDays = [
    { id: 1, name: 'Monday' },
    { id: 2, name: 'Tuesday' },
    { id: 3, name: 'Wednesday' },
    { id: 4, name: 'Thursday' },
    { id: 5, name: 'Friday' },
];

const weekendDays = [
    { id: 6, name: 'Saturday' },
];

const weekdayPeriods = computed(() => props.periods.filter(p => !p.is_weekend));
const weekendPeriods = computed(() => props.periods.filter(p => p.is_weekend));

const availableSections = computed(() => {
    if (!selectedClassId.value) return [];
    const cls = props.classes.find(c => c.id === selectedClassId.value);
    return cls ? cls.sections : [];
});

onMounted(() => {
    if (props.selectedSectionId) {
        for (const cls of props.classes) {
            const sec = cls.sections.find(s => s.id == props.selectedSectionId);
            if (sec) {
                selectedClassId.value = cls.id;
                break;
            }
        }
        initGrid();
    }

    const savedSettings = localStorage.getItem('timetableGenSettings');
    if (savedSettings) {
        try {
            generatorSettings.value = { ...generatorSettings.value, ...JSON.parse(savedSettings) };
        } catch(e) {}
    }
});

const timetableData = ref({});
const gridInitialized = ref(false);

const initGrid = () => {
    const data = {};
    if (props.periods && props.timetables) {
        weekdayPeriods.value.forEach(p => {
            weekdayDays.forEach(d => {
                const key = `${p.id}-${d.id}`;
                const existing = props.timetables.find(t => t.period_id == p.id && t.day_of_week == d.id);
                if (existing) {
                    const cs = props.classSubjects.find(c => c.subject_id == existing.subject_id && c.incharge_staff_id == existing.staff_id);
                    data[key] = { class_subject_id: cs ? cs.id : '' };
                } else {
                    data[key] = { class_subject_id: '' };
                }
            });
        });

        weekendPeriods.value.forEach(p => {
            weekendDays.forEach(d => {
                const key = `${p.id}-${d.id}`;
                const existing = props.timetables.find(t => t.period_id == p.id && t.day_of_week == d.id);
                if (existing) {
                    const cs = props.classSubjects.find(c => c.subject_id == existing.subject_id && c.incharge_staff_id == existing.staff_id);
                    data[key] = { class_subject_id: cs ? cs.id : '' };
                } else {
                    data[key] = { class_subject_id: '' };
                }
            });
        });
    }
    timetableData.value = data;
    gridInitialized.value = true;
};

watch(() => props.timetables, () => {
    initGrid();
}, { deep: true });

const fetchTimetable = () => {
    if (selectedSectionId.value) {
        router.get(route('school.timetable'), { section_id: selectedSectionId.value }, {
            preserveState: true,
            preserveScroll: true,
            only: ['timetables', 'classSubjects', 'selectedSectionId', 'filters']
        });
    }
};

const saveTimetable = () => {
    if (!selectedSectionId.value || !selectedClassId.value) return;

    const records = [];
    Object.keys(timetableData.value).forEach(key => {
        const [period_id, day_of_week] = key.split('-');
        const cell = timetableData.value[key];

        let subject_id = null;
        let staff_id = null;

        if (cell.class_subject_id) {
            const cs = props.classSubjects.find(c => c.id === cell.class_subject_id);
            if (cs) {
                subject_id = cs.subject_id;
                staff_id = cs.incharge_staff_id;
            }
        }

        records.push({
            period_id: parseInt(period_id),
            day_of_week: parseInt(day_of_week),
            subject_id: subject_id,
            staff_id: staff_id,
        });
    });

    router.post(route('school.timetable.save'), {
        section_id: selectedSectionId.value,
        course_class_id: selectedClassId.value,
        timetables: records
    }, {
        preserveScroll: true,
    });
};

const showAutoGenerateModal = ref(false);
const autoGenOptions = ref({
    day: '',
    class_subject_ids: []
});

const generatorSettings = ref({
    prevent_teacher_conflict: true,
    prevent_class_conflict: true,
    respect_weekly_limit: false,
    avoid_consecutive: false,
    evenly_distribute: false,
    limit_teacher_max: false,
    avoid_same_day: false,
    prefer_morning: false,
    enable_randomization: true,
    allow_double_periods: false,
    lock_existing: false,
});

const saveConditions = () => {
    localStorage.setItem('timetableGenSettings', JSON.stringify(generatorSettings.value));
    toast.success('Conditions saved to browser successfully!');
};

const executeAutoGenerate = () => {
    if (!selectedSectionId.value || !selectedClassId.value) return;

    router.post(route('school.timetable.generate'), {
        section_id: selectedSectionId.value,
        course_class_id: selectedClassId.value,
        target_day: autoGenOptions.value.day || null,
        target_class_subject_ids: autoGenOptions.value.class_subject_ids.length ? autoGenOptions.value.class_subject_ids : null,
        settings: generatorSettings.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAutoGenerateModal.value = false;
        }
    });
};

const resetTimetable = async () => {
    if (!selectedSectionId.value) return;
    const ok = await confirm({
        title: 'Reset timetable?',
        message: 'This will completely reset the timetable for this section, clearing all existing assignments.',
        confirmLabel: 'Reset',
        danger: true,
    });
    if (!ok) return;
    router.post(route('school.timetable.reset'), {
        section_id: selectedSectionId.value,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <SchoolLayout title="Timetable">

        <PageHeader title="Timetable Builder" subtitle="Manage schedules iteratively or auto-generate for a section.">
            <template #actions>
                <Button variant="secondary" v-if="selectedSectionId && classSubjects.length" @click="showAutoGenerateModal = true">
                    Auto Generate
                </Button>
                <Button variant="danger" v-if="selectedSectionId && gridInitialized" @click="resetTimetable">
                    Reset
                </Button>
                <Button v-if="selectedSectionId && gridInitialized" @click="saveTimetable">
                    Save Timetable
                </Button>
            </template>
        </PageHeader>

        <!-- Class + Section Filter -->
        <FilterBar :active="!!(selectedClassId || selectedSectionId)"
                   @clear="selectedClassId=''; selectedSectionId=''; gridInitialized=false">
            <div class="form-field">
                <label>Class</label>
                <select v-model="selectedClassId" @change="selectedSectionId = ''; gridInitialized = false" style="width:200px;">
                    <option value="">— Choose Class —</option>
                    <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                </select>
            </div>
            <div class="form-field">
                <label>Section</label>
                <select v-model="selectedSectionId" @change="fetchTimetable" :disabled="!selectedClassId" style="width:200px;">
                    <option value="">— Choose Section —</option>
                    <option v-for="sec in availableSections" :key="sec.id" :value="sec.id">{{ sec.name }}</option>
                </select>
            </div>
        </FilterBar>

        <!-- Empty state: no section selected -->
        <EmptyState
            v-if="!selectedSectionId"
            title="Select a Class & Section"
            description="Choose a class and section above to view or start building its timetable."
        />

        <!-- Warning: no periods -->
        <div v-else-if="!periods.length" class="state-card state-card--warn">
            <h3>No Periods Defined</h3>
            <p>Please set up periods for this school before creating timetables.</p>
            <Button variant="warning" size="sm" as="link" :href="route('school.periods.index')" class="mt-3.5">Go to Periods</Button>
        </div>

        <!-- Warning: no subjects -->
        <div v-else-if="!classSubjects.length" class="state-card state-card--danger">
            <h3>No Subjects Assigned</h3>
            <p>This section doesn't have any subjects assigned to it yet.</p>
            <Button variant="danger" size="sm" as="link" :href="route('school.class-subjects.index')" class="mt-3.5">Assign Subjects</Button>
        </div>

        <!-- Timetable Grid -->
        <div v-else-if="gridInitialized" class="tt-grids">

            <!-- Weekday Grid -->
            <div v-if="weekdayPeriods.length" class="card tt-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <span class="tt-dot tt-dot--weekday"></span>
                        Weekday Schedule
                        <span class="tt-subtitle">Mon – Fri</span>
                    </h2>
                </div>
                <div class="card-body" style="padding:0;overflow-x:auto">
                    <table class="tt-grid">
                        <thead>
                            <tr>
                                <th class="tt-period-col">Period</th>
                                <th v-for="day in weekdayDays" :key="day.id" class="tt-day-col">
                                    {{ day.name.substring(0, 3) }}
                                    <span class="tt-day-full">{{ day.name }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="period in weekdayPeriods" :key="period.id">
                                <td class="tt-period-cell">
                                    <span class="tt-period-name">{{ period.name }}</span>
                                    <span class="tt-period-time">{{ period.start_time.substring(0,5) }} – {{ period.end_time.substring(0,5) }}</span>
                                    <span :class="['tt-period-type', `tt-period-type--${period.type}`]">{{ period.type }}</span>
                                </td>

                                <template v-if="period.type === 'break' || period.type === 'lunch'">
                                    <td :colspan="weekdayDays.length" class="tt-break-cell">
                                        <span :class="['tt-break-label', `tt-break-label--${period.type}`]">{{ period.name }}</span>
                                    </td>
                                </template>

                                <template v-else>
                                    <td v-for="day in weekdayDays" :key="day.id" class="tt-subject-cell">
                                        <select
                                            v-model="timetableData[`${period.id}-${day.id}`].class_subject_id"
                                            :class="['tt-select', timetableData[`${period.id}-${day.id}`].class_subject_id ? 'tt-select--filled' : '']"
                                        >
                                            <option value="">Free</option>
                                            <option v-for="cs in classSubjects" :key="cs.id" :value="cs.id">
                                                {{ cs.subject.name }} ({{ cs.incharge_staff?.user?.name || 'Unassigned' }})
                                            </option>
                                        </select>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Weekend Grid -->
            <div v-if="weekendPeriods.length" class="card tt-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <span class="tt-dot tt-dot--weekend"></span>
                        Weekend Schedule
                        <span class="tt-subtitle">Saturday</span>
                    </h2>
                </div>
                <div class="card-body" style="padding:0;overflow-x:auto">
                    <table class="tt-grid">
                        <thead>
                            <tr>
                                <th class="tt-period-col">Period</th>
                                <th v-for="day in weekendDays" :key="day.id" class="tt-day-col">{{ day.name }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="period in weekendPeriods" :key="period.id">
                                <td class="tt-period-cell">
                                    <span class="tt-period-name">{{ period.name }}</span>
                                    <span class="tt-period-time">{{ period.start_time.substring(0,5) }} – {{ period.end_time.substring(0,5) }}</span>
                                    <span :class="['tt-period-type', `tt-period-type--${period.type}`]">{{ period.type }}</span>
                                </td>

                                <template v-if="period.type === 'break' || period.type === 'lunch'">
                                    <td :colspan="weekendDays.length" class="tt-break-cell">
                                        <span :class="['tt-break-label', `tt-break-label--${period.type}`]">{{ period.name }}</span>
                                    </td>
                                </template>

                                <template v-else>
                                    <td v-for="day in weekendDays" :key="day.id" class="tt-subject-cell">
                                        <select
                                            v-model="timetableData[`${period.id}-${day.id}`].class_subject_id"
                                            :class="['tt-select', timetableData[`${period.id}-${day.id}`].class_subject_id ? 'tt-select--filled' : '']"
                                        >
                                            <option value="">Free</option>
                                            <option v-for="cs in classSubjects" :key="cs.id" :value="cs.id">
                                                {{ cs.subject.name }} ({{ cs.incharge_staff?.user?.name || 'Unassigned' }})
                                            </option>
                                        </select>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Auto Generate Modal -->
        <Modal v-model:open="showAutoGenerateModal" title="Auto Generate Timetable" size="xl">
            <div class="modal-cols">
                <!-- Left: Scope -->
                <div class="modal-col">
                    <h4 class="modal-section-title">Generation Scope</h4>

                    <div class="form-field">
                        <label>Target Day <span class="optional-label">(Optional)</span></label>
                        <select v-model="autoGenOptions.day">
                            <option value="">All Days (Full Week)</option>
                            <option v-for="day in [...weekdayDays, ...weekendDays]" :key="day.id" :value="day.id">{{ day.name }}</option>
                        </select>
                        <span class="field-hint">Leave empty to generate for the entire week.</span>
                    </div>

                    <div class="form-field" style="margin-top:1rem">
                        <label>Target Subjects <span class="optional-label">(Optional)</span></label>
                        <div class="subject-checklist">
                            <div v-if="classSubjects.length === 0" class="subject-checklist-empty">No subjects assigned.</div>
                            <label v-for="cs in classSubjects" :key="cs.id" class="subject-check-row">
                                <input type="checkbox" v-model="autoGenOptions.class_subject_ids" :value="cs.id" />
                                <div>
                                    <span class="subject-name">{{ cs.subject.name }}</span>
                                    <span class="subject-teacher">{{ cs.incharge_staff?.user?.name || 'Unassigned' }}</span>
                                </div>
                            </label>
                        </div>
                        <span class="field-hint">Leave all unchecked to distribute all subjects.</span>
                    </div>
                </div>

                <!-- Right: Constraints -->
                <div class="modal-col">
                    <h4 class="modal-section-title">Constraint Settings</h4>
                    <div class="constraints-list">
                        <label class="constraint-row constraint-row--mandatory">
                            <input type="checkbox" v-model="generatorSettings.prevent_teacher_conflict" disabled />
                            <span>Prevent Teacher Conflict <span class="badge badge-indigo">Mandatory</span></span>
                        </label>
                        <label class="constraint-row constraint-row--mandatory">
                            <input type="checkbox" v-model="generatorSettings.prevent_class_conflict" disabled />
                            <span>Prevent Class Conflict <span class="badge badge-indigo">Mandatory</span></span>
                        </label>
                        <div class="constraint-divider"></div>
                        <label class="constraint-row">
                            <input type="checkbox" v-model="generatorSettings.respect_weekly_limit" />
                            <span>Respect Subject Weekly Limit</span>
                        </label>
                        <label class="constraint-row">
                            <input type="checkbox" v-model="generatorSettings.avoid_consecutive" />
                            <span>Avoid Same Subject Consecutively</span>
                        </label>
                        <label class="constraint-row">
                            <input type="checkbox" v-model="generatorSettings.evenly_distribute" />
                            <span>Evenly Distribute Subjects Across Week</span>
                        </label>
                        <label class="constraint-row">
                            <input type="checkbox" v-model="generatorSettings.limit_teacher_max" />
                            <span>Limit Teacher Max Periods Per Day</span>
                        </label>
                        <label class="constraint-row">
                            <input type="checkbox" v-model="generatorSettings.avoid_same_day" />
                            <span>Avoid Same Subject Same Day</span>
                        </label>
                        <label class="constraint-row">
                            <input type="checkbox" v-model="generatorSettings.prefer_morning" />
                            <span>Prefer Morning Slots for Core Subjects</span>
                        </label>
                        <label class="constraint-row">
                            <input type="checkbox" v-model="generatorSettings.enable_randomization" />
                            <span>Enable Randomization (shuffle allocation)</span>
                        </label>
                        <label class="constraint-row">
                            <input type="checkbox" v-model="generatorSettings.allow_double_periods" />
                            <span>Allow Double Periods (for labs)</span>
                        </label>
                        <div class="constraint-divider"></div>
                        <label class="constraint-row constraint-row--lock">
                            <input type="checkbox" v-model="generatorSettings.lock_existing" />
                            <span>Lock Existing Slots (do not override manual entries)</span>
                        </label>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button variant="secondary" size="sm" @click="saveConditions">Save Conditions</Button>
                <div style="margin-left:auto;display:flex;gap:.5rem;">
                    <Button variant="secondary" size="sm" @click="showAutoGenerateModal = false">Cancel</Button>
                    <Button @click="executeAutoGenerate">Generate Timetable</Button>
                </div>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
/* ── Filter card ── */
.filter-card { margin-bottom: 1.25rem; }
.filter-body {
    display: flex;
    align-items: flex-end;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding: 1rem 1.25rem;
}
.filter-step {
    display: flex;
    align-items: flex-end;
    gap: 0.65rem;
    flex: 1;
    min-width: 180px;
}
.filter-step-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--accent);
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    flex-shrink: 0;
    margin-bottom: 6px;
}
.filter-step-num--dim { background: #e2e8f0; color: #94a3b8; }
.filter-divider {
    color: #cbd5e1;
    flex-shrink: 0;
    margin-bottom: 10px;
}
.filter-field {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.filter-field label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.filter-field select {
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    background: var(--bg);
    color: #1e293b;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    width: 100%;
    box-sizing: border-box;
}
.filter-field select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}
.filter-field select:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── State cards ── */
.state-card {
    border-radius: var(--radius-lg);
    padding: 4rem 2rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.45rem;
}
.state-card--warn { background: #fffbeb; border: 1.5px solid #fde68a; }
.state-card--danger { background: #fef2f2; border: 1.5px solid #fecaca; }
.state-card h3 { font-size: 1rem; font-weight: 700; color: #1e293b; margin: 0; }
.state-card p { font-size: 0.875rem; color: #64748b; margin: 0; max-width: 360px; }

/* ── Timetable grids ── */
.tt-grids { display: flex; flex-direction: column; gap: 1.5rem; }

.tt-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}
.tt-dot--weekday { background: var(--accent); }
.tt-dot--weekend { background: var(--warning); }
.tt-subtitle {
    font-size: 0.74rem;
    font-weight: 400;
    color: #94a3b8;
    margin-left: 0.2rem;
}

.tt-grid {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.tt-grid thead tr { background: #f8fafc; }
.tt-grid th {
    padding: 0.65rem 0.75rem;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
.tt-period-col {
    min-width: 140px;
    background: #f1f5f9;
    border-right: 1px solid var(--border);
    position: sticky;
    left: 0;
    z-index: 1;
}
.tt-day-col {
    min-width: 155px;
    text-align: center;
    font-size: 0.72rem;
}
.tt-day-full { display: none; }

.tt-grid tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.1s; }
.tt-grid tbody tr:hover { background: #fafbfe; }

.tt-period-cell {
    padding: 0.6rem 0.75rem;
    background: #f8fafc;
    border-right: 1px solid var(--border);
    vertical-align: top;
    position: sticky;
    left: 0;
    z-index: 1;
}
.tt-period-name {
    display: block;
    font-weight: 700;
    color: #1e293b;
    font-size: 0.83rem;
}
.tt-period-time {
    display: block;
    font-size: 0.69rem;
    color: #94a3b8;
    font-family: 'Courier New', monospace;
    margin-top: 2px;
}
.tt-period-type {
    display: inline-block;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.1rem 0.45rem;
    border-radius: 10px;
    margin-top: 3px;
}
.tt-period-type--period  { background: #dbeafe; color: #1d4ed8; }
.tt-period-type--break   { background: #fef9c3; color: #a16207; }
.tt-period-type--lunch   { background: #ffedd5; color: #c2410c; }
.tt-period-type--assembly { background: #f3e8ff; color: #7e22ce; }

.tt-break-cell {
    text-align: center;
    padding: 0.75rem;
    background: #fffbeb;
}
.tt-break-label {
    font-size: 0.71rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    padding: 0.22rem 0.85rem;
    border-radius: 20px;
}
.tt-break-label--break { background: #fef9c3; color: #a16207; }
.tt-break-label--lunch { background: #ffedd5; color: #c2410c; }

.tt-subject-cell {
    padding: 0.38rem 0.45rem;
    border-right: 1px solid #f1f5f9;
    vertical-align: middle;
}
.tt-select {
    width: 100%;
    border: 1.5px solid var(--border);
    border-radius: 7px;
    padding: 0.35rem 0.5rem;
    font-size: 0.77rem;
    color: #475569;
    background: #f8fafc;
    outline: none;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
}
.tt-select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 2px rgba(99,102,241,0.1);
    background: var(--surface);
}
.tt-select--filled {
    background: #eef2ff;
    border-color: #a5b4fc;
    color: #3730a3;
    font-weight: 600;
}

.modal-cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.75rem;
}
.modal-col { display: flex; flex-direction: column; gap: 0; }
.modal-section-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    margin: 0 0 1rem 0;
    padding-bottom: 0.55rem;
    border-bottom: 1px solid var(--border);
}

/* Constraints */
.constraints-list {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}
.constraint-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.82rem;
    color: #374151;
    cursor: pointer;
    padding: 0.3rem 0.45rem;
    border-radius: 6px;
    transition: background 0.12s;
}
.constraint-row:hover { background: #f8fafc; }
.constraint-row input[type="checkbox"] { accent-color: var(--accent); flex-shrink: 0; }
.constraint-row--mandatory { opacity: 0.6; cursor: not-allowed; }
.constraint-row--lock { font-weight: 600; color: #047857; }
.constraint-divider { height: 1px; background: var(--border); margin: 0.3rem 0; }

/* Subject checklist */
.subject-checklist {
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    max-height: 210px;
    overflow-y: auto;
    background: var(--surface);
}
.subject-checklist-empty {
    padding: 1rem;
    font-size: 0.82rem;
    color: #94a3b8;
    text-align: center;
}
.subject-check-row {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.55rem 0.75rem;
    cursor: pointer;
    transition: background 0.12s;
    border-bottom: 1px solid #f8fafc;
}
.subject-check-row:last-child { border-bottom: none; }
.subject-check-row:hover { background: #f8fafc; }
.subject-check-row input[type="checkbox"] { accent-color: var(--accent); margin-top: 2px; flex-shrink: 0; }
.subject-name { display: block; font-size: 0.83rem; font-weight: 600; color: #1e293b; }
.subject-teacher { display: block; font-size: 0.71rem; color: #94a3b8; }

/* Form fields in modal */
.form-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.form-field label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #475569;
}
.form-field select {
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 0.5rem 0.7rem;
    font-size: 0.875rem;
    background: var(--surface);
    color: #1e293b;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.form-field select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}
.field-hint { font-size: 0.73rem; color: #94a3b8; }
.optional-label { font-size: 0.71rem; color: #94a3b8; font-weight: 400; margin-left: 2px; }

@media (max-width: 768px) {
    .modal-cols { grid-template-columns: 1fr; }
    .tt-day-full { display: none; }
}
</style>
