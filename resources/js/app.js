import './bootstrap';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import AppLayout from './Layouts/AppLayout.vue';

createInertiaApp({
    title: (title) => (title ? `${title} · BxTorneum` : 'BxTorneum'),
    // Unique cookie name: avoids colliding with other local Laravel projects on
    // 127.0.0.1/localhost, which all default to the generic "XSRF-TOKEN" cookie name.
    http: { xsrfCookieName: 'bxtorneum_xsrf_token' },
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')).then((module) => {
            module.default.layout = module.default.layout ?? AppLayout;
            return module;
        }),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#22d3ee',
    },
});
