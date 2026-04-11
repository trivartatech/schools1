<script setup>
import { ref, onMounted, computed } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import QRCode from 'qrcode';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    school:       Object,
    schoolUrl:    String,
    deepLink:     String,
    httpsLink:    String,
    appStoreUrl:  String,
    playStoreUrl: String,
});

// ── State ─────────────────────────────────────────────────────────────────────
const deepLinkCanvas  = ref(null);
const httpsCanvas     = ref(null);
const schoolUrlCanvas = ref(null);
const activeTab       = ref('deeplink'); // deeplink | https | url
const qrSize          = ref(280);
const copied          = ref('');

// ── QR Options ────────────────────────────────────────────────────────────────
const qrOptions = computed(() => ({
    width:           qrSize.value,
    margin:          2,
    color: {
        dark:  primaryHex.value,
        light: '#FFFFFF',
    },
    errorCorrectionLevel: 'M',
}));

const primaryHex = computed(() => {
    const color = props.school?.settings?.primary_color ?? '#1A73E8';
    return color.startsWith('#') ? color : '#1A73E8';
});

// ── Generate all QR codes ─────────────────────────────────────────────────────
async function generateAll() {
    if (deepLinkCanvas.value) {
        await QRCode.toCanvas(deepLinkCanvas.value, props.deepLink, qrOptions.value);
    }
    if (httpsCanvas.value) {
        await QRCode.toCanvas(httpsCanvas.value, props.httpsLink, qrOptions.value);
    }
    if (schoolUrlCanvas.value) {
        await QRCode.toCanvas(schoolUrlCanvas.value, props.schoolUrl, qrOptions.value);
    }
}

onMounted(generateAll);

// ── Download QR ───────────────────────────────────────────────────────────────
function downloadQr(type) {
    const canvasMap = {
        deeplink: deepLinkCanvas,
        https:    httpsCanvas,
        url:      schoolUrlCanvas,
    };
    const canvas = canvasMap[type]?.value;
    if (!canvas) return;

    const link = document.createElement('a');
    link.download = `${props.school?.slug ?? 'school'}-qr-${type}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
}

// ── Print ─────────────────────────────────────────────────────────────────────
function printQr(type) {
    const canvasMap = {
        deeplink: deepLinkCanvas,
        https:    httpsCanvas,
        url:      schoolUrlCanvas,
    };
    const canvas = canvasMap[type]?.value;
    if (!canvas) return;

    const dataUrl = canvas.toDataURL('image/png');
    const win = window.open('', '_blank');
    win.document.write(`
        <!DOCTYPE html><html>
        <head>
            <title>EduConnect QR Code – ${props.school?.name}</title>
            <style>
                * { margin:0; padding:0; box-sizing:border-box; }
                body { display:flex; justify-content:center; align-items:flex-start;
                       padding:40px; font-family: 'Segoe UI', sans-serif; }
                .card { text-align:center; max-width:400px; }
                .logo { font-size:22px; font-weight:700; color:#0f172a; margin-bottom:4px; }
                .sub  { font-size:13px; color:#64748b; margin-bottom:24px; }
                img   { width:260px; height:260px; border:1px solid #e2e8f0;
                        border-radius:12px; padding:12px; display:block; margin:0 auto 20px; }
                .url  { font-size:12px; color:#64748b; word-break:break-all;
                        background:#f8fafc; padding:8px 12px; border-radius:8px; }
                .steps { margin-top:20px; text-align:left; }
                .steps li { font-size:12px; color:#374151; margin-bottom:6px; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="logo">📱 EduConnect</div>
                <div class="sub">${props.school?.name} — Mobile App</div>
                <img src="${dataUrl}" />
                <div class="url">Scan to connect your school</div>
                <ol class="steps">
                    <li>Download <strong>EduConnect</strong> from Play Store / App Store</li>
                    <li>Tap "Scan QR Code" on the welcome screen</li>
                    <li>Point camera at this QR code</li>
                    <li>Login with your school credentials</li>
                </ol>
            </div>
        </body></html>
    `);
    win.document.close();
    setTimeout(() => win.print(), 500);
}

// ── Copy ──────────────────────────────────────────────────────────────────────
async function copyText(text, key) {
    await navigator.clipboard.writeText(text);
    copied.value = key;
    setTimeout(() => { copied.value = ''; }, 2000);
}

// ── Active tab helper ─────────────────────────────────────────────────────────
const activeTabData = computed(() => tabs.find(t => t.id === activeTab.value));

// ── Tab payloads ──────────────────────────────────────────────────────────────
const tabs = [
    {
        id:      'deeplink',
        label:   'App Deep Link',
        desc:    'Best for sharing — opens EduConnect app directly if installed',
        badge:   'Recommended',
        payload: computed(() => props.deepLink),
    },
    {
        id:      'https',
        label:   'Web / Universal Link',
        desc:    'Works on any device — opens browser if app not installed',
        badge:   null,
        payload: computed(() => props.httpsLink),
    },
    {
        id:      'url',
        label:   'School URL Only',
        desc:    'Minimal QR — user enters this URL manually in the app',
        badge:   null,
        payload: computed(() => props.schoolUrl),
    },
];
</script>

<template>
    <SchoolLayout title="Mobile App QR Code">
        <div class="max-w-5xl space-y-6">

            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Mobile App QR Code</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Generate QR codes so parents, students and staff can instantly connect to
                        <strong>{{ school?.name }}</strong> in the EduConnect mobile app.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a :href="playStoreUrl" target="_blank"
                       class="flex items-center gap-2 text-xs bg-gray-900 text-white px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                        <span>🤖</span> Play Store
                    </a>
                    <a :href="appStoreUrl" target="_blank"
                       class="flex items-center gap-2 text-xs bg-gray-900 text-white px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                        <span>🍎</span> App Store
                    </a>
                </div>
            </div>

            <!-- Tab selector -->
            <div class="border-b border-gray-200">
                <nav class="flex gap-1">
                    <button v-for="tab in tabs" :key="tab.id"
                        @click="activeTab = tab.id"
                        :class="[
                            'px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition',
                            activeTab === tab.id
                                ? 'border-blue-600 text-blue-700 bg-blue-50'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        ]">
                        {{ tab.label }}
                        <span v-if="tab.badge"
                            class="ml-1.5 text-xs bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full font-semibold">
                            {{ tab.badge }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- Main panel -->
            <div v-if="activeTabData">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- QR Card -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 flex flex-col items-center gap-4">
                        <div class="text-sm text-gray-500">{{ activeTabData.desc }}</div>

                        <!-- Canvas — each rendered once, toggled by activeTab -->
                        <div class="p-4 bg-white rounded-xl border-2 border-dashed border-gray-200">
                            <canvas ref="deepLinkCanvas"  v-show="activeTab === 'deeplink'" class="rounded-lg" />
                            <canvas ref="httpsCanvas"     v-show="activeTab === 'https'"    class="rounded-lg" />
                            <canvas ref="schoolUrlCanvas"  v-show="activeTab === 'url'"      class="rounded-lg" />
                        </div>

                        <!-- School badge overlay hint -->
                        <div class="text-center">
                            <p class="text-xs font-semibold text-gray-700">{{ school?.name }}</p>
                            <p class="text-xs text-gray-400">EduConnect Mobile App</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2 w-full">
                            <Button @click="downloadQr(activeTab)"
                               >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Download PNG
                            </Button>
                            <button @click="printQr(activeTab)"
                                class="flex-1 flex items-center justify-center gap-2 text-sm font-medium
                                       border border-gray-300 text-gray-700 px-4 py-2.5 rounded-xl hover:bg-gray-50 transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                </svg>
                                Print
                            </button>
                        </div>
                    </div>

                    <!-- Info panel -->
                    <div class="space-y-4">

                        <!-- Payload box -->
                        <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">QR Payload</p>
                            <div class="flex items-start gap-2">
                                <code class="flex-1 text-xs text-gray-800 break-all font-mono leading-relaxed">
                                    {{ activeTabData.payload.value }}
                                </code>
                                <button @click="copyText(activeTabData.payload.value, activeTab)"
                                    class="flex-shrink-0 text-xs px-3 py-1.5 rounded-lg border transition"
                                    :class="copied === activeTab
                                        ? 'bg-emerald-50 border-emerald-300 text-emerald-700'
                                        : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-100'">
                                    {{ copied === activeTab ? '✓ Copied' : 'Copy' }}
                                </button>
                            </div>
                        </div>

                        <!-- How it works -->
                        <div class="bg-blue-50 rounded-xl border border-blue-100 p-4">
                            <p class="text-xs font-bold text-blue-800 mb-3 flex items-center gap-1.5">
                                <span>📱</span> How parents & students use this
                            </p>
                            <ol class="space-y-2.5">
                                <li class="flex items-start gap-2 text-xs text-blue-700">
                                    <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-200 text-blue-800 font-bold flex items-center justify-center text-xs">1</span>
                                    Download <strong>EduConnect</strong> from Play Store or App Store
                                </li>
                                <li class="flex items-start gap-2 text-xs text-blue-700">
                                    <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-200 text-blue-800 font-bold flex items-center justify-center text-xs">2</span>
                                    Tap <strong>"Scan QR Code"</strong> on the welcome screen
                                </li>
                                <li class="flex items-start gap-2 text-xs text-blue-700">
                                    <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-200 text-blue-800 font-bold flex items-center justify-center text-xs">3</span>
                                    Point the camera at the printed QR code
                                </li>
                                <li class="flex items-start gap-2 text-xs text-blue-700">
                                    <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-200 text-blue-800 font-bold flex items-center justify-center text-xs">4</span>
                                    Enter their login credentials to access their portal
                                </li>
                            </ol>
                        </div>

                        <!-- Sharing tips -->
                        <div class="bg-amber-50 rounded-xl border border-amber-100 p-4">
                            <p class="text-xs font-bold text-amber-800 mb-2 flex items-center gap-1.5">
                                <span>💡</span> Distribution tips
                            </p>
                            <ul class="space-y-1.5 text-xs text-amber-700">
                                <li>• <strong>Print & paste</strong> on the school notice board</li>
                                <li>• <strong>Add to circulars</strong> sent home at term start</li>
                                <li>• <strong>WhatsApp broadcast</strong> the PNG to parent groups</li>
                                <li>• <strong>Add to admission kit</strong> for new student enrolments</li>
                            </ul>
                        </div>

                        <!-- School URL display -->
                        <div class="bg-white rounded-xl border border-gray-200 p-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">School URL</p>
                            <div class="flex items-center gap-2">
                                <span class="flex-1 text-sm font-mono text-gray-800 truncate">{{ schoolUrl }}</span>
                                <button @click="copyText(schoolUrl, 'schoolurl')"
                                    class="text-xs px-3 py-1.5 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 transition"
                                    :class="copied === 'schoolurl' ? 'bg-emerald-50 border-emerald-300 text-emerald-700' : ''">
                                    {{ copied === 'schoolurl' ? '✓' : 'Copy' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Print poster template hint -->
            <div class="bg-gray-50 rounded-xl border border-dashed border-gray-300 p-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center text-xl flex-shrink-0">🖨️</div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Need a printable poster?</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Click <strong>Print</strong> on any QR above to open a print-ready card with step-by-step instructions for parents.
                            Ideal for pinning on the notice board or including in the admission pack.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </SchoolLayout>
</template>
