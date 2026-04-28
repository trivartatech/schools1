<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed, onUnmounted } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const toast = useToast();
const confirm = useConfirm();
const school = useSchoolStore();

const props = defineProps({
    announcements: { type: Object, required: true },
    classes:       { type: Array,  required: true },
    templates:     { type: Array,  default: () => [] },
});

// ── Recording state ──────────────────────────────────────────────────────────
const isRecording    = ref(false);
const audioBlob      = ref(null);
const audioUrl       = ref(null);
const audioInputMode = ref('record');
let mediaRecorder    = null;
// chunks is local to each startRecording call — no module-level leak
let recordingChunks  = [];

/** Revoke a blob URL and null it out to free memory */
const revokeAudioUrl = () => {
    if (audioUrl.value) {
        URL.revokeObjectURL(audioUrl.value);
        audioUrl.value = null;
    }
};

const startRecording = async () => {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        recordingChunks = [];

        mediaRecorder.ondataavailable = (e) => recordingChunks.push(e.data);
        mediaRecorder.onstop = () => {
            revokeAudioUrl(); // revoke any previous URL before creating new one
            audioBlob.value = new Blob(recordingChunks, { type: 'audio/webm' });
            audioUrl.value  = URL.createObjectURL(audioBlob.value);
        };

        mediaRecorder.start();
        isRecording.value = true;
    } catch (err) {
        toast.error('Microphone access denied or not available.');
    }
};

const stopRecording = () => {
    if (mediaRecorder && isRecording.value) {
        mediaRecorder.stop();
        isRecording.value = false;
        mediaRecorder.stream.getTracks().forEach(t => t.stop());
    }
};

const discardRecording = () => {
    revokeAudioUrl(); // free memory before clearing
    audioBlob.value = null;
};

const handleFileUpload = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    revokeAudioUrl(); // revoke previous URL before creating new one
    audioBlob.value = file;
    audioUrl.value  = URL.createObjectURL(file);
};

const switchMode = (mode) => {
    audioInputMode.value = mode;
    discardRecording();
};

// Cleanup on component unmount — free any lingering blob URL
onUnmounted(() => {
    revokeAudioUrl();
    if (mediaRecorder && isRecording.value) {
        mediaRecorder.stop();
        mediaRecorder.stream?.getTracks().forEach(t => t.stop());
    }
});

// ── Template refs ─────────────────────────────────────────────────────────────
const errorSummaryEl = ref(null);

// ── Form ─────────────────────────────────────────────────────────────────────
const form = useForm({
    title:                    '',
    delivery_method:          'voice',
    audio:                    null,
    audience_type:            'school',
    audience_ids:             [],
    communication_template_id: '',
    scheduled_at:             null
});

const isScheduling = ref(false);

const availableTemplates = computed(() => {
    return props.templates.filter(t => t.type === form.delivery_method);
});

const saveAnnouncement = () => {
    if (form.delivery_method === 'voice') {
        if (!audioBlob.value && !form.communication_template_id) {
            toast.warning('Please record/upload an audio or select a voice template first.');
            return;
        }
        if (audioBlob.value) {
            const isFile = audioBlob.value instanceof File;
            form.audio = isFile
                ? audioBlob.value
                : new File([audioBlob.value], 'recording.webm', { type: 'audio/webm' });
        } else {
            form.audio = null;
        }
    } else {
        form.audio = null;
    }

    form.post(route('school.communication.announcements.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('title', 'audio', 'audience_ids', 'communication_template_id', 'scheduled_at');
            discardRecording();
            isScheduling.value = false;
        },
        onError: () => {
            // Use template ref instead of document.querySelector — no race condition
            errorSummaryEl.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
};

const broadcastingId = ref(null);

const broadcast = async (id) => {
    const ok = await confirm({
        title: 'Broadcast announcement?',
        message: 'Start broadcasting this announcement now?',
        confirmLabel: 'Broadcast',
    });
    if (!ok) return;
    broadcastingId.value = id;
    router.post(route('school.communication.announcements.broadcast', id), {}, {
        onFinish: () => { broadcastingId.value = null; },
    });
};

const audienceLabel = (type, ids) => {
    if (type === 'school')    return 'All Students & Parents';
    if (type === 'employee')  return 'All Employees';
    if (!ids || ids.length === 0) return 'Selected ' + type + 's';
    return `${ids.length} ${type}(s)`;
};

const methodBadge = (method) => {
    const map = { voice: 'badge-blue', whatsapp: 'badge-green', sms: 'badge-amber' };
    return map[method] || 'badge-gray';
};

const selectedTemplateContent = computed(() => {
    if (!form.communication_template_id) return null;
    const tpl = props.templates.find(t => t.id === form.communication_template_id);
    return tpl ? tpl.content : null;
});
</script>

<template>
    <SchoolLayout title="Announcements">
        <PageHeader title="Announcements" subtitle="Create and broadcast voice, SMS, and WhatsApp announcements" />

        <div class="announce-layout">
            <!-- ── Create Panel ──────────────────────────────────────── -->
            <div class="announce-sidebar">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">New Announcement</h3>
                    </div>
                    <div class="card-body">

                        <!-- Delivery Method -->
                        <div class="form-field" style="margin-bottom:16px;">
                            <label>Delivery Method</label>
                            <div class="radio-group">
                                <label class="radio-item">
                                    <input type="radio" v-model="form.delivery_method" value="voice">
                                    <span>Voice</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" v-model="form.delivery_method" value="sms">
                                    <span>SMS</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" v-model="form.delivery_method" value="whatsapp">
                                    <span>WhatsApp</span>
                                </label>
                            </div>
                        </div>

                        <!-- ── Voice Audio Panel ─────────────────────── -->
                        <div v-if="form.delivery_method === 'voice'" class="audio-panel">
                            <!-- Mode Toggle -->
                            <div class="mode-toggle">
                                <button type="button" @click="switchMode('record')"
                                    :class="audioInputMode === 'record' ? 'mode-active' : ''">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                                    Record
                                </button>
                                <button type="button" @click="switchMode('upload')"
                                    :class="audioInputMode === 'upload' ? 'mode-active' : ''">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    Upload
                                </button>
                            </div>

                            <!-- Record Mode -->
                            <template v-if="audioInputMode === 'record'">
                                <div v-if="!isRecording && !audioUrl" class="audio-action">
                                    <button type="button" @click="startRecording" class="audio-btn audio-btn-record" aria-label="Start recording">
                                        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                                    </button>
                                    <p class="audio-hint">Tap to start recording</p>
                                </div>
                                <div v-if="isRecording" class="audio-action">
                                    <button type="button" @click="stopRecording" class="audio-btn audio-btn-stop" aria-label="Stop recording">
                                        <div style="width:20px;height:20px;background:#dc2626;border-radius:4px;" aria-hidden="true"></div>
                                    </button>
                                    <p class="audio-hint" style="color:#dc2626;">Recording... tap to stop</p>
                                </div>
                                <div v-if="audioUrl && !isRecording" class="audio-preview">
                                    <audio :src="audioUrl" controls style="width:100%;height:40px;"></audio>
                                    <button type="button" @click="discardRecording" class="audio-discard">Discard & Re-record</button>
                                </div>
                            </template>

                            <!-- Upload Mode -->
                            <template v-else>
                                <div v-if="!audioUrl" class="audio-action">
                                    <label class="audio-btn audio-btn-upload">
                                        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                        <input type="file" accept="audio/mp3,audio/mpeg,audio/wav,audio/ogg,audio/webm" style="display:none;" @change="handleFileUpload">
                                    </label>
                                    <p class="audio-hint">Click to upload MP3 / WAV / OGG</p>
                                    <p style="font-size:.68rem;color:var(--text-muted);">Max 5MB</p>
                                </div>
                                <div v-if="audioUrl" class="audio-preview">
                                    <audio :src="audioUrl" controls style="width:100%;height:40px;"></audio>
                                    <button type="button" @click="discardRecording" class="audio-discard">Remove & Re-upload</button>
                                </div>
                            </template>

                            <div v-if="form.errors.audio" class="form-error">{{ form.errors.audio }}</div>
                            <p class="audio-note">If no audio provided, the voice template text will be spoken (TTS).</p>
                        </div>

                        <!-- Template Selection -->
                        <div v-if="form.delivery_method === 'sms' || form.delivery_method === 'whatsapp' || form.delivery_method === 'voice'" style="margin-top:16px;">
                            <div class="form-field">
                                <label>Select {{ form.delivery_method === 'sms' ? 'SMS' : (form.delivery_method === 'whatsapp' ? 'WhatsApp' : 'Voice') }} Template
                                    <span style="color:var(--text-muted);font-weight:400;">(optional for voice if audio uploaded)</span>
                                </label>
                                <select v-model="form.communication_template_id">
                                    <option value="">-- Choose Template --</option>
                                    <option v-for="tpl in availableTemplates" :key="tpl.id" :value="tpl.id">{{ tpl.name }}</option>
                                </select>
                                <div v-if="form.errors.communication_template_id" class="form-error">{{ form.errors.communication_template_id }}</div>
                            </div>

                            <div v-if="selectedTemplateContent" class="template-preview">
                                "{{ selectedTemplateContent }}"
                            </div>
                            <div v-else-if="availableTemplates.length === 0 && form.delivery_method !== 'voice'" style="font-size:.78rem;color:var(--warning);margin-top:8px;">
                                No custom templates available for this method.
                            </div>
                        </div>

                        <div style="padding-top:16px;margin-top:16px;border-top:1px solid var(--border);">
                            <div class="form-field" style="margin-bottom:14px;">
                                <label>Internal Title / Reference</label>
                                <input type="text" v-model="form.title" placeholder="e.g., Emergency Holiday Notice">
                                <div v-if="form.errors.title" class="form-error">{{ form.errors.title }}</div>
                            </div>

                            <div class="form-field" style="margin-bottom:14px;">
                                <label>Target Audience</label>
                                <select v-model="form.audience_type" @change="form.audience_ids = []">
                                    <option value="school">All Students & Parents</option>
                                    <option value="employee">All Employees</option>
                                    <option value="class">Specific Classes</option>
                                    <option value="section">Specific Sections</option>
                                </select>
                            </div>

                            <div v-if="form.audience_type === 'class' || form.audience_type === 'section'" class="form-field" style="margin-bottom:14px;">
                                <label>{{ form.audience_type === 'class' ? 'Select Classes' : 'Select Sections' }}</label>
                                <div class="checkbox-grid">
                                    <template v-if="form.audience_type === 'class'">
                                        <label v-for="c in classes" :key="c.id" class="checkbox-item">
                                            <input type="checkbox" :value="c.id" v-model="form.audience_ids">
                                            <span>{{ c.name }}</span>
                                        </label>
                                    </template>
                                    <template v-else>
                                        <template v-for="c in classes" :key="c.id">
                                            <label v-for="s in c.sections" :key="s.id" class="checkbox-item">
                                                <input type="checkbox" :value="s.id" v-model="form.audience_ids">
                                                <span>{{ c.name }} - {{ s.name }}</span>
                                            </label>
                                        </template>
                                    </template>
                                </div>
                                <div v-if="form.errors.audience_ids" class="form-error">{{ form.errors.audience_ids }}</div>
                            </div>

                            <div style="margin-bottom:14px;">
                                <label class="checkbox-item" style="cursor:pointer;">
                                    <input type="checkbox" v-model="isScheduling">
                                    <span style="font-weight:500;">Schedule for later</span>
                                </label>
                                <div v-if="isScheduling" class="form-field" style="margin-top:8px;">
                                    <label>Broadcast Date & Time</label>
                                    <input type="datetime-local" v-model="form.scheduled_at">
                                    <div v-if="form.errors.scheduled_at" class="form-error">{{ form.errors.scheduled_at }}</div>
                                </div>
                            </div>

                            <div v-if="form.hasErrors" ref="errorSummaryEl" class="error-box">
                                <p style="font-weight:700;margin-bottom:4px;">Please fix the following errors:</p>
                                <ul style="margin:0;padding-left:16px;">
                                    <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                                </ul>
                            </div>

                            <Button variant="success" @click="saveAnnouncement" :loading="form.processing" block>
                                Save Announcement
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Broadcast History ──────────────────────────────────── -->
            <div class="announce-main">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Broadcast History</h3>
                    </div>
                    <div style="overflow-x:auto;">
                        <Table>
                            <thead>
                                <tr>
                                    <th>Title & Details</th>
                                    <th>Target</th>
                                    <th>Content</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in announcements.data" :key="item.id">
                                    <td>
                                        <div style="font-weight:700;color:var(--text-primary);">{{ item.title }}</div>
                                        <div style="display:flex;align-items:center;gap:8px;margin-top:4px;flex-wrap:wrap;">
                                            <span class="badge" :class="methodBadge(item.delivery_method)" style="text-transform:uppercase;">
                                                {{ item.delivery_method }}
                                            </span>
                                            <span style="font-size:.72rem;color:var(--text-muted);">{{ school.fmtDateTime(item.created_at) }}</span>
                                            <div v-if="item.scheduled_at && !item.is_broadcasted" style="font-size:.72rem;color:var(--accent);font-weight:600;">
                                                Scheduled: {{ school.fmtDateTime(item.scheduled_at) }}
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-gray" style="text-transform:uppercase;">{{ audienceLabel(item.audience_type, item.audience_ids) }}</span>
                                    </td>
                                    <td>
                                        <audio v-if="item.delivery_method === 'voice' && (item.mp3_path || item.audio_path)" :src="item.mp3_path ? '/storage/' + item.mp3_path : '/storage/' + item.audio_path" controls style="height:32px;max-width:150px;"></audio>
                                        <div v-else-if="item.template" style="font-size:.78rem;color:var(--text-secondary);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" :title="item.template.name">
                                            Tpl: {{ item.template.name }}
                                        </div>
                                        <span v-else style="color:var(--text-muted);font-size:.78rem;">-</span>
                                    </td>
                                    <td>
                                        <div v-if="item.is_broadcasted && item.broadcast_error">
                                            <span class="badge badge-red">Broadcast Failed</span>
                                            <div class="error-detail" :title="'Detailed Trace:\n' + (item.broadcast_error.trace || '')">
                                                {{ item.broadcast_error.message }}
                                            </div>
                                            <div v-if="item.failed_at" style="font-size:.68rem;color:var(--text-muted);font-style:italic;margin-top:4px;">
                                                Occurred: {{ school.fmtDateTime(item.failed_at) }}
                                            </div>
                                        </div>
                                        <span v-else-if="item.is_broadcasted" class="badge badge-green">Sent successfully</span>
                                        <span v-else class="badge badge-amber">Draft / Pending</span>
                                    </td>
                                    <td>
                                        <Button size="sm" v-if="!item.is_broadcasted" @click="broadcast(item.id)" :loading="broadcastingId === item.id">
                                            Broadcast
                                        </Button>
                                        <span v-else style="font-size:.72rem;font-weight:600;color:var(--text-muted);">Broadcasted</span>
                                    </td>
                                </tr>
                                <tr v-if="announcements.data.length === 0">
                                    <td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted);font-style:italic;">No announcements found.</td>
                                </tr>
                            </tbody>
                        </Table>
                    </div>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.announce-layout { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
@media (max-width: 1024px) { .announce-layout { grid-template-columns: 1fr; } }

.radio-group { display: flex; gap: 16px; margin-top: 6px; }
.radio-item {
    display: flex; align-items: center; gap: 6px; cursor: pointer;
    font-size: .84rem; font-weight: 500; color: var(--text-secondary);
}

.audio-panel {
    background: #f8fafc; border: 2px dashed var(--border); border-radius: var(--radius);
    padding: 16px; margin-top: 16px;
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
.mode-toggle button.mode-active { background: var(--accent); color: #fff; }

.audio-action {
    display: flex; flex-direction: column; align-items: center;
    padding: 16px 0; gap: 10px;
}
.audio-btn {
    width: 56px; height: 56px; border-radius: 50%; display: flex;
    align-items: center; justify-content: center; cursor: pointer;
    transition: all .15s; box-shadow: 0 2px 8px rgba(0,0,0,.08);
}
.audio-btn-record { background: #dbeafe; color: #2563eb; }
.audio-btn-record:hover { background: #bfdbfe; transform: scale(1.05); }
.audio-btn-stop { background: #fee2e2; color: #dc2626; animation: pulse 1.5s infinite; }
.audio-btn-upload { background: #e0e7ff; color: #4f46e5; }
.audio-btn-upload:hover { background: #c7d2fe; transform: scale(1.05); }
.audio-hint { font-size: .78rem; font-weight: 600; color: var(--text-secondary); }
.audio-preview { margin-top: 12px; }
.audio-discard {
    display: block; margin: 8px auto 0; background: none; border: none; cursor: pointer;
    font-size: .72rem; font-weight: 600; color: var(--text-muted);
    transition: color .15s; text-transform: uppercase;
}
.audio-discard:hover { color: var(--danger); }
.audio-note {
    font-size: .72rem; color: var(--text-muted); text-align: center;
    margin-top: 10px; line-height: 1.5;
}

.template-preview {
    margin-top: 8px; padding: 10px 14px; background: #f8fafc; border-radius: var(--radius);
    font-size: .84rem; color: var(--text-secondary); font-style: italic; border: 1px solid var(--border);
}

.checkbox-grid {
    display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px;
    max-height: 160px; overflow-y: auto; padding: 8px;
    background: #fff; border: 1px solid var(--border); border-radius: var(--radius); margin-top: 6px;
}
.checkbox-item {
    display: flex; align-items: center; gap: 6px; padding: 4px;
    border-radius: 4px; cursor: pointer; font-size: .78rem; font-weight: 500;
}
.checkbox-item:hover { background: #f8fafc; }

.error-box {
    background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius);
    padding: 10px 14px; font-size: .78rem; color: #dc2626; margin-bottom: 14px;
}
.error-detail {
    font-size: .72rem; font-weight: 600; color: #dc2626; background: #fef2f2;
    padding: 6px 8px; border-radius: 6px; border: 1px solid #fecaca;
    max-width: 180px; line-height: 1.3; margin-top: 4px; cursor: help;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .6; }
}
</style>
