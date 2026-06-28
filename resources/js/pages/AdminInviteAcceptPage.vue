<template>
    <div class="min-h-dvh bg-gradient-to-br from-slate-900 via-blue-900 to-indigo-900">
        <div class="mx-auto flex min-h-dvh max-w-lg flex-col justify-center px-4 py-10">
            <div class="rounded-2xl bg-white p-6 shadow-xl sm:p-8">
                <div v-if="loadingInvite" class="py-8 text-center text-slate-500">{{ t('common.loading') }}</div>

                <div v-else-if="!invite || invite.status !== 'pending'" class="py-6 text-center">
                    <p class="text-4xl">⚠️</p>
                    <h1 class="mt-3 text-xl font-bold text-slate-900">{{ t('invite.invalidTitle') }}</h1>
                    <p class="mt-2 text-slate-600">{{ t('invite.invalidMessage') }}</p>
                </div>

                <div v-else-if="accepted" class="py-6 text-center">
                    <p class="text-4xl">🎉</p>
                    <h1 class="mt-3 text-xl font-bold text-slate-900">{{ t('invite.acceptedTitle') }}</h1>
                    <p class="mt-2 text-slate-600">{{ t('invite.acceptedMessage', { school: invite.school.name }) }}</p>
                    <router-link
                        :to="{ name: 'home' }"
                        class="mt-6 inline-block rounded-xl bg-blue-700 px-5 py-3 font-semibold text-white"
                    >
                        {{ t('invite.goToDashboard') }}
                    </router-link>
                </div>

                <template v-else>
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-700">EduBridge</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ t('invite.title') }}</h1>
                    <p class="mt-2 text-slate-600">
                        {{ t('invite.subtitle', { school: invite.school.name }) }}
                    </p>

                    <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                        <p><strong>{{ invite.name }}</strong></p>
                        <p>{{ invite.phone }}</p>
                        <p class="mt-1 text-slate-500">{{ invite.school.district }}</p>
                    </div>

                    <form class="mt-6 space-y-4" @submit.prevent="handleSubmit">
                        <div v-if="step === 'phone'">
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">{{ t('auth.phoneLabel') }}</span>
                                <input
                                    v-model="phone"
                                    type="tel"
                                    class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-lg"
                                    :placeholder="invite.phone"
                                    required
                                />
                            </label>
                            <p class="mt-2 text-xs text-slate-500">{{ t('invite.phoneHint') }}</p>
                        </div>

                        <div v-else>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">{{ t('auth.codeLabel') }}</span>
                                <input
                                    v-model="code"
                                    type="text"
                                    maxlength="6"
                                    inputmode="numeric"
                                    class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-center text-2xl tracking-[0.4em]"
                                    required
                                />
                            </label>
                        </div>

                        <p v-if="error" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</p>

                        <button
                            type="submit"
                            class="w-full rounded-xl bg-slate-900 py-3.5 font-semibold text-white disabled:opacity-60"
                            :disabled="loading"
                        >
                            {{ loading ? t('common.loading') : (step === 'phone' ? t('auth.sendCode') : t('invite.accept')) }}
                        </button>
                    </form>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';
import { setLocale } from '@/i18n';

const props = defineProps({
    token: { type: String, required: true },
});

const { t } = useI18n();
const { requestOtp, verifyOtp } = useAuth();

const loadingInvite = ref(true);
const invite = ref(null);
const step = ref('phone');
const phone = ref('');
const code = ref('');
const error = ref('');
const loading = ref(false);
const accepted = ref(false);

onMounted(async () => {
    if (!localStorage.getItem('edubridge.locale')) {
        setLocale('en');
    }

    try {
        const { data } = await axios.get(`/api/invites/admin/${props.token}`);
        invite.value = data.invite;
        phone.value = data.invite.phone;
    } catch {
        invite.value = null;
    } finally {
        loadingInvite.value = false;
    }
});

async function handleSubmit() {
    error.value = '';
    loading.value = true;

    try {
        if (step.value === 'phone') {
            await requestOtp(phone.value);
            step.value = 'code';
            return;
        }

        await verifyOtp(phone.value, code.value);
        await axios.post(`/api/invites/admin/${props.token}/accept`);
        accepted.value = true;
    } catch (err) {
        error.value = err?.response?.data?.message ?? t('auth.genericError');
    } finally {
        loading.value = false;
    }
}
</script>
