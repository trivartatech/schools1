<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({ application: Object });

const showRejectModal = ref(false);
const rejectForm = useForm({ rejection_reason: '' });

const doApprove = () => {
    if (confirm(`Approve ${props.application.first_name}? This will create a full student record.`)) {
        router.post(`/school/registrations/${props.application.id}/approve`);
    }
};

const submitReject = () => {
    rejectForm.post(`/school/registrations/${props.application.id}/reject`, {
        onSuccess: () => { showRejectModal.value = false; }
    });
};

const statusBadge = (status) => {
    const map = { pending: 'bg-yellow-100 text-yellow-800', approved: 'bg-green-100 text-green-800', rejected: 'bg-red-100 text-red-800' };
    return map[status] ?? 'bg-gray-100 text-gray-600';
};

const field = (label, value) => ({ label, value: value || '—' });
</script>

<template>
    <Head :title="`Application — ${application.first_name} ${application.last_name || ''}`" />
    <SchoolLayout :title="`Application: ${application.first_name} ${application.last_name || ''}`">
        <div class="max-w-4xl mx-auto space-y-6">

            <!-- Header -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-start justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-2xl font-bold">
                        {{ application.first_name?.charAt(0) }}
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">{{ application.first_name }} {{ application.last_name }}</h2>
                        <div class="flex items-center gap-3 mt-1 text-sm text-gray-500">
                            <span>{{ application.gender }}</span>
                            <span>·</span>
                            <span>{{ application.course_class?.name }}</span>
                            <span v-if="application.section">/ {{ application.section?.name }}</span>
                        </div>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="font-mono text-sm font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-3 py-0.5 rounded-full">
                                {{ application.reg_no || 'No Reg No' }}
                            </span>
                            <span :class="statusBadge(application.status)" class="px-3 py-1 rounded-full text-xs font-bold capitalize">
                                {{ application.status }}
                            </span>
                        </div>
                    </div>
                </div>
                <Link href="/school/registrations" class="text-sm text-gray-400 hover:text-gray-600">← Back to Queue</Link>
            </div>

            <!-- Rejection Reason -->
            <div v-if="application.status === 'rejected' && application.rejection_reason"
                 class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
                <strong>Rejection Reason:</strong> {{ application.rejection_reason }}
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- Student Details -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="font-bold text-gray-800 mb-4 pb-2 border-b">Student Details</h3>
                    <div class="space-y-3 text-sm">
                        <template v-for="f in [
                            field('Registration No', application.reg_no),
                            field('Date of Birth', application.dob),
                            field('Gender', application.gender),
                            field('Blood Group', application.blood_group),
                            field('Religion', application.religion),
                            field('Caste', application.caste),
                            field('Category', application.category),
                            field('Aadhaar No', application.aadhaar_no),
                            field('Mother Tongue', application.mother_tongue),
                            field('Birth Place', application.birth_place),
                            field('Address', application.student_address),
                        ]">
                            <div class="flex justify-between border-b border-gray-50 pb-2">
                                <span class="text-gray-500">{{ f.label }}</span>
                                <span class="font-medium text-gray-800 text-right max-w-xs">{{ f.value }}</span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Parent Details -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="font-bold text-gray-800 mb-4 pb-2 border-b">Parent / Guardian</h3>
                    <div class="space-y-3 text-sm">
                        <template v-for="f in [
                            field('Primary Phone', application.primary_phone),
                            field('Father', application.father_name),
                            field('Mother', application.mother_name),
                            field('Guardian', application.guardian_name),
                            field('Father Phone', application.father_phone),
                            field('Mother Phone', application.mother_phone),
                            field('Father Occupation', application.father_occupation),
                            field('Mother Occupation', application.mother_occupation),
                            field('Parent Address', application.parent_address),
                        ]">
                            <div class="flex justify-between border-b border-gray-50 pb-2">
                                <span class="text-gray-500">{{ f.label }}</span>
                                <span class="font-medium text-gray-800 text-right max-w-xs">{{ f.value }}</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Action Buttons (only for pending) -->
            <div v-if="application.status === 'pending'" class="flex items-center gap-4 justify-end">
                <Link :href="`/school/registrations/${application.id}/edit`"
                      class="px-6 py-2.5 border-2 border-yellow-300 text-yellow-700 rounded-xl font-bold text-sm hover:bg-yellow-50 transition flex items-center gap-2">
                    ✏️ Edit Application
                </Link>
                <button @click="showRejectModal = true"
                        class="px-6 py-2.5 border-2 border-red-300 text-red-600 rounded-xl font-bold text-sm hover:bg-red-50 transition flex items-center gap-2">
                    ❌ Reject Application
                </button>
                <Button variant="success" @click="doApprove"
                       >
                    ✅ Approve & Admit Student
                </Button>
            </div>

            <!-- Reviewed By -->
            <div v-if="application.status !== 'pending' && application.reviewer" class="text-sm text-gray-400 text-right">
                Reviewed by <strong>{{ application.reviewer?.name }}</strong> on {{ application.reviewed_at?.split('T')[0] }}
            </div>
        </div>

        <!-- Reject Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-1">Reject Application</h3>
                <p class="text-sm text-gray-500 mb-4">Please provide a reason for rejecting <strong>{{ application.first_name }}</strong>'s application.</p>
                <textarea v-model="rejectForm.rejection_reason" rows="3" placeholder="e.g. Incomplete documents / class is full..."
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"></textarea>
                <p v-if="rejectForm.errors.rejection_reason" class="text-xs text-red-500 mt-1">{{ rejectForm.errors.rejection_reason }}</p>
                <div class="flex justify-end gap-3 mt-4">
                    <button @click="showRejectModal = false" class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Cancel</button>
                    <Button variant="danger" @click="submitReject" :loading="rejectForm.processing"
                           >
                        Reject Application
                    </Button>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>
