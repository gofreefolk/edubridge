<template>
    <div v-if="hasMultipleRoles" class="flex flex-wrap gap-1.5">
        <button
            v-for="role in availableRoles"
            :key="role"
            type="button"
            class="rounded-full px-2.5 py-1 text-xs font-medium transition"
            :class="role === activeRole ? 'bg-blue-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
            @click="switchRole(role)"
        >
            {{ t(roleLabelKey(role)) }}
        </button>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useRole } from '@/composables/useRole';
import { roleLabelKey } from '@/config/roleNavigation';

const { t } = useI18n();
const router = useRouter();
const { activeRole, availableRoles, hasMultipleRoles, setActiveRole, defaultRouteForRole } = useRole();

function switchRole(role) {
    setActiveRole(role);
    router.push({ name: defaultRouteForRole() });
}
</script>
