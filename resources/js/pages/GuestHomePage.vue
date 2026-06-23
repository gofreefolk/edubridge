<template>
    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('app.name') }}</h1>
            <p class="mt-2 text-base leading-relaxed text-slate-600">{{ t('home.subtitle') }}</p>
        </div>

        <router-link
            :to="{ name: 'login' }"
            class="block w-full rounded-xl bg-blue-700 px-4 py-3.5 text-center text-base font-semibold text-white"
        >
            {{ t('auth.loginTitle') }}
        </router-link>

        <router-link
            :to="{ name: 'notice-magic', params: { token: 'demo123abc' } }"
            class="block rounded-2xl border border-blue-200 bg-blue-50 p-4 text-center text-sm font-semibold text-blue-800 underline"
        >
            {{ t('notice.demoLink') }}
        </router-link>
    </section>
</template>

<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>
