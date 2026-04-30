<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import SlidePanel from '@/Components/SlidePanel.vue';
import { useDelete } from '@/Composables/useDelete';
import Table from '@/Components/ui/Table.vue';

const props = defineProps(['periods']);
const typeColors = { period: 'bg-blue-100 text-blue-800', break: 'bg-yellow-100 text-yellow-800', lunch: 'bg-orange-100 text-orange-800', assembly: 'bg-purple-100 text-purple-800' };

const activeTab = ref('weekday'); // 'weekday' or 'weekend'

const filteredPeriods = computed(() => {
    return (props.periods || []).filter(p => {
        if (activeTab.value === 'weekday') return !p.is_weekend;
        return p.is_weekend;
    });
});

const panelOpen = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const form = useForm({ name: '', start_time: '', end_time: '', type: 'period', is_weekend: false, order: 1 });

const openCreate = () => {
    isEditing.value = false;
    form.reset();
    form.is_weekend = activeTab.value === 'weekend';
    form.order = filteredPeriods.value.length + 1;
    panelOpen.value = true;
};

const openEdit = (p) => {
    isEditing.value = true; editingId.value = p.id;
    form.name = p.name; form.start_time = p.start_time; form.end_time = p.end_time;
    form.type = p.type; form.is_weekend = !!p.is_weekend; form.order = p.order;
    panelOpen.value = true;
};

const closePanel = () => { panelOpen.value = false; form.reset(); };

const submit = () => {
    if (form.is_weekend) { activeTab.value = 'weekend'; } else { activeTab.value = 'weekday'; }
    if (isEditing.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/school/periods/${editingId.value}`, { onSuccess: () => closePanel(), onError: (e) => form.setError(e) });
    } else {
        form.transform((data) => data).post('/school/periods', { onSuccess: () => closePanel() });
    }
};

const { del } = useDelete();
const destroy = (id) => {
    if (!id) return;
    del(`/school/periods/${id}`, 'Delete this period?');
};

const calcDuration = (start, end) => {
    if (!start || !end) return '—';
    const toMin = (t) => {
        const [h, m] = t.split(':').map(Number);
        return h * 60 + m;
    };
    const diff = toMin(end) - toMin(start);
    if (diff <= 0) return '—';
    if (diff < 60) return `${diff}m`;
    const h = Math.floor(diff / 60);
    const m = diff % 60;
    return m ? `${h}h ${m}m` : `${h}h`;
};
</script>

<template>
    <SchoolLayout title="Periods">

        <!-- Page Header -->
        <PageHeader title="Period Configuration" subtitle="Manage bell timings and schedules for weekdays and weekends.">
            <template #actions>
                <Button @click="openCreate">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                Add Period
                            </Button>
            </template>
        </PageHeader>

        <!-- Schedule Track Tabs -->
        <div class="track-tabs">
            <button
                @click="activeTab = 'weekday'"
                :class="['track-tab', activeTab === 'weekday' ? 'track-tab--weekday' : '']"
            >
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Weekday Schedule
                <span class="track-tab-count">{{ (periods || []).filter(p => !p.is_weekend).length }}</span>
            </button>
            <button
                @click="activeTab = 'weekend'"
                :class="['track-tab', activeTab === 'weekend' ? 'track-tab--weekend' : '']"
            >
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m8.66-9h-1M4.34 12h-1m15.07-6.07l-.71.71M6.34 17.66l-.71.71M17.66 17.66l-.71-.71M6.34 6.34l-.71-.71M12 7a5 5 0 100 10A5 5 0 0012 7z"/></svg>
                Weekend Schedule
                <span class="track-tab-count">{{ (periods || []).filter(p => p.is_weekend).length }}</span>
            </button>
        </div>

        <!-- Periods Table -->
        <div class="card">
            <div class="card-body" style="padding:0">
                <Table>
                    <thead>
                        <tr>
                            <th class="col-order">#</th>
                            <th>Period Name</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Duration</th>
                            <th>Type</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="filteredPeriods.length === 0">
                            <td colspan="7">
                                <EmptyState
                                    tone="muted"
                                    :title="`No ${activeTab} periods configured`"
                                    description="Configure your bell timings using the Add Period button."
                                />
                            </td>
                        </tr>
                        <tr v-for="p in filteredPeriods" :key="p.id" class="period-row">
                            <td class="col-order">
                                <span class="order-bubble">{{ p.order }}</span>
                            </td>
                            <td>
                                <span class="period-name">{{ p.name }}</span>
                            </td>
                            <td>
                                <span class="time-chip">{{ p.start_time.substring(0, 5) }}</span>
                            </td>
                            <td>
                                <span class="time-chip">{{ p.end_time.substring(0, 5) }}</span>
                            </td>
                            <td>
                                <span class="duration-badge">{{ calcDuration(p.start_time, p.end_time) }}</span>
                            </td>
                            <td>
                                <span :class="['type-badge', `type-badge--${p.type}`]">{{ p.type }}</span>
                            </td>
                            <td class="col-actions">
                                <div class="row-actions">
                                    <Button variant="secondary" size="xs" @click="openEdit(p)">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        Edit
                                    </Button>
                                    <Button variant="danger" size="xs" @click="destroy(p.id)">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <!-- Add/Edit Slide Panel -->
        <SlidePanel :open="panelOpen" :title="isEditing ? 'Edit Period Timings' : 'Add New Period'" @close="closePanel">
            <template #sticky-footer>
                <div class="panel-footer-sticky">
                    <Button variant="secondary" type="button" @click="closePanel">Cancel</Button>
                    <Button @click="submit" :loading="form.processing">
                        <svg v-if="form.processing" class="spin-icon" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        {{ isEditing ? 'Save Changes' : 'Create Period' }}
                    </Button>
                </div>
            </template>

            <form @submit.prevent="submit" class="panel-form">

                <!-- Schedule Track -->
                <div class="track-selector">
                    <p class="track-selector-label">Schedule Track</p>
                    <div class="track-options">
                        <label :class="['track-option', !form.is_weekend ? 'track-option--weekday' : '']">
                            <input type="radio" v-model="form.is_weekend" :value="false" />
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Weekday
                        </label>
                        <label :class="['track-option', form.is_weekend ? 'track-option--weekend' : '']">
                            <input type="radio" v-model="form.is_weekend" :value="true" />
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m8.66-9h-1M4.34 12h-1m15.07-6.07l-.71.71M6.34 17.66l-.71.71M17.66 17.66l-.71-.71M6.34 6.34l-.71-.71M12 7a5 5 0 100 10A5 5 0 0012 7z"/></svg>
                            Weekend
                        </label>
                    </div>
                </div>

                <div class="form-field">
                    <label>Period Name <span class="req">*</span></label>
                    <input v-model="form.name" type="text" placeholder="e.g. Maths, Recess, Assembly" required />
                    <span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label>Start Time <span class="req">*</span></label>
                        <input v-model="form.start_time" type="time" required />
                        <span v-if="form.errors.start_time" class="form-error">{{ form.errors.start_time }}</span>
                    </div>
                    <div class="form-field">
                        <label>End Time <span class="req">*</span></label>
                        <input v-model="form.end_time" type="time" required />
                        <span v-if="form.errors.end_time" class="form-error">{{ form.errors.end_time }}</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label>Block Type</label>
                        <select v-model="form.type">
                            <option value="period">Academic Period</option>
                            <option value="break">Short Break</option>
                            <option value="lunch">Lunch Break</option>
                            <option value="assembly">Assembly</option>
                        </select>
                        <span v-if="form.errors.type" class="form-error">{{ form.errors.type }}</span>
                    </div>
                    <div class="form-field">
                        <label>Display Order</label>
                        <input v-model="form.order" type="number" min="1" placeholder="1" />
                        <span class="field-hint">Visual sequence in the list.</span>
                        <span v-if="form.errors.order" class="form-error">{{ form.errors.order }}</span>
                    </div>
                </div>

            </form>
        </SlidePanel>
    </SchoolLayout>
</template>


<style scoped>
/* ── Track tabs ── */
.track-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.25rem;
    background: var(--surface);
    border: 1.5px solid var(--border);
    padding: 4px;
    border-radius: var(--radius);
    width: fit-content;
    box-shadow: 0 1px 3px rgba(15,23,42,0.05);
}
.track-tab {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 1.05rem;
    font-size: 0.84rem;
    font-weight: 600;
    border: 1.5px solid transparent;
    border-radius: calc(var(--radius) - 3px);
    cursor: pointer;
    background: none;
    color: #64748b;
    transition: all 0.15s;
}
.track-tab:hover { color: #1e293b; background: #f8fafc; }
.track-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    background: #e2e8f0;
    color: #64748b;
    border-radius: 10px;
    padding: 0 5px;
    transition: all 0.15s;
}
.track-tab--weekday {
    background: #eff6ff;
    color: #1d4ed8;
    border-color: #bfdbfe;
}
.track-tab--weekday .track-tab-count { background: #bfdbfe; color: #1d4ed8; }
.track-tab--weekend {
    background: #fff7ed;
    color: #c2410c;
    border-color: #fed7aa;
}
.track-tab--weekend .track-tab-count { background: #fed7aa; color: #c2410c; }

/* ── Table ── */
.col-order { width: 56px; text-align: center; }
.col-actions { text-align: right; }

.order-bubble {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #f1f5f9;
    border: 1.5px solid var(--border);
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
}
.period-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: #1e293b;
}
.time-chip {
    font-family: 'Courier New', monospace;
    font-size: 0.83rem;
    font-weight: 600;
    color: #334155;
    background: #f1f5f9;
    border: 1px solid var(--border);
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
}
.duration-badge {
    font-size: 0.78rem;
    font-weight: 500;
    color: #64748b;
    background: #f8fafc;
    padding: 0.18rem 0.45rem;
    border-radius: 5px;
}
.type-badge {
    font-size: 0.69rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 0.22rem 0.65rem;
    border-radius: 20px;
}
.type-badge--period  { background: #dbeafe; color: #1d4ed8; }
.type-badge--break   { background: #fef9c3; color: #a16207; }
.type-badge--lunch   { background: #ffedd5; color: #c2410c; }
.type-badge--assembly { background: #f3e8ff; color: #7e22ce; }

.row-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.4rem;
}

/* ── Panel ── */
.panel-form {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}
.panel-footer-sticky {
    display: flex;
    justify-content: flex-end;
    gap: 0.6rem;
    padding: 1rem 1.25rem;
    background: #f8fafc;
    border-top: 1px solid var(--border);
}

/* Track selector in panel */
.track-selector {
    background: #f8fafc;
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 0.85rem 1rem;
}
.track-selector-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #94a3b8;
    margin: 0 0 0.65rem 0;
}
.track-options { display: flex; gap: 0.6rem; }
.track-option {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.9rem;
    font-size: 0.84rem;
    font-weight: 600;
    border: 1.5px solid var(--border);
    border-radius: calc(var(--radius) - 2px);
    cursor: pointer;
    background: var(--surface);
    color: #64748b;
    transition: all 0.15s;
}
.track-option input[type="radio"] { display: none; }
.track-option--weekday { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.track-option--weekend { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }

/* Form fields in panel */
.form-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.9rem;
}
.form-field label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #475569;
}
.form-field input,
.form-field select {
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 0.5rem 0.7rem;
    font-size: 0.875rem;
    color: #1e293b;
    background: var(--surface);
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    width: 100%;
    box-sizing: border-box;
}
.form-field input:focus,
.form-field select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}
.field-hint { font-size: 0.73rem; color: #94a3b8; }
.req { color: var(--danger); }

.spin-icon { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
