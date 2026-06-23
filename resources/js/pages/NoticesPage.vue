<template>
    <section class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('nav.notices') }}</h1>
            <router-link
                v-if="isAdmin"
                :to="{ name: 'admin-notice-create' }"
                class="rounded-xl bg-blue-700 px-3 py-2 text-sm font-semibold text-white"
            >
                + {{ t('admin.createNoticeShort') }}
            </router-link>
        </div>

        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>

        <p v-else-if="!isAuthenticated" class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-blue-900">
            <router-link :to="{ name: 'login' }" class="font-semibold underline">{{ t('auth.loginTitle') }}</router-link>
        </p>

        <div v-else class="space-y-3">
            <p v-if="!notices.length" class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-600">
                {{ t('admin.noNotices') }}
            </p>

            <div
                v-for="notice in notices"
                :key="notice.id"
                class="rounded-2xl border bg-white p-4 shadow-sm"
                :class="notice.priority === 'urgent' ? 'border-red-200' : 'border-slate-200'"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p v-if="notice.status === 'draft'" class="text-xs font-semibold uppercase text-amber-700">
                            {{ t('admin.draft') }}
                        </p>
                        <p v-if="notice.priority === 'urgent'" class="text-xs font-semibold uppercase text-red-700">
                            {{ t('home.urgent') }}
                        </p>
                        <p class="text-lg font-semibold text-slate-900">{{ noticeTitle(notice) }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ formatDate(notice.published_at) }}</p>
                    </div>
                </div>

                <div v-if="isAdmin && notice.magic_link_token" class="mt-3 space-y-2">
                    <p class="text-xs text-slate-500">{{ t('admin.parentLink') }}</p>
                    <p class="break-all rounded-lg bg-slate-50 p-2 text-xs text-slate-700">
                        {{ magicUrl(notice.magic_link_token) }}
                    </p>
                    <button
                        type="button"
                        class="text-sm font-semibold text-blue-800 underline"
                        @click="copyLink(notice.magic_link_token)"
                    >
                        {{ copiedToken === notice.magic_link_token ? t('admin.linkCopied') : t('admin.copyLink') }}
                    </button>
                </div>

                <router-link
                    v-else-if="notice.magic_link_token"
                    :to="{ name: 'notice-magic', params: { token: notice.magic_link_token } }"
                    class="mt-3 inline-block text-sm font-semibold text-blue-800 underline"
                >
                    {{ t('notice.openNotice') }}
                </router-link>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';
import { useRole } from '@/composables/useRole';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t, locale } = useI18n();
const { isAuthenticated } = useAuth();
const { activeRole } = useRole();
const { activeSchoolId, activeStudentId } = useSchoolContext();

const loading = ref(false);
const notices = ref([]);
const copiedToken = ref('');

const isAdmin = computed(() => ['school_admin', 'super_admin'].includes(activeRole.value));

function noticeTitle(notice) {
    return locale.value === 'en' && notice.title_en ? notice.title_en : notice.title;
}

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(locale.value === 'ml' ? 'ml-IN' : 'en-IN');
}

function magicUrl(token) {
    return `${window.location.origin}/n/${token}`;
}

async function copyLink(token) {
    try {
        await navigator.clipboard.writeText(magicUrl(token));
        copiedToken.value = token;
        setTimeout(() => {
            copiedToken.value = '';
        }, 2000);
    } catch {
        // ignore clipboard errors
    }
}

async function load() {
    if (!isAuthenticated.value || !activeSchoolId.value) return;
    loading.value = true;
    try {
        const { data } = await axios.get('/api/notices', {
            params: { school_id: activeSchoolId.value, student_id: activeStudentId.value },
        });
        notices.value = data.notices;
    } finally {
        loading.value = false;
    }
}

watch([activeSchoolId, activeStudentId, isAuthenticated, activeRole], load);
onMounted(load);
</script>
