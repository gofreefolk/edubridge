<template>
    <section class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('ops.checklistsTitle') }}</h1>
            <input v-model="date" type="date" :max="todayIso" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" />
        </div>

        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>
        <p v-else-if="!checklists.length" class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-600">{{ t('ops.noChecklists') }}</p>

        <!-- Complete today's checklists -->
        <form
            v-for="list in checklists.filter((c) => c.is_active)"
            :key="list.id"
            class="space-y-2 rounded-2xl border border-slate-200 bg-white p-4"
            @submit.prevent="submit(list)"
        >
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="font-semibold text-slate-900">{{ list.name }}</h2>
                    <p class="text-xs text-slate-500">{{ t(`ops.${list.frequency}`) }} · {{ list.period_date }}</p>
                </div>
                <span v-if="list.submission" class="rounded px-2 py-0.5 text-xs font-semibold" :class="list.submission.done_count === list.submission.total_count ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">
                    {{ list.submission.done_count }}/{{ list.submission.total_count }}
                </span>
            </div>

            <div v-for="(item, i) in list.items" :key="i" class="rounded-lg bg-slate-50 p-2">
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="drafts[list.id][i].done" type="checkbox" class="h-5 w-5" />
                    <span>{{ item }}</span>
                </label>
                <input
                    v-if="!drafts[list.id][i].done"
                    v-model="drafts[list.id][i].note"
                    :placeholder="t('ops.itemNote')"
                    class="mt-1 w-full rounded border border-slate-200 px-2 py-1 text-xs"
                />
            </div>

            <p v-if="list.submission" class="text-xs text-slate-500">{{ t('ops.completedBy', { name: list.submission.completed_by }) }}</p>
            <button type="submit" class="w-full rounded-xl bg-blue-700 py-2 font-semibold text-white">{{ t('ops.saveChecklist') }}</button>
        </form>

        <p v-if="message" class="rounded-xl bg-green-50 px-3 py-2 text-sm text-green-800">{{ message }}</p>
        <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

        <!-- Admin: manage templates and see completion -->
        <template v-if="canManage">
            <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                <h2 class="font-semibold text-slate-800">{{ t('ops.manage') }}</h2>

                <div v-for="list in checklists" :key="`m-${list.id}`" class="flex items-center justify-between rounded-lg bg-slate-50 p-2 text-sm">
                    <span>{{ list.name }} <span class="text-slate-500">· {{ t(`ops.${list.frequency}`) }} · {{ list.items.length }}</span></span>
                    <button type="button" class="text-xs font-semibold" :class="list.is_active ? 'text-red-700' : 'text-green-700'" @click="toggleActive(list)">
                        {{ list.is_active ? t('ops.deactivate') : t('ops.activate') }}
                    </button>
                </div>

                <form class="space-y-2 rounded-lg border border-dashed border-slate-200 p-3" @submit.prevent="createTemplate">
                    <p class="text-sm font-medium text-slate-700">{{ t('ops.newChecklist') }}</p>
                    <input v-model="newTemplate.name" required :placeholder="t('ops.checklistName')" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <select v-model="newTemplate.frequency" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="daily">{{ t('ops.daily') }}</option>
                        <option value="weekly">{{ t('ops.weekly') }}</option>
                    </select>
                    <textarea v-model="newTemplate.itemsText" required rows="4" :placeholder="t('ops.itemsHint')" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <button type="submit" class="w-full rounded-lg bg-slate-800 py-2 text-sm font-semibold text-white">{{ t('ops.newChecklist') }}</button>
                </form>
            </div>

            <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                <h2 class="font-semibold text-slate-800">{{ t('ops.completionReport') }}</h2>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-xs text-slate-600">{{ t('ops.from') }}<input v-model="reportFrom" type="date" :max="todayIso" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" /></label>
                    <label class="text-xs text-slate-600">{{ t('ops.to') }}<input v-model="reportTo" type="date" :max="todayIso" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" /></label>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" class="rounded-lg border border-blue-300 bg-blue-50 py-2 text-sm font-semibold text-blue-800" @click="loadReport">{{ t('ops.showReport') }}</button>
                    <a :href="reportCsvUrl" class="rounded-lg border border-slate-300 py-2 text-center text-sm font-semibold text-slate-700">{{ t('ops.downloadCsv') }}</a>
                </div>

                <div v-for="row in report" :key="`r-${row.id}`" class="rounded-lg bg-slate-50 p-3 text-sm">
                    <div class="flex justify-between">
                        <span class="font-medium">{{ row.name }}</span>
                        <span class="font-semibold" :class="row.percent !== null && row.percent < 90 ? 'text-red-700' : 'text-green-700'">
                            {{ row.completed }}/{{ row.expected }} {{ row.percent !== null ? `(${row.percent}%)` : '' }}
                        </span>
                    </div>
                    <p v-if="row.missed.length" class="mt-1 text-xs text-slate-500">{{ t('ops.missed') }}: {{ row.missed.join(', ') }}</p>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t } = useI18n();
const { activeSchoolId } = useSchoolContext();

const todayIso = new Date().toISOString().slice(0, 10);
const date = ref(todayIso);
const checklists = ref([]);
const drafts = reactive({});
const canManage = ref(false);
const loading = ref(false);
const message = ref('');
const error = ref('');
const newTemplate = reactive({ name: '', frequency: 'daily', itemsText: '' });
const reportFrom = ref(new Date(Date.now() - 6 * 86400000).toISOString().slice(0, 10));
const reportTo = ref(todayIso);
const report = ref([]);
const reportCsvUrl = computed(() => `/api/checklists/report?${new URLSearchParams({
    school_id: activeSchoolId.value ?? '',
    from: reportFrom.value,
    to: reportTo.value,
    format: 'csv',
})}`);

function resetDrafts() {
    for (const list of checklists.value) {
        drafts[list.id] = list.items.map((_, i) => ({
            done: list.submission?.responses?.[i]?.done ?? false,
            note: list.submission?.responses?.[i]?.note ?? '',
        }));
    }
}

async function load() {
    if (!activeSchoolId.value) return;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/api/checklists', {
            params: { school_id: activeSchoolId.value, date: date.value, include_inactive: 1 },
        });
        checklists.value = data.checklists;
        canManage.value = data.can_manage;
        resetDrafts();
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    } finally {
        loading.value = false;
    }
}

async function submit(list) {
    message.value = '';
    error.value = '';
    try {
        await axios.post(`/api/checklists/${list.id}/submit`, {
            date: date.value,
            responses: drafts[list.id].map((d) => ({ done: d.done, note: d.done ? null : d.note || null })),
        });
        message.value = t('ops.saved');
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

async function createTemplate() {
    const items = newTemplate.itemsText.split('\n').map((s) => s.trim()).filter(Boolean);
    try {
        await axios.post('/api/checklists', {
            school_id: activeSchoolId.value,
            name: newTemplate.name,
            frequency: newTemplate.frequency,
            items,
        });
        Object.assign(newTemplate, { name: '', frequency: 'daily', itemsText: '' });
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

async function toggleActive(list) {
    await axios.put(`/api/checklists/${list.id}`, { is_active: !list.is_active });
    await load();
}

async function loadReport() {
    const { data } = await axios.get('/api/checklists/report', {
        params: { school_id: activeSchoolId.value, from: reportFrom.value, to: reportTo.value },
    });
    report.value = data.checklists;
}

watch([date, activeSchoolId], load);
onMounted(load);
</script>
