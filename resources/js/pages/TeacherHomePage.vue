<template>
    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-medium text-slate-500">{{ t('roles.teacher') }}</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ t('teacher.homeTitle') }}</h1>
            <p class="mt-2 text-slate-600">{{ schoolName }}</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <router-link
                :to="{ name: 'teacher-class' }"
                class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
            >
                👥 {{ t('nav.class') }}
            </router-link>
            <router-link
                :to="{ name: 'messages' }"
                class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
            >
                💬 {{ t('nav.messages') }}
            </router-link>
            <router-link
                :to="{ name: 'notices' }"
                class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
            >
                📋 {{ t('nav.notices') }}
            </router-link>
            <router-link
                :to="{ name: 'calendar' }"
                class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
            >
                📅 {{ t('nav.calendar') }}
            </router-link>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t } = useI18n();
const { schools } = useSchoolContext();

const schoolName = computed(() => schools.value[0]?.name ?? '');
</script>
