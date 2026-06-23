<template>
    <section class="space-y-4">
        <h1 class="text-2xl font-bold">{{ t('transport.title') }}</h1>
        <div v-if="status?.route" class="rounded-2xl border bg-white p-4">
            <p class="font-semibold">{{ status.route.name }}</p>
            <p v-if="status.stop" class="text-slate-600">{{ t('transport.stop') }}: {{ status.stop.name }}</p>
            <p v-if="status.today_trip?.delay_note" class="mt-2 text-amber-700">{{ status.today_trip.delay_note }}</p>
        </div>
        <p v-else class="text-slate-600">{{ t('transport.noRoute') }}</p>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t } = useI18n();
const { activeStudentId } = useSchoolContext();
const status = ref(null);

onMounted(async () => {
    if (!activeStudentId.value) return;
    const { data } = await axios.get('/api/transport/student', { params: { student_id: activeStudentId.value } });
    status.value = data;
});
</script>
