<template>
    <section class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 lg:text-3xl">{{ t('platform.schoolsTitle') }}</h2>
                <p class="mt-2 text-slate-600">{{ t('platform.schoolsSubtitle') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <router-link
                    :to="{ name: 'platform-registrations' }"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800"
                >
                    {{ t('platform.pendingRegistrations') }}
                </router-link>
                <router-link
                    :to="{ name: 'platform-school-create' }"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white"
                >
                    {{ t('platform.addSchool') }}
                </router-link>
            </div>
        </div>

        <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-500">
            {{ t('common.loading') }}
        </div>

        <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3">{{ t('platform.table.school') }}</th>
                            <th class="px-5 py-3">{{ t('platform.table.district') }}</th>
                            <th class="px-5 py-3">{{ t('platform.table.type') }}</th>
                            <th class="px-5 py-3">{{ t('platform.students') }}</th>
                            <th class="px-5 py-3">{{ t('platform.table.status') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="schools.length === 0">
                            <td colspan="6" class="px-5 py-10 text-center text-slate-500">
                                {{ t('platform.noSchools') }}
                            </td>
                        </tr>
                        <tr v-for="school in schools" :key="school.id" class="hover:bg-slate-50">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">{{ school.name }}</p>
                                <p class="text-xs text-slate-500">{{ school.code }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-700">{{ school.district }}</td>
                            <td class="px-5 py-4 capitalize text-slate-700">{{ school.type }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ school.students_count }}</td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="approvalClass(school.approval_status)">
                                    {{ t(`platform.approval.${school.approval_status}`) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <router-link
                                    :to="{ name: 'platform-school-detail', params: { id: school.id } }"
                                    class="font-medium text-blue-700 hover:underline"
                                >
                                    {{ t('platform.manage') }}
                                </router-link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n();

const loading = ref(true);
const schools = ref([]);

function approvalClass(status) {
    return {
        pending: 'bg-amber-50 text-amber-700',
        approved: 'bg-emerald-50 text-emerald-700',
        rejected: 'bg-red-50 text-red-700',
    }[status] ?? 'bg-slate-100 text-slate-700';
}

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/platform/schools');
        schools.value = data.schools ?? [];
    } finally {
        loading.value = false;
    }
});
</script>
