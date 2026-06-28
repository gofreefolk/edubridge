<template>
    <div>
        <button
            ref="buttonRef"
            type="button"
            class="relative rounded-full border border-slate-200 bg-slate-50 p-2 text-lg transition hover:bg-slate-100"
            :aria-label="t('notifications.title')"
            :aria-expanded="open"
            @click="toggle"
        >
            <span aria-hidden="true">🔔</span>
            <span
                v-if="badgeCount > 0"
                class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white"
            >
                {{ badgeCount > 9 ? '9+' : badgeCount }}
            </span>
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                ref="panelRef"
                class="fixed z-50 rounded-2xl border border-slate-200 bg-white p-3 shadow-lg"
                :style="panelStyle"
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
                            @click="close"
                        >
                            {{ t('notifications.unreadNotices', { count: summary.unread_notices }) }}
                        </router-link>
                    </li>
                    <li v-if="summary.open_messages > 0">
                        <router-link
                            :to="{ name: 'messages' }"
                            class="block rounded-xl bg-amber-50 px-3 py-2 text-amber-900 hover:bg-amber-100"
                            @click="close"
                        >
                            {{ t('notifications.openMessages', { count: summary.open_messages }) }}
                        </router-link>
                    </li>
                    <li v-if="summary.inbox_messages > 0">
                        <router-link
                            :to="{ name: 'messages' }"
                            class="block rounded-xl bg-amber-50 px-3 py-2 text-amber-900 hover:bg-amber-100"
                            @click="close"
                        >
                            {{ t('notifications.inboxMessages', { count: summary.inbox_messages }) }}
                        </router-link>
                    </li>
                    <li v-if="summary.urgent_notice">
                        <router-link
                            :to="{ name: 'notice-magic', params: { token: summary.urgent_notice.magic_link_token } }"
                            class="block rounded-xl bg-red-50 px-3 py-2 font-medium text-red-900 hover:bg-red-100"
                            @click="close"
                        >
                            {{ t('notifications.urgentNotice') }}: {{ summary.urgent_notice.title }}
                        </router-link>
                    </li>
                    <li v-if="badgeCount === 0" class="py-2 text-center text-slate-500">
                        {{ t('notifications.allCaughtUp') }}
                    </li>
                </ul>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotifications } from '@/composables/useNotifications';

const PANEL_WIDTH = 288;
const VIEWPORT_PADDING = 8;

const { t } = useI18n();
const { summary } = useNotifications();

const open = ref(false);
const buttonRef = ref(null);
const panelRef = ref(null);
const panelStyle = ref({});

const badgeCount = computed(() => summary.value?.total_badge ?? 0);

function updatePosition() {
    const button = buttonRef.value;
    if (!button || !open.value) {
        return;
    }

    const rect = button.getBoundingClientRect();
    const width = Math.min(PANEL_WIDTH, window.innerWidth - VIEWPORT_PADDING * 2);
    let left = rect.right - width;
    left = Math.max(VIEWPORT_PADDING, Math.min(left, window.innerWidth - width - VIEWPORT_PADDING));

    panelStyle.value = {
        top: `${rect.bottom + 8}px`,
        left: `${left}px`,
        width: `${width}px`,
    };
}

function toggle() {
    open.value = !open.value;
}

function close() {
    open.value = false;
}

function handleDocumentClick(event) {
    if (!open.value) {
        return;
    }

    const target = event.target;
    if (buttonRef.value?.contains(target) || panelRef.value?.contains(target)) {
        return;
    }

    open.value = false;
}

function handleEscape(event) {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

watch(open, async (isOpen) => {
    if (isOpen) {
        await nextTick();
        updatePosition();
    }
});

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleEscape);
    window.addEventListener('resize', updatePosition);
    window.addEventListener('scroll', updatePosition, true);
});

onUnmounted(() => {
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleEscape);
    window.removeEventListener('resize', updatePosition);
    window.removeEventListener('scroll', updatePosition, true);
});
</script>
