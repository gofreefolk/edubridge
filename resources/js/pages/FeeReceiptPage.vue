<template>
    <section class="mx-auto max-w-md space-y-4 p-4 print:p-0">
        <div class="flex gap-2 print:hidden">
            <button type="button" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" @click="router.back()">‹ {{ t('fees.back') }}</button>
            <button type="button" class="flex-1 rounded-xl bg-blue-700 py-2 font-semibold text-white" @click="print">🖨️ {{ t('fees.print') }}</button>
        </div>

        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>
        <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

        <article v-if="r" class="relative rounded-2xl border border-slate-300 bg-white p-5 text-slate-900 print:rounded-none print:border-0">
            <p v-if="r.voided" class="absolute right-4 top-4 rotate-12 rounded border-2 border-red-600 px-2 text-lg font-bold text-red-600">
                {{ t('fees.voided') }}
            </p>
            <header class="border-b border-slate-200 pb-3 text-center">
                <p class="text-lg font-bold">{{ r.school.name }}</p>
                <p v-if="r.school.district" class="text-xs text-slate-500">{{ r.school.district }}</p>
                <p class="mt-2 text-sm font-semibold uppercase tracking-wide">{{ t('fees.receipt') }}</p>
            </header>

            <dl class="mt-3 grid grid-cols-2 gap-y-1 text-sm">
                <dt class="text-slate-500">{{ t('fees.receiptNo') }}</dt>
                <dd class="text-right font-semibold">{{ r.receipt_number }}</dd>
                <dt class="text-slate-500">{{ t('fees.date') }}</dt>
                <dd class="text-right">{{ new Date(r.paid_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) }}</dd>
                <dt class="text-slate-500">{{ t('fees.student') }}</dt>
                <dd class="text-right">{{ r.student.name }}</dd>
                <template v-if="r.student.admission_number">
                    <dt class="text-slate-500">{{ t('fees.admissionNo') }}</dt>
                    <dd class="text-right">{{ r.student.admission_number }}</dd>
                </template>
                <dt class="text-slate-500">{{ t('fees.classShort') }}</dt>
                <dd class="text-right">{{ r.student.class }}</dd>
                <dt class="text-slate-500">{{ t('fees.invoice') }}</dt>
                <dd class="text-right">{{ r.invoice.number }} ({{ r.invoice.label }}, {{ r.invoice.academic_year }})</dd>
                <dt class="text-slate-500">{{ t('fees.method') }}</dt>
                <dd class="text-right">{{ t(`fees.method_${r.method}`) }}<template v-if="r.reference"> · {{ r.reference }}</template></dd>
            </dl>

            <div class="mt-4 rounded-xl bg-slate-50 p-3 text-center print:border print:border-slate-300">
                <p class="text-xs text-slate-500">{{ t('fees.amountReceived') }}</p>
                <p class="text-3xl font-bold">{{ formatRupees(r.amount_paise) }}</p>
            </div>

            <dl class="mt-3 grid grid-cols-2 gap-y-1 text-sm">
                <dt class="text-slate-500">{{ t('fees.invoiceTotal') }}</dt>
                <dd class="text-right">{{ formatRupees(r.invoice.net_paise) }}</dd>
                <dt class="text-slate-500">{{ t('fees.paidToDate') }}</dt>
                <dd class="text-right">{{ formatRupees(r.invoice.paid_paise) }}</dd>
                <dt class="font-semibold">{{ t('fees.balance') }}</dt>
                <dd class="text-right font-semibold">{{ formatRupees(r.invoice.balance_paise) }}</dd>
            </dl>

            <footer class="mt-6 flex items-end justify-between text-xs text-slate-500">
                <span v-if="r.received_by">{{ t('fees.receivedBy') }}: {{ r.received_by }}</span>
                <span class="border-t border-slate-400 px-4 pt-1">{{ t('fees.signature') }}</span>
            </footer>
            <p v-if="r.voided" class="mt-3 text-xs text-red-700">{{ t('fees.voidReason') }}: {{ r.void_reason }}</p>
        </article>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { formatRupees } from '@/utils/money';

const props = defineProps({ id: { type: [String, Number], required: true } });

const { t } = useI18n();
const router = useRouter();
const r = ref(null);
const loading = ref(false);
const error = ref('');

const print = () => window.print();

onMounted(async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(`/api/fees/payments/${props.id}/receipt`);
        r.value = data.receipt;
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    } finally {
        loading.value = false;
    }
});
</script>
