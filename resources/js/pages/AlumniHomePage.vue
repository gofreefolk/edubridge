<template>
    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-medium text-slate-500">{{ t('roles.alumni') }}</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ t('alumni.title') }}</h1>
        </div>

        <router-link
            :to="{ name: 'alumni' }"
            class="block rounded-2xl border border-blue-200 bg-blue-50 p-4 font-semibold text-blue-800"
        >
            🎓 {{ t('alumni.viewPortal') }}
        </router-link>
    </section>
</template>

<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>
