<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { Html5Qrcode } from 'html5-qrcode';
import axios from 'axios';

const html5QrCode = ref(null);
const scannerActive = ref(false);
const showFlash = ref(false);
const isLoading = ref(false);
const lastScanned = ref(null); // The raw UUID/URL to prevent duplicate scans
const recentScans = ref([]);
const errorMessage = ref('');

// Audio context for beep
let audioCtx = null;
const playBeep = () => {
    try {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();
        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);
        oscillator.type = 'sine';
        oscillator.frequency.value = 800; // Beep frequency
        gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
        oscillator.start();
        setTimeout(() => oscillator.stop(), 150); // Beep duration
    } catch (e) {
        console.warn('Audio play failed', e);
    }
};

const triggerSuccessEffects = () => {
    playBeep();
    showFlash.value = true;
    setTimeout(() => {
        showFlash.value = false;
    }, 300);
};

const processQRCode = async (decodedText) => {
    // Prevent immediate duplicate scans of the same code
    if (isLoading.value || lastScanned.value === decodedText) return;
    
    isLoading.value = true;
    errorMessage.value = '';
    
    // Extract UUID if full URL was scanned, or just use the text if it's already a UUID
    let uuid = decodedText;
    const match = decodedText.match(/\/q\/([^/?]+)/);
    if (match) {
        uuid = match[1];
    }
    
    try {
        const response = await axios.post('/school/attendance/rapid-scan', { uuid });
        if (response.data.success) {
            triggerSuccessEffects();
            recentScans.value.unshift(response.data.student);
            // keep array small
            if (recentScans.value.length > 5) recentScans.value.pop();
            
            // Set cooldown to prevent duplicate scanning immediately
            lastScanned.value = decodedText;
            setTimeout(() => {
                lastScanned.value = null;
            }, 2500); // 2.5 second cooldown before same student can be scanned again
        }
    } catch (error) {
        // Error handling (e.g., student not found)
        errorMessage.value = error.response?.data?.error || "Invalid QR Code or Request Error.";
        
        lastScanned.value = decodedText;
        setTimeout(() => {
            lastScanned.value = null;
        }, 3000);
    } finally {
        isLoading.value = false;
    }
};

const startScanner = async () => {
    try {
        html5QrCode.value = new Html5Qrcode("qr-reader");
        const config = { fps: 10, qrbox: { width: 250, height: 250 } };
        
        await html5QrCode.value.start(
            { facingMode: "environment" }, 
            config, 
            (decodedText) => {
                processQRCode(decodedText);
            },
            (errorMessage) => {
                // Ignore general reading errors/noise
            }
        );
        scannerActive.value = true;
    } catch (err) {
        console.error("Scanner init error:", err);
        errorMessage.value = "Camera access denied or unavailable. Please grant permissions.";
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

onMounted(() => {
    // Automatically start the scanner on mount
    startScanner();
});

onUnmounted(() => {
    stopScanner();
});
</script>

<template>
    <SchoolLayout title="Rapid QR Scanner">

        <!-- Interactive Green Flash Overlay -->
        <div v-show="showFlash" class="fixed inset-0 z-50 bg-green-500/30 transition-opacity duration-200 pointer-events-none mix-blend-multiply"></div>

        <div class="page-header">
            <div>
                <h1 class="page-header-title" style="display:flex;align-items:center;gap:8px;">
                    <svg class="w-6 h-6" style="color:var(--accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                    Rapid QR Attendance
                </h1>
                <p class="page-header-sub">Continuously scan student ID cards to mark them Present instantly.</p>
            </div>
            <Button variant="secondary" as="link" href="/school/attendance">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to Manual
            </Button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left: Camera Viewer -->
            <div class="lg:col-span-2">
                <div class="bg-slate-900 rounded-3xl overflow-hidden shadow-xl border-4 border-slate-800 relative min-h-[400px]">
                    
                    <!-- Scanner Container -->
                    <div id="qr-reader" class="w-full"></div>
                    
                    <!-- Processing Overlay -->
                    <div v-if="isLoading" class="absolute inset-0 bg-slate-900/60 flex flex-col items-center justify-center text-white backdrop-blur-[2px] z-20">
                        <svg class="w-12 h-12 text-green-400 animate-spin mb-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span class="font-bold text-lg tracking-widest uppercase">Verifying...</span>
                    </div>

                    <!-- Error Overlay -->
                    <div v-if="errorMessage && !isLoading" class="absolute bottom-6 left-6 right-6 bg-red-600 shadow-lg text-white p-4 rounded-xl flex items-center gap-3 z-30 animate-in slide-in-from-bottom-5">
                        <svg class="w-6 h-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <div class="flex-1 text-sm font-semibold">
                            {{ errorMessage }}
                        </div>
                        <button @click="errorMessage = ''" class="text-red-200 hover:text-white">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                </div>

                <!-- Control Hints -->
                <div class="flex items-center justify-between mt-4 px-2">
                    <div class="flex gap-4">
                        <div class="flex items-center gap-2 text-sm font-semibold text-slate-500">
                            <span class="w-2.5 h-2.5 rounded-full" :class="scannerActive ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'"></span>
                            {{ scannerActive ? 'Camera Active' : 'Camera Off' }}
                        </div>
                    </div>
                    <button v-if="!scannerActive" @click="startScanner" class="text-sm font-bold text-indigo-600 hover:underline">
                        Restart Camera
                    </button>
                    <button v-else @click="stopScanner" class="text-sm font-bold text-red-600 hover:underline">
                        Stop Camera
                    </button>
                </div>
            </div>

            <!-- Right: Successfully Scanned Feed -->
            <div class="card scan-feed">
                <div class="card-header">
                    <span class="card-title" style="display:flex;align-items:center;gap:7px;">
                        <svg class="w-5 h-5" style="color:var(--success);" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Recent Approvals
                    </span>
                    <span v-if="recentScans.length" class="badge badge-green">{{ recentScans.length }}</span>
                </div>

                <div class="card-body scan-feed-body">
                    <div v-if="recentScans.length === 0" class="scan-feed-empty">
                        <svg class="w-14 h-14" style="opacity:0.25;margin-bottom:12px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 14v6m-3-3h6M6 10h2a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2zm10 0h2a2 2 0 002-2V6a2 2 0 00-2-2h-2a2 2 0 00-2 2v2a2 2 0 002 2zM6 20h2a2 2 0 002-2v-2a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2z" /></svg>
                        <p style="font-weight:600;font-size:0.875rem;">No recent scans.</p>
                        <p style="font-size:0.8125rem;color:var(--text-muted);margin-top:4px;">Scan a student ID to begin.</p>
                    </div>

                    <div v-else class="scan-list">
                        <TransitionGroup enter-active-class="transition duration-300 ease-out" enter-from-class="transform translate-x-4 opacity-0"
                                         enter-to-class="transform translate-x-0 opacity-100" leave-active-class="transition duration-200 ease-in"
                                         leave-from-class="transform translate-x-0 opacity-100" leave-to-class="transform translate-x-4 opacity-0">
                            <div v-for="student in recentScans" :key="student.id" class="scan-item">
                                <img :src="student.photo_url || `https://ui-avatars.com/api/?name=${student.name}&color=16a34a&background=dcfce7`"
                                     class="scan-avatar" alt="" />
                                <div style="flex:1;min-width:0;">
                                    <div class="scan-name">{{ student.name }}</div>
                                    <span class="badge badge-green" style="margin-top:3px;">PRESENT</span>
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
@reference "tailwindcss";

#qr-reader {
    /* override html5qrcode default styling */
    @apply !border-none;
}
#qr-reader__scan_region {
    @apply rounded-xl overflow-hidden;
}
#qr-reader__dashboard {
    @apply !hidden; /* hide default stop/start buttons of the plugin */
}

/* Scan feed panel */
.scan-feed { display: flex; flex-direction: column; height: 500px; }
.scan-feed-body { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
.scan-feed-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
}
.scan-list { display: flex; flex-direction: column; gap: 12px; }
.scan-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: var(--radius);
}
.scan-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    object-fit: cover;
    flex-shrink: 0;
}
.scan-name {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--text-primary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
