<template>
    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('admin.schoolProfileTitle') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ t('admin.schoolProfileSubtitle') }}</p>
        </div>

        <form v-if="profile" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="saveProfile">
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('platform.form.schoolName') }}</span>
                <input v-model="profile.name" type="text" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" />
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('platform.form.schoolCode') }}</span>
                <input v-model="profile.code" type="text" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 uppercase" />
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('platform.form.district') }}</span>
                <input v-model="profile.district" type="text" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" />
            </label>
            <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3">
                <input v-model="profile.whatsapp_bridge_enabled" type="checkbox" class="h-5 w-5 rounded" />
                <span class="text-sm font-medium text-slate-700">{{ t('admin.whatsappBridge') }}</span>
            </label>
            <p v-if="profileError" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ profileError }}</p>
            <p v-if="profileSaved" class="rounded-xl bg-green-50 px-3 py-2 text-sm text-green-800">{{ t('admin.profileSaved') }}</p>
            <button type="submit" class="w-full rounded-xl bg-blue-700 py-3 font-semibold text-white disabled:opacity-60" :disabled="savingProfile">
                {{ savingProfile ? t('common.loading') : t('admin.saveProfile') }}
            </button>
        </form>

        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ t('admin.coAdminTitle') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ t('admin.coAdminSubtitle') }}</p>

            <form class="mt-4 space-y-3" @submit.prevent="sendInvite">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">{{ t('platform.form.adminName') }}</span>
                    <input v-model="inviteForm.name" type="text" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" />
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">{{ t('platform.form.adminPhone') }}</span>
                    <input v-model="inviteForm.phone" type="tel" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" />
                </label>
                <p v-if="inviteError" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ inviteError }}</p>
                <p v-if="newInviteUrl" class="rounded-xl bg-green-50 p-3 text-sm text-green-900">
                    {{ t('admin.inviteCreated') }}
                    <span class="mt-1 block break-all font-mono text-xs">{{ newInviteUrl }}</span>
                </p>
                <button type="submit" class="w-full rounded-xl border border-blue-200 bg-blue-50 py-3 font-semibold text-blue-800" :disabled="sendingInvite">
                    {{ sendingInvite ? t('common.loading') : t('admin.sendInvite') }}
                </button>
            </form>

            <ul v-if="invites.length" class="mt-4 space-y-2">
                <li v-for="invite in invites" :key="invite.id" class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm">
                    <p class="font-medium text-slate-900">{{ invite.name }} · {{ invite.phone }}</p>
                    <p class="text-slate-500">{{ invite.status }}</p>
                    <p v-if="invite.invite_url" class="mt-1 break-all font-mono text-xs text-blue-800">{{ invite.invite_url }}</p>
                </li>
            </ul>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t } = useI18n();
const { activeSchoolId } = useSchoolContext();

const profile = ref(null);
const invites = ref([]);
const savingProfile = ref(false);
const profileSaved = ref(false);
const profileError = ref('');
const inviteForm = ref({ name: '', phone: '' });
const sendingInvite = ref(false);
const inviteError = ref('');
const newInviteUrl = ref('');

async function load() {
    if (!activeSchoolId.value) return;
    const [{ data: schoolData }, { data: inviteData }] = await Promise.all([
        axios.get('/api/admin/school', { params: { school_id: activeSchoolId.value } }),
        axios.get('/api/admin/invites', { params: { school_id: activeSchoolId.value } }),
    ]);
    profile.value = { ...schoolData.school };
    invites.value = inviteData.invites;
}

async function saveProfile() {
    savingProfile.value = true;
    profileSaved.value = false;
    profileError.value = '';
    try {
        const { data } = await axios.put('/api/admin/school', {
            school_id: activeSchoolId.value,
            ...profile.value,
        });
        profile.value = data.school;
        profileSaved.value = true;
    } catch (e) {
        profileError.value = e.response?.data?.message ?? t('common.error');
    } finally {
        savingProfile.value = false;
    }
}

async function sendInvite() {
    sendingInvite.value = true;
    inviteError.value = '';
    newInviteUrl.value = '';
    try {
        const { data } = await axios.post('/api/admin/invites', {
            school_id: activeSchoolId.value,
            ...inviteForm.value,
        });
        newInviteUrl.value = data.invite.invite_url;
        inviteForm.value = { name: '', phone: '' };
        await load();
    } catch (e) {
        inviteError.value = e.response?.data?.message ?? t('common.error');
    } finally {
        sendingInvite.value = false;
    }
}

onMounted(load);
</script>
