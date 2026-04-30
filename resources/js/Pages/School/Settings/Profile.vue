<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const page = usePage();
const school = page.props.school;

const form = useForm({
    name: school?.name || '',
    code: school?.code || '',
    board: school?.board || '',
    affiliation_no: school?.affiliation_no || '',
    udise_code: school?.udise_code || '',
    phone: school?.phone || '',
    email: school?.email || '',
    website: school?.website || '',
    address: school?.address || '',
    city: school?.city || '',
    state: school?.state || '',
    pincode: school?.pincode || '',
    principal_name: school?.principal_name || '',
});

const submit = () => {
    form.put('/school/settings/profile', { preserveScroll: true });
};
</script>

<template>
    <SchoolLayout title="School Profile">

        <PageHeader title="School Profile" subtitle="Manage your school's identity, contact details, and affiliation information." />

        <form @submit.prevent="submit">

            <!-- Identity Section -->
            <div class="card" style="margin-bottom:1.25rem">
                <div class="card-header">
                    <h2 class="card-title">
                        <span class="section-icon section-icon--indigo">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </span>
                        School Identity
                    </h2>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-field">
                            <label>School Name <span class="req">*</span></label>
                            <input v-model="form.name" type="text" placeholder="e.g. Delhi Public School" required />
                            <span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
                        </div>
                        <div class="form-field">
                            <label>School Code / Slug <span class="req">*</span></label>
                            <input v-model="form.code" type="text" placeholder="e.g. dps-rohini" required />
                            <span v-if="form.errors.code" class="form-error">{{ form.errors.code }}</span>
                        </div>
                        <div class="form-field">
                            <label>Board Type <span class="req">*</span></label>
                            <select v-model="form.board" required>
                                <option value="">Select Board</option>
                                <option value="CBSE">CBSE</option>
                                <option value="ICSE">ICSE</option>
                                <option value="State Board">State Board</option>
                            </select>
                            <span v-if="form.errors.board" class="form-error">{{ form.errors.board }}</span>
                        </div>
                        <div class="form-field">
                            <label>Principal Name</label>
                            <input v-model="form.principal_name" type="text" placeholder="Full name of principal" />
                        </div>
                        <div class="form-field">
                            <label>Affiliation No. <span class="hint-label">(Optional)</span></label>
                            <input v-model="form.affiliation_no" type="text" placeholder="Board affiliation number" />
                        </div>
                        <div class="form-field">
                            <label>UDISE Code <span class="hint-label">(Optional)</span></label>
                            <input v-model="form.udise_code" type="text" placeholder="11-digit UDISE code" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Section -->
            <div class="card" style="margin-bottom:1.25rem">
                <div class="card-header">
                    <h2 class="card-title">
                        <span class="section-icon section-icon--emerald">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </span>
                        Contact Information
                    </h2>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-field">
                            <label>Phone</label>
                            <div class="input-with-icon">
                                <svg class="input-icon" width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <input v-model="form.phone" type="text" placeholder="+91 00000 00000" class="has-icon" />
                            </div>
                        </div>
                        <div class="form-field">
                            <label>Email</label>
                            <div class="input-with-icon">
                                <svg class="input-icon" width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <input v-model="form.email" type="email" placeholder="school@example.com" class="has-icon" />
                            </div>
                        </div>
                        <div class="form-field" style="grid-column:span 2">
                            <label>Website</label>
                            <div class="input-with-icon">
                                <svg class="input-icon" width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                <input v-model="form.website" type="text" placeholder="https://www.yourschool.edu.in" class="has-icon" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address Section -->
            <div class="card" style="margin-bottom:1.5rem">
                <div class="card-header">
                    <h2 class="card-title">
                        <span class="section-icon section-icon--amber">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </span>
                        Address
                    </h2>
                </div>
                <div class="card-body">
                    <div class="form-field" style="margin-bottom:1.1rem">
                        <label>Street Address</label>
                        <textarea v-model="form.address" rows="2" placeholder="Building, street, area…"></textarea>
                    </div>
                    <div class="form-grid form-grid--3">
                        <div class="form-field">
                            <label>City</label>
                            <input v-model="form.city" type="text" placeholder="New Delhi" />
                        </div>
                        <div class="form-field">
                            <label>State</label>
                            <input v-model="form.state" type="text" placeholder="Delhi" />
                        </div>
                        <div class="form-field">
                            <label>Pincode</label>
                            <input v-model="form.pincode" type="text" placeholder="110001" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Bar -->
            <div class="save-bar">
                <transition name="fade">
                    <span v-if="form.recentlySuccessful" class="save-success">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        Changes saved successfully
                    </span>
                </transition>
                <div class="save-bar-actions">
                    <Button variant="secondary" type="button" @click="form.reset()">Reset</Button>
                    <Button type="submit" :loading="form.processing">
                        <svg v-if="form.processing" class="spin-icon" width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <svg v-else width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                        Save Profile
                    </Button>
                </div>
            </div>

        </form>

    </SchoolLayout>
</template>

<style scoped>
/* ── Form grids ── */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.1rem 1.5rem;
}
.form-grid--3 {
    grid-template-columns: 1fr 1fr 1fr;
}

.form-field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.form-field label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #475569;
    letter-spacing: 0.01em;
}

.form-field input,
.form-field select,
.form-field textarea {
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    color: #1e293b;
    background: var(--surface);
    transition: border-color 0.15s, box-shadow 0.15s;
    outline: none;
    width: 100%;
    box-sizing: border-box;
}
.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}
.form-field textarea {
    resize: vertical;
    min-height: 68px;
    line-height: 1.5;
}

/* ── Input with icon ── */
.input-with-icon {
    position: relative;
}
.input-icon {
    position: absolute;
    left: 0.65rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
}
.input-with-icon input.has-icon {
    padding-left: 2.2rem;
}

/* ── Labels ── */
.req  { color: var(--danger); margin-left: 1px; }
.hint-label { font-size: 0.71rem; color: #94a3b8; font-weight: 400; margin-left: 2px; }

/* ── Section icon ── */
.section-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 7px;
    flex-shrink: 0;
}
.section-icon--indigo { background: #eef2ff; color: var(--accent); }
.section-icon--emerald { background: #ecfdf5; color: var(--success); }
.section-icon--amber   { background: #fffbeb; color: var(--warning); }

.card-title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.9rem;
}

/* ── Save bar ── */
.save-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.9rem 1.25rem;
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    flex-wrap: wrap;
}
.save-bar-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-left: auto;
}
.save-success {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.83rem;
    font-weight: 600;
    color: var(--success);
    background: #ecfdf5;
    border: 1px solid #6ee7b7;
    padding: 0.3rem 0.75rem;
    border-radius: 20px;
}

/* ── Spinner ── */
.spin-icon { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Fade transition ── */
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* ── Responsive ── */
@media (max-width: 640px) {
    .form-grid { grid-template-columns: 1fr; }
    .form-grid--3 { grid-template-columns: 1fr; }
    .form-field[style*="span 2"] { grid-column: span 1 !important; }
    .save-bar { flex-direction: column; align-items: stretch; }
    .save-bar-actions { margin-left: 0; justify-content: flex-end; }
}
</style>
