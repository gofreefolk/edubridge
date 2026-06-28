<template>
    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('admin.classesTitle') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ t('admin.classesSubtitle') }}</p>
        </div>

        <form class="flex gap-2 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="addClass">
            <input
                v-model="newClassName"
                type="text"
                required
                :placeholder="t('admin.newClassPlaceholder')"
                class="min-w-0 flex-1 rounded-xl border border-slate-300 px-4 py-3"
            />
            <button type="submit" class="shrink-0 rounded-xl bg-blue-700 px-4 py-3 font-semibold text-white" :disabled="addingClass">
                +
            </button>
        </form>

        <p v-if="error" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
            {{ error }}
        </p>

        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>

        <div v-else-if="!classes.length" class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-600">
            {{ t('admin.noClasses') }}
        </div>

        <div v-for="cls in classes" :key="cls.id" class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="flex items-center gap-2">
                <input
                    v-if="editingClassId === cls.id"
                    v-model="editClassName"
                    type="text"
                    class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2"
                    @keyup.enter="saveClass(cls)"
                />
                <p v-else class="flex-1 text-lg font-semibold text-slate-900">{{ cls.name }}</p>
                <button type="button" class="text-sm text-blue-800" @click="startEditClass(cls)">
                    {{ editingClassId === cls.id ? t('common.save') : t('common.edit') }}
                </button>
                <button v-if="editingClassId === cls.id" type="button" class="text-sm text-slate-500" @click="editingClassId = null">
                    {{ t('common.cancel') }}
                </button>
                <button type="button" class="text-sm text-red-700" @click="removeClass(cls)">{{ t('common.delete') }}</button>
            </div>

            <ul class="mt-3 space-y-2">
                <li v-for="section in cls.sections" :key="section.id" class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2">
                    <input
                        v-if="editingSectionId === section.id"
                        v-model="editSectionName"
                        type="text"
                        class="min-w-0 flex-1 rounded border border-slate-300 px-2 py-1 text-sm"
                        @keyup.enter="saveSection(section)"
                    />
                    <span v-else class="flex-1 text-sm text-slate-800">{{ section.name }}</span>
                    <button type="button" class="text-xs text-blue-800" @click="startEditSection(section)">
                        {{ editingSectionId === section.id ? t('common.save') : t('common.edit') }}
                    </button>
                    <button type="button" class="text-xs text-red-700" @click="removeSection(section)">{{ t('common.delete') }}</button>
                </li>
            </ul>

            <form class="mt-3 flex gap-2" @submit.prevent="addSection(cls)">
                <input
                    v-model="newSectionNames[cls.id]"
                    type="text"
                    :placeholder="t('admin.newSectionPlaceholder')"
                    class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
                <button type="submit" class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-800">+</button>
            </form>
        </div>
    </section>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t } = useI18n();
const { activeSchoolId } = useSchoolContext();

const loading = ref(false);
const classes = ref([]);
const newClassName = ref('');
const newSectionNames = reactive({});
const addingClass = ref(false);
const error = ref('');
const editingClassId = ref(null);
const editClassName = ref('');
const editingSectionId = ref(null);
const editSectionName = ref('');

async function load() {
    if (!activeSchoolId.value) return;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/api/admin/classes', { params: { school_id: activeSchoolId.value } });
        classes.value = data.classes;
    } finally {
        loading.value = false;
    }
}

async function addClass() {
    addingClass.value = true;
    error.value = '';
    try {
        await axios.post('/api/admin/classes', {
            school_id: activeSchoolId.value,
            name: newClassName.value,
        });
        newClassName.value = '';
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    } finally {
        addingClass.value = false;
    }
}

function startEditClass(cls) {
    if (editingClassId.value === cls.id) {
        saveClass(cls);
        return;
    }
    editingClassId.value = cls.id;
    editClassName.value = cls.name;
}

async function saveClass(cls) {
    try {
        await axios.put(`/api/admin/classes/${cls.id}`, {
            school_id: activeSchoolId.value,
            name: editClassName.value,
        });
        editingClassId.value = null;
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

async function removeClass(cls) {
    if (!window.confirm(t('admin.confirmDeleteClass'))) return;
    try {
        await axios.delete(`/api/admin/classes/${cls.id}`, { data: { school_id: activeSchoolId.value } });
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

async function addSection(cls) {
    const name = newSectionNames[cls.id]?.trim();
    if (!name) return;
    try {
        await axios.post(`/api/admin/classes/${cls.id}/sections`, {
            school_id: activeSchoolId.value,
            name,
        });
        newSectionNames[cls.id] = '';
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

function startEditSection(section) {
    if (editingSectionId.value === section.id) {
        saveSection(section);
        return;
    }
    editingSectionId.value = section.id;
    editSectionName.value = section.name;
}

async function saveSection(section) {
    try {
        await axios.put(`/api/admin/sections/${section.id}`, {
            school_id: activeSchoolId.value,
            name: editSectionName.value,
        });
        editingSectionId.value = null;
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

async function removeSection(section) {
    if (!window.confirm(t('admin.confirmDeleteSection'))) return;
    try {
        await axios.delete(`/api/admin/sections/${section.id}`, { data: { school_id: activeSchoolId.value } });
        await load();
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

watch(activeSchoolId, load);
onMounted(load);
</script>
