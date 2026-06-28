<template>
    <div class="flex min-h-dvh">
        <section class="relative hidden w-1/2 overflow-hidden bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-900 lg:flex lg:flex-col lg:justify-between">
            <div class="absolute inset-0 opacity-20">
                <div class="absolute -left-20 top-20 h-72 w-72 rounded-full bg-white/20 blur-3xl" />
                <div class="absolute bottom-10 right-10 h-96 w-96 rounded-full bg-cyan-300/20 blur-3xl" />
            </div>

            <div class="relative px-12 pt-12">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-blue-200">EduBridge</p>
                <h1 class="mt-4 max-w-md text-4xl font-bold leading-tight text-white">
                    {{ t('platform.loginHeroTitle') }}
                </h1>
                <p class="mt-4 max-w-md text-lg leading-relaxed text-blue-100">
                    {{ t('platform.loginHeroSubtitle') }}
                </p>
            </div>

            <div class="relative px-12 pb-12">
                <div class="rounded-2xl border border-white/10 bg-white/10 p-6 backdrop-blur">
                    <p class="text-sm font-medium text-blue-100">{{ t('platform.loginHintTitle') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-blue-50">
                        <li>• {{ t('platform.loginHintSchools') }}</li>
                        <li>• {{ t('platform.loginHintUsers') }}</li>
                        <li>• {{ t('platform.loginHintMonitor') }}</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="relative flex w-full flex-col justify-center bg-white px-6 py-10 sm:px-10 lg:w-1/2 lg:px-16">
            <div class="mb-6 flex justify-end lg:absolute lg:right-8 lg:top-8">
                <LanguageSwitcher />
            </div>

            <div class="mx-auto w-full max-w-md">
                <div class="mb-8 lg:hidden">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-700">{{ t('platform.brandTitle') }}</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ t('platform.loginTitle') }}</h1>
                </div>

                <div class="hidden lg:block">
                    <h2 class="text-3xl font-bold text-slate-900">{{ t('platform.loginTitle') }}</h2>
                    <p class="mt-2 text-slate-600">{{ t('platform.loginSubtitle') }}</p>
                </div>

                <form class="mt-8 space-y-5" @submit.prevent="handleSubmit">
                    <div v-if="step === 'phone'" class="space-y-5">
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">{{ t('auth.phoneLabel') }}</span>
                            <input
                                v-model="phone"
                                type="tel"
                                inputmode="tel"
                                autocomplete="tel"
                                class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3.5 text-lg text-slate-900 outline-none ring-blue-500 transition focus:border-blue-500 focus:ring-2"
                                :placeholder="t('platform.phonePlaceholder')"
                                :disabled="loading"
                                required
                            />
                        </label>

                        <p v-if="error" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ error }}
                        </p>

                        <button
                            type="submit"
                            class="w-full rounded-xl bg-slate-900 px-4 py-3.5 text-base font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60"
                            :disabled="loading"
                        >
                            {{ loading ? t('common.loading') : t('auth.sendCode') }}
                        </button>
                    </div>

                    <div v-else class="space-y-5">
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
                                class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3.5 text-center text-2xl tracking-[0.4em] text-slate-900 outline-none ring-blue-500 transition focus:border-blue-500 focus:ring-2"
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
                            class="w-full rounded-xl bg-slate-900 px-4 py-3.5 text-base font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60"
                            :disabled="loading"
                        >
                            {{ loading ? t('common.loading') : t('platform.enterConsole') }}
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

                <p class="mt-8 text-center text-sm text-slate-500">
                    {{ t('platform.parentLoginHint') }}
                    <router-link :to="{ name: 'login' }" class="font-medium text-blue-700 hover:underline">
                        {{ t('auth.loginShort') }}
                    </router-link>
                </p>
            </div>
        </section>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useAuth } from '@/composables/useAuth';
import { setLocale } from '@/i18n';

const { t } = useI18n();
const router = useRouter();
const { requestOtp, verifyOtp, logout, loading } = useAuth();

const step = ref('phone');
const phone = ref('');
const code = ref('');
const error = ref('');

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
            return;
        }

        const user = await verifyOtp(phone.value, code.value);

        if (!user.roles?.includes('super_admin')) {
            await logout();
            error.value = t('platform.notAuthorized');
            step.value = 'phone';
            code.value = '';
            return;
        }

        router.replace({ name: 'platform-dashboard' });
    } catch (err) {
        error.value = extractErrorMessage(err);
    }
}

function goBackToPhone() {
    step.value = 'phone';
    code.value = '';
    error.value = '';
}

onMounted(() => {
    if (!localStorage.getItem('edubridge.locale')) {
        setLocale('en');
    }
});

onUnmounted(() => {
    error.value = '';
});
</script>
