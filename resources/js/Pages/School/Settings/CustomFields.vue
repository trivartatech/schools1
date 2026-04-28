<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, watch, onMounted, onUnmounted, computed } from 'vue'
import { useForm, router, Link } from '@inertiajs/vue3'
import Sortable from 'sortablejs'
import SchoolLayout from '@/Layouts/SchoolLayout.vue'
import SlidePanel from '@/Components/SlidePanel.vue'
import { useDelete } from '@/Composables/useDelete'
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    fields: Array,
    entityType: String,
    entityTypes: Array,
})

const localFields = ref(props.fields.map(f => ({ ...f })))
watch(() => props.fields, (newVal) => { localFields.value = newVal.map(f => ({ ...f })) }, { deep: true })

const panelOpen = ref(false)
const isEditing = ref(false)
const editingId = ref(null)
const saving = ref(false)
const tbodyRef = ref(null)
let sortableInstance = null

// Form maps 'options' array to a comma-separated string for editing
const form = useForm({
    entity_type: props.entityType,
    label: '',
    type: 'text',
    options: '', // comma separated string
    is_required: false,
    is_active: true,
})

const getCsrf = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const saveOrder = async () => {
    saving.value = true
    try {
        await fetch('/school/custom-fields/reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrf() },
            body: JSON.stringify({ order: localFields.value.map((f, i) => ({ id: f.id, order: i + 1 })) }),
        })
    } finally {
        saving.value = false
    }
}

onMounted(() => {
    if (tbodyRef.value) {
        sortableInstance = Sortable.create(tbodyRef.value, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd(evt) {
                const moved = localFields.value.splice(evt.oldIndex, 1)[0]
                localFields.value.splice(evt.newIndex, 0, moved)
                saveOrder()
            },
        })
    }
})
onUnmounted(() => sortableInstance?.destroy())

const openCreate = () => {
    isEditing.value = false; editingId.value = null;
    form.reset(); form.entity_type = props.entityType; form.clearErrors();
    panelOpen.value = true
}

const openEdit = (f) => {
    isEditing.value = true; editingId.value = f.id
    form.entity_type = f.entity_type
    form.label = f.label
    form.type = f.type
    form.options = f.options ? f.options.join(', ') : ''
    form.is_required = f.is_required
    form.is_active = f.is_active
    form.clearErrors()
    panelOpen.value = true
}

const closePanel = () => { panelOpen.value = false }

const submit = () => {
    const opts = { onSuccess: () => closePanel(), onError: (e) => form.setError(e) }
    if (isEditing.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/school/custom-fields/${editingId.value}`, opts)
    } else {
        form.transform((data) => data).post('/school/custom-fields', opts)
    }
}

const { del } = useDelete()

const destroy = (id) => del(`/school/custom-fields/${id}`, 'Delete this custom field? All data saved under this field will be lost.')

// Tab switching
const switchEntity = (type) => {
    router.visit(`/school/custom-fields?entity_type=${type}`)
}

const formatOptionsPreview = (opts) => {
    if (!opts || opts.length === 0) return '—'
    return opts.length > 3 ? opts.slice(0, 3).join(', ') + '…' : opts.join(', ')
}
</script>

<template>
    <SchoolLayout title="Custom Fields">

        <!-- Page Header -->
        <PageHeader title="Custom Fields Builder" subtitle="Add flexible data points to student profiles, staff records, and more.">
            <template #actions>
                <transition name="fade">
                    <span v-if="saving" class="saving-pill">
                        <svg class="spin-icon" width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Saving order…
                    </span>
                </transition>
                <Button @click="openCreate">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    Add Field
                </Button>

            </template>
        </PageHeader>

        <!-- Entity Type Tabs -->
        <div class="entity-tabs">
            <button
                v-for="type in entityTypes"
                :key="type"
                @click="switchEntity(type)"
                :class="['entity-tab', type === entityType ? 'entity-tab--active' : '']"
            >
                {{ type }}
            </button>
        </div>

        <!-- Fields Table -->
        <div class="card">
            <div class="card-body" style="padding:0">
                <Table>
                    <thead>
                        <tr>
                            <th class="col-drag"></th>
                            <th>Label</th>
                            <th>Type</th>
                            <th>Options</th>
                            <th>Required</th>
                            <th>Status</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody ref="tbodyRef">
                        <tr v-if="localFields.length === 0">
                            <td colspan="7" class="empty-state">
                                <div class="empty-icon">
                                    <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                </div>
                                <p class="empty-title">No custom fields yet</p>
                                <p class="empty-sub">No fields defined for <strong>{{ entityType }}s</strong>. Click "Add Field" to get started.</p>
                            </td>
                        </tr>
                        <tr v-for="f in localFields" :key="f.id">
                            <td class="col-drag">
                                <span class="drag-handle" title="Drag to reorder">
                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 110 4 2 2 0 010-4zm6 0a2 2 0 110 4 2 2 0 010-4zM7 8a2 2 0 110 4 2 2 0 010-4zm6 0a2 2 0 110 4 2 2 0 010-4zM7 14a2 2 0 110 4 2 2 0 010-4zm6 0a2 2 0 110 4 2 2 0 010-4z"/></svg>
                                </span>
                            </td>
                            <td>
                                <span class="field-label">{{ f.label }}</span>
                                <code class="field-name">{{ f.name }}</code>
                            </td>
                            <td>
                                <span class="type-pill">{{ f.type }}</span>
                            </td>
                            <td class="options-cell">{{ formatOptionsPreview(f.options) }}</td>
                            <td>
                                <span v-if="f.is_required" class="badge badge-red">Required</span>
                                <span v-else class="optional-text">Optional</span>
                            </td>
                            <td>
                                <span v-if="f.is_active" class="badge badge-green">Active</span>
                                <span v-else class="badge badge-gray">Inactive</span>
                            </td>
                            <td class="col-actions">
                                <div class="row-actions">
                                    <Button variant="secondary" size="sm" @click="openEdit(f)">
                                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        Edit
                                    </Button>
                                    <Button variant="danger" size="sm" @click="destroy(f.id)">
                                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <p class="drag-hint">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
            Drag rows to reorder how they appear in the {{ entityType }} form.
        </p>

        <!-- Slide Panel: Add / Edit Field -->
        <SlidePanel :open="panelOpen" :title="isEditing ? 'Edit Custom Field' : 'Add Custom Field'" width="w-[420px]" @close="closePanel">
            <form @submit.prevent="submit" class="panel-form">

                <div class="entity-badge">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    Entity: <strong class="capitalize">{{ form.entity_type }}</strong>
                </div>

                <div class="form-field">
                    <label>Field Label <span class="req">*</span></label>
                    <input v-model="form.label" type="text" placeholder="e.g. Blood Group, Bus Stop…" required />
                    <span class="field-hint">This is how the field will appear on the form.</span>
                    <span v-if="form.errors.label" class="form-error">{{ form.errors.label }}</span>
                </div>

                <div class="form-field">
                    <label>Input Type <span class="req">*</span></label>
                    <select v-model="form.type" required :disabled="isEditing">
                        <option value="text">Short Text</option>
                        <option value="textarea">Long Text (Paragraph)</option>
                        <option value="number">Number</option>
                        <option value="date">Date Picker</option>
                        <option value="select">Dropdown Select</option>
                        <option value="radio">Radio Buttons</option>
                        <option value="checkbox">Checkbox (Yes/No)</option>
                    </select>
                    <span v-if="isEditing" class="field-hint warn">
                        <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Input type cannot be changed after creation.
                    </span>
                    <span v-if="form.errors.type" class="form-error">{{ form.errors.type }}</span>
                </div>

                <div v-if="form.type === 'select' || form.type === 'radio'" class="options-box">
                    <div class="form-field">
                        <label>Options <span class="req">*</span></label>
                        <textarea
                            v-model="form.options"
                            rows="3"
                            placeholder="A+, A-, B+, B-, O+, O-, AB+, AB-"
                            :required="form.type === 'select' || form.type === 'radio'"
                        ></textarea>
                        <span class="field-hint">Separate options with commas — e.g. <code>Red, Green, Blue</code></span>
                        <span v-if="form.errors.options" class="form-error">{{ form.errors.options }}</span>
                    </div>
                </div>

                <div class="toggle-group">
                    <p class="toggle-group-title">Validation &amp; Visibility</p>
                    <label class="toggle-row">
                        <span class="toggle-switch">
                            <input v-model="form.is_required" type="checkbox" class="toggle-check" />
                            <span class="toggle-knob"></span>
                        </span>
                        <div class="toggle-info">
                            <span class="toggle-label">Mandatory Field</span>
                            <span class="toggle-sub">Users cannot submit the form without filling this.</span>
                        </div>
                    </label>
                    <label class="toggle-row">
                        <span class="toggle-switch">
                            <input v-model="form.is_active" type="checkbox" class="toggle-check" />
                            <span class="toggle-knob"></span>
                        </span>
                        <div class="toggle-info">
                            <span class="toggle-label">Active Field</span>
                            <span class="toggle-sub">Uncheck to hide this field from forms temporarily.</span>
                        </div>
                    </label>
                </div>

                <div class="panel-footer">
                    <Button variant="secondary" type="button" @click="closePanel">Cancel</Button>
                    <Button type="submit" :loading="form.processing">
                        <svg v-if="form.processing" class="spin-icon" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        {{ isEditing ? 'Update Field' : 'Save Field' }}
                    </Button>
                </div>
            </form>
        </SlidePanel>

    </SchoolLayout>
</template>

<style scoped>
/* ── Page header ── */
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
}
.saving-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    font-weight: 500;
    color: var(--accent);
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    padding: 0.25rem 0.7rem;
    border-radius: 20px;
}

/* ── Entity tabs ── */
.entity-tabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid var(--border);
    margin-bottom: 1.25rem;
}
.entity-tab {
    padding: 0.5rem 1.1rem;
    font-size: 0.84rem;
    font-weight: 600;
    color: #64748b;
    background: none;
    border: none;
    border-bottom: 2.5px solid transparent;
    cursor: pointer;
    text-transform: capitalize;
    margin-bottom: -2px;
    transition: color 0.15s, border-color 0.15s;
    letter-spacing: 0.01em;
}
.entity-tab:hover { color: #1e293b; }
.entity-tab--active {
    color: var(--accent);
    border-bottom-color: var(--accent);
}

/* ── Table ── */
.col-drag { width: 40px; }
.col-actions { text-align: right; }

.drag-handle {
    display: inline-flex;
    align-items: center;
    color: #cbd5e1;
    cursor: grab;
    padding: 3px;
    border-radius: 4px;
    transition: color 0.15s, background 0.15s;
}
.drag-handle:hover { color: #64748b; background: #f1f5f9; }
.drag-handle:active { cursor: grabbing; }

.field-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #1e293b;
}
.field-name {
    display: inline-block;
    font-size: 0.72rem;
    font-family: 'Courier New', monospace;
    color: #94a3b8;
    background: #f1f5f9;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    margin-top: 2px;
}
.type-pill {
    font-size: 0.71rem;
    font-family: monospace;
    font-weight: 700;
    background: #f1f5f9;
    color: #475569;
    padding: 0.22rem 0.6rem;
    border-radius: 5px;
    letter-spacing: 0.02em;
    border: 1px solid var(--border);
}
.options-cell {
    font-size: 0.81rem;
    color: #64748b;
    max-width: 140px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.optional-text {
    font-size: 0.78rem;
    color: #94a3b8;
}
.row-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.4rem;
}

/* ── Empty state ── */
.empty-state {
    text-align: center;
    padding: 4rem 1rem;
    color: #64748b;
}
.empty-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #94a3b8;
    margin-bottom: 0.9rem;
}
.empty-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 0.35rem;
}
.empty-sub {
    font-size: 0.82rem;
    color: #94a3b8;
    margin: 0;
}

.drag-hint {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    text-align: center;
    font-size: 0.74rem;
    color: #cbd5e1;
    margin-top: 0.6rem;
}

/* ── Panel form ── */
.panel-form {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    padding: 1.25rem;
}
.entity-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: var(--radius);
    padding: 0.55rem 0.9rem;
    font-size: 0.82rem;
    color: #1d4ed8;
}
.entity-badge strong { margin-left: 2px; }

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
.form-field input,
.form-field select,
.form-field textarea {
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
.form-field select:focus,
.form-field textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}
.form-field select:disabled { background: #f8fafc; opacity: 0.65; }
.form-field textarea { resize: vertical; min-height: 76px; line-height: 1.5; }

.field-hint {
    font-size: 0.74rem;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.field-hint.warn { color: var(--warning); }
.req { color: var(--danger); }

.options-box {
    background: #f8fafc;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 0.9rem;
}

/* ── Toggle group ── */
.toggle-group {
    background: #f8fafc;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 0.9rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}
.toggle-group-title {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #94a3b8;
    margin: 0 0 0.1rem 0;
}
.toggle-row {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    cursor: pointer;
}

/* Custom toggle switch */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 34px;
    height: 18px;
    flex-shrink: 0;
    margin-top: 2px;
}
.toggle-check {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
}
.toggle-knob {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background: #cbd5e1;
    border-radius: 18px;
    transition: background 0.2s;
}
.toggle-knob::before {
    content: '';
    position: absolute;
    height: 13px;
    width: 13px;
    left: 3px;
    bottom: 2.5px;
    background: white;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.toggle-check:checked + .toggle-knob { background: var(--accent); }
.toggle-check:checked + .toggle-knob::before { transform: translateX(15px); }

.toggle-info { display: flex; flex-direction: column; gap: 1px; }
.toggle-label { font-size: 0.85rem; font-weight: 600; color: #1e293b; }
.toggle-sub { font-size: 0.74rem; color: #94a3b8; }

/* ── Panel footer ── */
.panel-footer {
    display: flex;
    gap: 0.6rem;
    padding-top: 0.5rem;
}
.panel-footer .btn { flex: 1; justify-content: center; }

/* ── Sortable states ── */
:global(.sortable-ghost) { opacity: 0.35; background: #eef2ff !important; }
:global(.sortable-chosen) { background: #f0f4ff !important; box-shadow: 0 4px 16px rgba(99,102,241,0.15); }

/* ── Animations ── */
.spin-icon { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
