<template>
    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-medium text-slate-500">{{ t('roles.school_admin') }}</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ t('admin.homeTitle') }}</h1>
            <p class="mt-2 text-slate-600">{{ schoolName }}</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
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
            <router-link
                :to="{ name: 'admin-notice-create' }"
                class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
            >
                ✏️ {{ t('admin.createNoticeShort') }}
            </router-link>
            <router-link
                :to="{ name: 'admin-import' }"
                class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
            >
                📥 {{ t('admin.importShort') }}
            </router-link>
        </div>

        <div>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ t('admin.setupTitle') }}</h2>
            <div class="grid grid-cols-2 gap-3">
                <router-link
                    :to="{ name: 'admin-school' }"
                    class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
                >
                    🏫 {{ t('admin.schoolProfileShort') }}
                </router-link>
                <router-link
                    :to="{ name: 'admin-classes' }"
                    class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
                >
                    📚 {{ t('admin.classesShort') }}
                </router-link>
                <router-link
                    :to="{ name: 'admin-staff' }"
                    class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
                >
                    👥 {{ t('admin.staffShort') }}
                </router-link>
                <router-link
                    :to="{ name: 'admin-students' }"
                    class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
                >
                    🎒 {{ t('admin.studentsShort') }}
                </router-link>
            </div>
        </div>

        <p class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            {{ t('admin.publishHint') }}
        </p>
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
