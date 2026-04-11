import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createPinia } from 'pinia'
import '../css/app.css'

const pinia = createPinia()

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
