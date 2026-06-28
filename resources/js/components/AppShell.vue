<template>
    <div class="flex min-h-dvh flex-col bg-slate-50 text-slate-900">
        <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-lg items-center justify-between gap-3 px-4 py-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-lg font-semibold text-blue-800">
                        {{ t('app.name') }}
                    </p>
                    <p v-if="isAuthenticated && activeRole" class="truncate text-xs text-slate-500">
                        {{ t(roleLabelKey(activeRole)) }}
                    </p>
                    <p v-else class="truncate text-sm text-slate-600">
                        {{ t('app.tagline') }}
                    </p>
                    <div v-if="isAuthenticated" class="mt-1.5">
                        <RoleSwitcher />
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <NotificationBell v-if="isAuthenticated" />
                    <router-link
                        v-if="!isAuthenticated"
                        :to="{ name: 'login' }"
                        class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-800 transition hover:bg-blue-100"
                    >
                        {{ t('auth.loginShort') }}
                    </router-link>
                    <button
                        v-else
                        type="button"
                        class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                        @click="handleLogout"
                    >
                        {{ t('auth.logout') }}
                    </button>
                    <LanguageSwitcher />
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-lg flex-1 px-4 py-4">
            <slot />
        </main>

        <nav
            v-if="showNav"
            class="sticky bottom-0 border-t border-slate-200 bg-white/95 backdrop-blur"
            aria-label="Main navigation"
        >
            <div
                class="mx-auto grid gap-1 px-2 py-2"
                :class="navGridClass"
                :style="navGridStyle"
            >
                <router-link
                    v-for="item in navigationItems"
                    :key="item.key"
                    :to="{ name: item.route }"
                    class="flex flex-col items-center gap-1 rounded-xl px-2 py-2 text-center text-xs font-medium transition"
                    :class="isActive(item.route) ? 'bg-blue-50 text-blue-800' : 'text-slate-700 hover:bg-blue-50 hover:text-blue-800'"
                    :aria-label="t(item.label)"
                >
                    <span class="text-lg" aria-hidden="true">{{ item.icon }}</span>
                    <span class="leading-tight">{{ t(item.label) }}</span>
                </router-link>
            </div>
        </nav>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import RoleSwitcher from '@/components/RoleSwitcher.vue';
import { useAuth } from '@/composables/useAuth';
import { useRole } from '@/composables/useRole';
import { roleLabelKey } from '@/config/roleNavigation';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const { isAuthenticated, logout } = useAuth();
const { activeRole, navigationItems } = useRole();

const showNav = computed(() => {
    if (!isAuthenticated.value) {
        return false;
    }

    if (route.meta.hideNav) {
        return false;
    }

    return navigationItems.value.length > 0;
});

const navGridClass = computed(() => {
    const count = navigationItems.value.length;

    if (count <= 3) {
        return `grid-cols-${count}`;
    }

    return 'grid-cols-4';
});

const navGridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${navigationItems.value.length}, minmax(0, 1fr))`,
    maxWidth: '32rem',
}));

function isActive(name) {
    return route.name === name;
}

async function handleLogout() {
    await logout();
    router.push({ name: 'home' });
}
</script>
