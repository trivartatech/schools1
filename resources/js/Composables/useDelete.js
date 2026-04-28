/**
 * useDelete – reliable DELETE via native fetch (bypasses Inertia router.delete bug in v2)
 *
 * Usage:
 *   import { useDelete } from '@/Composables/useDelete'
 *   const { del } = useDelete()
 *   del('/school/academic-years/1')                                        // default confirm
 *   del('/school/academic-years/1', 'Delete this year? Cannot undo.')      // custom message
 *   del('/school/academic-years/1', null)                                  // skip confirm
 *
 * Uses the styled <ConfirmDialog /> via useConfirm() — replaces the previous
 * native browser confirm() so the UX matches the rest of the app.
 */
import { router } from '@inertiajs/vue3'
import { useConfirm } from '@/Composables/useConfirm'

const getCsrf = () =>
    decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

export function useDelete() {
    const confirm = useConfirm()

    const del = async (url, confirmMsg = 'Are you sure? This cannot be undone.') => {
        if (!url || url.endsWith('/null') || url.endsWith('/undefined') || url.endsWith('/')) {
            console.warn('[useDelete] Bad URL, aborting:', url)
            return
        }
        if (confirmMsg) {
            const ok = await confirm({
                title: 'Delete?',
                message: confirmMsg,
                confirmLabel: 'Delete',
                danger: true,
            })
            if (!ok) return
        }

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
