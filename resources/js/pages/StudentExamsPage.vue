<template>
    <section class="space-y-4">
        <h1 class="text-2xl font-bold text-slate-900">{{ t('nav.exams') }}</h1>

        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>

        <div v-else class="space-y-3">
            <div
                v-for="exam in exams"
                :key="exam.id"
                class="rounded-2xl border border-slate-200 bg-white p-4"
            >
                <p class="font-semibold text-slate-900">{{ exam.title }}</p>
                <p class="text-sm text-slate-500">
                    {{ formatDate(exam.opens_at) }} — {{ formatDate(exam.closes_at) }}
                </p>
            </div>
            <p v-if="!exams.length" class="text-slate-600">{{ t('student.noExams') }}</p>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';
import { useAuth } from '@/composables/useAuth';

const { t, locale } = useI18n();
const { activeSchoolId } = useSchoolContext();
const { user } = useAuth();

const loading = ref(false);
const exams = ref([]);

function formatDate(iso) {
    return new Date(iso).toLocaleDateString(locale.value === 'ml' ? 'ml-IN' : 'en-IN');
}

onMounted(async () => {
    loading.value = true;
    try {
        if (user.value?.student_profile) {
            const { data: portal } = await axios.get('/api/student/dashboard', {
                params: { student_id: user.value.student_profile.id },
            });
            exams.value = portal.upcoming_exams ?? [];
        } else if (activeSchoolId.value) {
            const { data } = await axios.get('/api/exams', { params: { school_id: activeSchoolId.value } });
            exams.value = data.exams ?? [];
        }
    } finally {
        loading.value = false;
    }
});
</script>
