<script setup>
import { useSchoolStore } from '@/stores/useSchoolStore';
const school = useSchoolStore();
defineProps({ visitor: Object, error: String });
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-indigo-50 to-purple-100 flex items-center justify-center p-4">
        <div class="w-full max-w-md">

            <!-- Header -->
            <div class="text-center mb-6">
                <div class="text-5xl mb-2">🪪</div>
                <h1 class="text-2xl font-bold text-gray-800">Visitor Pass Verification</h1>
                <p class="text-sm text-gray-500 mt-1">Hostel Security — Entry / Exit Check</p>
            </div>

            <!-- Error -->
            <div v-if="error" class="bg-white rounded-2xl shadow-xl p-8 text-center">
                <div class="text-5xl mb-4">❌</div>
                <h2 class="text-xl font-bold text-red-600 mb-2">Invalid Pass</h2>
                <p class="text-gray-500">{{ error }}</p>
            </div>

            <!-- Valid Visitor Pass -->
            <div v-else-if="visitor" class="bg-white rounded-2xl shadow-xl overflow-hidden">

                <!-- Status Banner -->
                <div class="p-4 text-center font-bold text-lg"
                    :class="visitor.out_time ? 'bg-gray-200 text-gray-700' : 'bg-green-100 text-green-800'">
                    {{ visitor.out_time ? '🔚 Visitor Has Exited' : '✅ Visitor Currently Inside' }}
                </div>

                <!-- Visitor Photo -->
                <div v-if="visitor.visitor_photo" class="flex justify-center pt-5">
                    <img :src="'/storage/' + visitor.visitor_photo"
                        class="w-24 h-24 rounded-full object-cover border-4 border-indigo-200 shadow">
                </div>

                <div class="p-6 space-y-4">
                    <!-- Visitor Info -->
                    <div class="p-4 bg-indigo-50 rounded-xl">
                        <p class="text-lg font-bold text-indigo-900">{{ visitor.visitor_name }}</p>
                        <p class="text-sm text-gray-600">
                            {{ visitor.visitor_type || 'General Visitor' }}
                            <span v-if="visitor.relation"> · {{ visitor.relation }}</span>
                        </p>
                        <p v-if="visitor.phone" class="text-sm text-gray-600">📞 {{ visitor.phone }}</p>
                        <p v-if="visitor.visitor_count > 1" class="text-sm text-gray-600">👥 {{ visitor.visitor_count }} persons</p>
                    </div>

                    <!-- Details -->
                    <table class="w-full text-sm">
                        <tr class="border-b border-gray-100">
                            <td class="py-2.5 text-gray-500 font-medium w-36">Purpose</td>
                            <td class="py-2.5 font-medium text-gray-800">{{ visitor.purpose || 'Not specified' }}</td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="py-2.5 text-gray-500 font-medium">Date</td>
                            <td class="py-2.5 text-gray-800">{{ visitor.date }}</td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="py-2.5 text-gray-500 font-medium">Entry Time</td>
                            <td class="py-2.5 text-gray-800">{{ visitor.in_time }}</td>
                        </tr>
                        <tr v-if="visitor.out_time" class="border-b border-gray-100">
                            <td class="py-2.5 text-gray-500 font-medium">Exit Time</td>
                            <td class="py-2.5 text-gray-800">{{ visitor.out_time }}</td>
                        </tr>
                        <tr v-if="visitor.id_proof_type" class="border-b border-gray-100">
                            <td class="py-2.5 text-gray-500 font-medium">ID Proof</td>
                            <td class="py-2.5 text-gray-800">{{ visitor.id_proof_type }}</td>
                        </tr>
                        <tr>
                            <td class="py-2.5 text-gray-500 font-medium">Visiting</td>
                            <td class="py-2.5">
                                <span v-if="visitor.meet_user_type === 'Student' && visitor.student" class="font-medium">
                                    {{ visitor.student.first_name }} {{ visitor.student.last_name }}
                                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full ml-1">Student</span>
                                </span>
                                <span v-else-if="visitor.meet_user_type === 'Staff' && visitor.staff?.user" class="font-medium">
                                    {{ visitor.staff.user.name }}
                                    <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full ml-1">Staff</span>
                                </span>
                            </td>
                        </tr>
                    </table>

                    <!-- Verified timestamp -->
                    <div class="text-xs text-gray-400 text-center pt-2 border-t border-gray-100">
                        Token: {{ visitor.pass_token?.slice(0, 10) }}... &nbsp;·&nbsp;
                        Verified at {{ school.fmtTime(new Date()) }}
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>





