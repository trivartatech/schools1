<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const token = ref('');
const isLoading = ref(false);
const result = ref(null);
const error = ref('');

// ── Scanner state ────────────────────────────────────────────────────────
const scannerActive = ref(false);
const scannerError = ref('');
let html5QrCode = null;

const startScanner = async () => {
    scannerError.value = '';
    result.value = null;
    error.value = '';

    try {
        const { Html5Qrcode } = await import('html5-qrcode');
        html5QrCode = new Html5Qrcode('qr-reader');
        scannerActive.value = true;

        await html5QrCode.start(
            { facingMode: 'environment' },
            {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0,
            },
            (decodedText) => {
                // QR scanned successfully
                token.value = decodedText;
                stopScanner();
                verify();
            },
            () => { /* ignore scan failures (no QR found in frame) */ }
        );
    } catch (e) {
        scannerActive.value = false;
        if (e?.toString?.().includes('NotAllowedError') || e?.toString?.().includes('Permission')) {
            scannerError.value = 'Camera permission denied. Please allow camera access and try again.';
        } else if (e?.toString?.().includes('NotFoundError')) {
            scannerError.value = 'No camera found on this device.';
        } else {
            scannerError.value = 'Could not start camera. Please use manual entry instead.';
        }
    }
};

const stopScanner = async () => {
    if (html5QrCode) {
        try {
            await html5QrCode.stop();
            html5QrCode.clear();
        } catch { /* ignore */ }
        html5QrCode = null;
    }
    scannerActive.value = false;
};

onBeforeUnmount(() => {
    stopScanner();
});

// ── Verify ───────────────────────────────────────────────────────────────
const statusBadge = {
    Pending:  'badge-amber',
    Approved: 'badge-green',
    Rejected: 'badge-red',
    Exited:   'badge-blue',
    Returned: 'badge-gray',
    Out:      'badge-amber',
};

const verify = async () => {
    const value = token.value.trim();
    if (!value) return;

    isLoading.value = true;
    result.value = null;
    error.value = '';

    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch('/school/front-office/gate-passes/verify-qr', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ token: value }),
        });

        const data = await response.json();

        if (!response.ok) {
            error.value = data.message || data.error || 'Gate pass not found or invalid token.';
        } else {
            result.value = data;
        }
    } catch (e) {
        error.value = 'Network error. Please check your connection and try again.';
    } finally {
        isLoading.value = false;
    }
};

const reset = () => {
    token.value = '';
    result.value = null;
    error.value = '';
    scannerError.value = '';
};

const formatDateTime = (dt) => {
    if (!dt) return '--';
    return new Date(dt).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
};
</script>

<template>
    <SchoolLayout title="Gate Pass QR Scanner">

        <div class="page-header">
            <div>
                <h1 class="page-header-title">Gate Pass QR Scanner</h1>
                <p class="page-header-sub">Scan QR code or enter token to verify gate passes.</p>
            </div>
            <Button variant="secondary" as="link" href="/school/front-office/gate-passes">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to Gate Passes
            </Button>
        </div>

        <div class="scanner-layout">

            <!-- Camera Scanner Card -->
            <div class="card scanner-card">
                <div class="card-header">
                    <span class="card-title" style="display:flex;align-items:center;gap:8px;">
                        <svg class="w-5 h-5" style="color:var(--accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Camera Scanner
                    </span>
                </div>
                <div class="card-body">
                    <div class="camera-area">
                        <div id="qr-reader" class="qr-reader-box" :class="{ active: scannerActive }"></div>

                        <div v-if="!scannerActive" class="camera-placeholder" @click="startScanner">
                            <svg class="placeholder-cam-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                            <p class="placeholder-text">Click to open camera</p>
                            <p class="placeholder-sub">Point at a gate pass QR code</p>
                        </div>
                    </div>

                    <div v-if="scannerActive" class="scanner-controls">
                        <div class="scanning-indicator">
                            <span class="scanning-dot"></span>
                            Scanning...
                        </div>
                        <Button variant="secondary" size="sm" type="button" @click="stopScanner">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Stop Camera
                        </Button>
                    </div>

                    <div v-if="scannerError" class="scanner-err">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ scannerError }}
                    </div>
                </div>
            </div>

            <!-- Manual Entry Card -->
            <div class="card scanner-card">
                <div class="card-header">
                    <span class="card-title" style="display:flex;align-items:center;gap:8px;">
                        <svg class="w-5 h-5" style="color:var(--accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Manual Token Entry
                    </span>
                </div>
                <div class="card-body">
                    <form @submit.prevent="verify" class="manual-form">
                        <div class="form-field">
                            <label>QR Token / Pass Code</label>
                            <input
                                v-model="token"
                                type="text"
                                placeholder="Enter or paste the gate pass token..."
                                :disabled="isLoading"
                            >
                        </div>
                        <div class="manual-actions">
                            <Button type="submit" :disabled="isLoading || !token.trim()">
                                <svg v-if="isLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ isLoading ? 'Verifying...' : 'Verify' }}
                            </Button>
                            <Button variant="secondary" v-if="result || error" type="button" @click="reset">
                                Clear
                            </Button>
                        </div>
                    </form>
                </div>
            </div>

        </div>

        <!-- Error State -->
        <div v-if="error" class="card error-card">
            <div class="card-body">
                <div class="error-content">
                    <div class="error-icon">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h3 class="error-title">Verification Failed</h3>
                        <p class="error-message">{{ error }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Valid Result -->
        <div v-if="result" class="result-area">

            <!-- Authorization Banner -->
            <div v-if="result.can_exit" class="auth-banner auth-banner--green">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                AUTHORIZED TO EXIT
            </div>

            <div v-if="result.status === 'Rejected'" class="auth-banner auth-banner--red">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                PASS REJECTED - DO NOT ALLOW EXIT
            </div>

            <div v-if="result.status === 'Returned'" class="auth-banner auth-banner--amber">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                PASS ALREADY USED - PERSON HAS RETURNED
            </div>

            <!-- Pass Details Card -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Pass Details</span>
                    <span :class="['badge', statusBadge[result.status] || 'badge-gray']">
                        {{ result.status }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="details-grid">
                        <div class="detail-row">
                            <span class="detail-label">Pass Type</span>
                            <span class="detail-value">{{ result.pass_type || '--' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Person Name</span>
                            <span class="detail-value" style="font-weight:700;">{{ result.person_name || result.picked_up_by_name || '--' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Status</span>
                            <span class="detail-value">
                                <span :class="['badge', statusBadge[result.status] || 'badge-gray']">{{ result.status }}</span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Verification Method</span>
                            <span class="detail-value">{{ result.verification_method || '--' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Exit Time</span>
                            <span class="detail-value">{{ formatDateTime(result.exit_time || result.actual_out_time) }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Expected Return</span>
                            <span class="detail-value">{{ formatDateTime(result.return_time || result.expected_return_time) }}</span>
                        </div>
                        <div v-if="result.actual_return_time || result.actual_in_time" class="detail-row">
                            <span class="detail-label">Actual Return</span>
                            <span class="detail-value">{{ formatDateTime(result.actual_return_time || result.actual_in_time) }}</span>
                        </div>
                        <div v-if="result.reason" class="detail-row detail-row--full">
                            <span class="detail-label">Reason</span>
                            <span class="detail-value">{{ result.reason }}</span>
                        </div>
                        <div v-if="result.relationship" class="detail-row">
                            <span class="detail-label">Relationship</span>
                            <span class="detail-value">{{ result.relationship }}</span>
                        </div>
                        <div v-if="result.approval_notes" class="detail-row detail-row--full">
                            <span class="detail-label">Approval Notes</span>
                            <span class="detail-value">{{ result.approval_notes }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scan Another -->
            <div class="scan-again">
                <Button type="button" @click="reset; startScanner()">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Scan Another
                </Button>
                <span class="verify-timestamp">Verified at {{ new Date().toLocaleString() }}</span>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* Scanner layout */
.scanner-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 768px) {
    .scanner-layout { grid-template-columns: 1fr; }
}

.scanner-card { margin-bottom: 0; }

/* Camera area */
.camera-area {
    position: relative;
    border-radius: 10px;
    overflow: hidden;
    background: #0f172a;
    min-height: 280px;
}

.qr-reader-box {
    display: none;
}
.qr-reader-box.active {
    display: block;
}
.qr-reader-box :deep(video) {
    border-radius: 10px;
}
.qr-reader-box :deep(#qr-shaded-region) {
    border-color: rgba(17, 105, 205, 0.5) !important;
}

.camera-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 280px;
    cursor: pointer;
    transition: all 0.2s;
}
.camera-placeholder:hover {
    background: #1e293b;
}
.camera-placeholder:hover .placeholder-cam-icon {
    color: #60a5fa;
    transform: scale(1.05);
}

.placeholder-cam-icon {
    width: 56px;
    height: 56px;
    color: #475569;
    margin-bottom: 12px;
    transition: all 0.2s;
}

.placeholder-text {
    font-size: 0.875rem;
    font-weight: 600;
    color: #94a3b8;
    margin: 0 0 4px;
}

.placeholder-sub {
    font-size: 0.75rem;
    color: #64748b;
    margin: 0;
}

/* Scanner controls */
.scanner-controls {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 0 0;
}

.scanning-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #16a34a;
}

.scanning-dot {
    width: 8px;
    height: 8px;
    background: #16a34a;
    border-radius: 50%;
    animation: pulse-dot 1.2s ease-in-out infinite;
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.75); }
}

.scanner-err {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    padding: 8px 12px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 6px;
    font-size: 0.775rem;
    color: #dc2626;
}

/* Manual form */
.manual-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.manual-actions {
    display: flex;
    gap: .5rem;
}

/* Error card */
.error-card {
    border-left: 4px solid #ef4444;
    margin-bottom: 1.5rem;
}

.error-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.error-icon {
    flex-shrink: 0;
    color: #ef4444;
}

.error-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: .25rem;
}

.error-message {
    font-size: .875rem;
    color: var(--text-muted);
}

/* Result area */
.result-area {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* Authorization banners */
.auth-banner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .75rem;
    padding: 1rem 1.5rem;
    border-radius: var(--radius);
    font-size: 1.125rem;
    font-weight: 800;
    letter-spacing: .05em;
    text-transform: uppercase;
}

.auth-banner--green {
    background: #dcfce7;
    color: #166534;
    border: 2px solid #86efac;
}

.auth-banner--red {
    background: #fee2e2;
    color: #991b1b;
    border: 2px solid #fca5a5;
}

.auth-banner--amber {
    background: #fef3c7;
    color: #92400e;
    border: 2px solid #fcd34d;
}

/* Details grid */
.details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
}
@media (max-width: 640px) {
    .details-grid { grid-template-columns: 1fr; }
}

.detail-row {
    display: flex;
    flex-direction: column;
    gap: .25rem;
    padding: .875rem 0;
    border-bottom: 1px solid var(--border);
}

.detail-row:nth-child(odd) {
    padding-right: 1.5rem;
}

.detail-row:nth-child(even) {
    padding-left: 1.5rem;
    border-left: 1px solid var(--border);
}

@media (max-width: 640px) {
    .detail-row:nth-child(odd) { padding-right: 0; }
    .detail-row:nth-child(even) { padding-left: 0; border-left: none; }
}

.detail-row--full {
    grid-column: 1 / -1;
    padding-left: 0 !important;
    padding-right: 0 !important;
    border-left: none !important;
}

.detail-label {
    font-size: .75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--text-muted);
}

.detail-value {
    font-size: .9375rem;
    color: var(--text-primary);
}

/* Scan again area */
.scan-again {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding-top: .5rem;
}

.verify-timestamp {
    font-size: .75rem;
    color: var(--text-muted);
}
</style>
