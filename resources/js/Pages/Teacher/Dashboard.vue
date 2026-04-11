<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    teacher_schedule: { type: Object, default: null }
});

const page = usePage();
const user = page.props.auth?.user;
const school = page.props.school;

const currentDayId = ref(new Date().getDay() || 1); // 1-6, fallback to 1 if 0 (Sunday)
if (currentDayId.value > 6 || currentDayId.value === 0) currentDayId.value = 1;

const schedule = computed(() => props.teacher_schedule?.schedule?.[currentDayId.value] || []);
const daysList = computed(() => props.teacher_schedule?.days || {});
</script>

<template>
    <div class="max-w-5xl mx-auto space-y-6">

        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Welcome, {{ user?.name }}</h1>
                <p class="text-sm text-gray-500 mt-0.5">{{ school?.name }} · Staff Portal</p>
            </div>
        </div>

        <!-- Quick Links Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <!-- ... existing links ... -->
            <Link href="/school/attendance" class="group bg-white rounded-xl border shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition-all">
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center mb-3 group-hover:bg-blue-100 transition-colors">
                    <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <p class="text-sm font-semibold text-gray-800">Mark Attendance</p>
                <p class="text-xs text-gray-400 mt-0.5">Take daily class roll call</p>
            </Link>

            <Link href="/school/academic/assignments" class="group bg-white rounded-xl border shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition-all">
                <div class="w-10 h-10 rounded-lg bg-violet-50 flex items-center justify-center mb-3 group-hover:bg-violet-100 transition-colors">
                    <svg class="w-5 h-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <p class="text-sm font-semibold text-gray-800">Assignments</p>
                <p class="text-xs text-gray-400 mt-0.5">Create & manage homework</p>
            </Link>

            <Link href="/school/timetable" class="group bg-white rounded-xl border shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition-all">
                <div class="w-10 h-10 rounded-lg bg-sky-50 flex items-center justify-center mb-3 group-hover:bg-sky-100 transition-colors">
                    <svg class="w-5 h-5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <p class="text-sm font-semibold text-gray-800">My Timetable</p>
                <p class="text-xs text-gray-400 mt-0.5">View full weekly schedule</p>
            </Link>
        </div>

        <!-- Schedule Section -->
        <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
            <div class="p-6 border-b flex items-center justify-between bg-gray-50/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white shadow-lg shadow-indigo-200">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900">My Schedule</h3>
                        <p class="text-xs text-gray-500">Track your classes for today</p>
                    </div>
                </div>
                
                <div class="flex items-center p-1 bg-gray-100 rounded-lg border">
                    <button 
                        v-for="(label, id) in daysList" :key="id"
                        @click="currentDayId = parseInt(id)"
                        :class="[
                            'px-3.5 py-1.5 text-xs font-semibold rounded-md transition-all',
                            currentDayId === parseInt(id) ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-black/5' : 'text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        {{ label.substring(0, 3) }}
                    </button>
                </div>
            </div>

            <div class="divide-y divide-gray-100">
                <div v-if="!schedule.length" class="p-12 text-center">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-dashed border-gray-200">
                        <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <p class="text-gray-500 font-medium">No classes scheduled for {{ daysList[currentDayId] }}</p>
                    <p class="text-xs text-gray-400 mt-1">Enjoy your free time!</p>
                </div>
                
                <div v-for="item in schedule" :key="item.id" class="p-5 flex items-center gap-6 hover:bg-gray-50/50 transition-colors group">
                    <div class="w-20 text-center flex-shrink-0">
                        <p class="text-xs font-bold text-gray-400 group-hover:text-indigo-600 transition-colors uppercase tracking-wider">{{ item.period }}</p>
                        <p class="text-[10px] text-gray-400 font-medium mt-0.5 whitespace-nowrap">{{ item.time }}</p>
                    </div>
                    
                    <div class="w-px h-10 bg-gray-100 group-hover:bg-indigo-100 transition-colors"></div>
                    
                    <div class="flex-grow">
                        <p class="font-bold text-gray-900 leading-tight group-hover:text-indigo-700 transition-colors">{{ item.subject }}</p>
                        <div class="flex items-center gap-2 mt-1.5">
                            <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 text-[10px] font-bold rounded uppercase tracking-wide border border-indigo-100">
                                {{ item.class }} - {{ item.section }}
                            </span>
                        </div>
                    </div>

                    <div class="flex-shrink-0">
                        <Button as="link" href="/school/attendance" class="transform group-hover:scale-105" size="xs">
                            Mark Attendance
                        </Button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>
