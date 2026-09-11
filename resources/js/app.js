import '../css/app.css';
import './bootstrap';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createVuetify } from 'vuetify';
import { aliases, mdi } from 'vuetify/iconsets/mdi';
import { pt } from 'vuetify/locale';
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';
import '@mdi/font/css/materialdesignicons.css';
import 'vuetify/styles';
import AppHeader from '@/Components/AppHeader.vue';
import AppSubHeader from '@/Components/AppSubHeader.vue';
import '@fontsource/source-sans-3/400.css';
import '@fontsource/source-sans-3/700.css';
import permission from '@/Directives/permission';
import tinymce from 'tinymce';
import { useSnackbar } from '@/Composables/useSnackbar';

const { showSnackbar } = useSnackbar();

let maxUploadSizeMb = 10;

router.on('invalid', (event) => {
    const response = event.detail.response;

    if (response?.status === 413) {
        event.preventDefault();

        const message =
            response.data?.message || `O arquivo enviado excede o limite máximo permitido de ${maxUploadSizeMb}MB.`;

        showSnackbar(message, 'error');
    }
});

tinymce.overrideDefaults({ license_key: 'gpl' });

const materialSymbols = {
    component: (props) => h('span', { class: 'material-symbols-outlined' }, props.icon),
};

const vuetify = createVuetify({
    components,
    directives,
    locale: {
        locale: 'pt',
        fallback: 'en',
        messages: { pt },
    },
    icons: {
        defaultSet: 'mdi',
        aliases,
        sets: {
            mdi,
            ms: materialSymbols,
        },
    },
    defaults: {
        VBtn: { style: 'text-transform: none; letter-spacing: normal;' },
        VTab: { style: 'text-transform: none; letter-spacing: normal;' },
    },
    theme: {
        defaultTheme: 'efomento',
        themes: {
            efomento: {
                colors: {
                    primary: '#008344',
                    secondary: '#ffcc05FF',
                    outlineSecondary: '#004c27',
                    subheader: '#485465',
                },
                variables: {
                    'font-family': 'Source Sans 3, sans-serif',
                },
            },
        },
    },
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

function resolvePage(name) {
    const pages = import.meta.glob('./Pages/**/*.vue');

    const normalizedName = name.replace(/\./g, '/').replace(/\/+$/, '');

    const formattedName = normalizedName
        .split('/')
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join('/');

    const paths = [`./Pages/${formattedName}.vue`, `./Pages/${formattedName}/Index.vue`];

    for (const path of paths) {
        if (pages[path]) {
            return pages[path]();
        }
    }

    console.error('Available pages:', Object.keys(pages));
    throw new Error(`Page not found: ${name}`);
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,

    resolve: resolvePage,

    setup({ el, App, props, plugin }) {
        maxUploadSizeMb = props.initialPage.props.uploadLimits?.maxMb ?? maxUploadSizeMb;

        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(vuetify);

        app.component('AppHeader', AppHeader);
        app.component('AppSubHeader', AppSubHeader);
        app.directive('permission', permission);

        return app.mount(el);
    },

    progress: {
        color: '#4B5563',
    },
});
