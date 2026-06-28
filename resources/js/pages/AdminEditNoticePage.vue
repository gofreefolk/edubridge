<template>
    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('admin.editNoticeTitle') }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ t('admin.editNoticeSubtitle') }}</p>
        </div>

        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>

        <form v-else-if="form" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="save">
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.noticeTitleMl') }}</span>
                <input v-model="form.title" type="text" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" />
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.noticeBodyMl') }}</span>
                <textarea v-model="form.body" required rows="5" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" />
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.noticeTitleEn') }}</span>
                <input v-model="form.title_en" type="text" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" />
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.noticeBodyEn') }}</span>
                <textarea v-model="form.body_en" rows="4" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" />
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.noticePriority') }}</span>
                <select v-model="form.priority" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3">
                    <option value="normal">{{ t('admin.priorityNormal') }}</option>
                    <option value="urgent">{{ t('admin.priorityUrgent') }}</option>
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.noticeAudience') }}</span>
                <select v-model="form.audience_type" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3">
                    <option value="whole_school">{{ t('admin.audienceWholeSchool') }}</option>
                    <option value="class">{{ t('admin.audienceClass') }}</option>
                    <option value="section">{{ t('admin.audienceSection') }}</option>
                </select>
            </label>
            <label v-if="form.audience_type === 'class'" class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.selectClass') }}</span>
                <select v-model="selectedClassId" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3">
                    <option value="">{{ t('admin.chooseClass') }}</option>
                    <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                </select>
            </label>
            <template v-if="form.audience_type === 'section'">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">{{ t('admin.selectClass') }}</span>
                    <select v-model="selectedClassId" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3">
                        <option value="">{{ t('admin.chooseClass') }}</option>
                        <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">{{ t('admin.selectSection') }}</span>
                    <select v-model="selectedSectionId" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3">
                        <option value="">{{ t('admin.chooseSection') }}</option>
                        <option v-for="section in sectionOptions" :key="section.id" :value="section.id">{{ section.name }}</option>
                    </select>
                </label>
            </template>

            <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
            <p v-if="saved" class="rounded-xl bg-green-50 px-3 py-2 text-sm text-green-800">{{ t('admin.noticeSaved') }}</p>

            <button type="submit" class="w-full rounded-xl bg-blue-700 py-3 font-semibold text-white" :disabled="submitting">
                {{ submitting ? t('common.loading') : t('admin.saveNotice') }}
            </button>
            <router-link :to="{ name: 'notices' }" class="block text-center text-sm font-semibold text-blue-800 underline">
                {{ t('admin.backToNotices') }}
            </router-link>
        </form>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';

const props = defineProps({ id: { type: [String, Number], required: true } });

const { t } = useI18n();
const route = useRoute();
const { activeSchoolId } = useSchoolContext();

const noticeId = computed(() => props.id ?? route.params.id);
const loading = ref(true);
const submitting = ref(false);
const error = ref('');
const saved = ref(false);
const classes = ref([]);
const selectedClassId = ref('');
const selectedSectionId = ref('');
const form = ref(null);

const sectionOptions = computed(() => {
    const cls = classes.value.find((item) => String(item.id) === String(selectedClassId.value));
    return cls?.sections ?? [];
});

function buildAudiences() {
    if (form.value.audience_type === 'class' && selectedClassId.value) {
        return [{ school_class_id: Number(selectedClassId.value) }];
    }
    if (form.value.audience_type === 'section' && selectedSectionId.value) {
        return [{ section_id: Number(selectedSectionId.value) }];
    }
    return [];
}

async function load() {
    loading.value = true;
    try {
        const [{ data: noticeData }, { data: classData }] = await Promise.all([
            axios.get(`/api/notices/${noticeId.value}`),
            axios.get('/api/admin/classes', { params: { school_id: activeSchoolId.value } }),
        ]);
        classes.value = classData.classes;
        const n = noticeData.notice;
        form.value = {
            title: n.title,
            body: n.body,
            title_en: n.title_en ?? '',
            body_en: n.body_en ?? '',
            priority: n.priority,
            audience_type: n.audience_type,
        };
        const audience = n.audiences?.[0];
        if (audience?.school_class_id) {
            selectedClassId.value = String(audience.school_class_id);
        }
        if (audience?.section_id) {
            selectedSectionId.value = String(audience.section_id);
            const section = classes.value.flatMap((c) => c.sections ?? []).find((s) => s.id === audience.section_id);
            if (section) {
                const cls = classes.value.find((c) => (c.sections ?? []).some((s) => s.id === section.id));
                if (cls) selectedClassId.value = String(cls.id);
            }
        }
    } finally {
        loading.value = false;
    }
}

async function save() {
    error.value = '';
    saved.value = false;
    submitting.value = true;
    try {
        await axios.put(`/api/notices/${noticeId.value}`, {
            ...form.value,
            title_en: form.value.title_en || null,
            body_en: form.value.body_en || null,
            audiences: buildAudiences(),
        });
        saved.value = true;
    } catch (err) {
        error.value = err?.response?.data?.message ?? t('common.error');
    } finally {
        submitting.value = false;
    }
}

onMounted(load);
</script>
