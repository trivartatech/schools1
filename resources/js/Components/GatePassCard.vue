<script setup>
import { ref, onMounted } from 'vue';
import QRCode from 'qrcode';
import { useSchoolStore } from '@/stores/useSchoolStore';

const props = defineProps({
    gatePass: { type: Object, required: true },
    baseUrl: { type: String, default: () => window.location.origin }
});

const school = useSchoolStore();

const qrDataUrl = ref('');
const printRef = ref(null);

onMounted(async () => {
    const passUrl = `${props.baseUrl}/school/hostel/gate-passes/verify/${props.gatePass.pass_token}`;
    qrDataUrl.value = await QRCode.toDataURL(passUrl, { width: 180, margin: 1 });
});

function printPass() {
    const content = printRef.value.innerHTML;
    const w = window.open('', '_blank');
    w.document.write(`
        <html><head><title>Gate Pass #${props.gatePass.id}</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            .pass { border: 2px solid #333; padding: 20px; max-width: 400px; margin: auto; border-radius: 8px; }
            h2 { margin: 0; color: #1e3a5f; }
            .badge { background: #ff6b00; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; }
            td { padding: 4px 0; vertical-align: top; }
            td:first-child { color: #888; width: 120px; }
            .qr { text-align: center; margin-top: 16px; }
            .footer { font-size: 11px; color: #888; text-align: center; margin-top: 10px; }
        </style></head><body>` + content + `</body></html>
    `);
    w.document.close();
    w.focus();
    w.print();
    w.close();
}
</script>

<template>
    <div>
        <!-- Digital Pass Card -->
        <div ref="printRef">
            <div class="pass border-2 border-gray-800 rounded-lg p-5 max-w-sm mx-auto bg-white text-sm font-sans">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <h2 class="text-lg font-bold text-blue-900">HOSTEL GATE PASS</h2>
                        <p class="text-gray-500 text-xs">Pass #{{ gatePass.id }}</p>
                    </div>
                    <span class="badge px-3 py-1 rounded-full text-xs font-bold"
                        :class="{
                            'bg-blue-600 text-white': gatePass.status === 'Approved',
                            'bg-orange-500 text-white': gatePass.status === 'Out',
                            'bg-green-600 text-white': gatePass.status === 'Returned',
                            'bg-gray-400 text-white': gatePass.status === 'Pending'
                        }">
                        {{ gatePass.status }}
                    </span>
                </div>

                <table class="w-full text-sm">
                    <tr><td class="text-gray-500 pr-3 py-1">Student</td><td class="font-semibold">{{ gatePass.student?.first_name }} {{ gatePass.student?.last_name }}</td></tr>
                    <tr><td class="text-gray-500 pr-3 py-1">Leave Type</td><td>{{ gatePass.leave_type }}</td></tr>
                    <tr><td class="text-gray-500 pr-3 py-1">Destination</td><td>{{ gatePass.destination || 'N/A' }}</td></tr>
                    <tr><td class="text-gray-500 pr-3 py-1">Out Time</td><td>{{ school.fmtDateTime(gatePass.from_date) }}</td></tr>
                    <tr><td class="text-gray-500 pr-3 py-1">Return By</td><td>{{ school.fmtDateTime(gatePass.to_date) }}</td></tr>
                    <tr v-if="gatePass.escort_name"><td class="text-gray-500 pr-3 py-1">Escort</td><td>{{ gatePass.escort_name }} ({{ gatePass.escort_relation }})</td></tr>
                    <tr v-if="gatePass.escort_phone"><td class="text-gray-500 pr-3 py-1">Escort Ph.</td><td>{{ gatePass.escort_phone }}</td></tr>
                    <tr v-if="gatePass.escort_id_proof_type"><td class="text-gray-500 pr-3 py-1">ID Proof</td><td>{{ gatePass.escort_id_proof_type }}</td></tr>
                </table>

                <div v-if="gatePass.parent_otp_verified" class="mt-3 flex items-center gap-1 text-green-700 text-xs font-medium">
                    <span>✓</span> Parent OTP Verified
                </div>

                <!-- QR Code -->
                <div class="qr mt-4 text-center">
                    <img v-if="qrDataUrl" :src="qrDataUrl" class="mx-auto" alt="QR Code">
                    <p class="text-xs text-gray-400 mt-1">Scan to verify</p>
                </div>

                <div class="footer mt-3 text-center text-xs text-gray-400">
                    Valid until {{ school.fmtDate(gatePass.to_date) }} &nbsp;·&nbsp; Token: {{ gatePass.pass_token?.slice(0,8) }}...
                </div>
            </div>
        </div>

        <div class="mt-4 text-center">
            <button @click="printPass" class="px-5 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 flex items-center gap-2 mx-auto">
                🖨️ Print / Download Pass
            </button>
        </div>
    </div>
</template>
