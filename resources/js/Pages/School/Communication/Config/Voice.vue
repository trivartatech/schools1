<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';

const toast = useToast();
const confirm = useConfirm();

const props = defineProps({
    config: Object
});

const form = useForm({
    provider:              props.config.provider || 'exotel',
    api_sid:              props.config.api_sid || '',
    api_key:              props.config.api_key || '',
    api_token:            props.config.api_token || '',
    subdomain:            props.config.subdomain || '',
    caller_id:            props.config.caller_id || '',
    number_prefix:        props.config.number_prefix || '91',
    test_number:          props.config.test_number || '',
    intro_audio:          null,
    intro_audio_path:     props.config.intro_audio_path || null,
    delete_intro_audio:   false,
    app_id:               props.config.app_id || '',
});

// ── Intro Audio Recording ─────────────────────────────────────────────────
const introAudioMode      = ref('upload');
const isIntroRecording    = ref(false);
const introAudioPreview   = ref(null);
let   introMediaRecorder  = null;
let   introChunks         = [];

const startIntroRecording = async () => {
    try {
        const stream  = await navigator.mediaDevices.getUserMedia({ audio: true });
        introMediaRecorder = new MediaRecorder(stream);
        introChunks   = [];

        introMediaRecorder.ondataavailable = (e) => introChunks.push(e.data);
        introMediaRecorder.onstop = () => {
            const blob         = new Blob(introChunks, { type: 'audio/webm' });
            form.intro_audio   = new File([blob], 'intro_recording.webm', { type: 'audio/webm' });
            introAudioPreview.value = URL.createObjectURL(blob);
        };

        introMediaRecorder.start();
        isIntroRecording.value = true;
    } catch {
        toast.error('Microphone access denied or not available.');
    }
};

const stopIntroRecording = () => {
    if (introMediaRecorder && isIntroRecording.value) {
        introMediaRecorder.stop();
        introMediaRecorder.stream.getTracks().forEach(t => t.stop());
        isIntroRecording.value = false;
    }
};

const handleIntroFileUpload = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    form.intro_audio      = file;
    introAudioPreview.value = URL.createObjectURL(file);
};

const discardIntroAudio = () => {
    form.intro_audio      = null;
    introAudioPreview.value = null;
};

const switchIntroMode = (mode) => {
    introAudioMode.value = mode;
    discardIntroAudio();
};

const deleteIntroAudio = async () => {
    const ok = await confirm({
        title: 'Delete intro audio?',
        message: 'Delete the saved intro audio? This cannot be undone.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    form.intro_audio_path    = null;
    form.delete_intro_audio  = true;
    form.intro_audio         = null;
    introAudioPreview.value  = null;
};

// ── Form Submission ───────────────────────────────────────────────────────
const submit = () => {
    if (!form.api_sid || !form.api_key || !form.api_token || !form.caller_id || !form.app_id) {
        toast.warning('Please fill in all required Exotel configuration fields.');
        return;
    }
    form.post(route('school.communication.config.voice.update'), {
        forceFormData: true
    });
};

const sendTest = () => {
    if (!form.test_number) {
        toast.warning('Please enter a test recipient number first.');
        return;
    }
    form.post(route('school.communication.config.voice.send-test'), {
        preserveScroll: true,
        onSuccess: () => toast.success('Test call triggered successfully!')
    });
};
</script>

<template>
    <SchoolLayout title="Voice Config">
        <PageHeader title="Voice Configuration" subtitle="Configure Voice Gateway for automated calls and intro messages">
            <template #actions>
                <Button variant="secondary" @click="sendTest">Trigger Test Call</Button>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" class="config-layout">
            <!-- Main Config -->
            <div class="config-main">
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <h3 class="card-title">Voice Gateway Settings</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Voice Provider</label>
                                <div class="provider-badge" style="color:#7c3aed;border-color:#ddd6fe;background:#f5f3ff;">
                                    <span class="provider-dot" style="background:#7c3aed;"></span>
                                    Exotel (Legacy/New)
                                </div>
                            </div>
                            <div class="form-field">
                                <label>Caller ID (Virtual Number)</label>
                                <input type="text" v-model="form.caller_id" placeholder="e.g. 08047XXXXXX">
                                <div v-if="form.errors.caller_id" class="form-error">{{ form.errors.caller_id }}</div>
                            </div>
                        </div>
                        <div class="form-row-2" style="margin-top:14px;">
                            <div class="form-field">
                                <label>Exotel Subdomain</label>
                                <input type="text" v-model="form.subdomain" placeholder="e.g. api.exotel.com">
                                <p style="font-size:.72rem;color:var(--text-muted);margin-top:4px;">Required for legacy Exotel account structures</p>
                            </div>
                            <div class="form-field">
                                <label>Exotel App ID</label>
                                <input type="text" v-model="form.app_id" placeholder="e.g. 1203048">
                                <p style="font-size:.72rem;color:var(--text-muted);margin-top:4px;">Your Exotel Flow/App ID for outbound calls</p>
                                <div v-if="form.errors.app_id" class="form-error">{{ form.errors.app_id }}</div>
                            </div>
                        </div>
                        <div class="form-row-2" style="margin-top:14px;">
                            <div class="form-field">
                                <label>Country Code Prefix</label>
                                <input type="text" v-model="form.number_prefix" placeholder="e.g. 91 for India">
                                <p style="font-size:.72rem;color:var(--text-muted);margin-top:4px;">Used to E.164-format outbound numbers</p>
                            </div>
                            <div class="form-field">
                                <label>Test Recipient Number</label>
                                <input type="text" v-model="form.test_number" placeholder="Enter phone number to receive test call">
                            </div>
                        </div>

                        <!-- Global Voice Intro -->
                        <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border);">
                            <div class="form-field">
                                <label>Global Voice Intro (Optional)</label>
                                <p style="font-size:.72rem;color:var(--text-muted);margin-top:2px;margin-bottom:12px;">Played before every voice announcement. Upload an MP3/WAV or record directly.</p>

                                <!-- Existing saved intro -->
                                <div v-if="form.intro_audio_path && !introAudioPreview" class="intro-existing">
                                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                                        <div class="intro-icon">
                                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                                        </div>
                                        <div style="flex:1;min-width:0;">
                                            <p style="font-weight:700;font-size:.78rem;color:#7c3aed;">Current Intro Audio</p>
                                            <p style="font-size:.72rem;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ form.intro_audio_path }}</p>
                                        </div>
                                    </div>
                                    <audio :src="'/storage/' + form.intro_audio_path" controls style="width:100%;height:36px;margin-bottom:10px;"></audio>
                                    <div style="display:flex;gap:8px;">
                                        <Button variant="secondary" size="xs" type="button" @click="switchIntroMode('upload'); form.intro_audio_path = null; form.delete_intro_audio = false" class="flex-1">Replace</Button>
                                        <Button variant="danger" size="xs" type="button" @click="deleteIntroAudio" class="flex-1">Delete</Button>
                                    </div>
                                </div>

                                <!-- Mode Toggle -->
                                <div v-if="!form.intro_audio_path || introAudioPreview" class="mode-toggle" style="margin-bottom:12px;">
                                    <button type="button" @click="switchIntroMode('record')"
                                        :class="introAudioMode === 'record' ? 'mode-active' : ''">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                                        Record
                                    </button>
                                    <button type="button" @click="switchIntroMode('upload')"
                                        :class="introAudioMode === 'upload' ? 'mode-active' : ''">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                        Upload File
                                    </button>
                                </div>

                                <!-- Record Mode -->
                                <div v-if="(!form.intro_audio_path || introAudioPreview) && introAudioMode === 'record'" class="audio-panel">
                                    <div v-if="!isIntroRecording && !introAudioPreview" class="audio-action">
                                        <div @click="startIntroRecording" class="audio-btn audio-btn-record">
                                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                                        </div>
                                        <p class="audio-hint">Tap to record intro audio</p>
                                    </div>
                                    <div v-if="isIntroRecording" class="audio-action">
                                        <div @click="stopIntroRecording" class="audio-btn audio-btn-stop">
                                            <div style="width:16px;height:16px;background:#dc2626;border-radius:3px;"></div>
                                        </div>
                                        <p class="audio-hint" style="color:#dc2626;">Recording... tap to stop</p>
                                    </div>
                                    <div v-if="introAudioPreview && !isIntroRecording">
                                        <audio :src="introAudioPreview" controls style="width:100%;height:36px;"></audio>
                                        <button type="button" @click="discardIntroAudio" class="audio-discard">Discard & Re-record</button>
                                    </div>
                                </div>

                                <!-- Upload Mode -->
                                <div v-if="(!form.intro_audio_path || introAudioPreview) && introAudioMode === 'upload'" class="audio-panel">
                                    <div v-if="!introAudioPreview" class="audio-action">
                                        <label class="audio-btn audio-btn-upload">
                                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                            <input type="file" accept="audio/*" style="display:none;" @change="handleIntroFileUpload">
                                        </label>
                                        <p class="audio-hint">Upload MP3 / WAV / OGG</p>
                                    </div>
                                    <div v-if="introAudioPreview">
                                        <audio :src="introAudioPreview" controls style="width:100%;height:36px;"></audio>
                                        <button type="button" @click="discardIntroAudio" class="audio-discard">Remove & Re-upload</button>
                                    </div>
                                </div>

                                <div v-if="form.errors.intro_audio" class="form-error" style="margin-top:8px;">{{ form.errors.intro_audio }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Provider Specifics -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Exotel API Authentication</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-field" style="margin-bottom:14px;">
                            <label>Account SID</label>
                            <input type="text" v-model="form.api_sid" placeholder="Enter Exotel Account SID (e.g. trivartatech1)">
                            <div v-if="form.errors.api_sid" class="form-error">{{ form.errors.api_sid }}</div>
                        </div>
                        <div class="form-field" style="margin-bottom:14px;">
                            <label>API Key</label>
                            <input type="password" v-model="form.api_key" placeholder="Enter Exotel API Key">
                            <div v-if="form.errors.api_key" class="form-error">{{ form.errors.api_key }}</div>
                        </div>
                        <div class="form-field">
                            <label>API Token</label>
                            <input type="password" v-model="form.api_token" placeholder="Enter Exotel API Token">
                            <div v-if="form.errors.api_token" class="form-error">{{ form.errors.api_token }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column / Summary -->
            <div class="config-sidebar">
                <div class="card config-status">
                    <div class="card-body">
                        <h4 class="status-heading">Configuration Status</h4>
                        <div class="status-rows">
                            <div class="status-row">
                                <span>Voice Service</span>
                                <span class="status-dot"></span>
                            </div>
                            <div class="status-row">
                                <span>API Validation</span>
                                <span class="badge" style="color:rgba(255,255,255,.8);background:rgba(255,255,255,.15);">Active</span>
                            </div>
                        </div>
                        <div style="margin-top:24px;">
                            <Button type="submit" :loading="form.processing" block>
                                Save Configuration
                            </Button>
                            <Button variant="secondary" type="button" @click="form.reset()" block class="mt-2">
                                Reset Changes
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="info-tip">
                    <h5 class="info-tip-title">Voice Setup Tip</h5>
                    <p class="info-tip-text">Ensure your virtual number (Caller ID) is active in your Exotel dashboard. Voice calls will use the Text-to-Speech engine specified in templates.</p>
                </div>
            </div>
        </form>
    </SchoolLayout>
</template>

<style scoped>
.config-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; }
@media (max-width: 1024px) { .config-layout { grid-template-columns: 1fr; } }

.provider-badge {
    font-size: .82rem; font-weight: 700; padding: 10px 14px; border-radius: var(--radius);
    border: 1px solid; display: flex; align-items: center; gap: 8px; text-transform: uppercase;
    letter-spacing: .03em;
}
.provider-dot { width: 6px; height: 6px; border-radius: 50%; }

.intro-existing {
    padding: 14px; background: #f5f3ff; border-radius: var(--radius);
    border: 1px solid #ddd6fe; margin-bottom: 12px;
}
.intro-icon {
    width: 32px; height: 32px; background: #ede9fe; color: #7c3aed;
    border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}

.mode-toggle {
    display: flex; border: 1px solid var(--border); border-radius: var(--radius);
    overflow: hidden; background: #fff;
}
.mode-toggle button {
    flex: 1; padding: 8px; font-size: .72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .04em; background: transparent; border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    color: var(--text-muted); transition: all .15s;
}
.mode-toggle button.mode-active { background: #7c3aed; color: #fff; }

.audio-panel {
    background: #f8fafc; border: 2px dashed var(--border); border-radius: var(--radius); padding: 16px;
}
.audio-action {
    display: flex; flex-direction: column; align-items: center; padding: 12px 0; gap: 8px;
}
.audio-btn {
    width: 48px; height: 48px; border-radius: 50%; display: flex;
    align-items: center; justify-content: center; cursor: pointer;
    transition: all .15s; box-shadow: 0 2px 8px rgba(0,0,0,.08);
}
.audio-btn-record { background: #ede9fe; color: #7c3aed; }
.audio-btn-record:hover { background: #ddd6fe; transform: scale(1.05); }
.audio-btn-stop { background: #fee2e2; color: #dc2626; animation: pulse 1.5s infinite; }
.audio-btn-upload { background: #e0e7ff; color: #4f46e5; }
.audio-btn-upload:hover { background: #c7d2fe; transform: scale(1.05); }
.audio-hint { font-size: .78rem; font-weight: 600; color: var(--text-secondary); }
.audio-discard {
    display: block; margin: 8px auto 0; background: none; border: none; cursor: pointer;
    font-size: .72rem; font-weight: 600; color: var(--text-muted);
    transition: color .15s; text-transform: uppercase;
}
.audio-discard:hover { color: var(--danger); }

.config-status { background: #7c3aed; color: #fff; }
.config-status .card-body { padding: 24px; }
.status-heading { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; opacity: .7; margin-bottom: 16px; }
.status-rows { display: flex; flex-direction: column; gap: 0; }
.status-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,.15);
    font-size: .78rem; font-weight: 500;
}
.status-dot {
    width: 8px; height: 8px; border-radius: 50%; background: #86efac;
    box-shadow: 0 0 6px rgba(134,239,172,.5);
}
.config-status :deep(.ui-btn--primary) { background: #fff; color: #7c3aed; border-color: #fff; }
.config-status :deep(.ui-btn--primary:hover) { background: #f5f3ff; border-color: #f5f3ff; }
.config-status :deep(.ui-btn--secondary) { background: transparent; color: rgba(255,255,255,.7); border-color: rgba(255,255,255,.2); }
.config-status :deep(.ui-btn--secondary:hover) { color: #fff; border-color: rgba(255,255,255,.4); background: transparent; }

.info-tip {
    padding: 16px 20px; background: #f5f3ff; border-radius: var(--radius);
    border: 1px solid #ddd6fe; margin-top: 16px;
}
.info-tip-title { font-size: .78rem; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; }
.info-tip-text { font-size: .78rem; color: var(--text-secondary); line-height: 1.5; }

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .6; }
}
</style>
