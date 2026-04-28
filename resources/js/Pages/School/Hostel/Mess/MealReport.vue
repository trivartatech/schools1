<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    hostels: Array, report: Array, filters: Object,
});

const selHostel = ref(props.filters?.hostel_id || '');
const search = ref('');
const expandedHostel = ref(null);

const apply = () => {
    router.get(route('school.hostel.mess.meal-report'), {
        hostel_id: selHostel.value,
    }, { preserveState: true, replace: true });
};

const toggleExpand = (id) => {
    expandedHostel.value = expandedHostel.value === id ? null : id;
};

const totals = computed(() => {
    const t = { veg: 0, nonVeg: 0, custom: 0, none: 0, total: 0 };
    props.report.forEach(h => {
        t.veg += h.Veg || 0;
        t.nonVeg += h['Non-Veg'] || 0;
        t.custom += h.Custom || 0;
        t.none += h.None || 0;
        t.total += h.total || 0;
    });
    return t;
});

const filteredStudents = (students) => {
    if (!search.value) return students;
    const q = search.value.toLowerCase();
    return students.filter(s => s.name.toLowerCase().includes(q) || s.admission_no?.toLowerCase().includes(q));
};

const messColor = (type) => ({ 'Veg': '#22c55e', 'Non-Veg': '#ef4444', 'Custom': '#f59e0b', 'None': '#94a3b8' })[type] || '#94a3b8';
</script>

<template>
<SchoolLayout title="Mess Meal Report">
    <PageHeader title="Mess Meal Count Report" subtitle="Veg / Non-Veg / Custom meal preference counts">
        <template #actions>
            <Button variant="secondary" as="a" :href="route('school.hostel.mess.index')">Mess Menu</Button>
        </template>
    </PageHeader>

    <!-- Filters -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-body" style="display:flex;gap:12px;align-items:flex-end;">
            <div class="form-field" style="min-width:180px;">
                <label>Hostel</label>
                <select v-model="selHostel" @change="apply">
                    <option value="">All Hostels</option>
                    <option v-for="h in hostels" :key="h.id" :value="h.id">{{ h.name }}</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="stats-row" v-if="report.length">
        <div class="stat-card"><div class="stat-label">Total Students</div><div class="stat-value">{{ totals.total }}</div></div>
        <div class="stat-card stat-green"><div class="stat-label">Veg</div><div class="stat-value">{{ totals.veg }}</div></div>
        <div class="stat-card stat-red"><div class="stat-label">Non-Veg</div><div class="stat-value">{{ totals.nonVeg }}</div></div>
        <div class="stat-card stat-amber"><div class="stat-label">Custom</div><div class="stat-value">{{ totals.custom }}</div></div>
        <div class="stat-card"><div class="stat-label">None</div><div class="stat-value">{{ totals.none }}</div></div>
    </div>

    <!-- Hostel Breakdown -->
    <div v-if="report.length">
        <div v-for="h in report" :key="h.hostel_id" class="card hostel-card">
            <div class="hostel-header" @click="toggleExpand(h.hostel_id)">
                <div>
                    <span class="hostel-name">{{ h.hostel_name }}</span>
                    <span class="hostel-total">{{ h.total }} students</span>
                </div>
                <div class="meal-badges">
                    <span class="meal-badge" style="background:#dcfce7;color:#16a34a;">Veg: {{ h.Veg }}</span>
                    <span class="meal-badge" style="background:#fef2f2;color:#dc2626;">Non-Veg: {{ h['Non-Veg'] }}</span>
                    <span class="meal-badge" style="background:#fffbeb;color:#d97706;">Custom: {{ h.Custom }}</span>
                    <span class="meal-badge" style="background:#f1f5f9;color:#64748b;">None: {{ h.None }}</span>
                    <span class="expand-icon">{{ expandedHostel === h.hostel_id ? '−' : '+' }}</span>
                </div>
            </div>

            <div v-if="expandedHostel === h.hostel_id" class="hostel-detail">
                <input v-model="search" placeholder="Search student..." class="search-input" style="margin:12px 16px;max-width:250px;" />
                <Table>
                    <thead><tr>
                        <th>#</th><th>Student</th><th>Admission No</th><th>Room / Bed</th><th>Meal Type</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="(s, i) in filteredStudents(h.students)" :key="i">
                            <td style="color:#94a3b8;font-size:.8rem;">{{ i + 1 }}</td>
                            <td style="font-weight:600;">{{ s.name }}</td>
                            <td style="font-size:.82rem;color:#64748b;">{{ s.admission_no }}</td>
                            <td style="font-size:.82rem;">Rm {{ s.room }} / {{ s.bed }}</td>
                            <td>
                                <span class="mess-badge" :style="{background: messColor(s.mess_type) + '18', color: messColor(s.mess_type)}">
                                    {{ s.mess_type }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!filteredStudents(h.students).length">
                            <td colspan="5" style="text-align:center;color:#94a3b8;padding:24px;">No students found.</td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>
    </div>
    <div v-else class="card" style="text-align:center;padding:48px;color:var(--text-muted);">
        No active hostel students found.
    </div>
</SchoolLayout>
</template>

<style scoped>
.stats-row { display:grid; grid-template-columns:repeat(auto-fill, minmax(140px,1fr)); gap:12px; margin-bottom:16px; }
.stat-card { background:#fff; border-radius:10px; padding:12px 14px; border:1.5px solid #e2e8f0; }
.stat-label { font-size:.7rem; color:#64748b; font-weight:600; text-transform:uppercase; }
.stat-value { font-size:1.4rem; font-weight:800; color:#1e293b; margin-top:2px; }
.stat-green { border-left:4px solid #22c55e; } .stat-red { border-left:4px solid #ef4444; } .stat-amber { border-left:4px solid #f59e0b; }
.hostel-card { margin-bottom:12px; overflow:hidden; }
.hostel-header { display:flex; justify-content:space-between; align-items:center; padding:14px 18px; cursor:pointer; transition:background .15s; }
.hostel-header:hover { background:#f8fafc; }
.hostel-name { font-weight:700; color:#1e293b; font-size:.95rem; }
.hostel-total { font-size:.78rem; color:#64748b; margin-left:8px; }
.meal-badges { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.meal-badge { padding:3px 10px; border-radius:6px; font-size:.72rem; font-weight:600; }
.expand-icon { font-size:1.2rem; font-weight:700; color:#64748b; margin-left:8px; width:20px; text-align:center; }
.hostel-detail { border-top:1px solid #f1f5f9; }
.search-input { border:1.5px solid #e2e8f0; border-radius:8px; padding:7px 12px; font-size:.84rem; outline:none; font-family:inherit; }
.mess-badge { display:inline-block; padding:3px 10px; border-radius:6px; font-size:.72rem; font-weight:600; }
</style>
