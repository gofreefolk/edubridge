<template>
    <section class="space-y-4">
        <h1 class="text-2xl font-bold text-slate-900">{{ t('ops.reportTitle') }}</h1>

        <div class="grid grid-cols-1 gap-2 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-3">
            <template v-if="isAdmin">
                <select v-model="classId" class="rounded-xl border border-slate-300 px-3 py-2">
                    <option :value="null">{{ t('admin.chooseClass') }}</option>
                    <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                </select>
                <select v-model="sectionId" class="rounded-xl border border-slate-300 px-3 py-2">
                    <option :value="null">{{ t('ops.allSections') }}</option>
                    <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </template>
            <input v-model="month" type="month" :max="thisMonth" class="rounded-xl border border-slate-300 px-3 py-2" :class="isAdmin ? '' : 'sm:col-span-3'" />
        </div>

        <p v-if="!classId" class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-600">{{ t('teacher.noClass') }}</p>
        <div v-else-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>
        <p v-else-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

        <template v-else-if="report">
            <div class="grid grid-cols-3 gap-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-3 text-center">
                    <p class="text-2xl font-bold text-slate-900">{{ report.summary.average_percent ?? '—' }}<span v-if="report.summary.average_percent !== null" class="text-base">%</span></p>
                    <p class="text-xs text-slate-500">{{ t('ops.averagePercent') }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-3 text-center">
                    <p class="text-2xl font-bold text-slate-900">{{ report.summary.school_days }}</p>
                    <p class="text-xs text-slate-500">{{ t('ops.schoolDays') }}</p>
                </div>
                <div class="rounded-2xl border p-3 text-center" :class="report.summary.below_75 ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-white'">
                    <p class="text-2xl font-bold" :class="report.summary.below_75 ? 'text-red-700' : 'text-slate-900'">{{ report.summary.below_75 }}</p>
                    <p class="text-xs text-slate-500">{{ t('ops.below75') }}</p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">{{ t('ops.student') }}</th>
                            <th class="px-2 py-2 text-center">{{ t('ops.presentShort') }}</th>
                            <th class="px-2 py-2 text-center">{{ t('ops.absentShort') }}</th>
                            <th class="px-2 py-2 text-center">{{ t('ops.lateShort') }}</th>
                            <th class="px-2 py-2 text-right">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in report.students" :key="row.id" class="border-t border-slate-100">
                            <td class="px-3 py-2">
                                <router-link :to="{ name: 'student-profile', params: { id: row.id } }" class="text-slate-900">{{ row.name }}</router-link>
                            </td>
                            <td class="px-2 py-2 text-center">{{ row.present }}</td>
                            <td class="px-2 py-2 text-center" :class="row.absent ? 'font-semibold text-red-700' : ''">{{ row.absent }}</td>
                            <td class="px-2 py-2 text-center">{{ row.late }}</td>
                            <td class="px-2 py-2 text-right font-semibold" :class="row.percent !== null && row.percent < 75 ? 'text-red-700' : 'text-slate-900'">
                                {{ row.percent ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-slate-500">{{ t('ops.reportHint') }}</p>
            <a :href="csvUrl" class="block rounded-xl border border-slate-300 bg-white py-2 text-center text-sm font-semibold text-slate-700">{{ t('ops.downloadCsv') }}</a>
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useClassPicker } from '@/composables/useClassPicker';

const { t } = useI18n();
const { classes, sections, classId, sectionId, isAdmin, loadClasses } = useClassPicker();

const thisMonth = new Date().toISOString().slice(0, 7);
const month = ref(thisMonth);
const report = ref(null);
const loading = ref(false);
const error = ref('');
const csvUrl = computed(() => {
    const params = new URLSearchParams({ school_class_id: classId.value ?? '', month: month.value, format: 'csv' });
    if (sectionId.value) params.set('section_id', sectionId.value);
    return `/api/attendance/report?${params}`;
});

async function load() {
    error.value = '';
    report.value = null;
    if (!classId.value || !month.value) return;
    loading.value = true;
    try {
        const { data } = await axios.get('/api/attendance/report', {
            params: { school_class_id: classId.value, section_id: sectionId.value || undefined, month: month.value },
        });
        report.value = data;
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    } finally {
        loading.value = false;
    }
}

watch([classId, sectionId, month], load);
onMounted(async () => {
    await loadClasses();
    await load();
});
</script>
