import { createApp, h } from 'vue'
import { createInertiaApp, router } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createPinia } from 'pinia'
import { i18n } from './plugins/i18n.js'
import '../css/app.css'

const pinia = createPinia()

// When the CSRF token expires (419 Page Expired), Inertia receives a non-Inertia
// HTML response and fires the 'invalid' event. We silently reload the page so the
// user gets a fresh token without seeing the raw error screen or needing to clear cookies.
router.on('invalid', (event) => {
    if (event.detail.response.status === 419) {
        event.preventDefault()
        window.location.reload()
    }
})

createInertiaApp({
    title: (title) => `${title} — School ERP`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(pinia)
            .use(i18n)

        app.config.errorHandler = (err, instance, info) => {
            console.error('[Vue Error]', err, info)
            // In production, errors can be forwarded to a monitoring service here
        }

        app.mount(el)
    },
    progress: {
        color: '#6366f1',
    },
})
