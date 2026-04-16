<script setup>
defineProps({ pass: Object, error: String });

const statusColors = {
    Pending:  { bg: 'bg-amber-100',  text: 'text-amber-800',  icon: '⏳' },
    Approved: { bg: 'bg-blue-100',   text: 'text-blue-800',   icon: '✅' },
    Rejected: { bg: 'bg-red-100',    text: 'text-red-800',    icon: '❌' },
    Out:      { bg: 'bg-orange-100', text: 'text-orange-800', icon: '🚪' },
    Returned: { bg: 'bg-green-100',  text: 'text-green-800',  icon: '🏠' },
};
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
        <div class="w-full max-w-md">

            <!-- Header -->
            <div class="text-center mb-6">
                <div class="text-5xl mb-2">🎫</div>
                <h1 class="text-2xl font-bold text-gray-800">Gate Pass Verification</h1>
                <p class="text-sm text-gray-500 mt-1">Hostel Security Check</p>
            </div>

            <!-- Error State -->
            <div v-if="error" class="bg-white rounded-2xl shadow-xl p-8 text-center">
                <div class="text-5xl mb-4">❌</div>
                <h2 class="text-xl font-bold text-red-600 mb-2">Invalid Pass</h2>
                <p class="text-gray-500">{{ error }}</p>
            </div>

            <!-- Valid Pass -->
            <div v-else-if="pass" class="bg-white rounded-2xl shadow-xl overflow-hidden">

                <!-- Status banner -->
                <div class="p-4 text-center font-bold text-lg" :class="[statusColors[pass.status]?.bg, statusColors[pass.status]?.text]">
                    {{ statusColors[pass.status]?.icon }} {{ pass.status }}
                    <span v-if="pass.is_expired" class="ml-2 text-sm font-normal">(Expired)</span>
                </div>

                <!-- Pass Details -->
                <div class="p-6 space-y-4">
                    <!-- Student info -->
                    <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl">
                        <div class="w-12 h-12 rounded-full bg-blue-600 flex items-center justify-center text-white text-xl font-bold">
                            {{ pass.student?.first_name?.charAt(0) }}
                        </div>
                        <div>
                            <p class="font-bold text-gray-900 text-lg">{{ pass.student?.first_name }} {{ pass.student?.last_name }}</p>
                            <p class="text-sm text-gray-500">Admission: {{ pass.student?.admission_no }}</p>
                        </div>
                    </div>

                    <!-- Details grid -->
                    <table class="w-full text-sm">
                        <tr class="border-b border-gray-100">
                            <td class="py-2.5 text-gray-500 font-medium w-36">Leave Type</td>
                            <td class="py-2.5 font-semibold text-gray-800">{{ pass.leave_type }}</td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="py-2.5 text-gray-500 font-medium">Destination</td>
                            <td class="py-2.5 text-gray-800">{{ pass.destination || 'Not specified' }}</td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="py-2.5 text-gray-500 font-medium">Approved Out</td>
                            <td class="py-2.5 text-gray-800">{{ pass.from_date?.slice(0,16) }}</td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="py-2.5 text-gray-500 font-medium">Return By</td>
                            <td class="py-2.5 text-gray-800">{{ pass.to_date?.slice(0,16) }}</td>
                        </tr>
                        <tr v-if="pass.reason" class="border-b border-gray-100">
                            <td class="py-2.5 text-gray-500 font-medium">Reason</td>
                            <td class="py-2.5 text-gray-800">{{ pass.reason }}</td>
                        </tr>
                        <tr v-if="pass.actual_out_time" class="border-b border-gray-100">
                            <td class="py-2.5 text-gray-500 font-medium">Actual Out</td>
                            <td class="py-2.5 text-gray-800">{{ pass.actual_out_time?.slice(0,16) }}</td>
                        </tr>
                        <tr v-if="pass.actual_in_time">
                            <td class="py-2.5 text-gray-500 font-medium">Returned At</td>
                            <td class="py-2.5 text-gray-800">{{ pass.actual_in_time?.slice(0,16) }}</td>
                        </tr>
                    </table>

                    <!-- Escort & Parent verification -->
                    <div v-if="pass.escort_name" class="p-3 bg-blue-50 rounded-xl">
                        <p class="text-xs font-semibold text-blue-900 mb-2 uppercase tracking-wide">Escort Details</p>
                        <div class="grid grid-cols-2 gap-1 text-sm">
                            <span class="text-gray-500">Name</span><span class="font-medium">{{ pass.escort_name }}</span>
                            <span class="text-gray-500">Relation</span><span>{{ pass.escort_relation }}</span>
                            <span class="text-gray-500">Phone</span><span>{{ pass.escort_phone }}</span>
                            <span class="text-gray-500">ID Proof</span><span>{{ pass.escort_id_proof_type }}</span>
                        </div>
                    </div>

                    <!-- Verification badges -->
                    <div class="flex flex-wrap gap-2">
                        <span class="px-3 py-1.5 rounded-full text-xs font-semibold"
                            :class="pass.parent_otp_verified ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'">
                            {{ pass.parent_otp_verified ? '✓ Parent OTP Verified' : '⚠ Parent OTP Pending' }}
                        </span>
                        <span class="px-3 py-1.5 rounded-full text-xs font-semibold"
                            :class="pass.approved_by ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600'">
                            {{ pass.approved_by ? '✓ Warden Approved' : '⏳ Awaiting Approval' }}
                        </span>
                        <span v-if="pass.is_expired" class="px-3 py-1.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                            ✗ Pass Expired
                        </span>
                    </div>

                    <!-- Security hint -->
                    <div class="text-xs text-gray-400 text-center pt-2 border-t border-gray-100">
                        Pass Token: {{ pass.pass_token?.slice(0,12) }}... &nbsp;·&nbsp;
                        Verified at {{ new Date().toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) }}
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>





