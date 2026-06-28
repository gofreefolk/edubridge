<template>
    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('admin.staffTitle') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ t('admin.staffSubtitle') }}</p>
        </div>

        <form class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="addStaff">
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('platform.form.adminName') }}</span>
                <input v-model="form.name" type="text" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" />
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('platform.form.adminPhone') }}</span>
                <input v-model="form.phone" type="tel" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" />
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.staffRole') }}</span>
                <select v-model="form.role" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3">
                    <option value="teacher">{{ t('roles.teacher') }}</option>
                    <option value="transport_staff">{{ t('roles.transport_staff') }}</option>
                    <option value="smc_member">{{ t('roles.smc_member') }}</option>
                </select>
            </label>
            <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
            <button type="submit" class="w-full rounded-xl bg-blue-700 py-3 font-semibold text-white" :disabled="saving">
                {{ saving ? t('common.loading') : t('admin.addStaff') }}
            </button>
        </form>

        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>
        <p v-else-if="!staff.length" class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-600">{{ t('admin.noStaff') }}</p>

        <ul v-else class="space-y-2">
            <li v-for="member in staff" :key="`${member.user_id}-${member.role}`" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4">
                <div>
                    <p class="font-semibold text-slate-900">{{ member.name }}</p>
                    <p class="text-sm text-slate-500">{{ member.phone }} · {{ t(`roles.${member.role}`) }}</p>
                </div>
                <button type="button" class="text-sm font-medium text-red-700" @click="removeStaff(member)">
                    {{ t('admin.removeStaff') }}
                </button>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t } = useI18n();
const { activeSchoolId } = useSchoolContext();

const loading = ref(false);
const saving = ref(false);
const staff = ref([]);
const error = ref('');
const form = ref({ name: '', phone: '', role: 'teacher' });

async function load() {
    if (!activeSchoolId.value) return;
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/staff', { params: { school_id: activeSchoolId.value } });
        staff.value = data.staff;
    } finally {
        loading.value = false;
    }
}

async function addStaff() {
    saving.value = true;
    error.value = '';
    try {
        await axios.post('/api/admin/staff', {
            school_id: activeSchoolId.value,
            ...form.value,
        });
        form.value = { name: '', phone: '', role: 'teacher' };
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    } finally {
        saving.value = false;
    }
}

async function removeStaff(member) {
    if (!window.confirm(t('admin.confirmRemoveStaff'))) return;
    try {
        await axios.delete(`/api/admin/staff/${member.user_id}`, {
            data: { school_id: activeSchoolId.value, role: member.role },
        });
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

watch(activeSchoolId, load);
onMounted(load);
</script>
