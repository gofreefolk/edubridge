<template>
    <section class="space-y-4">
        <h1 class="text-2xl font-bold">{{ t('alumni.title') }}</h1>
        <div v-if="portal?.events?.length" class="space-y-2">
            <h2 class="font-semibold">{{ t('alumni.events') }}</h2>
            <div v-for="e in portal.events" :key="e.id" class="rounded-xl border bg-white p-3">
                <p class="font-medium">{{ e.title }}</p>
                <p class="text-sm text-slate-500">{{ e.location }}</p>
            </div>
        </div>
        <div v-if="portal?.jobs?.length" class="space-y-2">
            <h2 class="font-semibold">{{ t('alumni.jobs') }}</h2>
            <div v-for="j in portal.jobs" :key="j.id" class="rounded-xl border bg-white p-3">
                <p class="font-medium">{{ j.title }}</p>
                <p class="text-sm">{{ j.company }}</p>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t } = useI18n();
const { activeSchoolId } = useSchoolContext();
const portal = ref(null);

onMounted(async () => {
    if (!activeSchoolId.value) return;
    const { data } = await axios.get('/api/alumni/portal', { params: { school_id: activeSchoolId.value } });
    portal.value = data;
});
</script>
