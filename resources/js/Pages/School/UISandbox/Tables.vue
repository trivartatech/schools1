<script setup>
/**
 * UI Sandbox · Tables
 * <Table> density / striped / bordered / loading / empty + <SortableTh>.
 * URL: /school/_ui-sandbox/tables
 */
import { ref, computed } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Table from '@/Components/ui/Table.vue';
import SortableTh from '@/Components/ui/SortableTh.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { useTableSort } from '@/Composables/useTableSort';

const sampleStudents = ref([
    { id: 1, name: 'Aanya Sharma',   class: 'Grade 7-A',  marks: 89,  attendance: 98.2, status: 'Active' },
    { id: 2, name: 'Rohan Mehta',    class: 'Grade 9-B',  marks: 72,  attendance: 85.5, status: 'Active' },
    { id: 3, name: 'Diya Iyer',      class: 'Grade 8-C',  marks: 94,  attendance: 96.0, status: 'Active' },
    { id: 4, name: 'Kabir Verma',    class: 'Grade 6-A',  marks: 65,  attendance: 78.3, status: 'Inactive' },
    { id: 5, name: 'Saanvi Reddy',   class: 'Grade 10-A', marks: 88,  attendance: 92.7, status: 'Active' },
]);

const { sortKey, sortDir, toggleSort, sortRows } = useTableSort('name', 'asc');
const sortedStudents = computed(() => sortRows(sampleStudents.value));

// Loading toggle
const tblLoading = ref(false);
function flashTblLoading() { tblLoading.value = true; setTimeout(() => tblLoading.value = false, 1500); }

// Standalone SortableTh
const standaloneSortKey = ref('email');
const standaloneSortDir = ref('asc');
function standaloneSort(key) {
    if (standaloneSortKey.value === key) {
        standaloneSortDir.value = standaloneSortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        standaloneSortKey.value = key;
        standaloneSortDir.value = 'asc';
    }
}
</script>

<template>
    <SchoolLayout title="UI Sandbox · Tables">

        <PageHeader
            title="Tables"
            subtitle="<Table> density, striped, bordered, loading, empty + <SortableTh> alignments and standalone use."
            back-href="/school/_ui-sandbox"
            back-label="← Back to sandbox"
        />

        <h2 class="section-heading">Sortable columns</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Click any header with the dual-arrow icon to sort. Click again to reverse direction.
            Active sort: <strong>{{ sortKey || 'none' }}</strong> ({{ sortDir }})
        </p>
        <div class="card" style="margin-bottom:20px;">
            <Table :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
                <thead>
                    <tr>
                        <SortableTh sort-key="name">Student</SortableTh>
                        <SortableTh sort-key="class">Class</SortableTh>
                        <SortableTh sort-key="marks" align="right">Marks</SortableTh>
                        <SortableTh sort-key="attendance" align="right">Attendance %</SortableTh>
                        <SortableTh sort-key="status">Status</SortableTh>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in sortedStudents" :key="s.id">
                        <td style="font-weight:600;color:var(--text-primary);">{{ s.name }}</td>
                        <td>{{ s.class }}</td>
                        <td style="text-align:right;font-family:monospace;">{{ s.marks }}</td>
                        <td style="text-align:right;font-family:monospace;">{{ s.attendance }}%</td>
                        <td>
                            <span class="badge" :class="s.status === 'Active' ? 'badge-green' : 'badge-gray'">
                                {{ s.status }}
                            </span>
                        </td>
                        <td style="text-align:right;">
                            <Button variant="secondary" size="xs">View</Button>
                        </td>
                    </tr>
                </tbody>
            </Table>
        </div>

        <h2 class="section-heading">Basic (size=&quot;md&quot; default, no sort)</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table size="md">
                <thead>
                    <tr><th>Name</th><th>Class</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <tr><td>Aanya Sharma</td><td>Grade 7-A</td><td><span class="badge badge-green">Active</span></td></tr>
                    <tr><td>Rohan Mehta</td><td>Grade 9-B</td><td><span class="badge badge-amber">Pending</span></td></tr>
                </tbody>
            </Table>
        </div>

        <h2 class="section-heading">Density: size=&quot;sm&quot;</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table size="sm">
                <thead><tr><th>Name</th><th>Class</th><th>Status</th></tr></thead>
                <tbody>
                    <tr><td>Aanya Sharma</td><td>Grade 7-A</td><td><span class="badge badge-green">Active</span></td></tr>
                    <tr><td>Diya Iyer</td><td>Grade 8-C</td><td><span class="badge badge-blue">New</span></td></tr>
                </tbody>
            </Table>
        </div>

        <h2 class="section-heading">Density: size=&quot;lg&quot;</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table size="lg">
                <thead><tr><th>Name</th><th>Class</th><th>Status</th></tr></thead>
                <tbody>
                    <tr><td>Aanya Sharma</td><td>Grade 7-A</td><td><span class="badge badge-green">Active</span></td></tr>
                    <tr><td>Diya Iyer</td><td>Grade 8-C</td><td><span class="badge badge-blue">New</span></td></tr>
                </tbody>
            </Table>
        </div>

        <h2 class="section-heading">Striped</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table striped>
                <thead><tr><th>#</th><th>Name</th><th>Marks</th></tr></thead>
                <tbody>
                    <tr v-for="i in 5" :key="i"><td>{{ i }}</td><td>Student {{ i }}</td><td>{{ 60 + i * 7 }}</td></tr>
                </tbody>
            </Table>
        </div>

        <h2 class="section-heading">Bordered (financial / report style)</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table bordered>
                <thead><tr><th>Account</th><th style="text-align:right;">Debit</th><th style="text-align:right;">Credit</th></tr></thead>
                <tbody>
                    <tr><td>Cash in Hand</td><td style="text-align:right;font-family:monospace;">12,500</td><td style="text-align:right;font-family:monospace;">—</td></tr>
                    <tr><td>Tuition Income</td><td style="text-align:right;font-family:monospace;">—</td><td style="text-align:right;font-family:monospace;">12,500</td></tr>
                </tbody>
            </Table>
        </div>

        <h2 class="section-heading">Loading state (overlay spinner)</h2>
        <div class="card" style="padding:12px;margin-bottom:20px;">
            <Button size="sm" @click="flashTblLoading">Flash 1.5s loading state</Button>
            <div style="margin-top:12px;">
                <Table :loading="tblLoading">
                    <thead><tr><th>Name</th><th>Class</th></tr></thead>
                    <tbody>
                        <tr><td>Aanya Sharma</td><td>Grade 7-A</td></tr>
                        <tr><td>Rohan Mehta</td><td>Grade 9-B</td></tr>
                        <tr><td>Diya Iyer</td><td>Grade 8-C</td></tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <h2 class="section-heading">Empty state (default text)</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table empty empty-text="No students match the filters">
                <thead><tr><th>Name</th><th>Class</th></tr></thead>
                <tbody />
            </Table>
        </div>

        <h2 class="section-heading">Custom #loading slot</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table loading>
                <thead><tr><th>Name</th><th>Class</th></tr></thead>
                <tbody>
                    <tr><td>Aanya Sharma</td><td>Grade 7-A</td></tr>
                </tbody>
                <template #loading>
                    <div style="display:flex;flex-direction:column;align-items:center;gap:8px;">
                        <div style="width:24px;height:24px;border:3px solid #e0e7ff;border-top-color:#6366f1;border-radius:50%;animation:erp-table-spin 0.8s linear infinite;"></div>
                        <span style="font-size:0.78rem;color:var(--text-muted);">Crunching the numbers…</span>
                    </div>
                </template>
            </Table>
        </div>

        <h2 class="section-heading">Explicit sort-dir literal demos (for the audit)</h2>
        <div class="card" style="padding:14px;margin-bottom:20px;font-size:0.78rem;color:var(--text-muted);">
            <Table sort-key="name" sort-dir="asc">
                <thead><tr><th>Asc</th></tr></thead><tbody><tr><td>—</td></tr></tbody>
            </Table>
            <Table sort-key="name" sort-dir="desc" style="margin-top:8px;">
                <thead><tr><th>Desc</th></tr></thead><tbody><tr><td>—</td></tr></tbody>
            </Table>
        </div>

        <h2 class="section-heading">Empty via #empty slot (custom)</h2>
        <div class="card" style="margin-bottom:20px;">
            <Table empty>
                <thead><tr><th>Name</th><th>Class</th></tr></thead>
                <tbody />
                <template #empty>
                    <EmptyState
                        variant="compact"
                        title="No students yet"
                        description="Use the form above to add your first student."
                    />
                </template>
            </Table>
        </div>

        <h2 class="section-heading">SortableTh — align center / right + standalone (no &lt;Table&gt; ancestor)</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Standalone usage: pass <code>:current-key</code> &amp; <code>:current-dir</code> directly,
            and listen to <code>@sort</code> on the cell. Active: <strong>{{ standaloneSortKey }}</strong> ({{ standaloneSortDir }})
        </p>
        <div class="card" style="margin-bottom:20px;">
            <table class="erp-table" style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <SortableTh sort-key="name"  align="left"   :current-key="standaloneSortKey" :current-dir="standaloneSortDir" @sort="standaloneSort">Name (left)</SortableTh>
                        <SortableTh sort-key="email" align="center" :current-key="standaloneSortKey" :current-dir="standaloneSortDir" @sort="standaloneSort">Email (center)</SortableTh>
                        <SortableTh sort-key="score" align="right"  :current-key="standaloneSortKey" :current-dir="standaloneSortDir" @sort="standaloneSort">Score (right)</SortableTh>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Aanya Sharma</td><td style="text-align:center;">aanya@example.com</td><td style="text-align:right;font-family:monospace;">94</td></tr>
                    <tr><td>Rohan Mehta</td><td style="text-align:center;">rohan@example.com</td><td style="text-align:right;font-family:monospace;">82</td></tr>
                </tbody>
            </table>
        </div>

    </SchoolLayout>
</template>

<style scoped>
.section-heading {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    margin: 24px 0 10px;
}
</style>
