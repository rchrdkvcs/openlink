import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, DefineComponent, h } from 'vue';

import EmailVerificationConfirmation from '@/Components/Auth/EmailVerificationConfirmation.vue';
import { type ErrorReportingConfig, startErrorReporting } from '@/lib/errorReporting';

import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Openlink';

createInertiaApp({
  title: (title) => (title ? `${title} · ${appName}` : appName),
  resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob<DefineComponent>('./Pages/**/*.vue')),
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => [h(App, props), h(EmailVerificationConfirmation)] })
      .use(plugin)
      .use(ZiggyVue);

    app.mount(el);
    void startErrorReporting(app, props.initialPage.props.errorReporting as ErrorReportingConfig | null | undefined);
  },
  progress: {
    color: 'hsl(234 60% 65%)',
  },
});
