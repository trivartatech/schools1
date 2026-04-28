<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useConfirm } from '@/Composables/useConfirm';

const confirm = useConfirm();

const props = defineProps({ leaveTypes: Array });

// ── Preset colors ──
const COLORS = [
    '#1169cd', '#0d9488', '#059669', '#7c3aed',
    '#db2777', '#ea580c', '#ca8a04', '#64748b',
    '#dc2626', '#0891b2', '#65a30d', '#9333ea',
];

const DEFAULTS = [
    { name: 'Casual Leave',    code: 'CL',  days_allowed: 12, color: '#1169cd', is_paid: true,  carry_forward: false, max_carry_forward_days: 0, requires_document: false, min_notice_days: 1 },
    { name: 'Sick Leave',      code: 'SL',  days_allowed: 12, color: '#dc2626', is_paid: true,  carry_forward: false, max_carry_forward_days: 0, requires_document: true,  min_notice_days: 0 },
    { name: 'Earned Leave',    code: 'EL',  days_allowed: 15, color: '#059669', is_paid: true,  carry_forward: true,  max_carry_forward_days: 10, requires_document: false, min_notice_days: 7 },
    { name: 'Maternity Leave', code: 'ML',  days_allowed: 180, color: '#db2777', is_paid: true, carry_forward: false, max_carry_forward_days: 0, requires_document: true,  min_notice_days: 30 },
    { name: 'Paternity Leave', code: 'PL',  days_allowed: 15, color: '#7c3aed', is_paid: true,  carry_forward: false, max_carry_forward_days: 0, requires_document: false, min_notice_days: 7 },
    { name: 'Unpaid Leave',    code: 'UL',  days_allowed: 0,  color: '#64748b', is_paid: false, carry_forward: false, max_carry_forward_days: 0, requires_document: false, min_notice_days: 3 },
];

// ── Form ──
const showForm = ref(false);
const editing = ref(null);

const blankForm = () => ({
    name: '', code: '', days_allowed: 12, color: '#1169cd',
    is_paid: true, carry_forward: false, max_carry_forward_days: 0,
    requires_document: false, min_notice_days: 1, description: '', is_active: true,
});

const form = useForm(blankForm());

const openCreate = () => { editing.value = null; form.reset(); Object.assign(form, blankForm()); showForm.value = true; };
const openEdit = (lt) => {
    editing.value = lt.id;
    Object.assign(form, {
        name: lt.name, code: lt.code, days_allowed: lt.days_allowed, color: lt.color,
        is_paid: lt.is_paid, carry_forward: lt.carry_forward,
        max_carry_forward_days: lt.max_carry_forward_days,
        requires_document: lt.requires_document, min_notice_days: lt.min_notice_days,
        description: lt.description || '', is_active: lt.is_active,
    });
    form.clearErrors();
    showForm.value = true;
};
const closeForm = () => { showForm.value = false; editing.value = null; form.reset(); };

const applyDefault = (preset) => {
    Object.assign(form, preset);
    form.clearErrors();
};

const submit = () => {
    if (editing.value) {
        form.put(`/school/leave-types/${editing.value}`, { onSuccess: closeForm });
    } else {
        form.post('/school/leave-types', { onSuccess: closeForm });
    }
};

const toggleActive = (lt) => {
    router.patch(`/school/leave-types/${lt.id}/toggle`, {}, { preserveScroll: true });
};
const deleteType = async (lt) => {
    const ok = await confirm({
        title: 'Delete leave type?',
        message: `Delete "${lt.name}"? This cannot be undone.`,
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/leave-types/${lt.id}`, { preserveScroll: true });
};

// ── Drag to reorder ──
const dragSrc = ref(null);
const onDragStart = (lt) => { dragSrc.value = lt; };
const onDropCard = (target) => {
    if (!dragSrc.value || dragSrc.value.id === target.id) return;
    const ordered = [...props.leaveTypes];
    const fromIdx = ordered.findIndex(l => l.id === dragSrc.value.id);
    const toIdx   = ordered.findIndex(l => l.id === target.id);
    ordered.splice(toIdx, 0, ordered.splice(fromIdx, 1)[0]);
    router.post('/school/leave-types/reorder', { order: ordered.map(l => l.id) }, { preserveScroll: true });
    dragSrc.value = null;
};
</script>

<template>
    <SchoolLayout title="Leave Type Management">

        <!-- Header -->
        <PageHeader title="Leave Types" subtitle="Configure leave categories, annual allocation, and rules for your school">
            <template #actions>
                <Button @click="openCreate">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Add Leave Type
                            </Button>
            </template>
        </PageHeader>

        <div class="lt-shell">

            <!-- ── Left: cards ── -->
            <div class="lt-list">

                <!-- Info tip -->
                <div class="lt-tip" v-if="leaveTypes.length === 0">
                    <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/></svg>
                    <div>
                        <p class="font-semibold text-sm text-blue-900 mb-1">No leave types yet</p>
                        <p class="text-xs text-blue-700">Use the quick-add presets on the right or click <strong>"Add Leave Type"</strong> to get started. Common types like CL, SL, and EL are available as defaults.</p>
                    </div>
                </div>

                <!-- Cards -->
                <div v-for="lt in leaveTypes" :key="lt.id"
                    class="lt-card"
                    :class="{ 'lt-card--inactive': !lt.is_active }"
                    draggable="true"
                    @dragstart="onDragStart(lt)"
                    @dragover.prevent
                    @drop.prevent="onDropCard(lt)">

                    <!-- Color stripe -->
                    <div class="lt-stripe" :style="`background:${lt.color}`"></div>

                    <div class="lt-card-body">
                        <!-- Header row -->
                        <div class="lt-card-top">
                            <div class="flex items-center gap-2">
                                <span class="lt-code-badge" :style="`background:${lt.color}22;color:${lt.color};border-color:${lt.color}44`">{{ lt.code }}</span>
                                <span class="lt-name">{{ lt.name }}</span>
                                <span v-if="!lt.is_active" class="badge badge-gray" style="font-size:0.6rem;">Inactive</span>
                            </div>
                            <div class="lt-drag-hint">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                            </div>
                        </div>

                        <!-- Info chips -->
                        <div class="lt-chips">
                            <div class="lt-chip">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                {{ lt.days_allowed === 0 ? 'Unlimited' : `${lt.days_allowed} days/yr` }}
                            </div>
                            <div class="lt-chip" :class="lt.is_paid ? 'lt-chip--green' : 'lt-chip--red'">
                                {{ lt.is_paid ? 'Paid' : 'Unpaid' }}
                            </div>
                            <div v-if="lt.carry_forward" class="lt-chip lt-chip--blue">
                                ↩ Carry Fwd
                                <span v-if="lt.max_carry_forward_days > 0">(max {{ lt.max_carry_forward_days }}d)</span>
                            </div>
                            <div v-if="lt.requires_document" class="lt-chip lt-chip--amber">
                                📄 Doc Required
                            </div>
                            <div v-if="lt.min_notice_days > 0" class="lt-chip">
                                {{ lt.min_notice_days }}d notice
                            </div>
                        </div>

                        <!-- Description -->
                        <p v-if="lt.description" class="lt-desc">{{ lt.description }}</p>

                        <!-- Footer: usage + actions -->
                        <div class="lt-card-footer">
                            <span class="lt-usage">{{ lt.leaves_count }} leave{{ lt.leaves_count !== 1 ? 's' : '' }} used</span>
                            <div class="flex items-center gap-2">
                                <button @click="toggleActive(lt)" class="lt-action-btn"
                                    :title="lt.is_active ? 'Deactivate' : 'Activate'"
                                    :class="lt.is_active ? 'lt-action-btn--amber' : 'lt-action-btn--green'">
                                    {{ lt.is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                                <button @click="openEdit(lt)" class="lt-action-btn lt-action-btn--blue" title="Edit">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.5-6.5a2.121 2.121 0 013 3L12 16H9v-3z"/></svg>
                                    Edit
                                </button>
                                <button @click="deleteType(lt)" class="lt-action-btn lt-action-btn--red" title="Delete">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16M10 4h4a1 1 0 011 1v1H9V5a1 1 0 011-1z"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <p v-if="leaveTypes.length > 1" class="lt-reorder-hint">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                    Drag cards to reorder
                </p>
            </div>

            <!-- ── Right: Form / Presets ── -->
            <div class="lt-sidebar">

                <!-- Add/Edit Form -->
                <div class="card" v-if="showForm">
                    <div class="card-header">
                        <span class="card-title">{{ editing ? 'Edit Leave Type' : 'New Leave Type' }}</span>
                        <button @click="closeForm" style="color:#94a3b8;background:none;border:none;cursor:pointer;font-size:1.2rem;line-height:1;">×</button>
                    </div>
                    <div class="card-body">
                        <form @submit.prevent="submit">
                            <div class="form-row form-row-2 mb-3">
                                <div class="form-field">
                                    <label>Name <span style="color:#fc4336">*</span></label>
                                    <input v-model="form.name" type="text" placeholder="e.g. Casual Leave" required>
                                    <div v-if="form.errors.name" class="form-error">{{ form.errors.name }}</div>
                                </div>
                                <div class="form-field">
                                    <label>Code <span style="color:#fc4336">*</span></label>
                                    <input v-model="form.code" type="text" placeholder="e.g. CL" maxlength="10" style="text-transform:uppercase;" required>
                                    <div v-if="form.errors.code" class="form-error">{{ form.errors.code }}</div>
                                </div>
                            </div>

                            <div class="form-row form-row-2 mb-3">
                                <div class="form-field">
                                    <label>Annual Days <span style="color:#94a3b8;font-weight:400">(0 = unlimited)</span></label>
                                    <input v-model.number="form.days_allowed" type="number" min="0" max="365">
                                    <div v-if="form.errors.days_allowed" class="form-error">{{ form.errors.days_allowed }}</div>
                                </div>
                                <div class="form-field">
                                    <label>Min Notice Days</label>
                                    <input v-model.number="form.min_notice_days" type="number" min="0">
                                </div>
                            </div>

                            <!-- Color picker -->
                            <div class="form-field mb-3">
                                <label>Color</label>
                                <div class="lt-color-picker">
                                    <div v-for="c in COLORS" :key="c"
                                        class="lt-color-dot"
                                        :style="`background:${c}`"
                                        :class="{ 'lt-color-dot--active': form.color === c }"
                                        @click="form.color = c"
                                        :title="c"></div>
                                    <input v-model="form.color" type="color" class="lt-color-custom" title="Custom color">
                                </div>
                            </div>

                            <!-- Toggles -->
                            <div class="lt-toggles mb-3">
                                <label class="lt-toggle">
                                    <input type="checkbox" v-model="form.is_paid">
                                    <div class="lt-toggle-track" :class="form.is_paid ? 'lt-toggle-track--on' : ''">
                                        <div class="lt-toggle-thumb"></div>
                                    </div>
                                    <span>Paid Leave</span>
                                </label>

                                <label class="lt-toggle">
                                    <input type="checkbox" v-model="form.requires_document">
                                    <div class="lt-toggle-track" :class="form.requires_document ? 'lt-toggle-track--on' : ''">
                                        <div class="lt-toggle-thumb"></div>
                                    </div>
                                    <span>Requires Medical/Document</span>
                                </label>

                                <label class="lt-toggle">
                                    <input type="checkbox" v-model="form.carry_forward">
                                    <div class="lt-toggle-track" :class="form.carry_forward ? 'lt-toggle-track--on' : ''">
                                        <div class="lt-toggle-thumb"></div>
                                    </div>
                                    <span>Carry Forward to Next Year</span>
                                </label>

                                <div v-if="form.carry_forward" class="form-field mt-2">
                                    <label>Max Carry Forward Days</label>
                                    <input v-model.number="form.max_carry_forward_days" type="number" min="0">
                                </div>

                                <label class="lt-toggle" v-if="editing">
                                    <input type="checkbox" v-model="form.is_active">
                                    <div class="lt-toggle-track" :class="form.is_active ? 'lt-toggle-track--on' : ''">
                                        <div class="lt-toggle-thumb"></div>
                                    </div>
                                    <span>Active</span>
                                </label>
                            </div>

                            <!-- Description -->
                            <div class="form-field mb-4">
                                <label>Description <span style="color:#94a3b8;font-weight:400">(optional)</span></label>
                                <textarea v-model="form.description" rows="2" placeholder="Brief description or policy note..."></textarea>
                            </div>

                            <!-- Preview badge -->
                            <div class="lt-preview mb-4" v-if="form.name || form.code">
                                <span class="lt-code-badge" :style="`background:${form.color}22;color:${form.color};border-color:${form.color}44`">{{ form.code || 'XX' }}</span>
                                <span style="font-size:0.875rem;font-weight:600;color:#1e293b;">{{ form.name || 'Preview' }}</span>
                                <span class="lt-chip" :class="form.is_paid ? 'lt-chip--green' : 'lt-chip--red'" style="margin-left:auto;">{{ form.is_paid ? 'Paid' : 'Unpaid' }}</span>
                            </div>

                            <div style="display:flex;gap:8px;">
                                <Button variant="secondary" type="button" @click="closeForm" class="flex-1">Cancel</Button>
                                <Button type="submit" :loading="form.processing" class="flex-[2]">
                                    <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    {{ (editing ? 'Update Leave Type' : 'Create Leave Type') }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Quick-add presets (shown when form is closed) -->
                <div class="card" v-if="!showForm">
                    <div class="card-header">
                        <span class="card-title">
                            <svg class="w-4 h-4 inline mr-1 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Quick-Add Presets
                        </span>
                    </div>
                    <div class="card-body" style="padding:12px;">
                        <p style="font-size:0.75rem;color:#64748b;margin-bottom:10px;">Click a preset to pre-fill the form, then customize and save.</p>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <button v-for="p in DEFAULTS" :key="p.code"
                                @click="applyDefault(p); showForm = true; editing = null"
                                class="lt-preset-btn">
                                <span class="lt-code-badge" :style="`background:${p.color}22;color:${p.color};border-color:${p.color}44`" style="min-width:32px;text-align:center;">{{ p.code }}</span>
                                <span style="flex:1;font-size:0.8125rem;font-weight:500;color:#1e293b;">{{ p.name }}</span>
                                <span style="font-size:0.725rem;color:#94a3b8;">{{ p.days_allowed === 0 ? 'Unlimited' : `${p.days_allowed}d/yr` }}</span>
                                <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Summary stats -->
                <div class="card" v-if="leaveTypes.length > 0">
                    <div class="card-header"><span class="card-title">Summary</span></div>
                    <div class="card-body" style="padding:12px;">
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            <div class="lt-stat">
                                <span>Total Types</span>
                                <strong>{{ leaveTypes.length }}</strong>
                            </div>
                            <div class="lt-stat">
                                <span>Active</span>
                                <strong style="color:#059669;">{{ leaveTypes.filter(l => l.is_active).length }}</strong>
                            </div>
                            <div class="lt-stat">
                                <span>Paid Types</span>
                                <strong>{{ leaveTypes.filter(l => l.is_paid).length }}</strong>
                            </div>
                            <div class="lt-stat">
                                <span>Total Annual Days</span>
                                <strong style="color:#1169cd;">{{ leaveTypes.reduce((s, l) => s + (l.days_allowed || 0), 0) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
.lt-shell { display: flex; gap: 20px; align-items: flex-start; }
.lt-list { flex: 1; display: flex; flex-direction: column; gap: 10px; min-width: 0; }
.lt-sidebar { width: 300px; min-width: 300px; display: flex; flex-direction: column; gap: 16px; position: sticky; top: 20px; }

/* Tip */
.lt-tip {
    display: flex; gap: 12px; align-items: flex-start;
    background: #eff6ff; border: 1px solid #bfdbfe;
    border-radius: 10px; padding: 14px 16px;
    font-size: 0.8125rem; color: #1e3a5f;
}

/* Card */
.lt-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    display: flex; overflow: hidden; cursor: grab;
    transition: box-shadow 0.15s, transform 0.15s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.lt-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); transform: translateY(-1px); }
.lt-card--inactive { opacity: 0.6; }
.lt-card:active { cursor: grabbing; }
.lt-stripe { width: 5px; flex-shrink: 0; }
.lt-card-body { flex: 1; padding: 14px 16px; min-width: 0; }

.lt-card-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.lt-name { font-size: 0.9rem; font-weight: 700; color: #1e293b; }
.lt-drag-hint { color: #cbd5e1; }

.lt-code-badge {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 2px 8px; border-radius: 6px; font-size: 0.725rem;
    font-weight: 800; letter-spacing: 0.06em; border: 1px solid;
    line-height: 1.4; flex-shrink: 0;
}

.lt-chips { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 8px; }
.lt-chip {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 8px; border-radius: 20px; font-size: 0.7rem;
    font-weight: 600; background: #f1f5f9; color: #475569; white-space: nowrap;
}
.lt-chip--green { background: #dcfce7; color: #166534; }
.lt-chip--red   { background: #fee2e2; color: #991b1b; }
.lt-chip--blue  { background: #dbeafe; color: #1e40af; }
.lt-chip--amber { background: #fef3c7; color: #78350f; }

.lt-desc { font-size: 0.775rem; color: #64748b; margin-bottom: 10px; line-height: 1.4; }

.lt-card-footer { display: flex; align-items: center; justify-content: space-between; padding-top: 10px; border-top: 1px solid #f1f5f9; }
.lt-usage { font-size: 0.725rem; color: #94a3b8; }

.lt-action-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 6px; font-size: 0.725rem;
    font-weight: 600; border: 1px solid transparent; cursor: pointer;
    transition: all 0.12s;
}
.lt-action-btn--blue  { background: #eff6ff; color: #1169cd; border-color: #bfdbfe; }
.lt-action-btn--blue:hover { background: #dbeafe; }
.lt-action-btn--red   { background: #fff5f5; color: #fc4336; border-color: #ffc9c9; padding: 4px 7px; }
.lt-action-btn--red:hover { background: #ffe0e0; }
.lt-action-btn--amber { background: #fffbeb; color: #92400e; border-color: #fcd34d; }
.lt-action-btn--amber:hover { background: #fef3c7; }
.lt-action-btn--green { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
.lt-action-btn--green:hover { background: #dcfce7; }

.lt-reorder-hint { display: flex; align-items: center; gap: 5px; font-size: 0.725rem; color: #cbd5e1; justify-content: center; padding: 4px; }

/* Color picker */
.lt-color-picker { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-top: 4px; }
.lt-color-dot {
    width: 24px; height: 24px; border-radius: 6px; cursor: pointer;
    border: 2px solid transparent; transition: all 0.12s;
}
.lt-color-dot:hover { transform: scale(1.15); }
.lt-color-dot--active { border-color: #1e293b; transform: scale(1.15); box-shadow: 0 0 0 2px white inset; }
.lt-color-custom { width: 32px; height: 24px; border: 1.5px solid #e2e8f0; border-radius: 6px; cursor: pointer; padding: 1px 2px; }

/* Toggles */
.lt-toggles { display: flex; flex-direction: column; gap: 10px; }
.lt-toggle { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.8125rem; color: #374151; }
.lt-toggle input[type="checkbox"] { display: none; }
.lt-toggle-track {
    width: 36px; height: 20px; border-radius: 20px; background: #e2e8f0;
    position: relative; transition: background 0.15s; flex-shrink: 0;
}
.lt-toggle-track--on { background: #1169cd; }
.lt-toggle-thumb {
    position: absolute; width: 14px; height: 14px; border-radius: 50%;
    background: white; top: 3px; left: 3px; transition: left 0.15s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.lt-toggle-track--on .lt-toggle-thumb { left: 19px; }

/* Preview */
.lt-preview { display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; }

/* Preset btn */
.lt-preset-btn {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px; border-radius: 8px;
    border: 1.5px solid #e2e8f0; background: #fff;
    cursor: pointer; transition: all 0.12s; width: 100%;
    text-align: left;
}
.lt-preset-btn:hover { border-color: #1169cd; background: #eff6ff; }

/* Stats */
.lt-stat { display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem; color: #475569; padding: 4px 0; border-bottom: 1px solid #f1f5f9; }
.lt-stat:last-child { border-bottom: none; }

.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.mt-2 { margin-top: 8px; }
</style>
