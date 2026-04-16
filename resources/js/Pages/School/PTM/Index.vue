<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ sessions: Array });

const showCreate = ref(false);

const form = useForm({
    title: '', date: '', start_time: '09:00', end_time: '13:00',
    slot_duration_minutes: 15, description: '', staff_ids: [],
});

const submit = () => {
    form.post('/school/ptm', { preserveScroll: true, onSuccess: () => { showCreate.value = false; form.reset(); } });
};

const updateStatus = (id, status) => {
    router.patch(`/school/ptm/${id}/status`, { status }, { preserveScroll: true });
};

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' }) : '—';

const statusConfig = {
    draft:  { label: 'Draft',  color: '#64748b', bg: '#f1f5f9', dot: '#94a3b8' },
    open:   { label: 'Open',   color: '#059669', bg: '#d1fae5', dot: '#10b981' },
    closed: { label: 'Closed', color: '#b45309', bg: '#fef3c7', dot: '#f59e0b' },
};

const stats = computed(() => ({
    total:  props.sessions?.length ?? 0,
    open:   props.sessions?.filter(s => s.status === 'open').length ?? 0,
    totalBookings: props.sessions?.reduce((a, s) => a + (s.bookings_count ?? 0), 0) ?? 0,
}));
</script>

<template>
    <SchoolLayout title="Parent-Teacher Meetings">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Parent-Teacher Meetings</h1>
                <p style="font-size:.8125rem;color:#64748b;margin:2px 0 0;">Schedule sessions, manage slots and track bookings</p>
            </div>
            <Button @click="showCreate = true">
                <template #icon>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                </template>
                New Session
            </Button>
        </div>

        <!-- KPI strip -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
            <div class="card">
                <div class="card-body" style="display:flex;align-items:center;gap:16px;padding:18px 20px;">
                    <div style="width:44px;height:44px;border-radius:12px;background:#ede9fe;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#7c3aed" width="22" height="22"><path d="M6.75 2.25A.75.75 0 017.5 3v1.5h9V3A.75.75 0 0118 3v1.5h.75A2.25 2.25 0 0121 6.75v13.5A2.25 2.25 0 0118.75 22.5H5.25A2.25 2.25 0 013 20.25V6.75A2.25 2.25 0 015.25 4.5H6V3a.75.75 0 01.75-.75zm13.5 9H3.75v9a.75.75 0 00.75.75h15a.75.75 0 00.75-.75v-9z"/></svg>
                    </div>
                    <div>
                        <div style="font-size:1.5rem;font-weight:700;color:#1e293b;line-height:1;">{{ stats.total }}</div>
                        <div style="font-size:.75rem;color:#64748b;margin-top:3px;">Total Sessions</div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body" style="display:flex;align-items:center;gap:16px;padding:18px 20px;">
                    <div style="width:44px;height:44px;border-radius:12px;background:#d1fae5;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#059669" width="22" height="22"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd"/></svg>
                    </div>
                    <div>
                        <div style="font-size:1.5rem;font-weight:700;color:#059669;line-height:1;">{{ stats.open }}</div>
                        <div style="font-size:.75rem;color:#64748b;margin-top:3px;">Open for Booking</div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body" style="display:flex;align-items:center;gap:16px;padding:18px 20px;">
                    <div style="width:44px;height:44px;border-radius:12px;background:#dbeafe;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#2563eb" width="22" height="22"><path d="M4.5 6.375a4.125 4.125 0 118.25 0 4.125 4.125 0 01-8.25 0zM14.25 8.625a3.375 3.375 0 116.75 0 3.375 3.375 0 01-6.75 0zM1.5 19.125a7.125 7.125 0 0114.25 0v.003l-.001.119a.75.75 0 01-.363.63 13.067 13.067 0 01-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 01-.364-.63l-.001-.122zM17.25 19.128l-.001.144a2.25 2.25 0 01-.233.96 10.088 10.088 0 005.06-1.01.75.75 0 00.42-.643 4.875 4.875 0 00-6.957-4.611 8.586 8.586 0 011.71 5.157v.003z"/></svg>
                    </div>
                    <div>
                        <div style="font-size:1.5rem;font-weight:700;color:#2563eb;line-height:1;">{{ stats.totalBookings }}</div>
                        <div style="font-size:.75rem;color:#64748b;margin-top:3px;">Total Bookings</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Session Cards -->
        <div v-if="sessions?.length" style="display:flex;flex-direction:column;gap:12px;">
            <div v-for="s in sessions" :key="s.id" class="card ptm-card">
                <div class="card-body" style="padding:20px 24px;">
                    <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">

                        <!-- Date badge -->
                        <div style="flex-shrink:0;width:52px;text-align:center;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:8px 4px;line-height:1.2;">
                            <div style="font-size:.65rem;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:.05em;">
                                {{ new Date(s.date).toLocaleDateString('en-IN',{month:'short'}) }}
                            </div>
                            <div style="font-size:1.5rem;font-weight:800;color:#1e293b;">
                                {{ new Date(s.date).getDate() }}
                            </div>
                            <div style="font-size:.6rem;color:#94a3b8;">
                                {{ new Date(s.date).toLocaleDateString('en-IN',{weekday:'short'}) }}
                            </div>
                        </div>

                        <!-- Main info -->
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                                <Link :href="`/school/ptm/${s.id}`"
                                      style="font-size:1rem;font-weight:700;color:#1e293b;text-decoration:none;">
                                    {{ s.title }}
                                </Link>
                                <span :style="{
                                    display:'inline-flex', alignItems:'center', gap:'5px',
                                    padding:'2px 10px', borderRadius:'20px', fontSize:'.72rem', fontWeight:700,
                                    background: statusConfig[s.status]?.bg,
                                    color: statusConfig[s.status]?.color,
                                }">
                                    <span :style="{ width:'6px',height:'6px',borderRadius:'50%',background:statusConfig[s.status]?.dot,display:'inline-block' }"></span>
                                    {{ statusConfig[s.status]?.label }}
                                </span>
                            </div>

                            <div style="display:flex;gap:20px;flex-wrap:wrap;">
                                <div style="display:flex;align-items:center;gap:5px;font-size:.8rem;color:#475569;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#94a3b8" width="14" height="14"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/></svg>
                                    {{ s.start_time?.slice(0,5) }} – {{ s.end_time?.slice(0,5) }}
                                </div>
                                <div style="display:flex;align-items:center;gap:5px;font-size:.8rem;color:#475569;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#94a3b8" width="14" height="14"><path d="M5.25 12a.75.75 0 01.75-.75h.01a.75.75 0 01.75.75v.01a.75.75 0 01-.75.75H6a.75.75 0 01-.75-.75V12zM6 13.25a.75.75 0 00-.75.75v.01c0 .414.336.75.75.75h.01a.75.75 0 00.75-.75V14a.75.75 0 00-.75-.75H6zM7.25 12a.75.75 0 01.75-.75h.01a.75.75 0 01.75.75v.01a.75.75 0 01-.75.75H8a.75.75 0 01-.75-.75V12zM8 13.25a.75.75 0 00-.75.75v.01c0 .414.336.75.75.75h.01a.75.75 0 00.75-.75V14A.75.75 0 008 13.25h-.01zM9.25 10a.75.75 0 01.75-.75h.01a.75.75 0 01.75.75v.01a.75.75 0 01-.75.75H10a.75.75 0 01-.75-.75V10zM10 11.25a.75.75 0 00-.75.75v.01c0 .414.336.75.75.75h.01a.75.75 0 00.75-.75V12a.75.75 0 00-.75-.75H10zM9.25 14a.75.75 0 01.75-.75h.01a.75.75 0 01.75.75v.01a.75.75 0 01-.75.75H10a.75.75 0 01-.75-.75V14zM12 9.25a.75.75 0 00-.75.75v.01c0 .414.336.75.75.75h.01a.75.75 0 00.75-.75V10a.75.75 0 00-.75-.75H12z"/><path fill-rule="evenodd" d="M6.75 2.25A.75.75 0 017.5 3v1.5h9V3A.75.75 0 0118 3v1.5h.75A2.25 2.25 0 0121 6.75v13.5A2.25 2.25 0 0118.75 22.5H5.25A2.25 2.25 0 013 20.25V6.75A2.25 2.25 0 015.25 4.5H6V3a.75.75 0 01.75-.75zm13.5 9H3.75v9a.75.75 0 00.75.75h15a.75.75 0 00.75-.75v-9z" clip-rule="evenodd"/></svg>
                                    {{ s.slot_duration_minutes }} min slots
                                </div>
                                <div style="display:flex;align-items:center;gap:5px;font-size:.8rem;color:#475569;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#94a3b8" width="14" height="14"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zM6 8a2 2 0 11-4 0 2 2 0 014 0zM1.49 15.326a.78.78 0 01-.358-.442 3 3 0 014.308-3.516 6.484 6.484 0 00-1.905 3.959c-.023.222-.014.442.025.654a4.97 4.97 0 01-2.07-.655zM16.44 15.98a4.97 4.97 0 002.07-.654.78.78 0 00.357-.442 3 3 0 00-4.308-3.517 6.484 6.484 0 011.907 3.96 2.32 2.32 0 01-.026.654zM18 8a2 2 0 11-4 0 2 2 0 014 0zM5.304 16.19a.844.844 0 01-.277-.71 5 5 0 019.947 0 .843.843 0 01-.277.71A6.975 6.975 0 0110 18a6.974 6.974 0 01-4.696-1.81z"/></svg>
                                    {{ s.bookings_count ?? 0 }} / {{ s.slots_count ?? 0 }} booked
                                </div>
                            </div>

                            <!-- Booking progress bar -->
                            <div v-if="s.slots_count > 0" style="margin-top:10px;">
                                <div style="height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden;">
                                    <div :style="{
                                        height:'100%', borderRadius:'3px', transition:'width .3s',
                                        background: s.status === 'open' ? '#6366f1' : '#94a3b8',
                                        width: Math.round((s.bookings_count / s.slots_count) * 100) + '%'
                                    }"></div>
                                </div>
                                <div style="font-size:.68rem;color:#94a3b8;margin-top:3px;">
                                    {{ Math.round((s.bookings_count / s.slots_count) * 100) }}% slots filled
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div style="display:flex;gap:8px;align-items:center;flex-shrink:0;">
                            <Button v-if="s.status === 'draft'" variant="success" size="sm" @click="updateStatus(s.id, 'open')">
                                Open Bookings
                            </Button>
                            <Button v-if="s.status === 'open'" variant="warning" size="sm" @click="updateStatus(s.id, 'closed')">
                                Close
                            </Button>
                            <Button variant="secondary" size="sm" as="link" :href="`/school/ptm/${s.id}`">
                                View Details →
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="card">
            <div class="card-body" style="text-align:center;padding:64px 40px;">
                <div style="width:64px;height:64px;background:#f1f5f9;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#94a3b8" width="32" height="32"><path d="M6.75 2.25A.75.75 0 017.5 3v1.5h9V3A.75.75 0 0118 3v1.5h.75A2.25 2.25 0 0121 6.75v13.5A2.25 2.25 0 0118.75 22.5H5.25A2.25 2.25 0 013 20.25V6.75A2.25 2.25 0 015.25 4.5H6V3a.75.75 0 01.75-.75zm13.5 9H3.75v9a.75.75 0 00.75.75h15a.75.75 0 00.75-.75v-9z"/></svg>
                </div>
                <h3 style="font-size:1rem;font-weight:600;color:#1e293b;margin:0 0 6px;">No PTM Sessions Yet</h3>
                <p style="font-size:.875rem;color:#64748b;margin:0 0 20px;">Create your first parent-teacher meeting session to get started.</p>
                <Button @click="showCreate = true">+ Create First Session</Button>
            </div>
        </div>

        <!-- Create Modal -->
        <Teleport to="body">
            <div v-if="showCreate" class="modal-backdrop" @click.self="showCreate = false">
                <div class="modal" style="max-width:520px;width:100%;">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title">New PTM Session</h3>
                            <p style="font-size:.78rem;color:#64748b;margin:2px 0 0;">Schedule a parent-teacher meeting</p>
                        </div>
                        <button @click="showCreate = false" class="modal-close">&times;</button>
                    </div>
                    <form @submit.prevent="submit">
                        <div class="modal-body" style="display:flex;flex-direction:column;gap:16px;">
                            <div class="form-field">
                                <label>Session Title *</label>
                                <input v-model="form.title" required placeholder="e.g. Mid-Term PTM 2026" />
                                <span v-if="form.errors.title" class="form-error">{{ form.errors.title }}</span>
                            </div>
                            <div class="form-field">
                                <label>Date *</label>
                                <input v-model="form.date" type="date" required />
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                                <div class="form-field" style="margin:0;">
                                    <label>Start Time *</label>
                                    <input v-model="form.start_time" type="time" required />
                                </div>
                                <div class="form-field" style="margin:0;">
                                    <label>End Time *</label>
                                    <input v-model="form.end_time" type="time" required />
                                </div>
                                <div class="form-field" style="margin:0;">
                                    <label>Slot (min) *</label>
                                    <input v-model="form.slot_duration_minutes" type="number" min="5" max="60" required />
                                </div>
                            </div>
                            <div class="form-field">
                                <label>Description</label>
                                <textarea v-model="form.description" rows="2" placeholder="Optional notes for parents..."></textarea>
                            </div>
                            <div style="font-size:.78rem;color:#6366f1;background:#eef2ff;padding:10px 14px;border-radius:8px;border-left:3px solid #6366f1;">
                                After creating the session, open it and add teacher slots from the detail page.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <Button variant="secondary" type="button" @click="showCreate = false">Cancel</Button>
                            <Button type="submit" :loading="form.processing">Create Session</Button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

    </SchoolLayout>
</template>

<style scoped>
.ptm-card { transition: box-shadow .15s, transform .15s; }
.ptm-card:hover { box-shadow: 0 4px 20px rgba(99,102,241,.1); transform: translateY(-1px); }
.modal-backdrop { position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,.5);backdrop-filter:blur(2px);display:flex;align-items:center;justify-content:center;z-index:1000; }
.modal { background:#fff;border-radius:14px;box-shadow:0 20px 40px rgba(0,0,0,.12); }
.modal-header { padding:20px 24px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:flex-start; }
.modal-title { font-size:1.0625rem;font-weight:700;color:#1e293b;margin:0; }
.modal-close { background:none;border:none;font-size:1.5rem;line-height:1;color:#94a3b8;cursor:pointer;padding:0 2px; }
.modal-body { padding:22px 24px; }
.modal-footer { padding:16px 24px;border-top:1px solid #e2e8f0;background:#f8fafc;border-radius:0 0 14px 14px;display:flex;justify-content:flex-end;gap:10px; }
.form-error { font-size:.75rem;color:#ef4444;margin-top:3px;display:block; }
</style>
