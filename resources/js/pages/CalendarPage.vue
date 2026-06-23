<template>
    <section class="space-y-4">
        <h1 class="text-2xl font-bold text-slate-900">{{ t('nav.calendar') }}</h1>

        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>

        <div v-else class="space-y-3">
            <div
                v-for="event in events"
                :key="event.id"
                class="rounded-2xl border border-slate-200 bg-white p-4"
            >
                <p class="text-xs font-medium uppercase text-blue-700">{{ event.event_type }}</p>
                <p class="text-lg font-semibold text-slate-900">{{ eventTitle(event) }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ formatDate(event.starts_at, event.all_day) }}</p>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t, locale } = useI18n();
const { isAuthenticated } = useAuth();
const { activeSchoolId } = useSchoolContext();

const loading = ref(false);
const events = ref([]);

function eventTitle(event) {
    return locale.value === 'en' && event.title_en ? event.title_en : event.title;
}

function formatDate(iso, allDay = false) {
    return new Date(iso).toLocaleString(locale.value === 'ml' ? 'ml-IN' : 'en-IN', {
        dateStyle: 'medium',
        ...(allDay ? {} : { timeStyle: 'short' }),
    });
}

async function load() {
    if (!isAuthenticated.value || !activeSchoolId.value) return;
    loading.value = true;
    try {
        const { data } = await axios.get('/api/calendar', { params: { school_id: activeSchoolId.value } });
        events.value = data.events;
    } finally {
        loading.value = false;
    }
}

watch([activeSchoolId, isAuthenticated], load);
onMounted(load);
</script>
