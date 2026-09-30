import './bootstrap';
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import i18n from './i18n';

// Served from the site root by Laravel so it can control every page (see routes/web.php).
if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
}

const app = createApp(App);

app.use(router);
app.use(i18n);
app.mount('#app');
