<template>
    <section class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 lg:text-3xl">{{ t('platform.schoolsTitle') }}</h2>
            <p class="mt-2 text-slate-600">{{ t('platform.schoolsSubtitle') }}</p>
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
                            <th class="px-5 py-3">{{ t('platform.users') }}</th>
                            <th class="px-5 py-3">{{ t('platform.table.notices') }}</th>
                            <th class="px-5 py-3">{{ t('platform.table.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="schools.length === 0">
                            <td colspan="7" class="px-5 py-10 text-center text-slate-500">
                                {{ t('platform.noSchools') }}
                            </td>
                        </tr>
                        <tr
                            v-for="school in schools"
                            :key="school.id"
                            class="transition hover:bg-slate-50"
                        >
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">{{ school.name }}</p>
                                <p class="text-xs text-slate-500">{{ school.code }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-700">{{ school.district }}</td>
                            <td class="px-5 py-4 capitalize text-slate-700">{{ school.type }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ school.students_count }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ school.users_count }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ school.notices_count }}</td>
                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="school.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                                >
                                    {{ school.is_active ? t('platform.active') : t('platform.inactive') }}
                                </span>
                                <span
                                    v-if="school.whatsapp_bridge_enabled"
                                    class="ml-2 inline-flex rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700"
                                >
                                    WhatsApp
                                </span>
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

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/platform/schools');
        schools.value = data.schools ?? [];
    } finally {
        loading.value = false;
    }
});
</script>
