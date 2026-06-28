<template>
    <section class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 lg:text-3xl">{{ t('platform.registrationsTitle') }}</h2>
                <p class="mt-2 text-slate-600">{{ t('platform.registrationsSubtitle') }}</p>
            </div>
            <router-link :to="{ name: 'school-register' }" class="text-sm font-medium text-blue-700 hover:underline" target="_blank">
                {{ t('platform.openRegistrationForm') }}
            </router-link>
        </div>

        <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-500">
            {{ t('common.loading') }}
        </div>

        <div v-else-if="registrations.length === 0" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500">
            {{ t('platform.noPendingRegistrations') }}
        </div>

        <div v-else class="space-y-4">
            <article
                v-for="school in registrations"
                :key="school.id"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ school.name }}</h3>
                        <p class="text-sm text-slate-500">{{ school.code }} · {{ school.district }} · {{ school.type }}</p>
                        <p class="mt-2 text-sm text-slate-700">
                            {{ t('platform.form.adminName') }}: {{ school.admin_contact_name }}
                            ({{ school.admin_contact_phone }})
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ t('platform.submittedAt') }}: {{ formatDate(school.created_at) }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
                            :disabled="actionId === school.id"
                            @click="approve(school)"
                        >
                            {{ t('platform.approve') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-100 disabled:opacity-60"
                            :disabled="actionId === school.id"
                            @click="openReject(school)"
                        >
                            {{ t('platform.reject') }}
                        </button>
                        <router-link
                            :to="{ name: 'platform-school-detail', params: { id: school.id } }"
                            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            {{ t('platform.viewDetails') }}
                        </router-link>
                    </div>
                </div>
                <div v-if="inviteBySchool[school.id]" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-sm font-medium text-emerald-900">{{ t('platform.inviteCreated') }}</p>
                    <p class="mt-1 break-all text-sm text-emerald-800">{{ inviteBySchool[school.id] }}</p>
                </div>
            </article>
        </div>

        <div v-if="rejecting" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-900">{{ t('platform.rejectTitle') }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ rejecting.name }}</p>
                <textarea
                    v-model="rejectReason"
                    rows="4"
                    class="mt-4 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                    :placeholder="t('platform.rejectReasonPlaceholder')"
                />
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-lg px-4 py-2 text-sm text-slate-600" @click="rejecting = null">
                        {{ t('platform.cancel') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white"
                        :disabled="!rejectReason.trim() || actionId"
                        @click="confirmReject"
                    >
                        {{ t('platform.reject') }}
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t, locale } = useI18n();

const loading = ref(true);
const registrations = ref([]);
const actionId = ref(null);
const rejecting = ref(null);
const rejectReason = ref('');
const inviteBySchool = ref({});

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString(locale.value === 'ml' ? 'ml-IN' : 'en-IN');
}

async function load() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/platform/registrations');
        registrations.value = data.registrations ?? [];
    } finally {
        loading.value = false;
    }
}

async function approve(school) {
    actionId.value = school.id;
    try {
        const { data } = await axios.post(`/api/platform/schools/${school.id}/approve`);
        if (data.invite?.invite_url) {
            inviteBySchool.value[school.id] = data.invite.invite_url;
        }
        registrations.value = registrations.value.filter((s) => s.id !== school.id);
    } finally {
        actionId.value = null;
    }
}

function openReject(school) {
    rejecting.value = school;
    rejectReason.value = '';
}

async function confirmReject() {
    if (!rejecting.value) return;
    actionId.value = rejecting.value.id;
    try {
        await axios.post(`/api/platform/schools/${rejecting.value.id}/reject`, {
            reason: rejectReason.value,
        });
        registrations.value = registrations.value.filter((s) => s.id !== rejecting.value.id);
        rejecting.value = null;
    } finally {
        actionId.value = null;
    }
}

onMounted(load);
</script>
