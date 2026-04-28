<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    recentIssues: Array,
});

const fmt = (n) => Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
</script>

<template>
    <SchoolLayout title="Library">
        <PageHeader title="Library">
            <template #actions>
                <Link href="/school/library/books" class="btn btn-secondary btn-sm">Catalog</Link>
                <Link href="/school/library/issues" class="btn btn-primary btn-sm">Issues</Link>
            </template>
        </PageHeader>

        <!-- Stats -->
        <div class="library-stats">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-value" style="color:#1d4ed8;">{{ stats.totalBooks }}</div>
                    <div class="stat-label">Total Copies</div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-value" style="color:#16a34a;">{{ stats.availableBooks }}</div>
                    <div class="stat-label">Available</div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-value" style="color:#d97706;">{{ stats.activeIssues }}</div>
                    <div class="stat-label">Issued Out</div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-value" style="color:#dc2626;">{{ stats.overdueIssues }}</div>
                    <div class="stat-label">Overdue</div>
                </div>
            </div>
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-value" style="color:#7c3aed;font-size:1.1rem;">₹{{ fmt(stats.totalFines) }}</div>
                    <div class="stat-label">Pending Fines</div>
                </div>
            </div>
        </div>

        <!-- Recent Issues -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Recent Issues</span>
                <Link href="/school/library/issues" style="font-size:.8rem;color:#3b82f6;">View All</Link>
            </div>
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Borrower</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="issue in recentIssues" :key="issue.id">
                            <td>
                                <div style="font-weight:500;">{{ issue.book?.title }}</div>
                                <div style="font-size:.75rem;color:#94a3b8;">{{ issue.book?.author }}</div>
                            </td>
                            <td>
                                <span v-if="issue.borrower_type === 'student'">
                                    {{ issue.student?.first_name }} {{ issue.student?.last_name }}
                                </span>
                                <span v-else>{{ issue.staff?.user?.name }}</span>
                            </td>
                            <td>{{ issue.issue_date?.slice(0,10) }}</td>
                            <td>{{ issue.due_date?.slice(0,10) }}</td>
                            <td>
                                <span class="badge" :class="{
                                    'badge-green': issue.status === 'returned',
                                    'badge-amber': issue.status === 'issued',
                                    'badge-red':   issue.status === 'overdue',
                                    'badge-gray':  issue.status === 'lost',
                                }">{{ issue.status }}</span>
                            </td>
                        </tr>
                        <tr v-if="!recentIssues?.length">
                            <td colspan="5" style="text-align:center;padding:32px;color:#94a3b8;">No issues yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.library-stats { display:grid; grid-template-columns:repeat(5,1fr); gap:16px; margin-bottom:20px; }
.stat-card .card-body { padding:16px; }
.stat-value { font-size:1.5rem; font-weight:700; }
.stat-label { font-size:.75rem; color:var(--text-muted); margin-top:4px; }
</style>
