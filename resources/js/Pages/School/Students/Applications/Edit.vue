<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    application:    Object,
    classes:        Array,
    routes:         { type: Array,  default: () => [] },
    standardMonths: { type: Number, default: 10 },
});

const form = useForm({
    _method:            'PUT',
    class_id:           props.application.class_id       ?? '',
    section_id:         props.application.section_id     ?? '',
    student_type:       props.application.student_type   ?? 'New Student',
    first_name:         props.application.first_name     ?? '',
    last_name:          props.application.last_name      ?? '',
    dob:                props.application.dob            ?? '',
    birth_place:        props.application.birth_place    ?? '',
    mother_tongue:      props.application.mother_tongue  ?? '',
    gender:             props.application.gender         ?? 'Male',
    blood_group:        props.application.blood_group    ?? '',
    religion:           props.application.religion       ?? '',
    caste:              props.application.caste          ?? '',
    category:           props.application.category       ?? '',
    aadhaar_no:         props.application.aadhaar_no     ?? '',
    student_address:    props.application.student_address ?? '',
    photo:              null,
    primary_phone:      props.application.primary_phone  ?? '',
    father_name:        props.application.father_name    ?? '',
    mother_name:        props.application.mother_name    ?? '',
    guardian_name:      props.application.guardian_name  ?? '',
    father_phone:       props.application.father_phone   ?? '',
    mother_phone:       props.application.mother_phone   ?? '',
    father_occupation:  props.application.father_occupation ?? '',
    mother_occupation:  props.application.mother_occupation ?? '',
    parent_address:     props.application.parent_address ?? '',
    // Student extras
    nationality:              props.application.nationality              ?? 'Indian',
    city:                     props.application.city                     ?? '',
    state:                    props.application.state                    ?? '',
    pincode:                  props.application.pincode                  ?? '',
    emergency_contact_name:   props.application.emergency_contact_name  ?? '',
    emergency_contact_phone:  props.application.emergency_contact_phone ?? '',
    // Parent extras
    guardian_email:           props.application.guardian_email          ?? '',
    guardian_phone:           props.application.guardian_phone          ?? '',
    father_qualification:     props.application.father_qualification     ?? '',
    mother_qualification:     props.application.mother_qualification     ?? '',
    // Background
    previous_school:          props.application.previous_school         ?? '',
    previous_class:           props.application.previous_class          ?? '',
    annual_income:            props.application.annual_income           ?? '',
    // Transport
    transport_route_id:    props.application.transport_route_id    ?? '',
    transport_stop_id:     props.application.transport_stop_id     ?? '',
    transport_pickup_type: props.application.transport_pickup_type ?? 'both',
    transport_months:      props.application.transport_months ?? Math.floor(props.standardMonths || 10),
    transport_days:        props.application.transport_days   ?? 0,
});

const sections = ref(
    props.application.section ? [props.application.section] : []
);

const fetchSections = async () => {
    if (!form.class_id) { sections.value = []; form.section_id = ''; return; }
    const res = await fetch(`/school/classes/${form.class_id}/sections`);
    sections.value = await res.json();
    // keep existing section if same class, else reset
    const stillValid = sections.value.some(s => s.id == form.section_id);
    if (!stillValid) form.section_id = sections.value[0]?.id ?? '';
};

const routeStops = computed(() => {
    if (!form.transport_route_id) return [];
    const route = props.routes.find(r => r.id == form.transport_route_id);
    return route?.stops ?? [];
});
const selectedStop = computed(() => routeStops.value.find(s => s.id == form.transport_stop_id));
const onRouteChange = () => { form.transport_stop_id = ''; };

// Pro-rata transport fee preview
const transportMonthsOpted = computed(() => {
    const m = Math.max(0, Math.min(24, Number(form.transport_months) || 0));
    const d = Math.max(0, Math.min(30, Number(form.transport_days)   || 0));
    return Math.round((m + d / 30) * 100) / 100;
});
const transportComputedFee = computed(() => {
    if (!selectedStop.value?.fee) return 0;
    const std = Number(props.standardMonths) > 0 ? Number(props.standardMonths) : 10;
    return Math.round(((Number(selectedStop.value.fee) / std) * transportMonthsOpted.value) * 100) / 100;
});

const submit = () => {
    form.post(`/school/registrations/${props.application.id}`, {
        forceFormData: true,
    });
};
</script>

<template>
    <Head :title="`Edit Application — ${application.first_name}`" />
    <SchoolLayout :title="`Edit Application: ${application.first_name} ${application.last_name || ''}`">
        <div class="max-w-4xl mx-auto space-y-6">

            <!-- Header -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center text-xl">✏️</div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Edit Application</h2>
                        <p class="text-sm text-gray-500">Update the details of this pending registration.</p>
                    </div>
                </div>
                <Link :href="`/school/registrations/${application.id}`" class="text-sm text-gray-500 hover:text-gray-700">← Back to Application</Link>
            </div>

            <form @submit.prevent="submit" class="space-y-6" enctype="multipart/form-data">

                <!-- Class Assignment -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="text-base font-bold text-gray-800 mb-4 pb-2 border-b">Class Assignment</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Class *</label>
                            <select v-model="form.class_id" @change="fetchSections"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none bg-white">
                                <option value="">Select Class</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p v-if="form.errors.class_id" class="text-xs text-red-500 mt-1">{{ form.errors.class_id }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                            <select v-model="form.section_id"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none bg-white"
                                    :disabled="!sections.length">
                                <option value="">Select Section</option>
                                <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Student Type</label>
                            <select v-model="form.student_type"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none bg-white">
                                <option value="New Student">New Student</option>
                                <option value="Old Student">Old Student</option>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Drives fee-rule matching for this academic year.</p>
                            <p v-if="form.errors.student_type" class="text-xs text-red-500 mt-1">{{ form.errors.student_type }}</p>
                        </div>
                    </div>
                </div>

                <!-- Student Information -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="text-base font-bold text-gray-800 mb-4 pb-2 border-b">Student Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                            <input v-model="form.first_name" type="text"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                            <p v-if="form.errors.first_name" class="text-xs text-red-500 mt-1">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input v-model="form.last_name" type="text"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth *</label>
                            <input v-model="form.dob" type="date"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                            <p v-if="form.errors.dob" class="text-xs text-red-500 mt-1">{{ form.errors.dob }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Gender *</label>
                            <select v-model="form.gender"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none bg-white">
                                <option>Male</option><option>Female</option><option>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Blood Group</label>
                            <input v-model="form.blood_group" type="text" placeholder="e.g. O+"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Religion</label>
                            <input v-model="form.religion" type="text"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Aadhaar No</label>
                            <input v-model="form.aadhaar_no" type="text"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Birth Place</label>
                            <input v-model="form.birth_place" type="text"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea v-model="form.student_address" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Update Photo
                                <span class="text-gray-400 font-normal text-xs ml-1">(leave blank to keep existing)</span>
                            </label>
                            <input type="file" accept="image/*" @change="e => form.photo = e.target.files[0]"
                                   class="w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                        </div>
                    </div>
                </div>

                <!-- Additional Details -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="text-base font-bold text-gray-800 mb-4 pb-2 border-b">Additional Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nationality</label>
                            <input v-model="form.nationality" type="text" placeholder="e.g. Indian"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                            <input v-model="form.city" type="text" placeholder="City"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                            <input v-model="form.state" type="text" placeholder="State"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Pincode</label>
                            <input v-model="form.pincode" type="text" placeholder="e.g. 400001"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Name</label>
                            <input v-model="form.emergency_contact_name" type="text" placeholder="Full name"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Phone</label>
                            <input v-model="form.emergency_contact_phone" type="text" placeholder="Mobile number"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                    </div>
                </div>

                <!-- Parent Information -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="text-base font-bold text-gray-800 mb-4 pb-2 border-b">Parent / Guardian</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Primary Phone *</label>
                            <input v-model="form.primary_phone" type="text"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                            <p v-if="form.errors.primary_phone" class="text-xs text-red-500 mt-1">{{ form.errors.primary_phone }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Father's Name</label>
                            <input v-model="form.father_name" type="text"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mother's Name</label>
                            <input v-model="form.mother_name" type="text"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Father's Phone</label>
                            <input v-model="form.father_phone" type="text"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Father Occupation</label>
                            <input v-model="form.father_occupation" type="text"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Father Qualification</label>
                            <input v-model="form.father_qualification" type="text" placeholder="e.g. B.Sc"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mother Occupation</label>
                            <input v-model="form.mother_occupation" type="text"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mother Qualification</label>
                            <input v-model="form.mother_qualification" type="text" placeholder="e.g. M.A"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Guardian Email</label>
                            <input v-model="form.guardian_email" type="email" placeholder="e.g. parent@email.com"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Guardian Phone</label>
                            <input v-model="form.guardian_phone" type="text" placeholder="Alternate mobile number"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Parent Address</label>
                            <textarea v-model="form.parent_address" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Previous School & Background -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="text-base font-bold text-gray-800 mb-4 pb-2 border-b">Previous School &amp; Background</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Previous School</label>
                            <input v-model="form.previous_school" type="text" placeholder="School name"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Previous Class</label>
                            <input v-model="form.previous_class" type="text" placeholder="e.g. Class 5"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Annual Income</label>
                            <select v-model="form.annual_income"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none bg-white">
                                <option value="">Select Range</option>
                                <option>Below 1 Lakh</option>
                                <option>1-3 Lakhs</option>
                                <option>3-5 Lakhs</option>
                                <option>5-10 Lakhs</option>
                                <option>Above 10 Lakhs</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Transport -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="text-base font-bold text-gray-800 mb-1 pb-2 border-b flex items-center gap-2">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        Transport Route
                        <span class="text-xs font-normal text-gray-400 ml-1">(Optional)</span>
                    </h3>
                    <p class="text-xs text-gray-400 mb-4">Applied when application is approved.</p>
                    <template v-if="routes.length">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Route</label>
                                <select v-model="form.transport_route_id" @change="onRouteChange"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-yellow-400 focus:outline-none">
                                    <option value="">— No Transport —</option>
                                    <option v-for="r in routes" :key="r.id" :value="r.id">{{ r.route_name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Boarding Stop</label>
                                <select v-model="form.transport_stop_id" :disabled="!form.transport_route_id"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-yellow-400 focus:outline-none disabled:bg-gray-50">
                                    <option value="">Select Stop</option>
                                    <option v-for="s in routeStops" :key="s.id" :value="s.id">
                                        {{ s.stop_name }}{{ s.fee ? ' — ₹' + s.fee : '' }}
                                    </option>
                                </select>
                                <p v-if="selectedStop?.fee" class="text-xs text-indigo-600 mt-1 font-medium">
                                    Stop full-term fee: ₹{{ selectedStop.fee }}
                                    <span class="text-gray-400 font-normal">(for {{ standardMonths }} months)</span>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pickup Type</label>
                                <select v-model="form.transport_pickup_type" :disabled="!form.transport_route_id"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-yellow-400 focus:outline-none disabled:bg-gray-50">
                                    <option value="both">Both (Pickup &amp; Drop)</option>
                                    <option value="pickup">Pickup Only</option>
                                    <option value="drop">Drop Only</option>
                                </select>
                            </div>
                        </div>
                        <div v-if="form.transport_route_id" class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Months Opted *</label>
                                <input v-model.number="form.transport_months" type="number" min="0" max="24" step="1"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-yellow-400 focus:outline-none">
                                <p class="text-xs text-gray-400 mt-1">Whole months (0–24)</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Extra Days</label>
                                <input v-model.number="form.transport_days" type="number" min="0" max="30" step="1"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-yellow-400 focus:outline-none">
                                <p class="text-xs text-gray-400 mt-1">0–30 days</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Transport Fee (auto)</label>
                                <div class="px-3 py-2 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-900 leading-snug">
                                    <div class="font-bold">₹{{ transportComputedFee }}</div>
                                    <div class="text-xs text-emerald-700">
                                        {{ form.transport_months || 0 }} mo<template v-if="form.transport_days"> + {{ form.transport_days }} d</template>
                                        = {{ transportMonthsOpted }} months
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                    <p v-else class="text-sm text-gray-400">No active transport routes configured.</p>
                </div>

                <!-- Submit -->
                <div class="flex justify-end gap-3">
                    <Link :href="`/school/registrations/${application.id}`"
                          class="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                        Cancel
                    </Link>
                    <Button variant="warning" type="submit" :loading="form.processing"
                           >
                        <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        ✏️ Save Changes
                    </Button>
                </div>
            </form>
        </div>
    </SchoolLayout>
</template>
