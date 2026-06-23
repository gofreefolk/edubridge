<template>
    <button
        type="button"
        class="shrink-0 rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-800 transition hover:bg-blue-100"
        :aria-label="t('home.language')"
        @click="toggleLocale"
    >
        {{ nextLocaleLabel }}
    </button>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { setLocale } from '@/i18n';

const { locale, t } = useI18n();

const nextLocaleLabel = computed(() =>
    locale.value === 'ml' ? t('home.switchToEnglish') : t('home.switchToMalayalam'),
);

function toggleLocale() {
    setLocale(locale.value === 'ml' ? 'en' : 'ml');
}
</script>
