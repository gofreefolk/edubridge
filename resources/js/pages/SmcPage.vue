<template>
    <section class="space-y-4">
        <h1 class="text-2xl font-bold text-slate-900">{{ t('smc.title') }}</h1>

        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>

        <template v-else-if="board">
            <div>
                <h2 class="mb-2 font-semibold">{{ t('smc.members') }}</h2>
                <div v-for="m in board.members" :key="m.id" class="rounded-xl border bg-white p-3 mb-2">
                    <p class="font-medium">{{ m.name }}</p>
                    <p class="text-sm text-slate-500">{{ m.role }}</p>
                </div>
            </div>

            <div>
                <h2 class="mb-2 font-semibold">{{ t('smc.meetings') }}</h2>
                <div v-for="meeting in board.meetings" :key="meeting.id" class="rounded-xl border bg-white p-3 mb-2">
                    <p class="font-medium">{{ meeting.title }}</p>
                    <p class="text-sm text-slate-500">{{ meeting.scheduled_at }}</p>
                </div>
            </div>

            <div>
                <h2 class="mb-2 font-semibold">{{ t('smc.development') }}</h2>
                <div v-for="item in board.development_items" :key="item.id" class="rounded-xl border bg-white p-3 mb-2">
                    <p class="font-medium">{{ item.title }}</p>
                    <p class="text-sm text-blue-700">{{ item.status }}</p>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t } = useI18n();
const { activeSchoolId } = useSchoolContext();

const loading = ref(false);
const board = ref(null);

async function load() {
    if (!activeSchoolId.value) return;
    loading.value = true;
    try {
        const { data } = await axios.get('/api/smc/board', { params: { school_id: activeSchoolId.value } });
        board.value = data;
    } finally {
        loading.value = false;
    }
}

watch(activeSchoolId, load);
onMounted(load);
</script>
