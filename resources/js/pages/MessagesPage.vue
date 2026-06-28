<template>
    <section class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('nav.messages') }}</h1>
            <button
                v-if="canCompose"
                type="button"
                class="rounded-xl bg-blue-700 px-3 py-2 text-sm font-semibold text-white"
                @click="showForm = !showForm"
            >
                {{ t('messages.new') }}
            </button>
        </div>

        <form v-if="showForm && canCompose" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="submit">
            <select v-model="form.category" class="w-full rounded-xl border px-3 py-2" required>
                <option value="general">{{ t('messages.categoryGeneral') }}</option>
                <option value="academic">{{ t('messages.categoryAcademic') }}</option>
                <option value="transport">{{ t('messages.categoryTransport') }}</option>
                <option value="fees">{{ t('messages.categoryFees') }}</option>
            </select>
            <select v-model="form.direction" class="w-full rounded-xl border px-3 py-2" required>
                <option value="parent_to_teacher">{{ t('messages.toTeacher') }}</option>
                <option value="parent_to_admin">{{ t('messages.toAdmin') }}</option>
            </select>
            <input v-model="form.subject" class="w-full rounded-xl border px-3 py-2" :placeholder="t('messages.subject')" required />
            <textarea v-model="form.body" class="w-full rounded-xl border px-3 py-2" rows="3" :placeholder="t('messages.body')" required />
            <button type="submit" class="w-full rounded-xl bg-blue-700 py-2.5 font-semibold text-white" :disabled="submitting">
                {{ submitting ? t('common.loading') : t('messages.send') }}
            </button>
        </form>

        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>

        <template v-else>
            <div v-if="!activeThread" class="space-y-3">
                <button
                    v-for="thread in threads"
                    :key="thread.id"
                    type="button"
                    class="w-full rounded-2xl border border-slate-200 bg-white p-4 text-left transition hover:border-blue-200"
                    @click="openThread(thread.id)"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold text-slate-900">{{ thread.subject }}</p>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="statusClass(thread.status)"
                        >
                            {{ t(`messages.status.${thread.status}`) }}
                        </span>
                    </div>
                    <p v-if="thread.creator?.name" class="mt-1 text-xs text-slate-500">
                        {{ thread.creator.name }}
                        <span v-if="thread.student?.name"> · {{ thread.student.name }}</span>
                    </p>
                    <p v-if="thread.last_message" class="mt-2 line-clamp-2 text-sm text-slate-600">
                        {{ thread.last_message.body }}
                    </p>
                </button>
                <p v-if="threads.length === 0" class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-slate-500">
                    {{ t('messages.empty') }}
                </p>
            </div>

            <div v-else class="space-y-3">
                <button type="button" class="text-sm font-medium text-blue-800" @click="closeThread">
                    ← {{ t('messages.backToList') }}
                </button>

                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-lg font-bold text-slate-900">{{ activeThread.subject }}</h2>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(activeThread.status)">
                            {{ t(`messages.status.${activeThread.status}`) }}
                        </span>
                    </div>
                    <p v-if="activeThread.creator?.name" class="mt-1 text-sm text-slate-500">
                        {{ activeThread.creator.name }}
                    </p>
                </div>

                <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                    <div
                        v-for="message in activeThread.messages"
                        :key="message.id"
                        class="rounded-xl px-3 py-2"
                        :class="message.author?.id === user?.id ? 'ml-6 bg-blue-50 text-blue-950' : 'mr-6 bg-slate-50 text-slate-900'"
                    >
                        <p class="text-xs font-medium text-slate-500">{{ message.author?.name }}</p>
                        <p class="mt-1 whitespace-pre-wrap text-sm">{{ message.body }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ formatTime(message.created_at) }}</p>
                    </div>
                </div>

                <form v-if="activeThread.status !== 'resolved'" class="space-y-2" @submit.prevent="sendReply">
                    <textarea
                        v-model="replyBody"
                        rows="3"
                        class="w-full rounded-xl border px-3 py-2"
                        :placeholder="t('messages.replyPlaceholder')"
                        required
                    />
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <button type="submit" class="rounded-xl bg-blue-700 py-2.5 font-semibold text-white" :disabled="replying">
                            {{ replying ? t('common.loading') : t('messages.reply') }}
                        </button>
                        <button
                            v-if="canResolve"
                            type="button"
                            class="rounded-xl border border-slate-300 py-2.5 font-semibold text-slate-800"
                            :disabled="resolving"
                            @click="resolveThread"
                        >
                            {{ resolving ? t('common.loading') : t('messages.resolve') }}
                        </button>
                    </div>
                </form>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t, locale } = useI18n();
const { user, isAuthenticated } = useAuth();
const { activeSchoolId, activeStudentId } = useSchoolContext();

const loading = ref(false);
const submitting = ref(false);
const replying = ref(false);
const resolving = ref(false);
const threads = ref([]);
const activeThread = ref(null);
const canResolve = ref(false);
const showForm = ref(false);
const replyBody = ref('');
const form = reactive({
    subject: '',
    body: '',
    category: 'general',
    direction: 'parent_to_teacher',
});

const canCompose = computed(() => ['parent', 'grandparent'].includes(activeRole.value));

function statusClass(status) {
    if (status === 'resolved') return 'bg-green-100 text-green-800';
    if (status === 'acknowledged') return 'bg-amber-100 text-amber-800';
    return 'bg-blue-100 text-blue-800';
}

function formatTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString(locale.value === 'ml' ? 'ml-IN' : 'en-IN', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

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

async function openThread(id) {
    const { data } = await axios.get(`/api/feedback/${id}`);
    activeThread.value = data.thread;
    canResolve.value = data.can_resolve;
    replyBody.value = '';
}

function closeThread() {
    activeThread.value = null;
    load();
}

async function submit() {
    submitting.value = true;
    try {
        await axios.post('/api/feedback', {
            school_id: activeSchoolId.value,
            student_id: activeStudentId.value,
            category: form.category,
            direction: form.direction,
            subject: form.subject,
            body: form.body,
        });
        form.subject = '';
        form.body = '';
        showForm.value = false;
        await load();
    } finally {
        submitting.value = false;
    }
}

async function sendReply() {
    if (!activeThread.value) return;
    replying.value = true;
    try {
        const { data } = await axios.post(`/api/feedback/${activeThread.value.id}/reply`, {
            body: replyBody.value,
        });
        activeThread.value = data.thread;
        replyBody.value = '';
    } finally {
        replying.value = false;
    }
}

async function resolveThread() {
    if (!activeThread.value) return;
    resolving.value = true;
    try {
        await axios.post(`/api/feedback/${activeThread.value.id}/resolve`);
        activeThread.value = { ...activeThread.value, status: 'resolved' };
    } finally {
        resolving.value = false;
    }
}

watch([activeSchoolId, isAuthenticated], () => {
    activeThread.value = null;
    load();
});
onMounted(load);
</script>
