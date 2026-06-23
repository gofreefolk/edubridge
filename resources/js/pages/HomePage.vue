<template>
    <section class="space-y-4" :class="{ 'text-xl': largeText }">
        <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-slate-600">
            {{ t('common.loading') }}
        </div>

        <template v-else>
            <div v-if="dashboard?.school" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm font-medium text-slate-500">{{ t('home.welcome') }}</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ dashboard.school.name }}</h1>
                <p v-if="dashboard.active_student" class="mt-1 text-base text-slate-700">
                    {{ dashboard.active_student.name }} — {{ dashboard.active_student.class }}-{{ dashboard.active_student.section }}
                </p>
            </div>

            <div v-if="children.length > 1" class="flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="child in children"
                    :key="child.id"
                    type="button"
                    class="shrink-0 rounded-full px-4 py-2 text-sm font-medium transition"
                    :class="child.id === activeStudentId ? 'bg-blue-700 text-white' : 'bg-white border border-slate-200 text-slate-700'"
                    @click="selectChild(child)"
                >
                    {{ child.name }}
                </button>
            </div>

            <div
                v-if="dashboard?.urgent_notice"
                class="rounded-2xl border border-red-200 bg-red-50 p-4"
            >
                <p class="text-sm font-semibold uppercase tracking-wide text-red-700">{{ t('home.urgent') }}</p>
                <router-link
                    :to="{ name: 'notice-magic', params: { token: dashboard.urgent_notice.magic_link_token } }"
                    class="mt-1 block text-lg font-medium text-red-900"
                >
                    {{ noticeTitle(dashboard.urgent_notice) }}
                </router-link>
            </div>

            <div class="grid gap-3">
                <div
                    v-if="dashboard?.upcoming_events?.[0]"
                    class="rounded-2xl border border-slate-200 bg-white p-4"
                >
                    <p class="text-base text-slate-700">
                        <span class="mr-2" aria-hidden="true">📅</span>
                        {{ t('home.upcoming') }}: {{ eventTitle(dashboard.upcoming_events[0]) }}
                    </p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-base text-slate-700">
                        <span class="mr-2" aria-hidden="true">📋</span>
                        {{ dashboard?.stats?.new_notices ?? 0 }} {{ t('home.newNotices') }}
                    </p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-base text-slate-700">
                        <span class="mr-2" aria-hidden="true">💬</span>
                        {{ dashboard?.stats?.replies_waiting ?? 0 }} {{ t('home.repliesWaiting') }}
                    </p>
                </div>
            </div>

            <div v-if="!isAuthenticated" class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                <router-link :to="{ name: 'login' }" class="font-semibold underline">{{ t('auth.loginTitle') }}</router-link>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t, locale } = useI18n();
const { user, isAuthenticated } = useAuth();
const { activeSchoolId, activeStudentId, children } = useSchoolContext();

const loading = ref(false);
const dashboard = ref(null);

const largeText = computed(() => user.value?.large_text_mode ?? false);

function noticeTitle(notice) {
    return locale.value === 'en' && notice.title_en ? notice.title_en : notice.title;
}

function eventTitle(event) {
    return locale.value === 'en' && event.title_en ? event.title_en : event.title;
}

async function loadDashboard() {
    if (!isAuthenticated.value || !activeSchoolId.value) {
        dashboard.value = null;
        return;
    }

    loading.value = true;
    try {
        const { data } = await axios.get('/api/parent/dashboard', {
            params: {
                school_id: activeSchoolId.value,
                student_id: activeStudentId.value,
            },
        });
        dashboard.value = data;
    } finally {
        loading.value = false;
    }
}

function selectChild(child) {
    activeStudentId.value = child.id;
    activeSchoolId.value = child.school_id;
}

watch([activeSchoolId, activeStudentId, isAuthenticated], loadDashboard);
onMounted(loadDashboard);
</script>
