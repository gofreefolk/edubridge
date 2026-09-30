<template>
    <section class="space-y-4">
        <h1 class="text-2xl font-bold text-slate-900">{{ t('fees.reportsTitle') }}</h1>
        <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

        <!-- Collections day-book -->
        <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
            <h2 class="font-semibold text-slate-800">{{ t('fees.collectionsTitle') }}</h2>
            <div class="grid grid-cols-2 gap-2">
                <label class="text-xs text-slate-600">{{ t('ops.from') }}<input v-model="from" type="date" :max="todayIso" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" /></label>
                <label class="text-xs text-slate-600">{{ t('ops.to') }}<input v-model="to" type="date" :max="todayIso" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" /></label>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" class="rounded-lg border border-blue-300 bg-blue-50 py-2 text-sm font-semibold text-blue-800" @click="loadCollections">{{ t('ops.showReport') }}</button>
                <a :href="collectionsCsv" class="rounded-lg border border-slate-300 py-2 text-center text-sm font-semibold text-slate-700">{{ t('ops.downloadCsv') }}</a>
            </div>

            <template v-if="collections">
                <div class="rounded-xl bg-slate-50 p-3 text-center">
                    <p class="text-2xl font-bold text-slate-900">{{ formatRupees(collections.total_paise) }}</p>
                    <p class="text-xs text-slate-500">{{ t('fees.receiptsCount', { count: collections.count }) }}</p>
                    <p class="mt-1 text-xs text-slate-600">
                        <span v-for="(amount, method) in collections.by_method" :key="method" class="mr-2">{{ t(`fees.method_${method}`) }} {{ formatRupees(amount) }}</span>
                    </p>
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <router-link
                        v-for="p in collections.payments"
                        :key="p.id"
                        :to="{ name: 'fee-receipt', params: { id: p.id } }"
                        class="flex justify-between border-b border-slate-100 py-1.5 text-sm"
                    >
                        <span>
                            {{ p.student }} <span class="text-xs text-slate-500">({{ p.class }})</span>
                            <span class="block text-xs text-slate-500">{{ p.receipt_number }} · {{ new Date(p.paid_at).toLocaleDateString() }} · {{ t(`fees.method_${p.method}`) }}</span>
                        </span>
                        <strong>{{ formatRupees(p.amount_paise) }}</strong>
                    </router-link>
                </div>
            </template>
        </div>

        <!-- Outstanding & defaulters -->
        <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-semibold text-slate-800">{{ t('fees.outstandingTitle') }}</h2>
                <select v-model="yearId" class="rounded-lg border border-slate-300 px-2 py-1 text-sm">
                    <option v-for="y in years" :key="y.id" :value="y.id">{{ y.name }}</option>
                </select>
            </div>

            <template v-if="outstanding">
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl bg-slate-50 p-2">
                        <p class="font-bold">{{ formatRupees(outstanding.totals.net_paise) }}</p>
                        <p class="text-xs text-slate-500">{{ t('fees.billed') }}</p>
                    </div>
                    <div class="rounded-xl bg-green-50 p-2">
                        <p class="font-bold text-green-800">{{ formatRupees(outstanding.totals.paid_paise) }}</p>
                        <p class="text-xs text-slate-500">{{ t('fees.collected') }}</p>
                    </div>
                    <div class="rounded-xl bg-red-50 p-2">
                        <p class="font-bold text-red-700">{{ formatRupees(outstanding.totals.overdue_paise) }}</p>
                        <p class="text-xs text-slate-500">{{ t('fees.overdue') }}</p>
                    </div>
                </div>

                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-1">{{ t('fees.classShort') }}</th>
                            <th class="py-1 text-right">{{ t('fees.billed') }}</th>
                            <th class="py-1 text-right">{{ t('fees.balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in outstanding.classes" :key="row.class" class="border-t border-slate-100">
                            <td class="py-1">{{ row.class ?? '—' }}</td>
                            <td class="py-1 text-right">{{ formatRupees(row.net_paise) }}</td>
                            <td class="py-1 text-right" :class="row.overdue_paise ? 'font-semibold text-red-700' : ''">{{ formatRupees(row.balance_paise) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">{{ t('fees.defaultersTitle', { count: outstanding.defaulters.length }) }}</h3>
                    <a v-if="outstanding.defaulters.length" :href="defaultersCsv" class="text-sm font-semibold text-blue-800">{{ t('ops.downloadCsv') }}</a>
                </div>
                <p v-if="!outstanding.defaulters.length" class="text-sm text-slate-500">{{ t('fees.noDefaulters') }}</p>
                <router-link
                    v-for="d in outstanding.defaulters"
                    :key="d.invoice_id"
                    :to="{ name: 'fee-invoice', params: { id: d.invoice_id } }"
                    class="flex justify-between rounded-lg border border-red-100 p-2 text-sm"
                >
                    <span>
                        {{ d.student }} <span class="text-xs text-slate-500">({{ d.class }})</span>
                        <span class="block text-xs text-slate-500">{{ d.label }} · {{ t('fees.daysOverdue', { days: d.days_overdue }) }}<template v-if="d.phone"> · {{ d.phone }}</template></span>
                    </span>
                    <strong class="text-red-700">{{ formatRupees(d.balance_paise) }}</strong>
                </router-link>
            </template>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';
import { formatRupees } from '@/utils/money';

const { t } = useI18n();
const { activeSchoolId } = useSchoolContext();

const todayIso = new Date().toISOString().slice(0, 10);
const from = ref(todayIso);
const to = ref(todayIso);
const years = ref([]);
const yearId = ref(null);
const collections = ref(null);
const outstanding = ref(null);
const error = ref('');

const collectionsCsv = computed(() => `/api/fees/reports/collections?${new URLSearchParams({
    school_id: activeSchoolId.value ?? '', from: from.value, to: to.value, format: 'csv',
})}`);
const defaultersCsv = computed(() => `/api/fees/reports/outstanding?${new URLSearchParams({
    school_id: activeSchoolId.value ?? '', academic_year_id: yearId.value ?? '', format: 'csv',
})}`);

async function loadCollections() {
    error.value = '';
    try {
        const { data } = await axios.get('/api/fees/reports/collections', {
            params: { school_id: activeSchoolId.value, from: from.value, to: to.value },
        });
        collections.value = data;
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

async function loadOutstanding() {
    if (!yearId.value) return;
    try {
        const { data } = await axios.get('/api/fees/reports/outstanding', {
            params: { school_id: activeSchoolId.value, academic_year_id: yearId.value },
        });
        outstanding.value = data;
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

async function init() {
    if (!activeSchoolId.value) return;
    try {
        const { data } = await axios.get('/api/fees/setup', { params: { school_id: activeSchoolId.value } });
        years.value = data.academic_years;
        yearId.value = data.academic_year_id;
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
    await Promise.all([loadCollections(), loadOutstanding()]);
}

watch(yearId, (next, prev) => {
    if (prev !== null && next !== prev) loadOutstanding();
});
watch(activeSchoolId, init);
onMounted(init);
</script>
