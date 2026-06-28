<template>
    <PlatformShell v-if="usePlatformLayout">
        <router-view />
    </PlatformShell>
    <PlatformLoginPage v-else-if="isPlatformLogin" />
    <router-view v-else-if="isStandalone" />
    <AppShell v-else>
        <router-view />
    </AppShell>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppShell from '@/components/AppShell.vue';
import PlatformShell from '@/layouts/PlatformShell.vue';
import PlatformLoginPage from '@/pages/platform/PlatformLoginPage.vue';

const route = useRoute();

const usePlatformLayout = computed(() => route.meta.platformLayout === true);
const isPlatformLogin = computed(() => route.name === 'platform-login');
const isStandalone = computed(() => route.meta.standalone === true);
</script>
