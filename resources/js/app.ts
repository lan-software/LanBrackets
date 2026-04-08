import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import DemoShell from '@/components/demo/DemoShell.vue';
import '../css/app.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        const page = await resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        );

        // Apply sidebar layout to authenticated pages (not Landing, Login, Overlay)
        if (
            !['Landing', 'Auth/Login', 'Auth/Unauthenticated'].includes(name) &&
            !name.startsWith('Overlay/')
        ) {
            page.default.layout = page.default.layout || AppLayout;
        }

        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({
            render: () =>
                h(DemoShell, null, { default: () => h(App, props) }),
        })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#f59e0b',
    },
});

initializeTheme();
