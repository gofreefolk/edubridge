<template>
    <section class="space-y-4">
        <h1 class="text-2xl font-bold text-slate-900">{{ t('nav.driver') }}</h1>

        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>

        <template v-else-if="routes.length">
            <div
                v-for="route in routes"
                :key="route.id"
                class="rounded-2xl border border-slate-200 bg-white p-4"
            >
                <p class="font-semibold">{{ route.name }}</p>
                <p class="text-sm text-slate-500">{{ route.stops?.length }} {{ t('driver.stops') }}</p>
                <button
                    type="button"
                    class="mt-3 w-full rounded-xl bg-blue-700 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                    :disabled="starting === route.id"
                    @click="startTrip(route.id)"
                >
                    {{ starting === route.id ? t('common.loading') : t('driver.startTrip') }}
                </button>
            </div>
            <p v-if="activeTrip" class="rounded-2xl border border-green-200 bg-green-50 p-4 text-green-800">
                {{ t('driver.tripActive') }} #{{ activeTrip.id }}
            </p>
        </template>

        <p v-else class="text-slate-600">{{ t('driver.noRoutes') }}</p>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t } = useI18n();
const { activeSchoolId } = useSchoolContext();

const loading = ref(false);
const routes = ref([]);
const starting = ref(null);
const activeTrip = ref(null);

async function load() {
    if (!activeSchoolId.value) {
        return;
    }

    loading.value = true;
    try {
        const { data } = await axios.get('/api/transport', { params: { school_id: activeSchoolId.value } });
        routes.value = data.routes ?? [];
    } finally {
        loading.value = false;
    }
}

async function startTrip(routeId) {
    starting.value = routeId;
    try {
        const { data } = await axios.post('/api/transport/trips', { route_id: routeId });
        activeTrip.value = data.trip;
    } finally {
        starting.value = null;
    }
}

onMounted(load);
</script>
