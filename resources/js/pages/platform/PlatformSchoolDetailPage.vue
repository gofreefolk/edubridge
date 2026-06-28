<template>
    <section class="space-y-6">
        <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-500">
            {{ t('common.loading') }}
        </div>

        <template v-else-if="school">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <router-link :to="{ name: 'platform-schools' }" class="text-sm font-medium text-blue-700 hover:underline">
                        ← {{ t('platform.backToSchools') }}
                    </router-link>
                    <h2 class="mt-2 text-2xl font-bold text-slate-900">{{ school.name }}</h2>
                    <p class="text-slate-500">{{ school.code }} · {{ school.district }}</p>
                    <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(school.approval_status)">
                        {{ t(`platform.approval.${school.approval_status}`) }}
                    </span>
                </div>
                <div v-if="school.approval_status === 'pending'" class="flex gap-2">
                    <button type="button" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white" @click="approve">
                        {{ t('platform.approve') }}
                    </button>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-sm text-slate-500">{{ t('platform.students') }}</p>
                    <p class="text-2xl font-bold">{{ school.students_count }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-sm text-slate-500">{{ t('platform.users') }}</p>
                    <p class="text-2xl font-bold">{{ school.users_count }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-sm text-slate-500">{{ t('platform.table.notices') }}</p>
                    <p class="text-2xl font-bold">{{ school.notices_count }}</p>
                </div>
            </div>

            <div v-if="school.approval_status === 'approved'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">{{ t('platform.inviteAdminTitle') }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ t('platform.inviteAdminSubtitle') }}</p>
                <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="sendInvite">
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">{{ t('platform.form.adminName') }}</span>
                        <input v-model="inviteForm.admin_name" type="text" required class="field-input" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">{{ t('platform.form.adminPhone') }}</span>
                        <input v-model="inviteForm.admin_phone" type="tel" required class="field-input" />
                    </label>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white" :disabled="inviteLoading">
                            {{ inviteLoading ? t('common.loading') : t('platform.sendInvite') }}
                        </button>
                    </div>
                </form>
                <p v-if="newInviteUrl" class="mt-4 break-all rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">
                    {{ newInviteUrl }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h3 class="font-semibold text-slate-900">{{ t('platform.inviteHistory') }}</h3>
                </div>
                <div v-if="invites.length === 0" class="px-5 py-8 text-center text-sm text-slate-500">
                    {{ t('platform.noInvites') }}
                </div>
                <div v-else class="divide-y divide-slate-100">
                    <div v-for="invite in invites" :key="invite.id" class="px-5 py-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-medium text-slate-900">{{ invite.name }} · {{ invite.phone }}</p>
                                <p class="text-xs text-slate-500">{{ invite.status }} · {{ formatDate(invite.expires_at) }}</p>
                            </div>
                            <button
                                v-if="invite.status === 'pending'"
                                type="button"
                                class="text-sm font-medium text-blue-700"
                                @click="copyUrl(invite.invite_url)"
                            >
                                {{ t('admin.copyLink') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const props = defineProps({
    id: { type: [String, Number], required: true },
});

const { t, locale } = useI18n();
const route = useRoute();

const loading = ref(true);
const school = ref(null);
const invites = ref([]);
const inviteLoading = ref(false);
const newInviteUrl = ref('');
const inviteForm = ref({ admin_name: '', admin_phone: '' });

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(locale.value === 'ml' ? 'ml-IN' : 'en-IN');
}

function statusClass(status) {
    return {
        pending: 'bg-amber-50 text-amber-700',
        approved: 'bg-emerald-50 text-emerald-700',
        rejected: 'bg-red-50 text-red-700',
    }[status] ?? 'bg-slate-100 text-slate-700';
}

async function load() {
    const schoolId = props.id ?? route.params.id;
    loading.value = true;
    try {
        const { data } = await axios.get(`/api/platform/schools/${schoolId}`);
        school.value = data.school;
        invites.value = data.invites ?? [];
        inviteForm.value.admin_name = school.value.admin_contact_name ?? '';
        inviteForm.value.admin_phone = school.value.admin_contact_phone ?? '';
    } finally {
        loading.value = false;
    }
}

async function sendInvite() {
    inviteLoading.value = true;
    newInviteUrl.value = '';
    try {
        const { data } = await axios.post(`/api/platform/schools/${school.value.id}/invites`, inviteForm.value);
        newInviteUrl.value = data.invite?.invite_url ?? '';
        invites.value = [data.invite, ...invites.value];
    } finally {
        inviteLoading.value = false;
    }
}

async function approve() {
    const { data } = await axios.post(`/api/platform/schools/${school.value.id}/approve`);
    school.value = data.school;
    if (data.invite) {
        invites.value = [data.invite, ...invites.value];
        newInviteUrl.value = data.invite.invite_url;
    }
}

async function copyUrl(url) {
    await navigator.clipboard.writeText(url);
}

onMounted(load);
</script>

<style scoped>
.field-input {
    @apply mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 outline-none ring-blue-500 focus:border-blue-500 focus:ring-2;
}
</style>
