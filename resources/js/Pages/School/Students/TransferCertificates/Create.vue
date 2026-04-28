<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { router, Link, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    students:      Array,
    preselectedId: Number,
});

const form = useForm({
    student_id:          props.preselectedId || '',
    leaving_date:        '',
    reason:              '',
    conduct:             'Good',
    last_class_studied:  '',
    fee_paid_upto:       '',
    has_dues:            false,
});

// Auto-fill class from selected student
const selectedStudent = computed(() =>
    form.student_id ? props.students.find(s => s.id === parseInt(form.student_id)) : null
);

watch(selectedStudent, (s) => {
    if (s?.current_academic_history) {
        const h = s.current_academic_history;
        const cls = h.course_class?.name || '';
        const sec = h.section?.name ? ' - ' + h.section.name : '';
        form.last_class_studied = cls + sec;
    } else {
        form.last_class_studied = '';
    }
});

const submit = () => {
    form.post(route('school.transfer-certificates.store'), {
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <SchoolLayout title="New TC Request">
        <PageHeader title="New Transfer Certificate Request" subtitle="Fill in the details to initiate a TC request">
            <template #actions>
                <Button variant="secondary" as="link" :href="route('school.transfer-certificates.index')">← Back</Button>
            </template>
        </PageHeader>

        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="card">
                <div class="card-body space-y-5">

                    <!-- Student Select -->
                    <div class="form-field">
                        <label class="required">Student</label>
                        <select v-model="form.student_id"
                                :class="['input', form.errors.student_id ? 'border-red-400' : '']">
                            <option value="">— Select student —</option>
                            <option v-for="s in students" :key="s.id" :value="s.id">
                                {{ s.first_name }} {{ s.last_name }} ({{ s.admission_no }})
                            </option>
                        </select>
                        <p v-if="form.errors.student_id" class="form-error">{{ form.errors.student_id }}</p>
                        <p v-if="selectedStudent" class="text-xs text-indigo-600 mt-1">
                            Class:
                            {{ selectedStudent.current_academic_history?.course_class?.name }}
                            {{ selectedStudent.current_academic_history?.section ? '/ ' + selectedStudent.current_academic_history.section.name : '' }}
                        </p>
                    </div>

                    <!-- Leaving Date -->
                    <div class="form-field">
                        <label class="required">Date of Leaving</label>
                        <input v-model="form.leaving_date" type="date"
                               :class="['input', form.errors.leaving_date ? 'border-red-400' : '']">
                        <p v-if="form.errors.leaving_date" class="form-error">{{ form.errors.leaving_date }}</p>
                    </div>

                    <!-- Last Class Studied -->
                    <div class="form-field">
                        <label>Last Class Studied</label>
                        <input v-model="form.last_class_studied" type="text"
                               placeholder="e.g. 10th - A (auto-filled from current class)"
                               :class="['input', form.errors.last_class_studied ? 'border-red-400' : '']">
                        <p v-if="form.errors.last_class_studied" class="form-error">{{ form.errors.last_class_studied }}</p>
                    </div>

                    <!-- Reason -->
                    <div class="form-field">
                        <label>Reason for Leaving</label>
                        <textarea v-model="form.reason" rows="3"
                                  placeholder="Family relocation, admission in other school, etc."
                                  :class="['input', form.errors.reason ? 'border-red-400' : '']"></textarea>
                        <p v-if="form.errors.reason" class="form-error">{{ form.errors.reason }}</p>
                    </div>

                    <!-- Conduct -->
                    <div class="form-field">
                        <label class="required">Student Conduct</label>
                        <select v-model="form.conduct"
                                :class="['input', form.errors.conduct ? 'border-red-400' : '']">
                            <option value="Good">Good</option>
                            <option value="Satisfactory">Satisfactory</option>
                            <option value="Poor">Poor</option>
                        </select>
                        <p v-if="form.errors.conduct" class="form-error">{{ form.errors.conduct }}</p>
                    </div>

                    <!-- Fee Paid Up To -->
                    <div class="form-field">
                        <label>Fee Paid Up To</label>
                        <input v-model="form.fee_paid_upto" type="date"
                               :class="['input', form.errors.fee_paid_upto ? 'border-red-400' : '']">
                        <p v-if="form.errors.fee_paid_upto" class="form-error">{{ form.errors.fee_paid_upto }}</p>
                    </div>

                    <!-- Has Dues -->
                    <div class="flex items-center gap-3">
                        <input id="has_dues" v-model="form.has_dues" type="checkbox"
                               class="w-4 h-4 rounded border-slate-300 text-red-500 focus:ring-red-400">
                        <label for="has_dues" class="text-sm font-medium text-slate-700">
                            Student has pending dues
                        </label>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3 pt-2 border-t border-slate-100">
                        <Button type="submit" :loading="form.processing">
                            <span v-if="form.processing">Submitting…</span>
                            <span v-else>Submit TC Request</span>
                        </Button>
                        <Button variant="secondary" as="link" :href="route('school.transfer-certificates.index')">
                            Cancel
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </SchoolLayout>
</template>
