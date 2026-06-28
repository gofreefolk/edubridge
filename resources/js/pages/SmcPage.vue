<template>
    <section class="space-y-4">
        <h1 class="text-2xl font-bold text-slate-900">{{ t('smc.title') }}</h1>

        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>

        <template v-else-if="board">
            <div v-if="isAdmin" class="rounded-2xl border border-slate-200 bg-white p-4">
                <h2 class="font-semibold text-slate-900">{{ t('smc.scheduleMeeting') }}</h2>
                <form class="mt-3 space-y-3" @submit.prevent="createMeeting">
                    <input
                        v-model="meetingForm.title"
                        type="text"
                        required
                        :placeholder="t('smc.meetingTitle')"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3"
                    />
                    <textarea
                        v-model="meetingForm.agenda"
                        rows="3"
                        :placeholder="t('smc.meetingAgenda')"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3"
                    />
                    <input
                        v-model="meetingForm.scheduled_at"
                        type="datetime-local"
                        required
                        class="w-full rounded-xl border border-slate-300 px-4 py-3"
                    />
                    <p v-if="formError" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError }}</p>
                    <button type="submit" class="w-full rounded-xl bg-blue-700 py-3 font-semibold text-white" :disabled="saving">
                        {{ saving ? t('common.loading') : t('smc.saveMeeting') }}
                    </button>
                </form>
            </div>

            <div>
                <h2 class="mb-2 font-semibold">{{ t('smc.members') }}</h2>
                <p v-if="!board.members.length" class="rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-600">
                    {{ t('smc.noMembers') }}
                </p>
                <div v-for="m in board.members" :key="m.id" class="mb-2 rounded-xl border bg-white p-3">
                    <p class="font-medium">{{ m.name }}</p>
                    <p class="text-sm text-slate-500">{{ m.role }} · {{ m.phone || '—' }}</p>
                </div>
                <p v-if="isAdmin" class="text-xs text-slate-500">{{ t('smc.membersHint') }}</p>
            </div>

            <div>
                <h2 class="mb-2 font-semibold">{{ t('smc.meetings') }}</h2>
                <p v-if="!board.meetings.length" class="rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-600">
                    {{ t('smc.noMeetings') }}
                </p>
                <div v-for="meeting in board.meetings" :key="meeting.id" class="mb-3 rounded-xl border bg-white p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-medium text-slate-900">{{ meeting.title }}</p>
                            <p class="text-sm text-slate-500">{{ formatDate(meeting.scheduled_at) }}</p>
                            <p v-if="meeting.agenda" class="mt-1 text-sm text-slate-600">{{ meeting.agenda }}</p>
                            <p class="mt-1 text-xs uppercase text-blue-700">{{ meeting.status || 'scheduled' }}</p>
                        </div>
                        <button
                            v-if="isAdmin"
                            type="button"
                            class="shrink-0 text-sm font-medium text-blue-800"
                            @click="toggleMinutes(meeting)"
                        >
                            {{ editingMinutesId === meeting.id ? t('common.cancel') : t('smc.editMinutes') }}
                        </button>
                    </div>

                    <p v-if="meeting.minutes && editingMinutesId !== meeting.id" class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700 whitespace-pre-wrap">
                        {{ meeting.minutes }}
                    </p>

                    <form v-if="isAdmin && editingMinutesId === meeting.id" class="mt-3 space-y-2" @submit.prevent="saveMinutes(meeting)">
                        <textarea
                            v-model="minutesForm.minutes"
                            rows="5"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            :placeholder="t('smc.minutesPlaceholder')"
                        />
                        <select v-model="minutesForm.status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="scheduled">{{ t('smc.statusScheduled') }}</option>
                            <option value="completed">{{ t('smc.statusCompleted') }}</option>
                            <option value="cancelled">{{ t('smc.statusCancelled') }}</option>
                        </select>
                        <button type="submit" class="w-full rounded-lg bg-slate-800 py-2 text-sm font-semibold text-white">
                            {{ t('smc.saveMinutes') }}
                        </button>
                    </form>
                </div>
            </div>

            <div>
                <h2 class="mb-2 font-semibold">{{ t('smc.development') }}</h2>
                <div v-for="item in board.development_items" :key="item.id" class="mb-2 rounded-xl border bg-white p-3">
                    <p class="font-medium">{{ item.title }}</p>
                    <p class="text-sm text-blue-700">{{ item.status }}</p>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useRole } from '@/composables/useRole';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t, locale } = useI18n();
const { activeRole } = useRole();
const { activeSchoolId } = useSchoolContext();

const loading = ref(false);
const saving = ref(false);
const formError = ref('');
const board = ref(null);
const editingMinutesId = ref(null);
const meetingForm = reactive({ title: '', agenda: '', scheduled_at: '' });
const minutesForm = reactive({ minutes: '', status: 'completed' });

const isAdmin = computed(() => ['school_admin', 'super_admin'].includes(activeRole.value));

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString(locale.value === 'ml' ? 'ml-IN' : 'en-IN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

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

async function createMeeting() {
    formError.value = '';
    saving.value = true;
    try {
        await axios.post('/api/smc/meetings', {
            school_id: activeSchoolId.value,
            title: meetingForm.title,
            agenda: meetingForm.agenda || null,
            scheduled_at: new Date(meetingForm.scheduled_at).toISOString(),
        });
        meetingForm.title = '';
        meetingForm.agenda = '';
        meetingForm.scheduled_at = '';
        await load();
    } catch (e) {
        formError.value = e.response?.data?.message ?? t('common.error');
    } finally {
        saving.value = false;
    }
}

function toggleMinutes(meeting) {
    if (editingMinutesId.value === meeting.id) {
        editingMinutesId.value = null;
        return;
    }
    editingMinutesId.value = meeting.id;
    minutesForm.minutes = meeting.minutes ?? '';
    minutesForm.status = meeting.status ?? 'completed';
}

async function saveMinutes(meeting) {
    try {
        await axios.put(`/api/smc/meetings/${meeting.id}/minutes`, {
            minutes: minutesForm.minutes,
            status: minutesForm.status,
        });
        editingMinutesId.value = null;
        await load();
    } catch (e) {
        formError.value = e.response?.data?.message ?? t('common.error');
    }
}

watch(activeSchoolId, load);
onMounted(load);
</script>
