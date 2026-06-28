import { createI18n } from 'vue-i18n';
import ml from './locales/ml.json';
import en from './locales/en.json';

const LOCALE_STORAGE_KEY = 'edubridge.locale';

function isPlatformPath() {
    return window.location.pathname.startsWith('/platform');
}

function getInitialLocale() {
    const stored = localStorage.getItem(LOCALE_STORAGE_KEY);
    if (stored === 'ml' || stored === 'en') {
        return stored;
    }

    if (isPlatformPath()) {
        return 'en';
    }

    return 'ml';
}

const i18n = createI18n({
    legacy: false,
    locale: getInitialLocale(),
    fallbackLocale: 'en',
    messages: {
        ml,
        en,
    },
});

export function setLocale(locale) {
    if (locale !== 'ml' && locale !== 'en') {
        return;
    }

    i18n.global.locale.value = locale;
    localStorage.setItem(LOCALE_STORAGE_KEY, locale);
    document.documentElement.lang = locale;
}

export function applyUserLocale(user) {
    const locale = user?.preferred_locale;
    if (locale === 'ml' || locale === 'en') {
        setLocale(locale);
    }
}

document.documentElement.lang = getInitialLocale();

export default i18n;
