<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, watchEffect } from 'vue'
import Button from '@/Components/ui/Button.vue';
import Toast from '@/Components/ui/Toast.vue';
import { useToast } from '@/Composables/useToast';

const toast = useToast();

const props = defineProps({
  student: Object,
  isStaff: Boolean,
  attendanceToday: Object,
  errors: Object,
  flash: Object,
})

watchEffect(() => {
    const f = usePage().props.flash;
    if (f?.success) toast.success(f.success);
    if (f?.error) toast.error(f.error);
});

const form = useForm({
  status: 'present'
})

function submitAttendance(status) {
  form.status = status
  form.post(route('qr.student.attendance', props.student.uuid), {
    preserveScroll: true
  })
}

const photoUrl = computed(() => {
  return props.student.photo_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(props.student.name)}&color=7F9CF5&background=EBF4FF`
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col items-center py-10 px-4 sm:px-6 lg:px-8 font-sans">
    
    <!-- Main Card -->
    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">
      <!-- Header / Banner -->
      <div class="bg-gradient-to-r from-blue-500 to-indigo-600 h-24 sm:h-32"></div>
      
      <div class="relative px-6 pb-6 text-center">
        <!-- Avatar -->
        <div class="flex justify-center -mt-12 sm:-mt-16 mb-4">
          <img 
            :src="photoUrl" 
            alt="Student Photo" 
            class="h-24 w-24 sm:h-32 sm:w-32 rounded-full border-4 border-white shadow-md object-cover bg-white"
          />
        </div>
        
        <!-- Name & ID -->
        <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ student.name }}</h2>
        <p class="text-sm text-gray-500 mt-1">Admission No: <span class="font-medium text-gray-700">{{ student.admission_no }}</span></p>

        <!-- Academic Info -->
        <div class="mt-6 border-t border-gray-100 pt-6">
          <dl class="grid grid-cols-2 gap-x-4 gap-y-6 text-sm">
             <div>
              <dt class="text-gray-500 font-medium">School</dt>
              <dd class="mt-1 text-gray-900">{{ student.school?.name }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 font-medium">Class & Section</dt>
              <dd class="mt-1 text-gray-900 font-semibold text-blue-600">
                <span v-if="student.current_academic_history">
                  {{ student.current_academic_history.course_class?.name }}
                  <span v-if="student.current_academic_history.section"> - {{ student.current_academic_history.section.name }}</span>
                </span>
                <span v-else class="text-gray-400 italic">Not Enrolled</span>
              </dd>
            </div>
            <div>
              <dt class="text-gray-500 font-medium">Roll No</dt>
              <dd class="mt-1 text-gray-900">{{ student.roll_no || 'N/A' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 font-medium">Status</dt>
              <dd class="mt-1">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold"
                  :class="student.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                  {{ (student.status || 'active').toUpperCase() }}
                </span>
              </dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- Staff Actions (Attendance) -->
      <div v-if="isStaff" class="bg-gray-50 border-t border-gray-100 p-6">
        <h3 class="text-base font-medium text-gray-900 mb-4 flex items-center justify-center">
          <svg class="mr-2 h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Staff Attendance Portal
        </h3>

        <div v-if="attendanceToday" class="text-center bg-blue-50 rounded-lg p-4 border border-blue-100 mb-4">
          <p class="text-sm text-blue-800">
            Attendance already marked for today: 
            <span class="font-bold uppercase">{{ attendanceToday.status.replace('_', ' ') }}</span>
          </p>
        </div>

        <div class="grid grid-cols-2 gap-3" v-if="!attendanceToday || true">
          <!-- Quick actions. Even if already marked, allow override. -->
          <Button variant="success" 
            @click="submitAttendance('present')"
            :disabled="form.processing"
           
          >
            Mark Present
          </Button>
          <Button variant="danger" 
            @click="submitAttendance('absent')"
            :disabled="form.processing"
           
          >
            Mark Absent
          </Button>
          <Button variant="secondary" 
            @click="submitAttendance('late')"
            :disabled="form.processing"
           
          >
            Mark Late
          </Button>
          <button 
            @click="submitAttendance('half_day')"
            :disabled="form.processing"
            class="flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-yellow-700 bg-yellow-100 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 disabled:opacity-50"
          >
            Half Day
          </button>
        </div>
      </div>
    </div>

    <Toast />
  </div>
</template>
