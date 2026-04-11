import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

/**
 * Notification store — manages in-app notifications and unread count.
 *
 * Polls the backend periodically (every 30s) when the user is authenticated.
 * Supports manual refresh and mark-as-read.
 *
 * Usage:
 *   const notifications = useNotificationStore();
 *   notifications.unreadCount
 *   notifications.items
 *   notifications.markAllRead()
 *   notifications.startPolling()
 */
export const useNotificationStore = defineStore('notifications', () => {
    const items       = ref([]);
    const unreadCount = ref(0);
    const loading     = ref(false);
    const pollingInterval = ref(null);

    const hasUnread = computed(() => unreadCount.value > 0);

    async function fetchNotifications() {
        if (loading.value) return;
        loading.value = true;
        try {
            const { data } = await axios.get('/api/v1/notifications');
            items.value       = data.notifications ?? [];
            unreadCount.value = data.unread_count  ?? 0;
        } catch {
            // Silently fail — notifications are non-critical
        } finally {
            loading.value = false;
        }
    }

    async function markAllRead() {
        try {
            await axios.post('/api/v1/notifications/read-all');
            unreadCount.value = 0;
            items.value = items.value.map(n => ({ ...n, read_at: new Date().toISOString() }));
        } catch {
            // Silently fail
        }
    }

    async function markRead(notificationId) {
        try {
            await axios.post(`/api/v1/notifications/${notificationId}/read`);
            const idx = items.value.findIndex(n => n.id === notificationId);
            if (idx !== -1) {
                items.value[idx] = { ...items.value[idx], read_at: new Date().toISOString() };
            }
            unreadCount.value = Math.max(0, unreadCount.value - 1);
        } catch {
            // Silently fail
        }
    }

    function startPolling(intervalMs = 30_000) {
        if (pollingInterval.value) return; // already polling
        fetchNotifications(); // immediate first fetch
        pollingInterval.value = setInterval(fetchNotifications, intervalMs);
    }

    function stopPolling() {
        if (pollingInterval.value) {
            clearInterval(pollingInterval.value);
            pollingInterval.value = null;
        }
    }

    function addOptimistic(notification) {
        items.value.unshift(notification);
        if (!notification.read_at) {
            unreadCount.value++;
        }
    }

    return {
        items, unreadCount, loading, hasUnread,
        fetchNotifications, markAllRead, markRead,
        startPolling, stopPolling, addOptimistic,
    };
});
