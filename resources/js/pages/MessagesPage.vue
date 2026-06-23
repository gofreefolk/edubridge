<template>
    <section class="space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('nav.messages') }}</h1>
            <button
                type="button"
                class="rounded-xl bg-blue-700 px-3 py-2 text-sm font-semibold text-white"
                @click="showForm = !showForm"
            >
                {{ t('messages.new') }}
            </button>
        </div>

        <form v-if="showForm" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="submit">
            <input v-model="form.subject" class="w-full rounded-xl border px-3 py-2" :placeholder="t('messages.subject')" required />
            <textarea v-model="form.body" class="w-full rounded-xl border px-3 py-2" rows="3" :placeholder="t('messages.body')" required />
            <button type="submit" class="w-full rounded-xl bg-blue-700 py-2.5 font-semibold text-white">{{ t('messages.send') }}</button>
        </form>

        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>

        <div v-else class="space-y-3">
            <div v-for="thread in threads" :key="thread.id" class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="font-semibold text-slate-900">{{ thread.subject }}</p>
                <p class="text-sm text-slate-500">{{ thread.status }}</p>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t } = useI18n();
const { isAuthenticated } = useAuth();
const { activeSchoolId, activeStudentId } = useSchoolContext();

const loading = ref(false);
const threads = ref([]);
const showForm = ref(false);
const form = reactive({ subject: '', body: '' });

async function load() {
    if (!isAuthenticated.value || !activeSchoolId.value) return;
    loading.value = true;
    try {
        const { data } = await axios.get('/api/feedback', { params: { school_id: activeSchoolId.value } });
        threads.value = data.threads;
    } finally {
        loading.value = false;
    }
}

async function submit() {
    await axios.post('/api/feedback', {
        school_id: activeSchoolId.value,
        student_id: activeStudentId.value,
        category: 'homework',
        direction: 'parent_to_teacher',
        subject: form.subject,
        body: form.body,
    });
    form.subject = '';
    form.body = '';
    showForm.value = false;
    await load();
}

watch([activeSchoolId, isAuthenticated], load);
onMounted(load);
</script>
