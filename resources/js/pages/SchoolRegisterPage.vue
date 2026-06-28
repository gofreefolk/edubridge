<template>
    <div class="min-h-dvh bg-slate-50">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-lg font-bold text-blue-800">EduBridge</p>
                    <p class="text-sm text-slate-500">{{ t('registration.title') }}</p>
                </div>
                <LanguageSwitcher />
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-4 py-8">
            <div v-if="submitted" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center">
                <p class="text-2xl">✅</p>
                <h1 class="mt-3 text-xl font-bold text-emerald-900">{{ t('registration.successTitle') }}</h1>
                <p class="mt-2 text-emerald-800">{{ t('registration.successMessage') }}</p>
            </div>

            <template v-else>
                <h1 class="text-2xl font-bold text-slate-900">{{ t('registration.heading') }}</h1>
                <p class="mt-2 text-slate-600">{{ t('registration.subtitle') }}</p>

                <form class="mt-8 space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="handleSubmit">
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">{{ t('platform.form.schoolName') }}</span>
                        <input v-model="form.name" type="text" required class="field-input" />
                    </label>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">{{ t('platform.form.schoolCode') }}</span>
                            <input v-model="form.code" type="text" required class="field-input uppercase" />
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">{{ t('platform.form.district') }}</span>
                            <input v-model="form.district" type="text" class="field-input" />
                        </label>
                    </div>
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">{{ t('platform.form.type') }}</span>
                        <select v-model="form.type" required class="field-input">
                            <option value="government">{{ t('platform.types.government') }}</option>
                            <option value="aided">{{ t('platform.types.aided') }}</option>
                            <option value="private">{{ t('platform.types.private') }}</option>
                        </select>
                    </label>

                    <div class="border-t border-slate-100 pt-5">
                        <h2 class="font-semibold text-slate-900">{{ t('platform.form.adminSection') }}</h2>
                        <div class="mt-4 grid gap-5 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">{{ t('platform.form.adminName') }}</span>
                                <input v-model="form.admin_name" type="text" required class="field-input" />
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">{{ t('platform.form.adminPhone') }}</span>
                                <input v-model="form.admin_phone" type="tel" required class="field-input" />
                            </label>
                        </div>
                    </div>

                    <p v-if="error" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</p>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-blue-700 px-4 py-3.5 font-semibold text-white hover:bg-blue-800 disabled:opacity-60"
                        :disabled="loading"
                    >
                        {{ loading ? t('common.loading') : t('registration.submit') }}
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-slate-500">
                    {{ t('registration.platformHint') }}
                    <router-link :to="{ name: 'platform-login' }" class="font-medium text-blue-700 hover:underline">
                        {{ t('platform.loginTitle') }}
                    </router-link>
                </p>
            </template>
        </main>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { setLocale } from '@/i18n';

const { t } = useI18n();

const loading = ref(false);
const submitted = ref(false);
const error = ref('');
const form = ref({
    name: '',
    code: '',
    district: '',
    type: 'government',
    admin_name: '',
    admin_phone: '',
});

onMounted(() => {
    if (!localStorage.getItem('edubridge.locale')) {
        setLocale('en');
    }
});

async function handleSubmit() {
    error.value = '';
    loading.value = true;
    try {
        await axios.post('/api/schools/register', form.value);
        submitted.value = true;
    } catch (err) {
        error.value = err?.response?.data?.message ?? t('auth.genericError');
    } finally {
        loading.value = false;
    }
}
</script>

<style scoped>
.field-input {
    @apply mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 outline-none ring-blue-500 focus:border-blue-500 focus:ring-2;
}
</style>
