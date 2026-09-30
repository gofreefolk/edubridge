<template>
    <section class="space-y-4">
        <div v-if="loading && !ledger" class="text-center text-slate-600">{{ t('common.loading') }}</div>
        <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

        <template v-if="ledger">
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-sm text-slate-500">{{ t('fees.title') }}</p>
                <h1 class="text-xl font-bold text-slate-900">{{ ledger.student.name }}</h1>
                <div class="mt-3 grid grid-cols-2 gap-2 text-center">
                    <div class="rounded-xl p-2" :class="ledger.totals.balance_paise ? 'bg-red-50' : 'bg-green-50'">
                        <p class="text-xl font-bold" :class="ledger.totals.balance_paise ? 'text-red-700' : 'text-green-700'">{{ formatRupees(ledger.totals.balance_paise) }}</p>
                        <p class="text-xs text-slate-600">{{ t('fees.balance') }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-2">
                        <p class="text-xl font-bold text-slate-900">{{ formatRupees(ledger.totals.paid_paise) }}</p>
                        <p class="text-xs text-slate-600">{{ t('fees.paid') }}</p>
                    </div>
                </div>
                <p v-if="ledger.totals.overdue_paise" class="mt-2 text-sm font-semibold text-red-700">
                    {{ t('fees.overdueAmount', { amount: formatRupees(ledger.totals.overdue_paise) }) }}
                </p>
                <p v-if="!isAdmin && ledger.totals.balance_paise" class="mt-2 text-xs text-slate-500">{{ t('fees.payAtOffice') }}</p>
            </div>

            <p v-if="!ledger.invoices.length" class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-600">{{ t('fees.noInvoices') }}</p>

            <div v-for="inv in ledger.invoices" :key="inv.id" class="rounded-2xl border bg-white p-4" :class="inv.overdue ? 'border-red-200' : 'border-slate-200'">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <router-link v-if="isAdmin" :to="{ name: 'fee-invoice', params: { id: inv.id } }" class="font-semibold text-blue-800">{{ inv.label }}</router-link>
                        <p v-else class="font-semibold text-slate-900">{{ inv.label }}</p>
                        <p class="text-xs text-slate-500">{{ inv.number }} · {{ t('fees.dueOn') }} {{ inv.due_on }}</p>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="feeStatusClass(inv)">{{ t(`fees.status_${feeStatusKey(inv)}`) }}</span>
                </div>
                <ul class="mt-2 space-y-0.5 text-sm text-slate-700">
                    <li v-for="line in inv.lines" :key="line.id" class="flex justify-between">
                        <span>{{ line.description }}</span>
                        <span>
                            {{ formatRupees(line.amount_paise - line.discount_paise) }}
                            <span v-if="line.discount_paise" class="text-xs text-slate-400 line-through">{{ formatRupees(line.amount_paise) }}</span>
                        </span>
                    </li>
                </ul>
                <div class="mt-2 flex justify-between border-t border-slate-100 pt-2 text-sm font-semibold">
                    <span>{{ t('fees.balance') }}</span>
                    <span :class="inv.balance_paise ? 'text-red-700' : 'text-green-700'">{{ formatRupees(inv.balance_paise) }}</span>
                </div>
                <div v-if="inv.payments.length" class="mt-2 space-y-1">
                    <router-link
                        v-for="p in inv.payments.filter((p) => !p.voided)"
                        :key="p.id"
                        :to="{ name: 'fee-receipt', params: { id: p.id } }"
                        class="flex justify-between rounded-lg bg-slate-50 px-2 py-1 text-xs text-blue-800"
                    >
                        <span>🧾 {{ p.receipt_number }} · {{ new Date(p.paid_at).toLocaleDateString() }}</span>
                        <span>{{ formatRupees(p.amount_paise) }}</span>
                    </router-link>
                </div>
            </div>

            <div v-if="isAdmin" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                <h2 class="font-semibold text-slate-800">{{ t('fees.concessions') }}</h2>
                <p class="text-xs text-slate-500">{{ t('fees.concessionsHint') }}</p>
                <div v-for="c in concessions" :key="c.id" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                    <span>
                        <strong>{{ c.type === 'percent' ? `${c.value}%` : formatRupees(c.value) }}</strong>
                        {{ c.fee_head ?? t('fees.wholeInvoice') }} — {{ c.reason }}
                    </span>
                    <button type="button" class="text-xs text-red-700" @click="removeConcession(c)">{{ t('common.delete') }}</button>
                </div>
                <form class="grid grid-cols-2 gap-2" @submit.prevent="addConcession">
                    <select v-model="concession.feeHeadId" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                        <option :value="null">{{ t('fees.wholeInvoice') }}</option>
                        <option v-for="h in heads" :key="h.id" :value="h.id">{{ h.name }}</option>
                    </select>
                    <div class="flex gap-1">
                        <input v-model="concession.value" inputmode="decimal" required class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" />
                        <select v-model="concession.type" class="rounded-lg border border-slate-300 px-1 text-sm">
                            <option value="percent">%</option>
                            <option value="amount">₹</option>
                        </select>
                    </div>
                    <input v-model="concession.reason" required :placeholder="t('fees.reason')" class="col-span-2 rounded-lg border border-slate-300 px-2 py-1.5 text-sm" />
                    <button type="submit" class="col-span-2 rounded-lg border border-blue-300 bg-blue-50 py-2 text-sm font-semibold text-blue-800">{{ t('fees.addConcession') }}</button>
                </form>
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
import { formatRupees, toPaise } from '@/utils/money';
import { feeStatusClass, feeStatusKey } from '@/utils/feeStatus';

const props = defineProps({ id: { type: [String, Number], required: true } });

const { t } = useI18n();
const { activeRole } = useRole();
const { activeSchoolId } = useSchoolContext();

const ledger = ref(null);
const concessions = ref([]);
const heads = ref([]);
const loading = ref(false);
const error = ref('');
const concession = reactive({ feeHeadId: null, type: 'percent', value: '', reason: '' });

const isAdmin = computed(() => ['school_admin', 'super_admin'].includes(activeRole.value));

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(`/api/fees/students/${props.id}`);
        ledger.value = data;
        if (isAdmin.value) {
            const [c, s] = await Promise.all([
                axios.get(`/api/fees/students/${props.id}/concessions`),
                axios.get('/api/fees/setup', { params: { school_id: activeSchoolId.value } }),
            ]);
            concessions.value = c.data.concessions;
            heads.value = s.data.heads.filter((h) => h.is_active);
        }
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    } finally {
        loading.value = false;
    }
}

async function addConcession() {
    const value = concession.type === 'percent' ? Number(concession.value) : toPaise(concession.value);
    if (!value || (concession.type === 'percent' && (value > 100 || !Number.isInteger(value)))) {
        error.value = t('fees.invalidAmount');
        return;
    }
    error.value = '';
    try {
        const { data } = await axios.post(`/api/fees/students/${props.id}/concessions`, {
            fee_head_id: concession.feeHeadId,
            type: concession.type,
            value,
            reason: concession.reason,
        });
        concessions.value = data.concessions;
        Object.assign(concession, { feeHeadId: null, type: 'percent', value: '', reason: '' });
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

async function removeConcession(c) {
    if (!window.confirm(t('fees.confirmRemoveConcession'))) return;
    const { data } = await axios.delete(`/api/fees/students/${props.id}/concessions/${c.id}`);
    concessions.value = data.concessions;
}

watch(() => props.id, load);
onMounted(load);
</script>
