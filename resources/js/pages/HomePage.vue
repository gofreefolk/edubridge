<template>
    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-medium text-slate-500">{{ t('home.welcome') }}</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">
                {{ t('app.name') }}
            </h1>
            <p class="mt-2 text-base leading-relaxed text-slate-700">
                {{ t('home.subtitle') }}
            </p>
        </div>

        <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
            <p class="text-sm font-semibold uppercase tracking-wide text-red-700">
                {{ t('home.urgent') }}
            </p>
            <p class="mt-1 text-lg font-medium text-red-900">
                {{ sampleNotice.title }}
            </p>
        </div>

        <div class="grid gap-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-base text-slate-700">
                    <span class="mr-2" aria-hidden="true">📅</span>
                    {{ t('home.upcoming') }}: {{ sampleEvent }}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-base text-slate-700">
                    <span class="mr-2" aria-hidden="true">📋</span>
                    2 {{ t('home.newNotices') }}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-base text-slate-700">
                    <span class="mr-2" aria-hidden="true">💬</span>
                    1 {{ t('home.repliesWaiting') }}
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
            <p class="font-medium">{{ t('home.offlineReady') }}</p>
            <p class="mt-1 text-blue-800">{{ t('home.pwaHint') }}</p>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const sampleNotice = computed(() =>
    locale.value === 'ml'
        ? { title: 'നാളെ സ്കൂൾ അവധി (മഴ)' }
        : { title: 'School closed tomorrow (rain holiday)' },
);

const sampleEvent = computed(() =>
    locale.value === 'ml' ? 'രക്ഷിതാവ് സമ്മേളനം' : 'Parent meeting',
);
</script>
