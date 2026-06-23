<template>
    <div class="flex min-h-dvh flex-col bg-slate-50 text-slate-900">
        <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-lg items-center justify-between gap-3 px-4 py-3">
                <div class="min-w-0">
                    <p class="truncate text-lg font-semibold text-blue-800">
                        {{ t('app.name') }}
                    </p>
                    <p class="truncate text-sm text-slate-600">
                        {{ t('app.tagline') }}
                    </p>
                </div>
                <LanguageSwitcher />
            </div>
        </header>

        <main class="mx-auto w-full max-w-lg flex-1 px-4 py-4">
            <slot />
        </main>

        <nav
            class="sticky bottom-0 border-t border-slate-200 bg-white/95 backdrop-blur"
            aria-label="Main navigation"
        >
            <div class="mx-auto grid max-w-lg grid-cols-4 gap-1 px-2 py-2">
                <button
                    v-for="item in navItems"
                    :key="item.key"
                    type="button"
                    class="flex flex-col items-center gap-1 rounded-xl px-2 py-2 text-center text-xs font-medium text-slate-700 transition hover:bg-blue-50 hover:text-blue-800"
                    :aria-label="t(item.label)"
                >
                    <span class="text-lg" aria-hidden="true">{{ item.icon }}</span>
                    <span class="leading-tight">{{ t(item.label) }}</span>
                </button>
            </div>
        </nav>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';

const { t } = useI18n();

const navItems = [
    { key: 'notices', label: 'nav.notices', icon: '📋' },
    { key: 'calendar', label: 'nav.calendar', icon: '📅' },
    { key: 'messages', label: 'nav.messages', icon: '💬' },
    { key: 'myChild', label: 'nav.myChild', icon: '👤' },
];
</script>
