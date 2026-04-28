<script setup>
import { computed } from 'vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    houses:     { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
});

const CATEGORY_LABELS = {
    sports:     'Sports',
    academic:   'Academic',
    cultural:   'Cultural',
    discipline: 'Discipline',
    general:    'General',
};
const CATEGORY_COLORS = {
    sports:     '#22c55e',
    academic:   '#6366f1',
    cultural:   '#f59e0b',
    discipline: '#ef4444',
    general:    '#94a3b8',
};

const maxPoints = computed(() => {
    const top = props.houses[0];
    return top ? Math.max(top.total_points, 1) : 1;
});

const medals = ['🥇', '🥈', '🥉'];
</script>

<template>
<SchoolLayout title="House Leaderboard">
    <PageHeader title="House Leaderboard" subtitle="Points tally for the current academic year">
        <template #actions>
            <Link href="/school/houses" class="btn btn-secondary">← Back to Houses</Link>
        </template>
    </PageHeader>

    <div v-if="!houses.length" class="card" style="text-align:center;padding:3rem;">
        <p style="color:var(--text-muted);">No houses or points recorded yet.</p>
    </div>

    <div v-else>
        <!-- Podium top-3 -->
        <div class="podium" v-if="houses.length >= 1">
            <div
                v-for="(house, i) in houses.slice(0, 3)"
                :key="house.id"
                class="podium-card"
                :style="{ '--hcolor': house.color }"
                :class="{ 'podium-first': i === 0 }"
            >
                <div class="podium-medal">{{ medals[i] }}</div>
                <div class="podium-color-bar" :style="{ background: house.color }"></div>
                <div class="podium-name">{{ house.name }}</div>
                <div class="podium-points" :style="{ color: house.color }">{{ house.total_points }}</div>
                <div class="podium-sub">points</div>
                <div class="podium-students">{{ house.student_count }} students</div>
            </div>
        </div>

        <!-- Full table -->
        <div class="card" style="margin-top:20px;">
            <div class="card-header"><h3 class="card-title">Full Standings</h3></div>
            <div style="overflow-x:auto;">
                <table class="lb-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>House</th>
                            <th>Students</th>
                            <th v-for="cat in categories" :key="cat">
                                <span :style="{ color: CATEGORY_COLORS[cat] }">{{ CATEGORY_LABELS[cat] }}</span>
                            </th>
                            <th>Total</th>
                            <th>Bar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(house, i) in houses" :key="house.id">
                            <td class="rank">
                                <span v-if="i < 3">{{ medals[i] }}</span>
                                <span v-else style="color:var(--text-muted)">{{ i + 1 }}</span>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span class="house-dot" :style="{ background: house.color }"></span>
                                    <Link :href="`/school/houses/${house.id}`" style="font-weight:700;color:var(--text-primary);text-decoration:none;">
                                        {{ house.name }}
                                    </Link>
                                </div>
                            </td>
                            <td>{{ house.student_count }}</td>
                            <td v-for="cat in categories" :key="cat">
                                <span
                                    :style="{ color: house.breakdown[cat] !== 0 ? CATEGORY_COLORS[cat] : 'var(--text-muted)', fontWeight: house.breakdown[cat] !== 0 ? 700 : 400 }"
                                >
                                    {{ house.breakdown[cat] >= 0 ? '+' : '' }}{{ house.breakdown[cat] }}
                                </span>
                            </td>
                            <td>
                                <span class="total-pts" :style="{ color: house.color }">{{ house.total_points }}</span>
                            </td>
                            <td class="bar-cell">
                                <div class="pts-bar-wrap">
                                    <div
                                        class="pts-bar"
                                        :style="{
                                            width: Math.max((house.total_points / maxPoints) * 100, 0) + '%',
                                            background: house.color
                                        }"
                                    ></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</SchoolLayout>
</template>

<style scoped>
.btn { display:inline-flex;align-items:center;justify-content:center;padding:7px 16px;border-radius:6px;font-size:.83rem;font-weight:600;text-decoration:none;transition:all .15s; }
.btn-secondary { background:var(--bg);border:1px solid var(--border);color:var(--text-primary); }
.btn-secondary:hover { background:#f1f5f9; }

.podium {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
    margin-bottom: 4px;
}
.podium-card {
    background: #fff;
    border: 2px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    text-align: center;
    padding-bottom: 16px;
    transition: box-shadow .15s;
}
.podium-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.1); }
.podium-first { border-color: var(--hcolor); box-shadow: 0 4px 20px rgba(0,0,0,.12); }

.podium-medal { font-size: 1.8rem; padding: 14px 0 4px; }
.podium-color-bar { height: 4px; margin: 0 20px 12px; border-radius: 2px; }
.podium-name  { font-size: 1rem; font-weight: 800; color: var(--text-primary); }
.podium-points { font-size: 2rem; font-weight: 900; margin-top: 4px; }
.podium-sub    { font-size: .68rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
.podium-students { font-size: .75rem; color: var(--text-muted); margin-top: 8px; }

.lb-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
.lb-table th { padding: 10px 14px; text-align: left; font-size: .72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .4px; border-bottom: 2px solid var(--border); background: var(--surface-muted); }
.lb-table td { padding: 11px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.lb-table tr:last-child td { border-bottom: none; }
.lb-table tr:hover td { background: #f8fafc; }

.rank { font-size: 1rem; width: 40px; }
.house-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.total-pts { font-size: 1rem; font-weight: 900; }

.bar-cell { width: 120px; min-width: 80px; }
.pts-bar-wrap { height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden; }
.pts-bar { height: 100%; border-radius: 4px; transition: width .4s; min-width: 4px; }
</style>
