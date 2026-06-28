<template>
    <div class="relative">
        <button
            type="button"
            class="relative rounded-full border border-slate-200 bg-slate-50 p-2 text-lg transition hover:bg-slate-100"
            :aria-label="t('notifications.title')"
            @click="open = !open"
        >
            <span aria-hidden="true">🔔</span>
            <span
                v-if="badgeCount > 0"
                class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white"
            >
                {{ badgeCount > 9 ? '9+' : badgeCount }}
            </span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-20 mt-2 w-72 rounded-2xl border border-slate-200 bg-white p-3 shadow-lg"
        >
            <p class="mb-2 text-sm font-semibold text-slate-900">{{ t('notifications.title') }}</p>

            <div v-if="!summary" class="py-4 text-center text-sm text-slate-500">
                {{ t('common.loading') }}
            </div>

            <ul v-else class="space-y-2 text-sm">
                <li v-if="summary.unread_notices > 0">
                    <router-link
                        :to="{ name: 'notices' }"
                        class="block rounded-xl bg-blue-50 px-3 py-2 text-blue-900 hover:bg-blue-100"
                        @click="open = false"
                    >
                        {{ t('notifications.unreadNotices', { count: summary.unread_notices }) }}
                    </router-link>
                </li>
                <li v-if="summary.open_messages > 0">
                    <router-link
                        :to="{ name: 'messages' }"
                        class="block rounded-xl bg-amber-50 px-3 py-2 text-amber-900 hover:bg-amber-100"
                        @click="open = false"
                    >
                        {{ t('notifications.openMessages', { count: summary.open_messages }) }}
                    </router-link>
                </li>
                <li v-if="summary.inbox_messages > 0">
                    <router-link
                        :to="{ name: 'messages' }"
                        class="block rounded-xl bg-amber-50 px-3 py-2 text-amber-900 hover:bg-amber-100"
                        @click="open = false"
                    >
                        {{ t('notifications.inboxMessages', { count: summary.inbox_messages }) }}
                    </router-link>
                </li>
                <li v-if="summary.urgent_notice">
                    <router-link
                        :to="{ name: 'notice-magic', params: { token: summary.urgent_notice.magic_link_token } }"
                        class="block rounded-xl bg-red-50 px-3 py-2 font-medium text-red-900 hover:bg-red-100"
                        @click="open = false"
                    >
                        {{ t('notifications.urgentNotice') }}: {{ summary.urgent_notice.title }}
                    </router-link>
                </li>
                <li v-if="badgeCount === 0" class="py-2 text-center text-slate-500">
                    {{ t('notifications.allCaughtUp') }}
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotifications } from '@/composables/useNotifications';

const { t } = useI18n();
const { summary } = useNotifications();
const open = ref(false);

const badgeCount = computed(() => summary.value?.total_badge ?? 0);
</script>
