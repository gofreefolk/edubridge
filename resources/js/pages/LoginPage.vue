<template>
    <section class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('auth.loginTitle') }}</h1>
            <p class="mt-2 text-base leading-relaxed text-slate-600">
                {{ t('auth.loginSubtitle') }}
            </p>
        </div>

        <form class="space-y-4" @submit.prevent="handleSubmit">
            <div v-if="step === 'phone'" class="space-y-4">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">{{ t('auth.phoneLabel') }}</span>
                    <input
                        v-model="phone"
                        type="tel"
                        inputmode="tel"
                        autocomplete="tel"
                        class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-lg text-slate-900 outline-none ring-blue-500 focus:border-blue-500 focus:ring-2"
                        :placeholder="t('auth.phonePlaceholder')"
                        :disabled="loading"
                        required
                    />
                </label>

                <p v-if="error" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ error }}
                </p>

                <button
                    type="submit"
                    class="w-full rounded-xl bg-blue-700 px-4 py-3.5 text-base font-semibold text-white transition hover:bg-blue-800 disabled:opacity-60"
                    :disabled="loading"
                >
                    {{ loading ? t('common.loading') : t('auth.sendCode') }}
                </button>
            </div>

            <div v-else class="space-y-4">
                <p class="text-base text-slate-700">
                    {{ t('auth.codeSentTo') }}
                    <span class="font-semibold text-slate-900">{{ phone }}</span>
                </p>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">{{ t('auth.codeLabel') }}</span>
                    <input
                        v-model="code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-center text-2xl tracking-[0.4em] text-slate-900 outline-none ring-blue-500 focus:border-blue-500 focus:ring-2"
                        :placeholder="t('auth.codePlaceholder')"
                        :disabled="loading"
                        required
                    />
                </label>

                <p v-if="error" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ error }}
                </p>

                <button
                    type="submit"
                    class="w-full rounded-xl bg-blue-700 px-4 py-3.5 text-base font-semibold text-white transition hover:bg-blue-800 disabled:opacity-60"
                    :disabled="loading"
                >
                    {{ loading ? t('common.loading') : t('auth.verifyCode') }}
                </button>

                <button
                    type="button"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-base font-medium text-slate-700 transition hover:bg-slate-50"
                    :disabled="loading || resendCooldown > 0"
                    @click="resendCode"
                >
                    {{
                        resendCooldown > 0
                            ? t('auth.resendIn', { seconds: resendCooldown })
                            : t('auth.resendCode')
                    }}
                </button>

                <button
                    type="button"
                    class="w-full text-sm font-medium text-blue-700"
                    @click="goBackToPhone"
                >
                    {{ t('auth.changePhone') }}
                </button>
            </div>
        </form>
    </section>
</template>

<script setup>
import { onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const { requestOtp, verifyOtp, loading } = useAuth();

const step = ref('phone');
const phone = ref('');
const code = ref('');
const error = ref('');
const resendCooldown = ref(0);

let cooldownTimer = null;

function startCooldown(seconds = 60) {
    resendCooldown.value = seconds;
    clearInterval(cooldownTimer);
    cooldownTimer = setInterval(() => {
        resendCooldown.value -= 1;
        if (resendCooldown.value <= 0) {
            clearInterval(cooldownTimer);
        }
    }, 1000);
}

function extractErrorMessage(err) {
    const errors = err?.response?.data?.errors;
    if (errors) {
        const firstKey = Object.keys(errors)[0];
        return errors[firstKey]?.[0] ?? t('auth.genericError');
    }

    return err?.response?.data?.message ?? t('auth.genericError');
}

async function handleSubmit() {
    error.value = '';

    try {
        if (step.value === 'phone') {
            await requestOtp(phone.value);
            step.value = 'code';
            startCooldown();
            return;
        }

        await verifyOtp(phone.value, code.value);

        const redirect = route.query.redirect;
        if (typeof redirect === 'string' && redirect.startsWith('/')) {
            router.replace(redirect);
            return;
        }

        router.replace({ name: 'home' });
    } catch (err) {
        error.value = extractErrorMessage(err);
    }
}

async function resendCode() {
    error.value = '';
    try {
        await requestOtp(phone.value);
        startCooldown();
    } catch (err) {
        error.value = extractErrorMessage(err);
    }
}

function goBackToPhone() {
    step.value = 'phone';
    code.value = '';
    error.value = '';
}

onUnmounted(() => {
    clearInterval(cooldownTimer);
});
</script>
