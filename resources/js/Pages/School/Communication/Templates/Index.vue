<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed, nextTick } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    templates: Array,
    type: String,
    triggers: { type: Array, default: () => [] },
    allVariables: { type: Array, default: () => [] }
});

const contentTextarea = ref(null);

const showModal = ref(false);
const editingTemplate = ref(null);

const form = useForm({
    type: props.type,
    name: '',
    slug: '',
    template_id: '',
    subject: '',
    content: '',
    audio_url: '',
    language_code: 'en',
    variables: [],
    is_active: true
});

const openCreate = () => {
    editingTemplate.value = null;
    form.reset();
    form.type = props.type;
    showModal.value = true;
};

const openEdit = (template) => {
    editingTemplate.value = template;
    form.type = template.type;
    form.name = template.name;
    form.slug = template.slug;
    form.template_id = template.template_id;
    form.subject = template.subject;
    form.content = template.content;
    form.audio_url = template.audio_url || '';
    form.language_code = template.language_code || 'en';
    form.variables = template.variables || [];
    form.is_active = !!template.is_active;
    showModal.value = true;
};

const submit = () => {
    if (editingTemplate.value) {
        form.put(route('school.communication.templates.update', editingTemplate.value.id), {
            onSuccess: () => closeModal()
        });
    } else {
        form.post(route('school.communication.templates.store'), {
            onSuccess: () => closeModal()
        });
    }
};

const closeModal = () => {
    showModal.value = false;
    editingTemplate.value = null;
    form.reset();
};

const toggleStatus = (id) => {
    router.patch(route('school.communication.templates.toggle', id));
};

const deleteTemplate = (id) => {
    if (confirm('Are you sure you want to delete this template?')) {
        router.delete(route('school.communication.templates.destroy', id));
    }
};

const triggersForChannel = computed(() =>
    props.triggers.filter(t => !t.system || !t.channels || t.channels.includes(props.type))
);

const SYSTEM_SLUGS = computed(() => props.triggers.filter(t => t.system).map(t => t.value));

const isSystemTemplate = (template) =>
    !!template?.is_system || SYSTEM_SLUGS.value.includes(template?.slug);

const availableVariables = computed(() => {
    const map = {};
    for (const t of props.triggers) map[t.value] = t.variables || [];
    return map;
});

const variablesForCurrentForm = computed(() => {
    if (form.slug && availableVariables.value[form.slug]?.length) {
        return availableVariables.value[form.slug];
    }
    return props.allVariables;
});

const variablesPanelLabel = computed(() => {
    if (!form.slug) return 'All Available Variables';
    if (form.slug === 'custom') return 'All Available Variables (Custom Template)';
    return `Available Variables for ${getTriggerLabel(form.slug)}`;
});

const insertVariable = (variable) => {
    const ta = contentTextarea.value;
    if (!ta) {
        form.content = (form.content || '') + variable;
        return;
    }
    const start = ta.selectionStart ?? form.content.length;
    const end = ta.selectionEnd ?? form.content.length;
    const before = (form.content || '').substring(0, start);
    const after = (form.content || '').substring(end);
    form.content = before + variable + after;
    nextTick(() => {
        ta.focus();
        const cursor = start + variable.length;
        ta.setSelectionRange(cursor, cursor);
    });
};

const getTriggerLabel = (slug) => {
    const trigger = props.triggers.find(t => t.value === slug);
    return trigger ? trigger.label : slug;
};

const titleMap = {
    sms: 'SMS Templates',
    whatsapp: 'WhatsApp Templates',
    push: 'Push Notification Templates',
    voice: 'Voice Templates'
};
</script>

<template>
    <SchoolLayout :title="titleMap[type]">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">{{ titleMap[type] }}</h1>
                <p class="page-header-sub">Manage external provider templates and mappings</p>
            </div>
            <Button @click="openCreate">+ Create Template</Button>
        </div>

        <!-- List -->
        <div class="card">
            <div style="overflow-x:auto;">
                <Table>
                    <thead>
                        <tr>
                            <th>Name / Trigger</th>
                            <th>Template ID</th>
                            <th>Content Preview</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="template in templates" :key="template.id">
                            <td>
                                <div style="display:flex;flex-direction:column;gap:6px;">
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <svg v-if="isSystemTemplate(template)" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#94a3b8" stroke-width="2" title="System template — cannot be deleted">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                        <span style="font-weight:700;color:var(--text-primary);">{{ template.name }}</span>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <label class="toggle toggle-sm" @click.stop>
                                            <input type="checkbox" :checked="template.is_active" @change="toggleStatus(template.id)">
                                            <span class="toggle-track"></span>
                                        </label>
                                        <span class="badge badge-blue">{{ getTriggerLabel(template.slug) }}</span>
                                        <span v-if="isSystemTemplate(template)" class="badge badge-gray" style="font-size:.6rem;">SYSTEM</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <code v-if="template.template_id" class="template-id">{{ template.template_id }}</code>
                                <span v-else style="font-size:.78rem;color:var(--text-muted);font-style:italic;">Not Set</span>
                            </td>
                            <td>
                                <div style="max-width:300px;">
                                    <p class="content-preview">
                                        <span v-if="template.subject" style="font-weight:700;color:var(--accent);margin-right:6px;">{{ template.subject }}</span>
                                        <span v-if="template.audio_url" style="color:#7c3aed;font-weight:600;margin-right:6px;">[AUDIO: {{ template.audio_url.split('/').pop() }}]</span>
                                        {{ template.content }}
                                    </p>
                                </div>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:flex;align-items:center;justify-content:flex-end;gap:4px;">
                                    <Button variant="secondary" size="xs" @click="openEdit(template)">Edit</Button>
                                    <Button variant="danger" size="xs" v-if="!isSystemTemplate(template)" @click="deleteTemplate(template.id)">Delete</Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="templates.length === 0">
                            <td colspan="4" style="text-align:center;padding:3rem;color:var(--text-muted);">
                                <p style="margin-bottom:8px;">No templates created for {{ type }}</p>
                                <Button size="sm" @click="openCreate">Create your first template</Button>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Teleport to="body">
        <div v-if="showModal" class="modal-backdrop" @mousedown.self="closeModal">
            <div class="modal modal-lg">
                <div class="card-header">
                    <h3 class="card-title">{{ editingTemplate ? 'Update' : 'Create' }} Template</h3>
                    <button @click="closeModal" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:1.2rem;">&times;</button>
                </div>
                <div class="card-body" style="max-height:70vh;overflow-y:auto;">
                    <form @submit.prevent="submit">
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Template Name</label>
                                <input type="text" v-model="form.name" :disabled="(editingTemplate && isSystemTemplate(editingTemplate))" placeholder="e.g. Student Daily Attendance">
                                <div v-if="form.errors.name" class="form-error">{{ form.errors.name }}</div>
                            </div>
                            <div class="form-field">
                                <label>System Trigger</label>
                                <select v-model="form.slug" :disabled="(editingTemplate && isSystemTemplate(editingTemplate))">
                                    <option value="" disabled>Select Trigger</option>
                                    <option v-for="t in ((editingTemplate && isSystemTemplate(editingTemplate)) ? triggersForChannel : triggers.filter(t => t.value === 'custom'))" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                                <div v-if="form.errors.slug" class="form-error">{{ form.errors.slug }}</div>
                            </div>
                        </div>

                        <div class="form-row" style="margin-top:14px;">
                            <div class="form-field">
                                <label>Provider Template ID</label>
                                <input type="text" v-model="form.template_id" placeholder="ID from MSG91/WhatsApp Portal">
                            </div>
                        </div>

                        <div v-if="type === 'whatsapp'" class="form-row" style="margin-top:14px;">
                            <div class="form-field">
                                <label>Template Language Code</label>
                                <input type="text" v-model="form.language_code" placeholder="e.g. en, en_US">
                                <p style="font-size:.72rem;color:var(--text-muted);margin-top:4px;">Must match Meta/MSG91 template language</p>
                            </div>
                        </div>

                        <div v-if="type === 'push' || type === 'mail'" class="form-row" style="margin-top:14px;">
                            <div class="form-field">
                                <label>Subject</label>
                                <input type="text" v-model="form.subject" placeholder="Enter subject line">
                            </div>
                        </div>

                        <div v-if="type === 'voice'" class="form-row" style="margin-top:14px;">
                            <div class="form-field">
                                <label>Intro Voice (Audio URL)</label>
                                <input type="text" v-model="form.audio_url" placeholder="https://prefix.com/intro.mp3">
                                <p style="font-size:.72rem;color:var(--text-muted);margin-top:4px;">Plays before the message body</p>
                            </div>
                        </div>

                        <div class="form-row" style="margin-top:14px;">
                            <div class="form-field">
                                <label>Message Body / TTS Content</label>
                                <textarea ref="contentTextarea" v-model="form.content" rows="4" placeholder="Use ##NAME##, ##DATE## as placeholders. Click any variable below to insert it."></textarea>

                                <div v-if="variablesForCurrentForm.length" class="variables-box">
                                    <label style="font-size:.72rem;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.03em;display:block;text-align:center;margin-bottom:4px;">
                                        {{ variablesPanelLabel }}
                                    </label>
                                    <p style="font-size:.68rem;color:var(--text-muted);text-align:center;margin:0 0 8px 0;">Click a variable to insert it at the cursor</p>
                                    <div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;">
                                        <code
                                            v-for="variable in variablesForCurrentForm"
                                            :key="variable"
                                            class="var-tag var-tag-clickable"
                                            :title="`Insert ${variable}`"
                                            @click="insertVariable(variable)"
                                        >{{ variable }}</code>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="toggle-row" style="margin-top:14px;">
                            <div>
                                <span style="font-weight:700;font-size:.84rem;color:var(--text-primary);">Is Active</span>
                                <p style="font-size:.72rem;color:var(--text-muted);margin-top:2px;">If disabled, this template will not trigger</p>
                            </div>
                            <label class="toggle">
                                <input type="checkbox" v-model="form.is_active">
                                <span class="toggle-track"></span>
                            </label>
                        </div>

                        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
                            <Button variant="secondary" type="button" @click="closeModal">Cancel</Button>
                            <Button type="submit" :loading="form.processing">
                                {{ (editingTemplate ? 'Update Template' : 'Save Template') }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </Teleport>
    </SchoolLayout>
</template>

<style scoped>
.template-id {
    font-size: .75rem; background: #f8fafc; color: var(--text-secondary);
    padding: 3px 8px; border-radius: 4px; border: 1px solid var(--border);
}
.content-preview {
    font-size: .78rem; color: var(--text-secondary); line-height: 1.5;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.variables-box {
    margin-top: 12px; padding: 14px; background: #eef2ff; border-radius: var(--radius);
    border: 1px solid #c7d2fe;
}
.var-tag {
    font-size: .72rem; font-weight: 700; background: #fff; color: var(--accent);
    padding: 3px 8px; border-radius: 6px; border: 1px solid #c7d2fe;
}
.var-tag-clickable {
    cursor: pointer; user-select: none; transition: background .12s, transform .12s;
}
.var-tag-clickable:hover {
    background: var(--accent); color: #fff; transform: translateY(-1px);
}
.var-tag-clickable:active {
    transform: translateY(0);
}
.toggle-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px; background: #f8fafc; border-radius: var(--radius); border: 1px solid var(--border);
}

.modal-backdrop {
    position: fixed; inset: 0; background: rgba(15,23,42,.5);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.modal {
    background: #fff; border-radius: 0.75rem; width: 100%;
    max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-lg { max-width: 42rem; }

.toggle { position: relative; display: inline-flex; cursor: pointer; }
.toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
.toggle-track {
    width: 44px; height: 24px; background: #e2e8f0; border-radius: 12px;
    transition: background .2s; position: relative;
}
.toggle-track::after {
    content: ''; position: absolute; top: 2px; left: 2px;
    width: 20px; height: 20px; background: #fff; border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,.15); transition: transform .2s;
}
.toggle input:checked + .toggle-track { background: var(--accent); }
.toggle input:checked + .toggle-track::after { transform: translateX(20px); }
.toggle-sm .toggle-track { width: 32px; height: 18px; }
.toggle-sm .toggle-track::after { width: 14px; height: 14px; }
.toggle-sm input:checked + .toggle-track::after { transform: translateX(14px); }
</style>
