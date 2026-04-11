<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    student: Object,
});

const h = props.student.health_record || {};

const form = useForm({
    // Physical
    height_cm:                  h.height_cm || '',
    weight_kg:                  h.weight_kg || '',
    vision_left:                h.vision_left || '',
    vision_right:               h.vision_right || '',
    hearing:                    h.hearing || 'Normal',

    // Medical
    known_allergies:            h.known_allergies || '',
    chronic_conditions:         h.chronic_conditions || '',
    current_medications:        h.current_medications || '',
    past_surgeries:             h.past_surgeries || '',
    disability:                 h.disability || '',
    special_needs:              h.special_needs || '',

    // Vaccinations
    vaccinations: h.vaccinations || [],

    // Emergency
    emergency_contact_name:     h.emergency_contact_name || '',
    emergency_contact_phone:    h.emergency_contact_phone || '',
    emergency_contact_relation: h.emergency_contact_relation || '',

    // Doctor
    family_doctor_name:         h.family_doctor_name || '',
    family_doctor_phone:        h.family_doctor_phone || '',
    remarks:                    h.remarks || '',
});

// BMI calculation
const bmi = computed(() => {
    if (!form.height_cm || !form.weight_kg) return null;
    const h = form.height_cm / 100;
    return (form.weight_kg / (h * h)).toFixed(1);
});

const bmiCategory = computed(() => {
    if (!bmi.value) return null;
    const b = parseFloat(bmi.value);
    if (b < 18.5) return { label: 'Underweight', cls: 'bmi-blue' };
    if (b < 25)   return { label: 'Normal',      cls: 'bmi-green' };
    if (b < 30)   return { label: 'Overweight',  cls: 'bmi-amber' };
    return             { label: 'Obese',          cls: 'bmi-red' };
});

// Vaccination management
const addVaccination = () => {
    form.vaccinations.push({ name: '', date: '', dose: '', notes: '' });
};

const removeVaccination = (index) => {
    form.vaccinations.splice(index, 1);
};

const submit = () => {
    form.put(`/school/students/${props.student.id}/health`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <SchoolLayout :title="`Health Record — ${student.first_name} ${student.last_name || ''}`">

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-left">
                <Link :href="`/school/students/${student.id}`" class="back-btn" aria-label="Back">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </Link>
                <div>
                    <h1 class="page-header-title health-page-title">
                        <span class="health-icon-wrap">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        </span>
                        Health Record
                    </h1>
                    <p class="page-header-sub">{{ student.first_name }} {{ student.last_name }} &middot; {{ student.admission_no }}</p>
                </div>
            </div>
        </div>

        <!-- Quick stat cards -->
        <div class="health-stats" v-if="form.height_cm || form.weight_kg || bmi">
            <div class="stat-card" v-if="form.height_cm">
                <div class="stat-card-icon" style="background:#eff6ff; color:#3b82f6">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <div class="stat-card-value">{{ form.height_cm }} cm</div>
                    <div class="stat-card-label">Height</div>
                </div>
            </div>
            <div class="stat-card" v-if="form.weight_kg">
                <div class="stat-card-icon" style="background:#f0fdf4; color:#16a34a">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                </div>
                <div>
                    <div class="stat-card-value">{{ form.weight_kg }} kg</div>
                    <div class="stat-card-label">Weight</div>
                </div>
            </div>
            <div class="stat-card" v-if="bmi">
                <div class="stat-card-icon" :class="{
                    'bmi-icon-blue':  bmiCategory?.cls === 'bmi-blue',
                    'bmi-icon-green': bmiCategory?.cls === 'bmi-green',
                    'bmi-icon-amber': bmiCategory?.cls === 'bmi-amber',
                    'bmi-icon-red':   bmiCategory?.cls === 'bmi-red',
                }">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <div>
                    <div class="stat-card-value">{{ bmi }}</div>
                    <div class="stat-card-label">BMI — {{ bmiCategory?.label }}</div>
                </div>
            </div>
            <div class="stat-card" v-if="form.vaccinations.length > 0">
                <div class="stat-card-icon" style="background:#fdf4ff; color:#a21caf">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <div>
                    <div class="stat-card-value">{{ form.vaccinations.length }}</div>
                    <div class="stat-card-label">Vaccines on record</div>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="health-form">

            <!-- 1. Physical Measurements -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title section-title">
                        <span class="section-badge section-badge--red">1</span>
                        Physical Measurements
                    </h3>
                </div>
                <div class="card-body">
                    <div class="form-grid form-grid--4">
                        <div class="form-field">
                            <label class="form-label">Height (cm)</label>
                            <input v-model="form.height_cm" type="number" step="0.1" min="0" placeholder="e.g. 152" class="form-input">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Weight (kg)</label>
                            <input v-model="form.weight_kg" type="number" step="0.1" min="0" placeholder="e.g. 45" class="form-input">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Vision (Left)</label>
                            <input v-model="form.vision_left" type="text" placeholder="e.g. 6/6" class="form-input">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Vision (Right)</label>
                            <input v-model="form.vision_right" type="text" placeholder="e.g. 6/9" class="form-input">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Hearing</label>
                            <select v-model="form.hearing" class="form-input">
                                <option>Normal</option>
                                <option>Mild Impairment</option>
                                <option>Moderate Impairment</option>
                                <option>Severe Impairment</option>
                                <option>Deaf</option>
                            </select>
                        </div>
                        <div v-if="bmi" class="bmi-live-wrap">
                            <div class="bmi-live" :class="bmiCategory?.cls">
                                <span class="bmi-live-value">BMI {{ bmi }}</span>
                                <span class="bmi-live-label">{{ bmiCategory?.label }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Medical Information -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title section-title">
                        <span class="section-badge section-badge--red">2</span>
                        Medical Information
                    </h3>
                </div>
                <div class="card-body">
                    <div class="form-grid form-grid--2">
                        <div class="form-field">
                            <label class="form-label">Known Allergies</label>
                            <textarea v-model="form.known_allergies" rows="3" placeholder="e.g. Penicillin, Peanuts, Dust..." class="form-input form-textarea"></textarea>
                            <p v-if="form.errors.known_allergies" class="form-error">{{ form.errors.known_allergies }}</p>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Chronic Conditions</label>
                            <textarea v-model="form.chronic_conditions" rows="3" placeholder="e.g. Asthma, Diabetes, Epilepsy..." class="form-input form-textarea"></textarea>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Current Medications</label>
                            <textarea v-model="form.current_medications" rows="3" placeholder="List any ongoing medications..." class="form-input form-textarea"></textarea>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Past Surgeries / Hospitalizations</label>
                            <textarea v-model="form.past_surgeries" rows="3" placeholder="e.g. Appendix removed 2022..." class="form-input form-textarea"></textarea>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Disability (if any)</label>
                            <select v-model="form.disability" class="form-input">
                                <option value="">None</option>
                                <option>Physical Disability</option>
                                <option>Visual Impairment</option>
                                <option>Hearing Impairment</option>
                                <option>Learning Disability</option>
                                <option>Intellectual Disability</option>
                                <option>Autism Spectrum</option>
                                <option>Multiple Disabilities</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Special Needs / Accommodations</label>
                            <input v-model="form.special_needs" type="text" placeholder="e.g. Requires wheelchair access..." class="form-input">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Vaccination Record -->
            <div class="card">
                <div class="card-header vax-header">
                    <h3 class="card-title section-title">
                        <span class="section-badge section-badge--red">3</span>
                        Vaccination Record
                        <span v-if="form.vaccinations.length > 0" class="badge badge-green">{{ form.vaccinations.length }}</span>
                    </h3>
                    <Button size="sm" type="button" @click="addVaccination">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        Add Vaccine
                    </Button>
                </div>
                <div class="card-body">
                    <div v-if="form.vaccinations.length === 0" class="empty-vax">
                        <div class="empty-vax-icon">
                            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <p class="empty-vax-text">No vaccination records added.</p>
                        <p class="empty-vax-sub">Click "Add Vaccine" to begin building the immunisation history.</p>
                    </div>
                    <div v-else class="vax-list">
                        <div v-for="(vac, idx) in form.vaccinations" :key="idx" class="vax-row">
                            <div class="vax-idx">{{ idx + 1 }}</div>
                            <div class="form-field vax-name">
                                <label class="form-label">Vaccine Name</label>
                                <input v-model="vac.name" type="text" placeholder="e.g. Hepatitis B" class="form-input">
                            </div>
                            <div class="form-field vax-date">
                                <label class="form-label">Date</label>
                                <input v-model="vac.date" type="date" class="form-input">
                            </div>
                            <div class="form-field vax-dose">
                                <label class="form-label">Dose</label>
                                <input v-model="vac.dose" type="text" placeholder="1st / Booster" class="form-input">
                            </div>
                            <div class="form-field vax-notes">
                                <label class="form-label">Notes</label>
                                <input v-model="vac.notes" type="text" placeholder="Optional notes..." class="form-input">
                            </div>
                            <Button variant="danger" size="xs" type="button" @click="removeVaccination(idx)" aria-label="Remove" class="vax-remove">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Emergency & Doctor -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title section-title">
                        <span class="section-badge section-badge--red">4</span>
                        Emergency Contact &amp; Doctor
                    </h3>
                </div>
                <div class="card-body">
                    <div class="contact-grid">
                        <!-- Emergency Contact -->
                        <div class="contact-block">
                            <div class="contact-block-header">
                                <span class="contact-block-dot" style="background:#ef4444"></span>
                                <h4 class="contact-block-title">Medical Emergency Contact</h4>
                            </div>
                            <div class="form-field">
                                <label class="form-label">Contact Name</label>
                                <input v-model="form.emergency_contact_name" type="text" placeholder="Full name" class="form-input">
                            </div>
                            <div class="form-field">
                                <label class="form-label">Phone</label>
                                <input v-model="form.emergency_contact_phone" type="text" placeholder="Mobile number" class="form-input">
                            </div>
                            <div class="form-field">
                                <label class="form-label">Relation to Student</label>
                                <input v-model="form.emergency_contact_relation" type="text" placeholder="e.g. Uncle, Grandmother" class="form-input">
                            </div>
                        </div>
                        <!-- Family Doctor -->
                        <div class="contact-block">
                            <div class="contact-block-header">
                                <span class="contact-block-dot" style="background:#3b82f6"></span>
                                <h4 class="contact-block-title">Family Doctor / Paediatrician</h4>
                            </div>
                            <div class="form-field">
                                <label class="form-label">Doctor Name</label>
                                <input v-model="form.family_doctor_name" type="text" placeholder="Dr. Name" class="form-input">
                            </div>
                            <div class="form-field">
                                <label class="form-label">Doctor Phone</label>
                                <input v-model="form.family_doctor_phone" type="text" placeholder="Clinic / Mobile number" class="form-input">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Additional Remarks -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title section-title">
                        <span class="section-badge section-badge--red">5</span>
                        Additional Remarks
                    </h3>
                </div>
                <div class="card-body">
                    <div class="form-field">
                        <textarea v-model="form.remarks" rows="4"
                            placeholder="Any other medical notes, dietary restrictions, or important health information..."
                            class="form-input form-textarea"></textarea>
                    </div>
                </div>
            </div>

            <!-- Form actions -->
            <div class="form-actions">
                <Button variant="secondary" as="link" :href="`/school/students/${student.id}`">Cancel</Button>
                <Button type="submit" :loading="form.processing">
                    <svg v-if="form.processing" class="spin-icon" width="15" height="15" fill="none" viewBox="0 0 24 24">
                        <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                    </svg>
                    <svg v-else width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Save Health Record
                </Button>
            </div>

        </form>
    </SchoolLayout>
</template>

<style scoped>
/* ── Header ── */
.page-header-left { display: flex; align-items: center; gap: .875rem; }
.back-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--surface);
    color: #64748b;
    text-decoration: none;
    transition: background .15s, color .15s;
    flex-shrink: 0;
}
.back-btn:hover { background: #f1f5f9; color: #1e293b; }
.health-page-title { display: flex; align-items: center; gap: .5rem; }
.health-icon-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 7px;
    background: #fee2e2;
    color: #ef4444;
    flex-shrink: 0;
}

/* ── Stat cards row ── */
.health-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.25rem;
}
.health-stats .stat-card {
    display: flex;
    align-items: center;
    gap: .875rem;
    flex: 1;
    min-width: 140px;
}
.bmi-icon-blue  { background: #eff6ff; color: #3b82f6; }
.bmi-icon-green { background: #f0fdf4; color: #16a34a; }
.bmi-icon-amber { background: #fffbeb; color: #b45309; }
.bmi-icon-red   { background: #fef2f2; color: #dc2626; }

/* ── Section badges ── */
.section-title { display: flex; align-items: center; gap: .5rem; }
.section-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 5px;
    font-size: .6875rem;
    font-weight: 800;
    flex-shrink: 0;
}
.section-badge--red { background: #fee2e2; color: #dc2626; }

/* ── Form layout ── */
.health-form { display: flex; flex-direction: column; gap: 1.125rem; padding-bottom: 3rem; }

.form-grid { display: grid; gap: 1rem; }
.form-grid--2 { grid-template-columns: repeat(2, 1fr); }
.form-grid--4 { grid-template-columns: repeat(4, 1fr); }
@media (max-width: 900px) { .form-grid--4 { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .form-grid--2, .form-grid--4 { grid-template-columns: 1fr; } }

.form-label {
    display: block;
    font-size: .8125rem;
    font-weight: 500;
    color: #374151;
    margin-bottom: .3rem;
}
.form-input {
    width: 100%;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: .4375rem .75rem;
    font-size: .875rem;
    font-family: inherit;
    background: #fff;
    transition: border-color .15s, box-shadow .15s;
    box-sizing: border-box;
}
.form-input:focus {
    outline: none;
    border-color: #ef4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, .12);
}
.form-textarea { resize: vertical; min-height: 72px; }

/* ── BMI live badge ── */
.bmi-live-wrap { display: flex; align-items: center; }
.bmi-live {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .4rem .875rem;
    border-radius: 999px;
    font-size: .8125rem;
}
.bmi-live-value { font-weight: 700; }
.bmi-live-label { font-size: .75rem; opacity: .75; }
.bmi-blue  { background: #eff6ff; color: #1d4ed8; }
.bmi-green { background: #f0fdf4; color: #15803d; }
.bmi-amber { background: #fffbeb; color: #b45309; }
.bmi-red   { background: #fef2f2; color: #dc2626; }

/* ── Vaccination ── */
.vax-header { justify-content: space-between; }

.empty-vax {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .375rem;
    padding: 2.5rem 1rem;
    text-align: center;
}
.empty-vax-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: #f1f5f9;
    color: #94a3b8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: .25rem;
}
.empty-vax-text { font-size: .875rem; font-weight: 600; color: #475569; margin: 0; }
.empty-vax-sub  { font-size: .8125rem; color: #94a3b8; margin: 0; }

.vax-list { display: flex; flex-direction: column; gap: .625rem; }
.vax-row {
    display: flex;
    align-items: flex-end;
    gap: .625rem;
    background: #f8fafc;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: .75rem;
}
.vax-idx {
    font-size: .6875rem;
    font-weight: 700;
    color: #94a3b8;
    min-width: 18px;
    text-align: center;
    padding-bottom: .5rem;
}
.vax-name  { flex: 2; }
.vax-date  { flex: 1.5; }
.vax-dose  { flex: 1.2; }
.vax-notes { flex: 2.5; }
.vax-remove { align-self: flex-end; margin-bottom: .05rem; }

/* ── Emergency / Doctor grid ── */
.contact-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}
@media (max-width: 680px) { .contact-grid { grid-template-columns: 1fr; } }
.contact-block { display: flex; flex-direction: column; gap: .75rem; }
.contact-block-header { display: flex; align-items: center; gap: .5rem; margin-bottom: .25rem; }
.contact-block-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.contact-block-title {
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #475569;
    margin: 0;
}

/* ── Form actions ── */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: .625rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}
.spin-icon { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
