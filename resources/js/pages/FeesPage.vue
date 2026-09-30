<template>
    <section class="space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('fees.title') }}</h1>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <router-link :to="{ name: 'fee-setup' }" class="rounded-2xl border border-slate-200 bg-white p-3 text-center font-semibold text-blue-800">
                ⚙️ {{ t('fees.setupTitle') }}
            </router-link>
            <router-link :to="{ name: 'fee-reports' }" class="rounded-2xl border border-slate-200 bg-white p-3 text-center font-semibold text-blue-800">
                📊 {{ t('fees.reportsTitle') }}
            </router-link>
        </div>

        <details class="rounded-2xl border border-slate-200 bg-white p-4" :open="generateOpen">
            <summary class="cursor-pointer font-semibold text-slate-800">{{ t('fees.generateTitle') }}</summary>
            <div class="mt-3 space-y-3">
                <p class="text-sm text-slate-600">{{ t('fees.generateHint') }}</p>
                <p v-if="!setup?.labels?.length" class="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ t('fees.noStructuresYet') }}</p>
                <template v-else>
                    <select v-model="generate.label" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        <option value="">{{ t('fees.choosePeriod') }}</option>
                        <option v-for="label in setup.labels" :key="label" :value="label">{{ label }}</option>
                    </select>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="cls in yearClasses" :key="cls.id" class="flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-sm">
                            <input v-model="generate.classIds" type="checkbox" :value="cls.id" />
                            {{ cls.name }}
                        </label>
                    </div>
                    <p class="text-xs text-slate-500">{{ t('fees.classesHint') }}</p>
                    <label class="block text-xs text-slate-600">
                        {{ t('fees.issuedOn') }}
                        <input v-model="generate.issuedOn" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" />
                    </label>
                    <button type="button" class="w-full rounded-xl bg-blue-700 py-2 font-semibold text-white disabled:opacity-50" :disabled="!generate.label || busy" @click="runGenerate">
                        {{ t('fees.generateButton') }}
                    </button>
                </template>
                <p v-if="generateMessage" class="rounded-xl bg-green-50 px-3 py-2 text-sm text-green-800">{{ generateMessage }}</p>
            </div>
        </details>

        <div class="grid grid-cols-2 gap-2 rounded-2xl border border-slate-200 bg-white p-3 sm:grid-cols-4">
            <input v-model="filters.q" type="search" :placeholder="t('fees.searchPlaceholder')" class="col-span-2 rounded-xl border border-slate-300 px-3 py-2" />
            <select v-model="filters.status" class="rounded-xl border border-slate-300 px-3 py-2">
                <option value="">{{ t('fees.allStatuses') }}</option>
                <option value="unpaid">{{ t('fees.status_unpaid') }}</option>
                <option value="overdue">{{ t('fees.status_overdue') }}</option>
                <option value="paid">{{ t('fees.status_paid') }}</option>
                <option value="void">{{ t('fees.status_void') }}</option>
            </select>
            <select v-model="filters.classId" class="rounded-xl border border-slate-300 px-3 py-2">
                <option :value="null">{{ t('fees.allClasses') }}</option>
                <option v-for="cls in yearClasses" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
            </select>
        </div>

        <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>
        <p v-else-if="!invoices.length" class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-600">{{ t('fees.noInvoices') }}</p>

        <div v-else class="space-y-2">
            <router-link
                v-for="inv in invoices"
                :key="inv.id"
                :to="{ name: 'fee-invoice', params: { id: inv.id } }"
                class="block rounded-2xl border bg-white p-3"
                :class="inv.overdue ? 'border-red-200' : 'border-slate-200'"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-slate-900">{{ inv.student?.name }}</p>
                        <p class="text-xs text-slate-500">{{ inv.number }} · {{ inv.label }} · {{ t('fees.classShort') }} {{ inv.student?.class }}</p>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusClass(inv)">{{ statusLabel(inv) }}</span>
                </div>
                <div class="mt-2 flex justify-between text-sm">
                    <span class="text-slate-600">{{ t('fees.net') }} {{ formatRupees(inv.net_paise) }}</span>
                    <span v-if="inv.balance_paise" class="font-semibold text-red-700">{{ t('fees.balance') }} {{ formatRupees(inv.balance_paise) }}</span>
                </div>
            </router-link>

            <div v-if="meta.last_page > 1" class="flex items-center justify-between pt-2 text-sm">
                <button type="button" class="rounded-lg border px-3 py-1 disabled:opacity-40" :disabled="meta.current_page <= 1" @click="page--">‹</button>
                <span class="text-slate-600">{{ meta.current_page }} / {{ meta.last_page }} ({{ meta.total }})</span>
                <button type="button" class="rounded-lg border px-3 py-1 disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" @click="page++">›</button>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';
import { formatRupees } from '@/utils/money';
import { feeStatusClass, feeStatusKey } from '@/utils/feeStatus';

const { t } = useI18n();
const { activeSchoolId } = useSchoolContext();

const setup = ref(null);
const invoices = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const page = ref(1);
const loading = ref(false);
const busy = ref(false);
const error = ref('');
const generateMessage = ref('');
const filters = reactive({ q: '', status: 'unpaid', classId: null });
const generate = reactive({ label: '', classIds: [], issuedOn: new Date().toISOString().slice(0, 10) });

const generateOpen = computed(() => setup.value?.labels?.length > 0 && meta.value.total === 0 && !filters.q);
const yearClasses = computed(() => (setup.value?.classes ?? []).filter(
    (c) => !c.academic_year_id || c.academic_year_id === setup.value?.academic_year_id,
));

const statusLabel = (inv) => t(`fees.status_${feeStatusKey(inv)}`);
const statusClass = feeStatusClass;

async function loadSetup() {
    const { data } = await axios.get('/api/fees/setup', { params: { school_id: activeSchoolId.value } });
    setup.value = data;
}

let searchTimer;
async function loadInvoices() {
    if (!activeSchoolId.value) return;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/api/fees/invoices', {
            params: {
                school_id: activeSchoolId.value,
                academic_year_id: setup.value?.academic_year_id || undefined,
                status: filters.status || undefined,
                school_class_id: filters.classId || undefined,
                q: filters.q || undefined,
                page: page.value,
            },
        });
        invoices.value = data.invoices;
        meta.value = data.meta;
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    } finally {
        loading.value = false;
    }
}

async function runGenerate() {
    busy.value = true;
    error.value = '';
    generateMessage.value = '';
    try {
        const { data } = await axios.post('/api/fees/invoices/generate', {
            school_id: activeSchoolId.value,
            academic_year_id: setup.value.academic_year_id,
            label: generate.label,
            class_ids: generate.classIds.length ? generate.classIds : undefined,
            issued_on: generate.issuedOn,
        });
        generateMessage.value = t('fees.generated', { created: data.created, skipped: data.skipped });
        await loadInvoices();
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    } finally {
        busy.value = false;
    }
}

watch(() => [filters.status, filters.classId], () => {
    page.value = 1;
    loadInvoices();
});
watch(() => filters.q, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        page.value = 1;
        loadInvoices();
    }, 300);
});
watch(page, loadInvoices);
watch(activeSchoolId, async () => {
    await loadSetup();
    await loadInvoices();
});

onMounted(async () => {
    if (!activeSchoolId.value) return;
    try {
        await loadSetup();
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
    await loadInvoices();
});
</script>
