<template>
    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('admin.createNoticeTitle') }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ t('admin.createNoticeSubtitle') }}</p>
        </div>

        <form v-if="!publishedNotice" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="submit">
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.noticeTitleMl') }}</span>
                <input
                    v-model="form.title"
                    type="text"
                    required
                    class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-base"
                />
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.noticeBodyMl') }}</span>
                <textarea
                    v-model="form.body"
                    required
                    rows="5"
                    class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-base"
                />
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.noticeTitleEn') }}</span>
                <input
                    v-model="form.title_en"
                    type="text"
                    class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-base"
                />
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.noticeBodyEn') }}</span>
                <textarea
                    v-model="form.body_en"
                    rows="4"
                    class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-base"
                />
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.noticePriority') }}</span>
                <select v-model="form.priority" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-base">
                    <option value="normal">{{ t('admin.priorityNormal') }}</option>
                    <option value="urgent">{{ t('admin.priorityUrgent') }}</option>
                </select>
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.noticeAudience') }}</span>
                <select v-model="form.audience_type" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-base">
                    <option value="whole_school">{{ t('admin.audienceWholeSchool') }}</option>
                    <option value="class">{{ t('admin.audienceClass') }}</option>
                    <option value="section">{{ t('admin.audienceSection') }}</option>
                </select>
            </label>

            <label v-if="form.audience_type === 'class'" class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.selectClass') }}</span>
                <select v-model="selectedClassId" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-base">
                    <option value="">{{ t('admin.chooseClass') }}</option>
                    <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                </select>
            </label>

            <template v-if="form.audience_type === 'section'">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">{{ t('admin.selectClass') }}</span>
                    <select v-model="selectedClassId" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-base">
                        <option value="">{{ t('admin.chooseClass') }}</option>
                        <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">{{ t('admin.selectSection') }}</span>
                    <select v-model="selectedSectionId" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-base">
                        <option value="">{{ t('admin.chooseSection') }}</option>
                        <option v-for="section in sectionOptions" :key="section.id" :value="section.id">{{ section.name }}</option>
                    </select>
                </label>
            </template>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.pinDays') }}</span>
                <input
                    v-model.number="form.pin_days"
                    type="number"
                    min="1"
                    max="30"
                    class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3 text-base"
                    :placeholder="t('admin.pinDaysOptional')"
                />
            </label>

            <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

            <button
                type="submit"
                class="w-full rounded-xl bg-blue-700 py-3 text-base font-semibold text-white disabled:opacity-60"
                :disabled="submitting"
            >
                {{ submitting ? t('common.loading') : t('admin.publishNotice') }}
            </button>
        </form>

        <div v-else class="space-y-3 rounded-2xl border border-green-200 bg-green-50 p-4">
            <p class="font-semibold text-green-900">{{ t('admin.noticePublished') }}</p>
            <p class="text-sm text-green-800">{{ t('admin.shareLinkHint') }}</p>
            <div class="rounded-xl bg-white p-3 text-sm break-all text-slate-800">{{ shareUrl }}</div>
            <button
                type="button"
                class="w-full rounded-xl border border-green-300 bg-white py-3 text-sm font-semibold text-green-900"
                @click="copyLink"
            >
                {{ copied ? t('admin.linkCopied') : t('admin.copyLink') }}
            </button>
            <router-link
                :to="{ name: 'notices' }"
                class="block text-center text-sm font-semibold text-blue-800 underline"
            >
                {{ t('admin.backToNotices') }}
            </router-link>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t } = useI18n();
const { activeSchoolId } = useSchoolContext();

const classes = ref([]);
const selectedClassId = ref('');
const selectedSectionId = ref('');
const submitting = ref(false);
const error = ref('');
const publishedNotice = ref(null);
const copied = ref(false);

const form = reactive({
    title: '',
    body: '',
    title_en: '',
    body_en: '',
    priority: 'normal',
    audience_type: 'whole_school',
    pin_days: null,
});

const sectionOptions = computed(() => {
    const cls = classes.value.find((item) => String(item.id) === String(selectedClassId.value));
    return cls?.sections ?? [];
});

const shareUrl = computed(() => {
    if (!publishedNotice.value?.magic_link_token) {
        return '';
    }

    return `${window.location.origin}/n/${publishedNotice.value.magic_link_token}`;
});

async function loadClasses() {
    if (!activeSchoolId.value) {
        return;
    }

    const { data } = await axios.get('/api/admin/classes', {
        params: { school_id: activeSchoolId.value },
    });
    classes.value = data.classes;
}

function buildAudiences() {
    if (form.audience_type === 'class' && selectedClassId.value) {
        return [{ school_class_id: Number(selectedClassId.value) }];
    }

    if (form.audience_type === 'section' && selectedSectionId.value) {
        return [{ section_id: Number(selectedSectionId.value) }];
    }

    return [];
}

async function submit() {
    error.value = '';
    submitting.value = true;

    try {
        const payload = {
            school_id: activeSchoolId.value,
            title: form.title,
            body: form.body,
            title_en: form.title_en || null,
            body_en: form.body_en || null,
            priority: form.priority,
            audience_type: form.audience_type,
            pin_days: form.pin_days || null,
            audiences: buildAudiences(),
        };

        const { data: created } = await axios.post('/api/notices', payload);
        const { data: published } = await axios.post(`/api/notices/${created.notice.id}/publish`);
        publishedNotice.value = published.notice;
    } catch (err) {
        error.value = err?.response?.data?.message ?? t('auth.genericError');
    } finally {
        submitting.value = false;
    }
}

async function copyLink() {
    try {
        await navigator.clipboard.writeText(shareUrl.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // ignore clipboard errors
    }
}

onMounted(loadClasses);
</script>
