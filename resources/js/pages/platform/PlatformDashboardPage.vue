<template>
    <section class="space-y-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-blue-700">
                {{ t('platform.welcomeBack') }}
            </p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900 lg:text-3xl">
                {{ t('platform.dashboardTitle') }}
            </h2>
            <p class="mt-2 max-w-2xl text-slate-600">{{ t('platform.dashboardSubtitle') }}</p>
        </div>

        <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-500">
            {{ t('common.loading') }}
        </div>

        <template v-else>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="card in statCards"
                    :key="card.key"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    :class="card.link ? 'cursor-pointer transition hover:border-blue-200 hover:shadow-md' : ''"
                    @click="card.link ? $router.push({ name: card.link }) : null"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-slate-500">{{ card.label }}</p>
                            <p class="mt-2 text-3xl font-bold text-slate-900">{{ card.value }}</p>
                        </div>
                        <span
                            class="flex h-11 w-11 items-center justify-center rounded-xl text-lg"
                            :class="card.iconClass"
                            aria-hidden="true"
                        >
                            {{ card.icon }}
                        </span>
                    </div>
                </article>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ t('platform.recentSchools') }}</h3>
                        <p class="text-sm text-slate-500">{{ t('platform.recentSchoolsHint') }}</p>
                    </div>
                    <router-link
                        :to="{ name: 'platform-schools' }"
                        class="rounded-lg bg-blue-50 px-3 py-2 text-sm font-medium text-blue-800 transition hover:bg-blue-100"
                    >
                        {{ t('platform.viewAllSchools') }}
                    </router-link>
                </div>

                <div v-if="recentSchools.length === 0" class="px-5 py-10 text-center text-slate-500">
                    {{ t('platform.noSchools') }}
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <div
                        v-for="school in recentSchools"
                        :key="school.id"
                        class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-900">{{ school.name }}</p>
                            <p class="text-sm text-slate-500">
                                {{ school.code }} · {{ school.district }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-700">
                                {{ school.students_count }} {{ t('platform.students') }}
                            </span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-700">
                                {{ school.users_count }} {{ t('platform.users') }}
                            </span>
                            <span
                                class="rounded-full px-2.5 py-1 font-medium"
                                :class="school.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                            >
                                {{ school.is_active ? t('platform.active') : t('platform.inactive') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n();

const loading = ref(true);
const stats = ref({
    schools: 0,
    active_schools: 0,
    pending_registrations: 0,
    users: 0,
    students: 0,
    notices: 0,
    published_notices: 0,
});
const recentSchools = ref([]);

const statCards = computed(() => [
    {
        key: 'schools',
        label: t('platform.stats.schools'),
        value: stats.value.schools,
        icon: '🏫',
        iconClass: 'bg-blue-50 text-blue-700',
    },
    {
        key: 'active_schools',
        label: t('platform.stats.activeSchools'),
        value: stats.value.active_schools,
        icon: '✅',
        iconClass: 'bg-emerald-50 text-emerald-700',
    },
    {
        key: 'pending_registrations',
        label: t('platform.stats.pendingRegistrations'),
        value: stats.value.pending_registrations,
        icon: '📝',
        iconClass: 'bg-amber-50 text-amber-700',
        link: 'platform-registrations',
    },
    {
        key: 'users',
        label: t('platform.stats.users'),
        value: stats.value.users,
        icon: '👥',
        iconClass: 'bg-violet-50 text-violet-700',
    },
    {
        key: 'students',
        label: t('platform.stats.students'),
        value: stats.value.students,
        icon: '🎒',
        iconClass: 'bg-amber-50 text-amber-700',
    },
    {
        key: 'notices',
        label: t('platform.stats.notices'),
        value: stats.value.notices,
        icon: '📋',
        iconClass: 'bg-sky-50 text-sky-700',
    },
    {
        key: 'published_notices',
        label: t('platform.stats.publishedNotices'),
        value: stats.value.published_notices,
        icon: '📣',
        iconClass: 'bg-rose-50 text-rose-700',
    },
]);

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/platform/dashboard');
        stats.value = data.stats;
        recentSchools.value = data.recent_schools ?? [];
    } finally {
        loading.value = false;
    }
});
</script>
