<template>
    <section class="space-y-4">
        <h1 class="text-2xl font-bold text-slate-900">{{ t('nav.class') }}</h1>

        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>

        <p v-else-if="!teacherClassId" class="text-slate-600">{{ t('teacher.noClass') }}</p>

        <div v-else class="space-y-2">
            <div
                v-for="student in students"
                :key="student.id"
                class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-3"
            >
                <span class="font-medium">{{ student.name }}</span>
                <span class="text-sm text-slate-500">{{ student.admission_number }}</span>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';

const { t } = useI18n();
const { user } = useAuth();

const loading = ref(false);
const students = ref([]);

const teacherClassId = computed(() => user.value?.teacher_class_id ?? null);

async function load() {
    if (!teacherClassId.value) {
        students.value = [];
        return;
    }

    loading.value = true;
    try {
        const { data } = await axios.get('/api/teacher/roster', {
            params: { school_class_id: teacherClassId.value },
        });
        students.value = data.students;
    } finally {
        loading.value = false;
    }
}

watch(teacherClassId, load);
onMounted(load);
</script>
