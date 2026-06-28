<template>
    <section class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('nav.calendar') }}</h1>
            <button
                v-if="isAdmin"
                type="button"
                class="rounded-xl bg-blue-700 px-3 py-2 text-sm font-semibold text-white"
                @click="showForm = !showForm"
            >
                + {{ t('admin.addEventShort') }}
            </button>
        </div>

        <form v-if="isAdmin && showForm" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="createEvent">
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.eventTitle') }}</span>
                <input v-model="form.title" type="text" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" />
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.eventTitleEn') }}</span>
                <input v-model="form.title_en" type="text" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" />
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.eventType') }}</span>
                <select v-model="form.event_type" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3">
                    <option value="holiday">{{ t('admin.eventHoliday') }}</option>
                    <option value="exam">{{ t('admin.eventExam') }}</option>
                    <option value="ptm">{{ t('admin.eventPtm') }}</option>
                    <option value="sports_day">{{ t('admin.eventSports') }}</option>
                    <option value="fee_due">{{ t('admin.eventFee') }}</option>
                    <option value="cultural_program">{{ t('admin.eventCultural') }}</option>
                    <option value="other">{{ t('admin.eventOther') }}</option>
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.eventStarts') }}</span>
                <input v-model="form.starts_at" type="datetime-local" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" />
            </label>
            <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3">
                <input v-model="form.all_day" type="checkbox" class="h-5 w-5" />
                <span class="text-sm font-medium text-slate-700">{{ t('admin.eventAllDay') }}</span>
            </label>
            <p v-if="formError" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError }}</p>
            <button type="submit" class="w-full rounded-xl bg-blue-700 py-3 font-semibold text-white" :disabled="saving">
                {{ saving ? t('common.loading') : t('admin.saveEvent') }}
            </button>
        </form>

        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>

        <p v-else-if="!events.length" class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-600">{{ t('admin.noEvents') }}</p>

        <div v-else class="space-y-3">
            <div v-for="event in events" :key="event.id" class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-medium uppercase text-blue-700">{{ eventTypeLabel(event.event_type) }}</p>
                <p class="text-lg font-semibold text-slate-900">{{ eventTitle(event) }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ formatDate(event.starts_at, event.all_day) }}</p>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';
import { useRole } from '@/composables/useRole';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t, locale } = useI18n();
const { isAuthenticated } = useAuth();
const { activeRole } = useRole();
const { activeSchoolId } = useSchoolContext();

const loading = ref(false);
const saving = ref(false);
const showForm = ref(false);
const formError = ref('');
const events = ref([]);

const isAdmin = computed(() => ['school_admin', 'super_admin'].includes(activeRole.value));

const form = reactive({
    title: '',
    title_en: '',
    event_type: 'holiday',
    starts_at: '',
    all_day: true,
});

const eventTypeLabels = {
    holiday: 'admin.eventHoliday',
    exam: 'admin.eventExam',
    ptm: 'admin.eventPtm',
    sports_day: 'admin.eventSports',
    fee_due: 'admin.eventFee',
    cultural_program: 'admin.eventCultural',
    other: 'admin.eventOther',
};

function eventTypeLabel(type) {
    return t(eventTypeLabels[type] ?? 'admin.eventOther');
}

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

async function createEvent() {
    formError.value = '';
    saving.value = true;
    try {
        await axios.post('/api/calendar', {
            school_id: activeSchoolId.value,
            title: form.title,
            title_en: form.title_en || null,
            event_type: form.event_type,
            starts_at: new Date(form.starts_at).toISOString(),
            all_day: form.all_day,
            audience_type: 'whole_school',
        });
        form.title = '';
        form.title_en = '';
        form.starts_at = '';
        showForm.value = false;
        await load();
    } catch (e) {
        formError.value = e.response?.data?.message ?? t('common.error');
    } finally {
        saving.value = false;
    }
}

watch([activeSchoolId, isAuthenticated, activeRole], load);
onMounted(load);
</script>
