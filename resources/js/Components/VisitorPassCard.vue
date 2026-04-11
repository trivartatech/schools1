<script setup>
import { ref, onMounted } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    visitor: { type: Object, required: true },
    baseUrl: { type: String, default: () => window.location.origin }
});

const qrDataUrl = ref('');
const printRef = ref(null);

onMounted(async () => {
    const passUrl = `${props.baseUrl}/school/hostel/visitors/verify/${props.visitor.pass_token}`;
    qrDataUrl.value = await QRCode.toDataURL(passUrl, { width: 180, margin: 1 });
});

function printPass() {
    const content = printRef.value.innerHTML;
    const w = window.open('', '_blank');
    w.document.write(`
        <html><head><title>Visitor Pass #${props.visitor.id}</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            .pass { border: 2px solid #333; padding: 20px; max-width: 400px; margin: auto; border-radius: 8px; }
            h2 { margin: 0; color: #1e3a5f; } 
            .badge { background: #6366f1; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
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
        <div ref="printRef">
            <div class="pass border-2 border-gray-800 rounded-lg p-5 max-w-sm mx-auto bg-white text-sm font-sans">
                <!-- Header row -->
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <h2 class="text-lg font-bold text-indigo-900">VISITOR PASS</h2>
                        <p class="text-gray-500 text-xs">Pass #{{ visitor.id }}</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-bold"
                        :class="visitor.out_time ? 'bg-gray-400 text-white' : 'bg-green-500 text-white'">
                        {{ visitor.out_time ? 'Exited' : 'Inside' }}
                    </span>
                </div>

                <!-- Visitor photo if available -->
                <div v-if="visitor.visitor_photo" class="flex justify-center mb-4">
                    <img :src="'/storage/' + visitor.visitor_photo" class="w-20 h-20 rounded-full object-cover border-4 border-indigo-100">
                </div>

                <table class="w-full text-sm">
                    <tr><td class="text-gray-500 pr-3 py-1">Visitor</td><td class="font-semibold">{{ visitor.visitor_name }}</td></tr>
                    <tr><td class="text-gray-500 pr-3 py-1">Relation</td><td>{{ visitor.relation || 'N/A' }}</td></tr>
                    <tr><td class="text-gray-500 pr-3 py-1">Phone</td><td>{{ visitor.phone || 'N/A' }}</td></tr>
                    <tr><td class="text-gray-500 pr-3 py-1">Type</td><td>{{ visitor.visitor_type || 'General' }}</td></tr>
                    <tr><td class="text-gray-500 pr-3 py-1">Purpose</td><td>{{ visitor.purpose || 'N/A' }}</td></tr>
                    <tr><td class="text-gray-500 pr-3 py-1">Date</td><td>{{ visitor.date }}</td></tr>
                    <tr><td class="text-gray-500 pr-3 py-1">Entry</td><td>{{ visitor.in_time }}</td></tr>
                    <tr v-if="visitor.out_time"><td class="text-gray-500 pr-3 py-1">Exit</td><td>{{ visitor.out_time }}</td></tr>
                    <tr v-if="visitor.id_proof_type"><td class="text-gray-500 pr-3 py-1">ID Proof</td><td>{{ visitor.id_proof_type }}</td></tr>
                    <tr>
                        <td class="text-gray-500 pr-3 py-1 align-top">Visiting</td>
                        <td>
                            <span v-if="visitor.meet_user_type === 'Student' && visitor.student">
                                {{ visitor.student.first_name }} {{ visitor.student.last_name }}
                                <span class="text-xs text-blue-600">(Student)</span>
                            </span>
                            <span v-else-if="visitor.meet_user_type === 'Staff' && visitor.staff?.user">
                                {{ visitor.staff.user.name }}
                                <span class="text-xs text-purple-600">(Staff)</span>
                            </span>
                        </td>
                    </tr>
                </table>

                <!-- QR Code -->
                <div class="qr mt-4 text-center">
                    <img v-if="qrDataUrl" :src="qrDataUrl" class="mx-auto" alt="Visitor QR">
                    <p class="text-xs text-gray-400 mt-1">Scan to verify visitor pass</p>
                </div>

                <div class="footer mt-3 text-center text-xs text-gray-400">
                    Token: {{ visitor.pass_token?.slice(0, 10) }}...
                </div>
            </div>
        </div>

        <div class="mt-4 text-center">
            <button @click="printPass" class="px-5 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 flex items-center gap-2 mx-auto">
                🖨️ Print Visitor Pass
            </button>
        </div>
    </div>
</template>
