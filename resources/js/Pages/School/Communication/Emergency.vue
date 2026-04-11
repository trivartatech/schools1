<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    channels: {
        type: Object,
        default: () => ({ sms: false, whatsapp: false, voice: false }),
    },
    templates: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    message: '',
    channels: [],
});

const selectedTemplate = ref('');
const sending = ref(false);

const charCount = computed(() => form.message.length);
const charsRemaining = computed(() => 500 - form.message.length);

const hasAnyChannel = computed(() => {
    return props.channels.sms || props.channels.whatsapp || props.channels.voice;
});

const canSubmit = computed(() => {
    return form.message.trim().length > 0
        && form.channels.length > 0
        && !sending.value;
});

function applyTemplate() {
    if (!selectedTemplate.value) return;
    const tpl = props.templates.find(t => t.id === Number(selectedTemplate.value));
    if (tpl) {
        form.message = tpl.content.substring(0, 500);
    }
}

function toggleChannel(channel) {
    const idx = form.channels.indexOf(channel);
    if (idx === -1) {
        form.channels.push(channel);
    } else {
        form.channels.splice(idx, 1);
    }
}

function sendBroadcast() {
    if (!canSubmit.value) return;

    const confirmed = confirm(
        'EMERGENCY BROADCAST CONFIRMATION\n\n'
        + 'This will immediately send a message to ALL parents and ALL staff members '
        + 'via: ' + form.channels.join(', ').toUpperCase() + '.\n\n'
        + 'Message:\n"' + form.message.substring(0, 100) + (form.message.length > 100 ? '...' : '') + '"\n\n'
        + 'Are you sure you want to proceed?'
    );

    if (!confirmed) return;

    sending.value = true;
    form.post('/school/communication/emergency', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            selectedTemplate.value = '';
            sending.value = false;
        },
        onError: () => {
            sending.value = false;
        },
        onFinish: () => {
            sending.value = false;
        },
    });
}
</script>

<template>
    <SchoolLayout title="Emergency Broadcast">
        <div class="emergency-page">
            <!-- Page Header -->
            <div class="emergency-header">
                <div class="emergency-header-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div>
                    <h1 class="emergency-header-title">Emergency Broadcast</h1>
                    <p class="emergency-header-sub">Send urgent messages to all parents and staff immediately</p>
                </div>
            </div>

            <!-- Warning Banner -->
            <div class="emergency-warning">
                <div class="emergency-warning-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <div>
                    <strong>Warning:</strong> This will send messages to <strong>ALL parents</strong> and
                    <strong>ALL staff members</strong>. Use only for genuine emergencies.
                </div>
            </div>

            <!-- Form Card -->
            <div class="emergency-card">
                <div class="emergency-card-header">
                    <h2 class="emergency-card-title">Compose Emergency Message</h2>
                </div>

                <div class="emergency-card-body">
                    <!-- Template Quick-Fill -->
                    <div v-if="templates.length > 0" class="form-field">
                        <label class="form-label">Quick Fill from Template</label>
                        <div class="template-row">
                            <select v-model="selectedTemplate" class="form-select">
                                <option value="">-- Select a template --</option>
                                <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id">
                                    {{ tpl.name }} ({{ tpl.type }})
                                </option>
                            </select>
                            <button type="button" class="btn-template-apply" @click="applyTemplate"
                                    :disabled="!selectedTemplate">
                                Apply
                            </button>
                        </div>
                    </div>

                    <!-- Message Textarea -->
                    <div class="form-field">
                        <label class="form-label">Emergency Message <span class="required">*</span></label>
                        <textarea
                            v-model="form.message"
                            class="form-textarea"
                            :class="{ 'form-textarea-error': form.errors.message }"
                            rows="6"
                            maxlength="500"
                            placeholder="Type your emergency message here..."
                        ></textarea>
                        <div class="textarea-footer">
                            <span v-if="form.errors.message" class="form-error">{{ form.errors.message }}</span>
                            <span class="char-counter" :class="{ 'char-counter-warn': charsRemaining <= 50 }">
                                {{ charCount }} / 500
                            </span>
                        </div>
                    </div>

                    <!-- Channel Checkboxes -->
                    <div class="form-field">
                        <label class="form-label">Broadcast Channels <span class="required">*</span></label>
                        <span v-if="form.errors.channels" class="form-error">{{ form.errors.channels }}</span>

                        <div class="channel-grid">
                            <!-- SMS -->
                            <label class="channel-option" :class="{ 'channel-disabled': !channels.sms, 'channel-active': form.channels.includes('sms') }">
                                <input
                                    type="checkbox"
                                    :checked="form.channels.includes('sms')"
                                    :disabled="!channels.sms"
                                    @change="toggleChannel('sms')"
                                    class="channel-checkbox"
                                />
                                <div class="channel-info">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                         stroke-linejoin="round">
                                        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                                    </svg>
                                    <span class="channel-name">SMS</span>
                                    <span v-if="!channels.sms" class="channel-not-configured">(Not configured)</span>
                                </div>
                            </label>

                            <!-- WhatsApp -->
                            <label class="channel-option" :class="{ 'channel-disabled': !channels.whatsapp, 'channel-active': form.channels.includes('whatsapp') }">
                                <input
                                    type="checkbox"
                                    :checked="form.channels.includes('whatsapp')"
                                    :disabled="!channels.whatsapp"
                                    @change="toggleChannel('whatsapp')"
                                    class="channel-checkbox"
                                />
                                <div class="channel-info">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                         fill="currentColor">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                    </svg>
                                    <span class="channel-name">WhatsApp</span>
                                    <span v-if="!channels.whatsapp" class="channel-not-configured">(Not configured)</span>
                                </div>
                            </label>

                            <!-- Voice -->
                            <label class="channel-option" :class="{ 'channel-disabled': !channels.voice, 'channel-active': form.channels.includes('voice') }">
                                <input
                                    type="checkbox"
                                    :checked="form.channels.includes('voice')"
                                    :disabled="!channels.voice"
                                    @change="toggleChannel('voice')"
                                    class="channel-checkbox"
                                />
                                <div class="channel-info">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                         stroke-linejoin="round">
                                        <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/>
                                    </svg>
                                    <span class="channel-name">Voice Call</span>
                                    <span v-if="!channels.voice" class="channel-not-configured">(Not configured)</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="form-actions">
                        <button
                            type="button"
                            class="btn-emergency"
                            :disabled="!canSubmit"
                            @click="sendBroadcast"
                        >
                            <template v-if="sending">
                                <span class="spinner"></span>
                                Sending Broadcast...
                            </template>
                            <template v-else>
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round">
                                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                                    <path d="M19.07 4.93a10 10 0 010 14.14"/>
                                    <path d="M15.54 8.46a5 5 0 010 7.07"/>
                                </svg>
                                Send Emergency Broadcast
                            </template>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
/* ── Page Container ────────────────────────────────────────────────── */
.emergency-page {
    max-width: 720px;
    margin: 0 auto;
    padding: 24px 16px;
}

/* ── Page Header ───────────────────────────────────────────────────── */
.emergency-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
}

.emergency-header-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: #fef2f2;
    color: #dc2626;
    flex-shrink: 0;
}

.emergency-header-title {
    font-size: 24px;
    font-weight: 700;
    color: #dc2626;
    margin: 0;
    line-height: 1.2;
}

.emergency-header-sub {
    font-size: 14px;
    color: #6b7280;
    margin: 4px 0 0;
}

/* ── Warning Banner ────────────────────────────────────────────────── */
.emergency-warning {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    background: #fef3c7;
    border: 2px solid #dc2626;
    border-radius: 8px;
    margin-bottom: 24px;
    font-size: 14px;
    color: #92400e;
    line-height: 1.5;
}

.emergency-warning-icon {
    flex-shrink: 0;
    color: #dc2626;
    margin-top: 1px;
}

/* ── Form Card ─────────────────────────────────────────────────────── */
.emergency-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.emergency-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.emergency-card-title {
    font-size: 16px;
    font-weight: 600;
    color: #111827;
    margin: 0;
}

.emergency-card-body {
    padding: 20px;
}

/* ── Form Fields ───────────────────────────────────────────────────── */
.form-field {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}

.required {
    color: #dc2626;
}

.form-textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    color: #111827;
    resize: vertical;
    transition: border-color 0.15s;
    box-sizing: border-box;
}

.form-textarea:focus {
    outline: none;
    border-color: #dc2626;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
}

.form-textarea-error {
    border-color: #dc2626;
}

.textarea-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 6px;
}

.char-counter {
    font-size: 12px;
    color: #9ca3af;
    margin-left: auto;
}

.char-counter-warn {
    color: #dc2626;
    font-weight: 600;
}

.form-error {
    font-size: 12px;
    color: #dc2626;
}

/* ── Template Quick-Fill ───────────────────────────────────────────── */
.template-row {
    display: flex;
    gap: 8px;
}

.form-select {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    color: #111827;
    background: #fff;
    font-family: inherit;
}

.form-select:focus {
    outline: none;
    border-color: #6b7280;
}

.btn-template-apply {
    padding: 8px 16px;
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    transition: background 0.15s;
}

.btn-template-apply:hover:not(:disabled) {
    background: #e5e7eb;
}

.btn-template-apply:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ── Channel Checkboxes ────────────────────────────────────────────── */
.channel-grid {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 4px;
}

.channel-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s;
    user-select: none;
}

.channel-option:hover:not(.channel-disabled) {
    border-color: #d1d5db;
    background: #f9fafb;
}

.channel-active {
    border-color: #dc2626;
    background: #fef2f2;
}

.channel-active:hover {
    background: #fef2f2;
}

.channel-disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f9fafb;
}

.channel-checkbox {
    accent-color: #dc2626;
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.channel-info {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #374151;
}

.channel-name {
    font-size: 14px;
    font-weight: 500;
}

.channel-not-configured {
    font-size: 12px;
    color: #9ca3af;
    font-style: italic;
}

/* ── Submit Button ─────────────────────────────────────────────────── */
.form-actions {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.btn-emergency {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 14px 24px;
    background: #dc2626;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s;
}

.btn-emergency:hover:not(:disabled) {
    background: #b91c1c;
}

.btn-emergency:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ── Spinner ───────────────────────────────────────────────────────── */
.spinner {
    display: inline-block;
    width: 18px;
    height: 18px;
    border: 2.5px solid rgba(255, 255, 255, 0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
