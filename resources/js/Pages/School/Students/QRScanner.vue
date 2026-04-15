<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { Html5Qrcode } from 'html5-qrcode';
import axios from 'axios';

// ── state ────────────────────────────────────────────────────────────────────
const html5QrCode    = ref(null);
const scannerActive  = ref(false);
const showFlash      = ref(false);
const isLoading      = ref(false);
const lastScanned    = ref(null);          // raw QR text — debounce key
const errorMessage   = ref('');
const lastStudent    = ref(null);          // last successful resolution (preview before redirect)

// ── audio cue ────────────────────────────────────────────────────────────────
let audioCtx = null;
const playBeep = () => {
    try {
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioCtx.createOscillator();
        const gainNode   = audioCtx.createGain();
        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);
        oscillator.type = 'sine';
        oscillator.frequency.value = 1000;
        gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
        oscillator.start();
        setTimeout(() => oscillator.stop(), 150);
    } catch (e) {
        console.warn('Audio play failed', e);
    }
};

const triggerSuccessEffects = () => {
    playBeep();
    showFlash.value = true;
    setTimeout(() => { showFlash.value = false; }, 300);
};

// ── scan handler ─────────────────────────────────────────────────────────────
const processQRCode = async (decodedText) => {
    if (isLoading.value || lastScanned.value === decodedText) return;

    isLoading.value = true;
    errorMessage.value = '';
    lastScanned.value = decodedText;

    try {
        const response = await axios.post('/school/students/scan-by-uuid', { uuid: decodedText });

        if (response.data?.success && response.data.redirect_url) {
            triggerSuccessEffects();
            lastStudent.value = response.data.student;

            // Stop the camera before navigating away so the device releases the hardware.
            await stopScanner();
            // Brief pause so the user sees the green flash + name confirmation.
            setTimeout(() => {
                router.visit(response.data.redirect_url);
            }, 400);
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.error || 'Invalid QR Code or Request Error.';
        // Allow re-scan after a short cooldown
        setTimeout(() => { lastScanned.value = null; }, 2500);
    } finally {
        isLoading.value = false;
    }
};

// ── scanner lifecycle ────────────────────────────────────────────────────────
const startScanner = async () => {
    try {
        html5QrCode.value = new Html5Qrcode('qr-reader');
        const config = { fps: 10, qrbox: { width: 250, height: 250 } };

        await html5QrCode.value.start(
            { facingMode: 'environment' },
            config,
            (decodedText) => { processQRCode(decodedText); },
            () => { /* ignore frame-level decode noise */ }
        );
        scannerActive.value = true;
    } catch (err) {
        console.error('Scanner init error:', err);
        errorMessage.value = 'Camera access denied or unavailable. Please grant permissions.';
    }
};

const stopScanner = async () => {
    if (html5QrCode.value && html5QrCode.value.isScanning) {
        try {
            await html5QrCode.value.stop();
            html5QrCode.value.clear();
            scannerActive.value = false;
        } catch (error) {
            console.error(error);
        }
    }
};

onMounted(() => { startScanner(); });
onUnmounted(() => { stopScanner(); });
</script>

<template>
    <SchoolLayout title="Student QR Scanner">

        <!-- Green success flash -->
        <div v-show="showFlash"
             class="fixed inset-0 z-50 bg-emerald-500/30 transition-opacity duration-200 pointer-events-none mix-blend-multiply"></div>

        <div class="page-header">
            <div>
                <h1 class="page-header-title" style="display:flex;align-items:center;gap:8px;">
                    <svg class="w-6 h-6" style="color:var(--accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                    </svg>
                    Scan Student ID
                </h1>
                <p class="page-header-sub">Point the camera at a student ID card to open their profile.</p>
            </div>
            <Button variant="secondary" as="link" href="/school/students">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Directory
            </Button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Camera viewport -->
            <div class="lg:col-span-2">
                <div class="bg-slate-900 rounded-3xl overflow-hidden shadow-xl border-4 border-slate-800 relative min-h-[400px]">

                    <div id="qr-reader" class="w-full"></div>

                    <!-- Loading overlay -->
                    <div v-if="isLoading"
                         class="absolute inset-0 bg-slate-900/60 flex flex-col items-center justify-center text-white backdrop-blur-[2px] z-20">
                        <svg class="w-12 h-12 text-emerald-400 animate-spin mb-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="font-bold text-lg tracking-widest uppercase">Looking up...</span>
                    </div>

                    <!-- Success preview -->
                    <div v-if="lastStudent && !isLoading && !errorMessage"
                         class="absolute bottom-6 left-6 right-6 bg-emerald-600 shadow-lg text-white p-4 rounded-xl flex items-center gap-3 z-30 animate-in slide-in-from-bottom-5">
                        <img v-if="lastStudent.photo_url" :src="lastStudent.photo_url" class="w-12 h-12 rounded-full border-2 border-white object-cover shrink-0"/>
                        <div v-else class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold truncate">{{ lastStudent.name }}</div>
                            <div class="text-xs opacity-90">Opening profile…</div>
                        </div>
                    </div>

                    <!-- Error overlay -->
                    <div v-if="errorMessage && !isLoading"
                         class="absolute bottom-6 left-6 right-6 bg-red-600 shadow-lg text-white p-4 rounded-xl flex items-center gap-3 z-30 animate-in slide-in-from-bottom-5">
                        <svg class="w-6 h-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="flex-1 text-sm font-semibold">{{ errorMessage }}</div>
                        <button @click="errorMessage = ''" class="text-red-200 hover:text-white">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Camera status row -->
                <div class="flex items-center justify-between mt-4 px-2">
                    <div class="flex items-center gap-2 text-sm font-semibold text-slate-500">
                        <span class="w-2.5 h-2.5 rounded-full"
                              :class="scannerActive ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'"></span>
                        {{ scannerActive ? 'Camera Active' : 'Camera Off' }}
                    </div>
                    <button v-if="!scannerActive" @click="startScanner" class="text-sm font-bold text-indigo-600 hover:underline">
                        Restart Camera
                    </button>
                    <button v-else @click="stopScanner" class="text-sm font-bold text-red-600 hover:underline">
                        Stop Camera
                    </button>
                </div>
            </div>

            <!-- How-to panel -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title" style="display:flex;align-items:center;gap:7px;">
                        <svg class="w-5 h-5" style="color:var(--accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        How it works
                    </span>
                </div>
                <div class="card-body" style="font-size:0.875rem;color:var(--text-secondary);line-height:1.6;">
                    <ol style="list-style:decimal;padding-left:18px;display:flex;flex-direction:column;gap:8px;">
                        <li>Hold a student's printed ID card in front of the camera.</li>
                        <li>The QR code is detected automatically — no need to click anything.</li>
                        <li>You'll be redirected to that student's profile page.</li>
                    </ol>
                    <div style="margin-top:14px;padding:10px 12px;background:#FFF8E1;border-left:3px solid #FFB300;border-radius:6px;font-size:0.8125rem;color:#6D4C00;">
                        <strong>Tip:</strong> This scanner only opens profiles. To mark attendance with the same card, use
                        <a href="/school/attendance/scanner" class="font-semibold underline">Rapid QR Attendance</a>.
                    </div>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
@reference "tailwindcss";

#qr-reader {
    @apply !border-none;
}
#qr-reader__scan_region {
    @apply rounded-xl overflow-hidden;
}
#qr-reader__dashboard {
    @apply !hidden;
}
</style>
