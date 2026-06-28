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
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap gap-2">
                            <p v-if="notice.status === 'draft'" class="text-xs font-semibold uppercase text-amber-700">{{ t('admin.draft') }}</p>
                            <p v-if="notice.status === 'archived'" class="text-xs font-semibold uppercase text-slate-500">{{ t('admin.archived') }}</p>
                            <p v-if="notice.scheduled_publish_at && notice.status === 'draft'" class="text-xs font-semibold uppercase text-violet-700">
                                {{ t('admin.scheduled') }}
                            </p>
                            <p v-if="notice.priority === 'urgent'" class="text-xs font-semibold uppercase text-red-700">{{ t('home.urgent') }}</p>
                        </div>
                        <p class="text-lg font-semibold text-slate-900">{{ noticeTitle(notice) }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            <span v-if="notice.scheduled_publish_at && notice.status === 'draft'">
                                {{ t('admin.scheduledFor') }} {{ formatDate(notice.scheduled_publish_at) }}
                            </span>
                            <span v-else>{{ formatDate(notice.published_at) }}</span>
                        </p>
                        <p v-if="isAdmin && notice.status === 'published' && notice.eligible_count != null" class="mt-1 text-sm text-blue-800">
                            {{ t('admin.readStats', { read: notice.read_count, total: notice.eligible_count, percent: notice.read_percent }) }}
                        </p>
                    </div>
                </div>

                <div v-if="isAdmin" class="mt-3 flex flex-wrap gap-2">
                    <router-link
                        v-if="notice.status !== 'archived'"
                        :to="{ name: 'admin-notice-edit', params: { id: notice.id } }"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700"
                    >
                        {{ t('common.edit') }}
                    </router-link>
                    <button
                        v-if="notice.status === 'published'"
                        type="button"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-900"
                        @click="unpublish(notice)"
                    >
                        {{ t('admin.unpublish') }}
                    </button>
                    <button
                        v-if="notice.status === 'published' && notice.priority === 'urgent'"
                        type="button"
                        class="rounded-lg border border-green-200 bg-green-50 px-3 py-1.5 text-sm font-medium text-green-900"
                        @click="sendWhatsApp(notice, !!notice.whatsapp_sent_at)"
                    >
                        {{ notice.whatsapp_sent_at ? t('admin.resendWhatsapp') : t('admin.sendWhatsapp') }}
                    </button>
                    <button
                        v-if="notice.status === 'published'"
                        type="button"
                        class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-900"
                        @click="toggleStats(notice)"
                    >
                        {{ expandedStatsId === notice.id ? t('admin.hideStats') : t('admin.viewStats') }}
                    </button>
                </div>

                <div v-if="isAdmin && expandedStatsId === notice.id && stats[notice.id]" class="mt-3 rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm">
                    <p class="font-semibold text-slate-900">{{ t('admin.readStats', { read: stats[notice.id].read_count, total: stats[notice.id].eligible_count, percent: stats[notice.id].read_percent }) }}</p>
                    <p v-if="stats[notice.id].unread.length" class="mt-2 font-medium text-slate-700">{{ t('admin.notReadYet') }}</p>
                    <ul class="mt-1 space-y-1 text-slate-600">
                        <li v-for="parent in stats[notice.id].unread.slice(0, 8)" :key="parent.id">
                            {{ parent.name }} · {{ parent.phone }}
                        </li>
                    </ul>
                    <p v-if="stats[notice.id].unread.length > 8" class="mt-1 text-xs text-slate-500">
                        +{{ stats[notice.id].unread.length - 8 }} {{ t('admin.moreParents') }}
                    </p>
                </div>

                <div v-if="isAdmin && notice.magic_link_token && notice.status === 'published'" class="mt-3 space-y-2">
                    <p class="text-xs text-slate-500">{{ t('admin.parentLink') }}</p>
                    <p class="break-all rounded-lg bg-slate-50 p-2 text-xs text-slate-700">{{ magicUrl(notice.magic_link_token) }}</p>
                    <button type="button" class="text-sm font-semibold text-blue-800 underline" @click="copyLink(notice.magic_link_token)">
                        {{ copiedToken === notice.magic_link_token ? t('admin.linkCopied') : t('admin.copyLink') }}
                    </button>
                </div>

                <router-link
                    v-else-if="notice.magic_link_token && notice.status === 'published'"
                    :to="{ name: 'notice-magic', params: { token: notice.magic_link_token } }"
                    class="mt-3 inline-block text-sm font-semibold text-blue-800 underline"
                >
                    {{ t('notice.openNotice') }}
                </router-link>
            </div>
        </div>

        <p v-if="actionMessage" class="rounded-xl bg-green-50 px-3 py-2 text-sm text-green-800">{{ actionMessage }}</p>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
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
const expandedStatsId = ref(null);
const stats = reactive({});
const actionMessage = ref('');

const isAdmin = computed(() => ['school_admin', 'super_admin'].includes(activeRole.value));

function noticeTitle(notice) {
    return locale.value === 'en' && notice.title_en ? notice.title_en : notice.title;
}

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString(locale.value === 'ml' ? 'ml-IN' : 'en-IN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function magicUrl(token) {
    return `${window.location.origin}/n/${token}`;
}

async function copyLink(token) {
    try {
        await navigator.clipboard.writeText(magicUrl(token));
        copiedToken.value = token;
        setTimeout(() => { copiedToken.value = ''; }, 2000);
    } catch {
        // ignore
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

async function unpublish(notice) {
    if (!window.confirm(t('admin.confirmUnpublish'))) return;
    const { data } = await axios.post(`/api/notices/${notice.id}/unpublish`);
    Object.assign(notice, data.notice);
    actionMessage.value = t('admin.unpublishedSuccess');
}

async function sendWhatsApp(notice, force) {
    const { data } = await axios.post(`/api/notices/${notice.id}/whatsapp`, { force });
    actionMessage.value = data.message;
    await load();
}

async function toggleStats(notice) {
    if (expandedStatsId.value === notice.id) {
        expandedStatsId.value = null;
        return;
    }
    expandedStatsId.value = notice.id;
    if (!stats[notice.id]) {
        const { data } = await axios.get(`/api/notices/${notice.id}/stats`);
        stats[notice.id] = data.stats;
    }
}

watch([activeSchoolId, activeStudentId, isAuthenticated, activeRole], load);
onMounted(load);
</script>
