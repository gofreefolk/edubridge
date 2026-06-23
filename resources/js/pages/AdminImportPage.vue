<template>
    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('admin.importTitle') }}</h1>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ t('admin.importSubtitle') }}</p>
        </div>

        <a
            :href="sampleUrl"
            download
            class="flex items-center justify-center gap-2 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-800"
        >
            ⬇️ {{ t('admin.downloadSample') }}
        </a>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-700">
            <p class="font-semibold text-slate-900">{{ t('admin.csvColumns') }}</p>
            <p class="mt-2 font-mono text-xs leading-relaxed">
                student_name, admission_number, class, section, parent_name, parent_phone, relationship
            </p>
        </div>

        <form class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="upload">
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ t('admin.selectCsv') }}</span>
                <input
                    ref="fileInput"
                    type="file"
                    accept=".csv,text/csv"
                    class="mt-2 w-full text-sm"
                    required
                    @change="onFileChange"
                />
            </label>

            <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
            <p v-if="success" class="rounded-xl bg-green-50 px-3 py-2 text-sm text-green-800">{{ success }}</p>

            <ul v-if="errors.length" class="space-y-1 text-sm text-amber-800">
                <li v-for="item in errors" :key="item.line">
                    {{ t('admin.lineError', { line: item.line, message: item.message }) }}
                </li>
            </ul>

            <button
                type="submit"
                class="w-full rounded-xl bg-blue-700 py-3 text-base font-semibold text-white disabled:opacity-60"
                :disabled="uploading || !selectedFile"
            >
                {{ uploading ? t('common.loading') : t('admin.uploadCsv') }}
            </button>
        </form>
    </section>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t } = useI18n();
const { activeSchoolId } = useSchoolContext();

const sampleUrl = '/api/admin/import/sample.csv';
const fileInput = ref(null);
const selectedFile = ref(null);
const uploading = ref(false);
const error = ref('');
const success = ref('');
const errors = ref([]);

function onFileChange(event) {
    selectedFile.value = event.target.files?.[0] ?? null;
    error.value = '';
    success.value = '';
    errors.value = [];
}

async function upload() {
    if (!selectedFile.value || !activeSchoolId.value) {
        return;
    }

    uploading.value = true;
    error.value = '';
    success.value = '';
    errors.value = [];

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    formData.append('school_id', activeSchoolId.value);

    try {
        const { data } = await axios.post('/api/admin/import/parents', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        success.value = data.message;
        errors.value = data.errors ?? [];
        if (fileInput.value) {
            fileInput.value.value = '';
        }
        selectedFile.value = null;
    } catch (err) {
        error.value = err?.response?.data?.message ?? t('auth.genericError');
    } finally {
        uploading.value = false;
    }
}
</script>
