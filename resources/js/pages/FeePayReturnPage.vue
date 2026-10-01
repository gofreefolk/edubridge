<template>
    <section class="mx-auto max-w-md space-y-4 p-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center">
            <template v-if="state === 'checking'">
                <p class="text-4xl">⏳</p>
                <p class="mt-2 font-semibold text-slate-800">{{ t('fees.checkingPayment') }}</p>
            </template>

            <template v-else-if="state === 'paid'">
                <p class="text-4xl">✅</p>
                <h1 class="mt-2 text-xl font-bold text-green-800">{{ t('fees.paymentReceived') }}</h1>
                <p v-if="result.receipt_number" class="mt-1 text-sm text-slate-600">{{ t('fees.receiptNo') }} {{ result.receipt_number }}</p>
            </template>

            <template v-else-if="state === 'not-paid'">
                <p class="text-4xl">↩️</p>
                <h1 class="mt-2 text-xl font-bold text-slate-900">{{ t('fees.paymentNotCompleted') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ t('fees.paymentNotCompletedHint') }}</p>
            </template>

            <template v-else>
                <p class="text-4xl">⚠️</p>
                <p class="mt-2 text-sm text-slate-700">{{ error }}</p>
            </template>
        </div>

        <router-link
            v-if="state === 'paid' && result.payment_id && isAuthenticated"
            :to="{ name: 'fee-receipt', params: { id: result.payment_id } }"
            class="block rounded-xl bg-blue-700 py-2.5 text-center font-semibold text-white"
        >
            {{ t('fees.viewReceipt') }}
        </router-link>
        <router-link
            v-if="result?.student_id && isAuthenticated"
            :to="{ name: 'student-fees', params: { id: result.student_id } }"
            class="block rounded-xl border border-slate-300 bg-white py-2.5 text-center font-semibold text-slate-800"
        >
            {{ t('fees.backToFees') }}
        </router-link>
        <router-link v-if="!isAuthenticated" :to="{ name: 'login' }" class="block text-center text-sm text-blue-800">
            {{ t('fees.loginToSeeFees') }}
        </router-link>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';

const { t } = useI18n();
const route = useRoute();
const { isAuthenticated } = useAuth();

const state = ref('checking');
const result = ref(null);
const error = ref('');

// Razorpay redirects here with signed query params; the server verifies the signature.
onMounted(async () => {
    const q = route.query;
    if (!q.razorpay_payment_link_id || !q.razorpay_signature) {
        state.value = 'error';
        error.value = t('common.error');
        return;
    }
    try {
        const { data } = await axios.post('/api/fees/online/confirm', {
            razorpay_payment_id: q.razorpay_payment_id || null,
            razorpay_payment_link_id: q.razorpay_payment_link_id,
            razorpay_payment_link_reference_id: q.razorpay_payment_link_reference_id || null,
            razorpay_payment_link_status: q.razorpay_payment_link_status,
            razorpay_signature: q.razorpay_signature,
        });
        result.value = data;
        state.value = data.paid ? 'paid' : 'not-paid';
    } catch (e) {
        state.value = 'error';
        error.value = e.response?.data?.message ?? t('common.error');
    }
});
</script>
