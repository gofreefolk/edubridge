<template>
    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-medium text-slate-500">{{ t('roles.student') }}</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ portal?.student?.name }}</h1>
            <p v-if="portal?.student" class="text-slate-600">
                {{ portal.student.class }}-{{ portal.student.section }}
            </p>
        </div>

        <div v-if="portal?.homework?.length" class="space-y-2">
            <h2 class="font-semibold">{{ t('nav.homework') }}</h2>
            <div v-for="hw in portal.homework" :key="hw.id" class="rounded-xl border bg-white p-3">
                <p class="font-medium">{{ hw.title }}</p>
                <p class="text-sm text-slate-500">{{ hw.due_date }}</p>
            </div>
        </div>

        <router-link
            :to="{ name: 'student-exams' }"
            class="block rounded-2xl border border-blue-200 bg-blue-50 p-4 font-semibold text-blue-800"
        >
            📖 {{ t('student.viewExams') }}
        </router-link>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';

const { t } = useI18n();
const { user } = useAuth();
const portal = ref(null);

onMounted(async () => {
    const studentId = user.value?.student_profile?.id;
    if (!studentId) {
        return;
    }

    const { data } = await axios.get('/api/student/dashboard', { params: { student_id: studentId } });
    portal.value = data;
});
</script>
