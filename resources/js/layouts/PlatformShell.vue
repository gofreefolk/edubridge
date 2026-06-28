<template>
    <div class="flex min-h-dvh bg-slate-100 text-slate-900">
        <aside class="hidden w-64 shrink-0 flex-col border-r border-slate-800 bg-slate-900 text-white lg:flex">
            <div class="border-b border-slate-800 px-6 py-5">
                <p class="text-lg font-bold tracking-tight">EduBridge</p>
                <p class="mt-1 text-xs font-medium uppercase tracking-wider text-slate-400">
                    {{ t('platform.console') }}
                </p>
            </div>

            <nav class="flex-1 space-y-1 px-3 py-4">
                <router-link
                    v-for="item in navItems"
                    :key="item.route"
                    :to="{ name: item.route }"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                    :class="isActive(item.route)
                        ? 'bg-blue-600 text-white shadow-sm'
                        : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
                >
                    <span class="text-base" aria-hidden="true">{{ item.icon }}</span>
                    {{ t(item.label) }}
                </router-link>
            </nav>

            <div class="border-t border-slate-800 px-4 py-4">
                <p class="truncate text-sm font-medium text-white">{{ user?.name }}</p>
                <p class="truncate text-xs text-slate-400">{{ user?.phone }}</p>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 backdrop-blur">
                <div class="flex items-center justify-between gap-4 px-4 py-3 lg:px-8">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-blue-700 lg:hidden">
                            {{ t('platform.brandTitle') }}
                        </p>
                        <h1 class="truncate text-lg font-bold text-slate-900">{{ pageTitle }}</h1>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <LanguageSwitcher />
                        <router-link
                            :to="{ name: 'home' }"
                            class="hidden rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:inline-flex"
                        >
                            {{ t('platform.openApp') }}
                        </router-link>
                        <button
                            type="button"
                            class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                            @click="handleLogout"
                        >
                            {{ t('auth.logout') }}
                        </button>
                    </div>
                </div>

                <nav class="flex gap-1 overflow-x-auto border-t border-slate-100 px-4 py-2 lg:hidden">
                    <router-link
                        v-for="item in navItems"
                        :key="item.route"
                        :to="{ name: item.route }"
                        class="whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-medium transition"
                        :class="isActive(item.route)
                            ? 'bg-blue-600 text-white'
                            : 'bg-slate-100 text-slate-700'"
                    >
                        {{ t(item.label) }}
                    </router-link>
                </nav>
            </header>

            <main class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-6xl">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useAuth } from '@/composables/useAuth';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const { user, logout } = useAuth();

const navItems = [
    { route: 'platform-dashboard', label: 'platform.nav.dashboard', icon: '📊' },
    { route: 'platform-schools', label: 'platform.nav.schools', icon: '🏫' },
];

const pageTitle = computed(() => {
    const titles = {
        'platform-dashboard': t('platform.dashboardTitle'),
        'platform-schools': t('platform.schoolsTitle'),
    };

    return titles[route.name] ?? t('platform.console');
});

function isActive(name) {
    return route.name === name;
}

async function handleLogout() {
    await logout();
    router.push({ name: 'platform-login' });
}
</script>
