<script setup>
import { computed } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    allocations: Array,
});

function sortedStops(stops) {
    if (!stops || !stops.length) return [];
    return [...stops].sort((a, b) => (a.stop_order ?? 0) - (b.stop_order ?? 0));
}

function formatTime(time) {
    if (!time) return '--';
    // Handle both "HH:mm" and "HH:mm:ss" formats
    const parts = time.split(':');
    if (parts.length < 2) return time;
    let h = parseInt(parts[0], 10);
    const m = parts[1];
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${m} ${ampm}`;
}

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)} min ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} hr ago`;
    return `${Math.floor(diff / 86400)} days ago`;
}
</script>

<template>
    <SchoolLayout title="My Transport">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">My Transport</h1>
                <p class="page-header-sub">View your children's transport details</p>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="!allocations || allocations.length === 0" class="card">
            <div class="card-body pv-empty">
                <svg class="pv-empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
                <p>No transport allocations found for your children.</p>
            </div>
        </div>

        <!-- Per-child allocation blocks -->
        <div v-for="alloc in allocations" :key="alloc.student?.id" class="pv-child-block">

            <!-- Child header -->
            <div class="pv-child-header">
                <div class="pv-child-avatar">
                    {{ alloc.student?.user?.name?.charAt(0) || '?' }}
                </div>
                <div>
                    <h2 class="pv-child-name">{{ alloc.student?.user?.name || 'Student' }}</h2>
                    <p class="pv-child-adm">Admission No: {{ alloc.student?.admission_no || '--' }}</p>
                </div>
                <span v-if="alloc.vehicle?.liveLocation" class="badge badge-green pv-live-badge">
                    <span class="pv-live-dot"></span>
                    Bus is live
                    <span class="pv-live-time">{{ timeAgo(alloc.vehicle.liveLocation.updated_at) }}</span>
                </span>
            </div>

            <div class="pv-cards-grid">

                <!-- Card 1 - Route Info -->
                <div class="card">
                    <div class="card-header">
                        <svg class="pv-card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        <span class="card-title">Route Info</span>
                    </div>
                    <div class="card-body">
                        <div class="pv-detail-row">
                            <span class="pv-detail-label">Route Name</span>
                            <span class="pv-detail-value">{{ alloc.route?.route_name || '--' }}</span>
                        </div>
                        <div class="pv-detail-row">
                            <span class="pv-detail-label">Route Code</span>
                            <span class="pv-detail-value pv-mono">{{ alloc.route?.route_code || '--' }}</span>
                        </div>
                        <div class="pv-detail-row">
                            <span class="pv-detail-label">Path</span>
                            <span class="pv-detail-value">
                                {{ alloc.route?.start_location || '--' }}
                                <svg class="pv-arrow-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                                {{ alloc.route?.end_location || '--' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Card 2 - Pickup / Drop Details -->
                <div class="card">
                    <div class="card-header">
                        <svg class="pv-card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="card-title">Pickup / Drop Details</span>
                    </div>
                    <div class="card-body">
                        <div class="pv-detail-row">
                            <span class="pv-detail-label">Stop Name</span>
                            <span class="pv-detail-value">{{ alloc.stop?.stop_name || '--' }}</span>
                        </div>
                        <div class="pv-detail-row">
                            <span class="pv-detail-label">Pickup Time</span>
                            <span class="pv-detail-value pv-time">{{ formatTime(alloc.stop?.pickup_time) }}</span>
                        </div>
                        <div class="pv-detail-row">
                            <span class="pv-detail-label">Drop Time</span>
                            <span class="pv-detail-value pv-time">{{ formatTime(alloc.stop?.drop_time) }}</span>
                        </div>
                        <div class="pv-detail-row">
                            <span class="pv-detail-label">Monthly Fee</span>
                            <span class="pv-detail-value pv-fee">
                                {{ alloc.stop?.fee != null ? '\u20B9' + Number(alloc.stop.fee).toLocaleString('en-IN') : '--' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Card 3 - Vehicle & Driver -->
                <div class="card">
                    <div class="card-header">
                        <svg class="pv-card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <span class="card-title">Vehicle &amp; Driver</span>
                    </div>
                    <div class="card-body">
                        <div class="pv-detail-row">
                            <span class="pv-detail-label">Vehicle Number</span>
                            <span class="pv-detail-value pv-mono">{{ alloc.vehicle?.vehicle_number || '--' }}</span>
                        </div>
                        <div class="pv-detail-row">
                            <span class="pv-detail-label">Vehicle Name</span>
                            <span class="pv-detail-value">{{ alloc.vehicle?.vehicle_name || '--' }}</span>
                        </div>
                        <div class="pv-detail-row">
                            <span class="pv-detail-label">Driver</span>
                            <span class="pv-detail-value">{{ alloc.vehicle?.driver?.user?.name || '--' }}</span>
                        </div>
                        <div class="pv-detail-row">
                            <span class="pv-detail-label">Driver Phone</span>
                            <span class="pv-detail-value">
                                <a v-if="alloc.vehicle?.driver?.user?.phone"
                                   :href="'tel:' + alloc.vehicle.driver.user.phone"
                                   class="pv-phone-link">
                                    <svg class="pv-phone-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                    {{ alloc.vehicle.driver.user.phone }}
                                </a>
                                <span v-else>--</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 4 - Route Stops Timeline -->
            <div class="card pv-timeline-card">
                <div class="card-header">
                    <svg class="pv-card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="card-title">Route Stops</span>
                    <span class="badge" style="margin-left:auto;">{{ alloc.route?.stops?.length || 0 }} stops</span>
                </div>
                <div class="card-body">
                    <div v-if="alloc.route?.stops && alloc.route.stops.length"
                         class="pv-timeline">
                        <div v-for="(routeStop, idx) in sortedStops(alloc.route.stops)"
                             :key="idx"
                             class="pv-timeline-item"
                             :class="{ 'pv-timeline-active': alloc.stop && routeStop.stop_name === alloc.stop.stop_name }">
                            <div class="pv-timeline-marker">
                                <span class="pv-timeline-dot"></span>
                                <span v-if="idx < sortedStops(alloc.route.stops).length - 1"
                                      class="pv-timeline-line"></span>
                            </div>
                            <div class="pv-timeline-content">
                                <span class="pv-timeline-name">{{ routeStop.stop_name }}</span>
                                <span v-if="alloc.stop && routeStop.stop_name === alloc.stop.stop_name"
                                      class="badge badge-green pv-your-stop-badge">Your Stop</span>
                                <div class="pv-timeline-times">
                                    <span v-if="routeStop.pickup_time" class="pv-timeline-time">
                                        Pickup: {{ formatTime(routeStop.pickup_time) }}
                                    </span>
                                    <span v-if="routeStop.drop_time" class="pv-timeline-time">
                                        Drop: {{ formatTime(routeStop.drop_time) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="pv-empty" style="padding:1.5rem 0;">
                        <p>No stops available for this route.</p>
                    </div>
                </div>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* ── Child Block ────────────────────────────────────────────────────────────── */
.pv-child-block {
    margin-bottom: 2.5rem;
}

.pv-child-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.pv-child-avatar {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 50%;
    background: var(--accent, #6366f1);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.pv-child-name {
    font-size: 1.125rem;
    font-weight: 700;
    color: #111827;
    margin: 0;
    line-height: 1.3;
}

.pv-child-adm {
    font-size: 0.8rem;
    color: #6b7280;
    margin: 0;
}

/* ── Cards Grid ─────────────────────────────────────────────────────────────── */
.pv-cards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}

@media (max-width: 1024px) {
    .pv-cards-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .pv-cards-grid {
        grid-template-columns: 1fr;
    }
}

/* ── Card header icon ───────────────────────────────────────────────────────── */
.pv-card-icon {
    width: 1.125rem;
    height: 1.125rem;
    color: var(--accent, #6366f1);
    flex-shrink: 0;
}

/* ── Detail rows ────────────────────────────────────────────────────────────── */
.pv-detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f3f4f6;
}

.pv-detail-row:last-child {
    border-bottom: none;
}

.pv-detail-label {
    font-size: 0.8rem;
    color: #6b7280;
    white-space: nowrap;
    margin-right: 0.75rem;
}

.pv-detail-value {
    font-size: 0.875rem;
    font-weight: 500;
    color: #111827;
    text-align: right;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pv-mono {
    font-family: monospace;
    color: var(--accent, #6366f1);
    font-weight: 600;
}

.pv-time {
    font-weight: 600;
}

.pv-fee {
    color: var(--success, #10b981);
    font-weight: 700;
    font-size: 0.95rem;
}

.pv-arrow-icon {
    width: 1rem;
    height: 1rem;
    color: #9ca3af;
    flex-shrink: 0;
}

/* ── Phone link ─────────────────────────────────────────────────────────────── */
.pv-phone-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--accent, #6366f1);
    text-decoration: none;
    font-weight: 600;
    transition: opacity 0.15s;
}

.pv-phone-link:hover {
    opacity: 0.75;
}

.pv-phone-icon {
    width: 0.9rem;
    height: 0.9rem;
}

/* ── Live badge ─────────────────────────────────────────────────────────────── */
.pv-live-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    margin-left: auto;
    font-size: 0.75rem;
}

.pv-live-dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    background: #10b981;
    animation: pv-pulse 1.5s ease-in-out infinite;
    flex-shrink: 0;
}

@keyframes pv-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

.pv-live-time {
    font-weight: 400;
    color: #6b7280;
    margin-left: 0.25rem;
}

/* ── Timeline ───────────────────────────────────────────────────────────────── */
.pv-timeline-card {
    margin-bottom: 0;
}

.pv-timeline {
    padding: 0.25rem 0;
}

.pv-timeline-item {
    display: flex;
    gap: 0.75rem;
    position: relative;
    min-height: 3rem;
}

.pv-timeline-marker {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex-shrink: 0;
    width: 1.25rem;
}

.pv-timeline-dot {
    width: 0.625rem;
    height: 0.625rem;
    border-radius: 50%;
    background: #d1d5db;
    flex-shrink: 0;
    margin-top: 0.35rem;
    z-index: 1;
}

.pv-timeline-active .pv-timeline-dot {
    width: 0.75rem;
    height: 0.75rem;
    background: var(--accent, #6366f1);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.pv-timeline-line {
    width: 2px;
    flex: 1;
    background: #e5e7eb;
    margin-top: 0.25rem;
}

.pv-timeline-content {
    flex: 1;
    padding-bottom: 1rem;
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0.35rem 0.5rem;
}

.pv-timeline-name {
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
}

.pv-timeline-active .pv-timeline-name {
    color: #111827;
    font-weight: 700;
}

.pv-your-stop-badge {
    font-size: 0.65rem;
    padding: 0.1rem 0.4rem;
}

.pv-timeline-times {
    width: 100%;
    display: flex;
    gap: 1rem;
    margin-top: 0.15rem;
}

.pv-timeline-time {
    font-size: 0.75rem;
    color: #6b7280;
}

.pv-timeline-active .pv-timeline-time {
    color: #4b5563;
    font-weight: 500;
}

/* ── Empty state ────────────────────────────────────────────────────────────── */
.pv-empty {
    text-align: center;
    padding: 3rem 0;
    color: #9ca3af;
}

.pv-empty-icon {
    width: 3rem;
    height: 3rem;
    margin: 0 auto 0.75rem;
    color: #e5e7eb;
}

.pv-empty p {
    font-size: 0.875rem;
    margin: 0;
}
</style>
