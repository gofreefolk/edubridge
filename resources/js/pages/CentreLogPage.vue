<template>
    <section class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('ops.logsTitle') }}</h1>
            <button v-if="canWrite" type="button" class="rounded-xl bg-blue-700 px-3 py-2 text-sm font-semibold text-white" @click="showForm = !showForm">
                {{ t('ops.newLog') }}
            </button>
        </div>

        <form v-if="showForm && canWrite" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="submit">
            <select v-model="form.category" class="w-full rounded-xl border px-3 py-2" required>
                <option v-for="c in categories" :key="c" :value="c">{{ t(`ops.cat_${c}`) }}</option>
            </select>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <select v-if="isAdmin" v-model="classId" class="rounded-xl border px-3 py-2">
                    <option :value="null">{{ t('ops.wholeSchool') }}</option>
                    <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                </select>
                <select v-model="form.student_id" class="rounded-xl border px-3 py-2" :disabled="!classId" :class="isAdmin ? '' : 'sm:col-span-2'">
                    <option :value="null">{{ t('ops.aboutStudent') }}</option>
                    <option v-for="s in roster" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>

            <input v-model="form.title" required maxlength="255" :placeholder="t('ops.logTitle')" class="w-full rounded-xl border px-3 py-2" />
            <textarea v-model="form.body" required rows="4" :placeholder="t('ops.details')" class="w-full rounded-xl border px-3 py-2" />
            <label class="flex items-center gap-2 text-sm"><input v-model="form.visible_to_parents" type="checkbox" /> {{ t('ops.visibleToParents') }}</label>

            <p v-if="formError" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError }}</p>
            <button type="submit" class="w-full rounded-xl bg-blue-700 py-2.5 font-semibold text-white disabled:opacity-60" :disabled="submitting">
                {{ submitting ? t('common.loading') : t('common.save') }}
            </button>
        </form>

        <div class="flex gap-2 overflow-x-auto pb-1">
            <button
                v-for="c in ['', ...categories]"
                :key="c || 'all'"
                type="button"
                class="whitespace-nowrap rounded-full border px-3 py-1 text-sm"
                :class="filter === c ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white text-slate-700'"
                @click="filter = c"
            >
                {{ c ? t(`ops.cat_${c}`) : t('ops.all') }}
            </button>
        </div>

        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>
        <p v-else-if="!logs.length" class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-600">{{ t('ops.noLogs') }}</p>

        <article v-for="log in logs" :key="log.id" class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <span class="rounded px-2 py-0.5 text-xs font-semibold" :class="categoryClass(log.category)">{{ t(`ops.cat_${log.category}`) }}</span>
                    <h2 class="mt-1 font-semibold text-slate-900">{{ log.title }}</h2>
                </div>
                <span class="whitespace-nowrap text-xs text-slate-500">{{ formatTime(log.occurred_at) }}</span>
            </div>
            <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ log.body }}</p>
            <p class="mt-2 text-xs text-slate-500">
                {{ log.author }}
                <template v-if="log.student"> · {{ log.student.name }}</template>
                <template v-else-if="log.class"> · {{ log.class }}</template>
                <template v-if="canWrite"> · {{ log.visible_to_parents ? t('ops.shared') : t('ops.staffOnly') }}</template>
            </p>
            <button v-if="log.can_delete" type="button" class="mt-2 text-xs text-red-700" @click="remove(log)">{{ t('common.delete') }}</button>
        </article>

        <button v-if="meta.current_page < meta.last_page" type="button" class="w-full rounded-xl border bg-white py-2 text-sm" @click="loadMore">
            {{ t('ops.loadMore') }}
        </button>
    </section>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';
import { useClassPicker } from '@/composables/useClassPicker';

const { t, locale } = useI18n();
const route = useRoute();
const { activeSchoolId } = useSchoolContext();
const { classes, classId, isAdmin, loadClasses } = useClassPicker();

const categories = ['daily', 'incident', 'health', 'behaviour', 'other'];
const logs = ref([]);
const meta = ref({ current_page: 1, last_page: 1 });
const canWrite = ref(false);
const loading = ref(false);
const filter = ref('');
const showForm = ref(false);
const submitting = ref(false);
const formError = ref('');
const roster = ref([]);
const emptyForm = () => ({ category: 'daily', student_id: null, title: '', body: '', visible_to_parents: false });
const form = reactive(emptyForm());

function categoryClass(category) {
    return {
        incident: 'bg-red-100 text-red-800',
        health: 'bg-amber-100 text-amber-800',
        behaviour: 'bg-purple-100 text-purple-800',
        daily: 'bg-blue-100 text-blue-800',
    }[category] ?? 'bg-slate-100 text-slate-700';
}

function formatTime(iso) {
    return new Date(iso).toLocaleString(locale.value === 'ml' ? 'ml-IN' : 'en-IN', { dateStyle: 'medium', timeStyle: 'short' });
}

async function fetchPage(page) {
    const { data } = await axios.get('/api/centre-logs', {
        params: {
            school_id: activeSchoolId.value,
            category: filter.value || undefined,
            student_id: route.query.student_id || undefined,
            page,
        },
    });
    canWrite.value = data.can_write;
    meta.value = data.meta;
    return data.logs;
}

async function load() {
    if (!activeSchoolId.value) return;
    loading.value = true;
    try {
        logs.value = await fetchPage(1);
    } finally {
        loading.value = false;
    }
}

async function loadMore() {
    logs.value = [...logs.value, ...(await fetchPage(meta.value.current_page + 1))];
}

async function loadRoster() {
    form.student_id = null;
    roster.value = [];
    if (!classId.value) return;
    try {
        const { data } = await axios.get('/api/attendance/sheet', { params: { school_class_id: classId.value } });
        roster.value = data.students;
    } catch {
        roster.value = [];
    }
}

async function submit() {
    submitting.value = true;
    formError.value = '';
    try {
        const { data } = await axios.post('/api/centre-logs', {
            school_id: activeSchoolId.value,
            school_class_id: classId.value,
            ...form,
        });
        logs.value = [data.log, ...logs.value];
        Object.assign(form, emptyForm());
        showForm.value = false;
    } catch (e) {
        formError.value = e.response?.data?.message ?? t('common.error');
    } finally {
        submitting.value = false;
    }
}

async function remove(log) {
    if (!window.confirm(t('ops.confirmDeleteLog'))) return;
    await axios.delete(`/api/centre-logs/${log.id}`);
    logs.value = logs.value.filter((l) => l.id !== log.id);
}

watch([filter, activeSchoolId, () => route.query.student_id], load);
watch(classId, loadRoster);
watch(showForm, async (open) => {
    if (open && !classes.value.length) {
        await loadClasses();
        await loadRoster();
    }
});
onMounted(load);
</script>
