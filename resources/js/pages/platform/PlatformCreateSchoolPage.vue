<template>
    <section class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 lg:text-3xl">{{ t('platform.createSchoolTitle') }}</h2>
                <p class="mt-2 text-slate-600">{{ t('platform.createSchoolSubtitle') }}</p>
            </div>
            <router-link
                :to="{ name: 'platform-schools' }"
                class="text-sm font-medium text-blue-700 hover:underline"
            >
                {{ t('platform.backToSchools') }}
            </router-link>
        </div>

        <form class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="handleSubmit">
            <div class="grid gap-5 md:grid-cols-2">
                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-slate-700">{{ t('platform.form.schoolName') }}</span>
                    <input v-model="form.name" type="text" required class="field-input" />
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">{{ t('platform.form.schoolCode') }}</span>
                    <input v-model="form.code" type="text" required class="field-input uppercase" />
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">{{ t('platform.form.district') }}</span>
                    <input v-model="form.district" type="text" class="field-input" />
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">{{ t('platform.form.type') }}</span>
                    <select v-model="form.type" required class="field-input">
                        <option value="government">{{ t('platform.types.government') }}</option>
                        <option value="aided">{{ t('platform.types.aided') }}</option>
                        <option value="private">{{ t('platform.types.private') }}</option>
                    </select>
                </label>
                <label class="flex items-center gap-2 md:col-span-2">
                    <input v-model="form.whatsapp_bridge_enabled" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                    <span class="text-sm text-slate-700">{{ t('platform.form.whatsappEnabled') }}</span>
                </label>
            </div>

            <div class="mt-8 border-t border-slate-100 pt-6">
                <h3 class="text-lg font-semibold text-slate-900">{{ t('platform.form.adminSection') }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ t('platform.form.adminSectionHint') }}</p>
                <div class="mt-4 grid gap-5 md:grid-cols-2">
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

            <p v-if="error" class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</p>

            <div v-if="inviteUrl" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-sm font-medium text-emerald-900">{{ t('platform.inviteCreated') }}</p>
                <p class="mt-2 break-all text-sm text-emerald-800">{{ inviteUrl }}</p>
                <button type="button" class="mt-3 text-sm font-medium text-emerald-700 hover:underline" @click="copyInvite">
                    {{ copied ? t('admin.linkCopied') : t('admin.copyLink') }}
                </button>
            </div>

            <button
                type="submit"
                class="mt-6 w-full rounded-xl bg-slate-900 px-4 py-3.5 text-base font-semibold text-white hover:bg-slate-800 disabled:opacity-60 sm:w-auto"
                :disabled="loading"
            >
                {{ loading ? t('common.loading') : t('platform.createSchoolSubmit') }}
            </button>
        </form>
    </section>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n();

const loading = ref(false);
const error = ref('');
const inviteUrl = ref('');
const copied = ref(false);

const form = ref({
    name: '',
    code: '',
    district: '',
    type: 'government',
    whatsapp_bridge_enabled: true,
    admin_name: '',
    admin_phone: '',
});

async function handleSubmit() {
    error.value = '';
    inviteUrl.value = '';
    loading.value = true;

    try {
        const { data } = await axios.post('/api/platform/schools', form.value);
        inviteUrl.value = data.invite?.invite_url ?? '';
        form.value = {
            name: '',
            code: '',
            district: '',
            type: 'government',
            whatsapp_bridge_enabled: true,
            admin_name: '',
            admin_phone: '',
        };
    } catch (err) {
        error.value = err?.response?.data?.message ?? t('auth.genericError');
    } finally {
        loading.value = false;
    }
}

async function copyInvite() {
    await navigator.clipboard.writeText(inviteUrl.value);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}
</script>

<style scoped>
.field-input {
    @apply mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 outline-none ring-blue-500 focus:border-blue-500 focus:ring-2;
}
</style>
