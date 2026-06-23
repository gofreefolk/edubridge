<template>
    <section class="space-y-4">
        <h1 class="text-2xl font-bold text-slate-900">{{ t('nav.myChild') }}</h1>

        <div v-if="!activeStudent" class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-600">
            {{ t('myChild.noChild') }}
        </div>

        <template v-else>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-lg font-bold">{{ portal?.student?.name }}</p>
                <p class="text-slate-600">{{ portal?.student?.class }}-{{ portal?.student?.section }}</p>
            </div>

            <div v-if="portal?.homework?.length" class="space-y-2">
                <h2 class="font-semibold text-slate-800">{{ t('myChild.homework') }}</h2>
                <div v-for="hw in portal.homework" :key="hw.id" class="rounded-xl border bg-white p-3">
                    <p class="font-medium">{{ hw.title }}</p>
                    <p class="text-sm text-slate-500">{{ t('myChild.due') }}: {{ hw.due_date }}</p>
                </div>
            </div>

            <div v-if="portal?.attendance?.length" class="space-y-2">
                <h2 class="font-semibold text-slate-800">{{ t('myChild.attendance') }}</h2>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="rec in portal.attendance.slice(0, 7)"
                        :key="rec.id"
                        class="rounded-lg px-2 py-1 text-xs font-medium"
                        :class="rec.status === 'present' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                    >
                        {{ rec.date }}: {{ rec.status }}
                    </span>
                </div>
            </div>

            <router-link :to="{ name: 'smc' }" class="block rounded-2xl border border-blue-200 bg-blue-50 p-4 font-semibold text-blue-800">
                {{ t('myChild.smcBoard') }}
            </router-link>
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t } = useI18n();
const { isAuthenticated } = useAuth();
const { activeStudentId, children } = useSchoolContext();

const portal = ref(null);
const activeStudent = computed(() => children.value.find((c) => c.id === activeStudentId.value));

async function load() {
    if (!isAuthenticated.value || !activeStudentId.value) return;
    const { data } = await axios.get('/api/student/dashboard', { params: { student_id: activeStudentId.value } });
    portal.value = data;
}

watch(activeStudentId, load);
onMounted(load);
</script>
