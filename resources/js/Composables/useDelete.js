/**
 * useDelete – reliable DELETE via native fetch (bypasses Inertia router.delete bug in v2)
 *
 * Usage:
 *   import { useDelete } from '@/Composables/useDelete'
 *   const { del } = useDelete()
 *   del('/school/academic-years/1')  // sends DELETE, then Inertia reloads page
 */
import { router } from '@inertiajs/vue3'

const getCsrf = () =>
    decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

export function useDelete() {
    const del = async (url, confirmMsg = 'Are you sure?') => {
        if (!url || url.endsWith('/null') || url.endsWith('/undefined') || url.endsWith('/')) {
            console.warn('[useDelete] Bad URL, aborting:', url)
            return
        }
        if (confirmMsg && !confirm(confirmMsg)) return

        try {
            const res = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-XSRF-TOKEN': getCsrf(),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json, text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            if (res.ok || res.redirected) {
                router.reload()
            } else {
                console.error('[useDelete] Error:', res.status, res.statusText)
            }
        } catch (err) {
            console.error('[useDelete] Fetch error:', err)
        }
    }

    return { del }
}
