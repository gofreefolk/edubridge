<template>
    <section class="space-y-4">
        <div v-if="loading && !invoice" class="text-center text-slate-600">{{ t('common.loading') }}</div>
        <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

        <template v-if="invoice">
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs text-slate-500">{{ invoice.number }} · {{ invoice.academic_year }}</p>
                        <h1 class="text-xl font-bold text-slate-900">{{ invoice.label }}</h1>
                        <router-link
                            v-if="invoice.student"
                            :to="{ name: 'student-fees', params: { id: invoice.student.id } }"
                            class="text-blue-800"
                        >
                            {{ invoice.student.name }} · {{ t('fees.classShort') }} {{ invoice.student.class }}
                        </router-link>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="feeStatusClass(invoice)">{{ t(`fees.status_${feeStatusKey(invoice)}`) }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-600">{{ t('fees.issuedOn') }} {{ invoice.issued_on }} · {{ t('fees.dueOn') }} {{ invoice.due_on }}</p>
                <p v-if="invoice.void_reason" class="mt-2 text-sm text-slate-600">{{ t('fees.voidReason') }}: {{ invoice.void_reason }}</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="line in invoice.lines" :key="line.id" class="border-b border-slate-100">
                            <td class="py-1.5">{{ line.description }}</td>
                            <td class="py-1.5 text-right">
                                {{ formatRupees(line.amount_paise) }}
                                <span v-if="line.discount_paise" class="block text-xs text-green-700">− {{ formatRupees(line.discount_paise) }}</span>
                            </td>
                        </tr>
                        <tr class="font-semibold">
                            <td class="pt-2">{{ t('fees.net') }}</td>
                            <td class="pt-2 text-right">{{ formatRupees(invoice.net_paise) }}</td>
                        </tr>
                        <tr>
                            <td>{{ t('fees.paid') }}</td>
                            <td class="text-right">{{ formatRupees(invoice.paid_paise) }}</td>
                        </tr>
                        <tr class="text-lg font-bold" :class="invoice.balance_paise ? 'text-red-700' : 'text-green-700'">
                            <td>{{ t('fees.balance') }}</td>
                            <td class="text-right">{{ formatRupees(invoice.balance_paise) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form v-if="isAdmin && invoice.balance_paise > 0" class="space-y-2 rounded-2xl border border-blue-200 bg-blue-50 p-4" @submit.prevent="recordPayment">
                <h2 class="font-semibold text-slate-800">{{ t('fees.recordPayment') }}</h2>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-xs text-slate-600">
                        {{ t('fees.amount') }} (₹)
                        <input v-model="payment.amount" inputmode="decimal" required class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-base" />
                    </label>
                    <label class="text-xs text-slate-600">
                        {{ t('fees.method') }}
                        <select v-model="payment.method" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-base">
                            <option v-for="m in methods" :key="m" :value="m">{{ t(`fees.method_${m}`) }}</option>
                        </select>
                    </label>
                </div>
                <input v-model="payment.reference" :placeholder="t('fees.referencePlaceholder')" class="w-full rounded-lg border border-slate-300 px-2 py-1.5" />
                <label class="block text-xs text-slate-600">
                    {{ t('fees.paidOn') }}
                    <input v-model="payment.paidOn" type="date" :max="todayIso" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" />
                </label>
                <button type="submit" class="w-full rounded-xl bg-blue-700 py-2 font-semibold text-white disabled:opacity-50" :disabled="busy">
                    {{ t('fees.saveAndReceipt') }}
                </button>
            </form>

            <div v-if="invoice.payments.length" class="space-y-2">
                <h2 class="font-semibold text-slate-800">{{ t('fees.payments') }}</h2>
                <div v-for="p in invoice.payments" :key="p.id" class="rounded-xl border border-slate-200 bg-white p-3 text-sm" :class="p.voided ? 'opacity-60' : ''">
                    <div class="flex justify-between">
                        <router-link :to="{ name: 'fee-receipt', params: { id: p.id } }" class="font-semibold text-blue-800" :class="p.voided ? 'line-through' : ''">
                            {{ p.receipt_number }}
                        </router-link>
                        <span class="font-semibold">{{ formatRupees(p.amount_paise) }}</span>
                    </div>
                    <p class="text-xs text-slate-500">
                        {{ new Date(p.paid_at).toLocaleDateString() }} · {{ t(`fees.method_${p.method}`) }}<template v-if="p.reference"> · {{ p.reference }}</template>
                        <template v-if="p.received_by"> · {{ p.received_by }}</template>
                    </p>
                    <p v-if="p.voided" class="text-xs text-red-700">{{ t('fees.voided') }}: {{ p.void_reason }}</p>
                    <button v-else-if="isAdmin" type="button" class="mt-1 text-xs text-red-700" @click="voidPayment(p)">{{ t('fees.voidPayment') }}</button>
                </div>
            </div>

            <button
                v-if="isAdmin && invoice.status !== 'void' && !invoice.payments.some((p) => !p.voided)"
                type="button"
                class="w-full rounded-xl border border-red-300 py-2 text-sm font-semibold text-red-700"
                @click="voidInvoice"
            >
                {{ t('fees.voidInvoice') }}
            </button>
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useRole } from '@/composables/useRole';
import { formatRupees, paiseToInput, toPaise } from '@/utils/money';
import { feeStatusClass, feeStatusKey } from '@/utils/feeStatus';

const props = defineProps({ id: { type: [String, Number], required: true } });

const { t } = useI18n();
const router = useRouter();
const { activeRole } = useRole();

const methods = ['cash', 'upi', 'bank', 'cheque'];
const todayIso = new Date().toISOString().slice(0, 10);
const invoice = ref(null);
const loading = ref(false);
const busy = ref(false);
const error = ref('');
const payment = reactive({ amount: '', method: 'cash', reference: '', paidOn: todayIso });

const isAdmin = computed(() => ['school_admin', 'super_admin'].includes(activeRole.value));

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(`/api/fees/invoices/${props.id}`);
        invoice.value = data.invoice;
        payment.amount = data.invoice.balance_paise ? paiseToInput(data.invoice.balance_paise) : '';
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    } finally {
        loading.value = false;
    }
}

async function recordPayment() {
    const amountPaise = toPaise(payment.amount);
    if (!amountPaise) {
        error.value = t('fees.invalidAmount');
        return;
    }
    busy.value = true;
    error.value = '';
    try {
        // Today's payments keep the current time; back-dated ones are recorded at noon.
        const paidAt = payment.paidOn === todayIso ? undefined : `${payment.paidOn} 12:00:00`;
        const { data } = await axios.post(`/api/fees/invoices/${props.id}/payments`, {
            amount_paise: amountPaise,
            method: payment.method,
            reference: payment.reference || undefined,
            paid_at: paidAt,
        });
        router.push({ name: 'fee-receipt', params: { id: data.payment_id } });
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    } finally {
        busy.value = false;
    }
}

async function voidPayment(p) {
    const reason = window.prompt(t('fees.voidPrompt', { number: p.receipt_number }));
    if (!reason) return;
    try {
        const { data } = await axios.post(`/api/fees/payments/${p.id}/void`, { reason });
        invoice.value = data.invoice;
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

async function voidInvoice() {
    const reason = window.prompt(t('fees.voidPrompt', { number: invoice.value.number }));
    if (!reason) return;
    try {
        const { data } = await axios.post(`/api/fees/invoices/${props.id}/void`, { reason });
        invoice.value = data.invoice;
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

watch(() => props.id, load);
onMounted(load);
</script>
