<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    stats: { type: Object, required: true },
    occupancy: { type: Array, default: () => [] },
});

const expandedHostel = ref(null);

const cards = computed(() => [
    { title: 'Total Beds', value: props.stats.total_beds, color: '#6366f1' },
    { title: 'Available Beds', value: props.stats.available_beds, color: '#22c55e' },
    { title: 'Occupied Beds', value: props.stats.occupied_beds, color: '#f97316' },
    { title: 'Active Students', value: props.stats.active_students, color: '#8b5cf6' },
    { title: 'Pending Leaves', value: props.stats.pending_leaves, color: '#f59e0b' },
    { title: 'Students Out', value: props.stats.students_on_leave, color: '#ef4444' },
    { title: 'Open Complaints', value: props.stats.open_complaints, color: '#ec4899' },
]);

const quickLinks = [
    { label: 'Manage Hostels', href: '/school/hostel/hostels' },
    { label: 'Rooms & Beds', href: '/school/hostel/rooms' },
    { label: 'Student Allocations', href: '/school/hostel/allocations' },
    { label: 'Fee Collection', href: '/school/hostel/fees' },
    { label: 'Gate Passes', href: '/school/hostel/gate-passes' },
    { label: 'Visitor Logs', href: '/school/hostel/visitors' },
    { label: 'Mess Menu', href: '/school/hostel/mess' },
    { label: 'Roll Call', href: '/school/hostel/roll-call' },
    { label: 'Complaints', href: '/school/hostel/complaints' },
    { label: 'Meal Report', href: '/school/hostel/mess/meal-report' },
];

const occupancyPct = (h) => h.total_beds > 0 ? Math.round((h.occupied_beds / h.total_beds) * 100) : 0;
const barColor = (pct) => pct > 90 ? '#ef4444' : pct > 70 ? '#f59e0b' : '#22c55e';

const toggleHostel = (id) => {
    expandedHostel.value = expandedHostel.value === id ? null : id;
};
</script>

<template>
<SchoolLayout title="Hostel Dashboard">
    <PageHeader title="Hostel Dashboard" subtitle="Overview of hostel occupancy and operations">
        <template #actions>
            <Link href="/school/hostel/fees">
                        <Button>Fee Collection</Button>
                    </Link>
        </template>
    </PageHeader>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div v-for="card in cards" :key="card.title" class="stat-card">
            <div class="stat-bar" :style="{background: card.color}"></div>
            <p class="stat-label">{{ card.title }}</p>
            <p class="stat-value" :style="{color: card.color}">{{ card.value }}</p>
        </div>
    </div>

    <!-- Occupancy Map -->
    <div class="card" style="margin-bottom:16px;" v-if="occupancy.length">
        <div class="card-header"><h3 class="card-title">Occupancy Map</h3></div>
        <div class="card-body" style="padding:0;">
            <div v-for="h in occupancy" :key="h.id" class="occ-hostel">
                <button class="occ-header"
                    @click="toggleHostel(h.id)"
                    :aria-expanded="expandedHostel === h.id"
                    :aria-label="`${h.name} — ${occupancyPct(h)}% occupied. Click to ${expandedHostel === h.id ? 'collapse' : 'expand'}`">
                    <div class="occ-info">
                        <span class="occ-name">{{ h.name }}</span>
                        <span class="occ-type">{{ h.type }}</span>
                        <span class="occ-count">{{ h.occupied_beds }}/{{ h.total_beds }} beds</span>
                    </div>
                    <div class="occ-bar-wrap">
                        <div class="occ-bar" :style="{width: occupancyPct(h) + '%', background: barColor(occupancyPct(h))}"></div>
                    </div>
                    <span class="occ-pct" :style="{color: barColor(occupancyPct(h))}">{{ occupancyPct(h) }}%</span>
                    <span class="expand-icon" aria-hidden="true">{{ expandedHostel === h.id ? '−' : '+' }}</span>
                </button>

                <div v-if="expandedHostel === h.id" class="occ-detail">
                    <div v-for="block in h.blocks" :key="block.name" class="occ-block">
                        <div class="block-label">{{ block.name }}</div>
                        <div v-for="floor in block.floors" :key="floor.name" class="occ-floor">
                            <div class="floor-label">{{ floor.name }}</div>
                            <div class="room-grid">
                                <div v-for="room in floor.rooms" :key="room.id" class="room-cell"
                                     :class="{'room-full': room.available === 0, 'room-inactive': room.status === 'Maintenance'}">
                                    <div class="room-number">{{ room.room_number }}</div>
                                    <div class="room-beds">
                                        <span class="bed-occ">{{ room.occupied }}</span>/<span class="bed-total">{{ room.total_beds }}</span>
                                    </div>
                                    <div class="room-bar">
                                        <div class="room-bar-fill" :style="{width: (room.total_beds > 0 ? (room.occupied / room.total_beds * 100) : 0) + '%', background: room.available === 0 ? '#ef4444' : '#22c55e'}"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="card">
        <div class="card-header"><h3 class="card-title">Quick Actions</h3></div>
        <div class="card-body">
            <div class="quick-links">
                <Link v-for="link in quickLinks" :key="link.href" :href="link.href" class="quick-link">
                    {{ link.label }}
                </Link>
            </div>
        </div>
    </div>

</SchoolLayout>
</template>

<style scoped>
.stats-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px,1fr)); gap:12px; margin-bottom:16px; }
@media (max-width: 640px) { .stats-grid { grid-template-columns:repeat(2,1fr); } }
.stat-card { background:#fff; border-radius:10px; padding:14px 16px; border:1.5px solid #e2e8f0; position:relative; overflow:hidden; transition:box-shadow .15s; }
.stat-card:hover { box-shadow:0 2px 8px rgba(0,0,0,.06); }
.stat-bar { position:absolute; top:0; left:0; right:0; height:3px; }
.stat-label { font-size:.72rem; color:#64748b; font-weight:600; text-transform:uppercase; margin-top:2px; }
.stat-value { font-size:1.5rem; font-weight:800; margin-top:4px; }

.occ-hostel { border-bottom:1px solid #f1f5f9; }
.occ-hostel:last-child { border-bottom:none; }
.occ-header { display:flex; align-items:center; gap:12px; padding:14px 18px; cursor:pointer; transition:background .15s; width:100%; border:none; background:transparent; text-align:left; font-family:inherit; }
.occ-header:hover { background:#f8fafc; }
.occ-header:focus-visible { outline:2px solid #6366f1; outline-offset:-2px; }
.occ-info { display:flex; align-items:center; gap:8px; min-width:200px; }
.occ-name { font-weight:700; color:#1e293b; font-size:.9rem; }
.occ-type { font-size:.68rem; color:#94a3b8; background:#f1f5f9; padding:2px 8px; border-radius:4px; }
.occ-count { font-size:.75rem; color:#64748b; }
.occ-bar-wrap { flex:1; height:8px; background:#f1f5f9; border-radius:4px; overflow:hidden; }
.occ-bar { height:100%; border-radius:4px; transition:width .3s; }
.occ-pct { font-weight:800; font-size:.85rem; min-width:40px; text-align:right; }
.expand-icon { font-size:1.2rem; font-weight:700; color:#94a3b8; width:20px; text-align:center; }
.occ-detail { padding:0 18px 18px; }
.occ-block { margin-bottom:12px; }
.block-label { font-size:.72rem; font-weight:700; color:#6366f1; text-transform:uppercase; letter-spacing:.5px; margin-bottom:6px; padding:4px 0; border-bottom:1px dashed #e2e8f0; }
.occ-floor { margin-bottom:10px; }
.floor-label { font-size:.68rem; color:#94a3b8; font-weight:600; margin-bottom:4px; padding-left:4px; }
.room-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(90px, 1fr)); gap:8px; }
.room-cell { background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:8px; padding:8px; text-align:center; transition:all .15s; }
.room-cell:hover { border-color:#c4b5fd; box-shadow:0 2px 8px rgba(0,0,0,.06); }
.room-full { background:#fef2f2; border-color:#fecaca; }
.room-inactive { opacity:.5; }
.room-number { font-weight:700; font-size:.82rem; color:#1e293b; }
.room-beds { font-size:.7rem; color:#64748b; margin:2px 0; }
.bed-occ { font-weight:700; } .bed-total { color:#94a3b8; }
.room-bar { height:4px; background:#e2e8f0; border-radius:2px; margin-top:4px; }
.room-bar-fill { height:100%; border-radius:2px; transition:width .3s; }

.quick-links { display:grid; grid-template-columns:repeat(auto-fill, minmax(160px,1fr)); gap:10px; }
@media (max-width: 800px) { .quick-links { grid-template-columns:repeat(2,1fr); } }
.quick-link { padding:12px; border-radius:var(--radius); border:1px solid var(--border); background:var(--bg); font-weight:600; font-size:.84rem; color:var(--text-primary); text-align:center; text-decoration:none; transition:all .15s; }
.quick-link:hover { background:#eef2ff; border-color:#c7d2fe; color:var(--accent); }

.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:1000; display:flex; align-items:center; justify-content:center; }
.modal-box { background:#fff; border-radius:12px; padding:24px; width:90%; box-shadow:0 8px 30px rgba(0,0,0,.15); }
.modal-title { font-size:1.1rem; font-weight:700; margin-bottom:8px; color:#1e293b; }
.modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:16px; }
</style>
