<template>
    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('admin.studentsTitle') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ t('admin.studentsSubtitle') }}</p>
        </div>

        <form class="grid grid-cols-1 gap-2 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2" @submit.prevent="search">
            <input
                v-model="filters.q"
                type="search"
                :placeholder="t('admin.searchStudents')"
                class="rounded-xl border border-slate-300 px-4 py-3 sm:col-span-2"
            />
            <select v-model="filters.school_class_id" class="rounded-xl border border-slate-300 px-4 py-3">
                <option value="">{{ t('admin.allClasses') }}</option>
                <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
            </select>
            <select v-model="filters.status" class="rounded-xl border border-slate-300 px-4 py-3">
                <option value="">{{ t('admin.allStatuses') }}</option>
                <option value="active">{{ t('admin.statusActive') }}</option>
                <option value="transferred">{{ t('admin.statusTransferred') }}</option>
                <option value="graduated">{{ t('admin.statusGraduated') }}</option>
                <option value="alumni">{{ t('admin.statusAlumni') }}</option>
            </select>
            <button type="submit" class="rounded-xl bg-blue-700 py-3 font-semibold text-white sm:col-span-2">
                {{ t('admin.search') }}
            </button>
        </form>

        <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
        <div v-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>
        <p v-else-if="!students.length" class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-600">{{ t('admin.noStudents') }}</p>

        <div v-for="student in students" :key="student.id" class="rounded-2xl border border-slate-200 bg-white p-4">
            <button type="button" class="w-full text-left" @click="toggleStudent(student)">
                <p class="font-semibold text-slate-900">{{ student.name }}</p>
                <p class="text-sm text-slate-500">
                    {{ student.class || '—' }} {{ student.section ? `· ${student.section}` : '' }}
                    · {{ student.admission_number || t('admin.noAdmission') }}
                </p>
                <p class="text-xs uppercase text-slate-400">{{ student.status }}</p>
            </button>

            <div v-if="expandedId === student.id" class="mt-4 space-y-4 border-t border-slate-100 pt-4">
                <form class="space-y-2" @submit.prevent="saveStudent(student)">
                    <input v-model="editForm.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    <input v-model="editForm.admission_number" type="text" :placeholder="t('admin.admissionNumber')" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    <select v-model="editForm.school_class_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option :value="null">{{ t('admin.chooseClass') }}</option>
                        <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                    </select>
                    <select v-model="editForm.section_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option :value="null">{{ t('admin.chooseSection') }}</option>
                        <option v-for="section in sectionsForClass(editForm.school_class_id)" :key="section.id" :value="section.id">
                            {{ section.name }}
                        </option>
                    </select>
                    <select v-model="editForm.status" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="active">{{ t('admin.statusActive') }}</option>
                        <option value="transferred">{{ t('admin.statusTransferred') }}</option>
                        <option value="graduated">{{ t('admin.statusGraduated') }}</option>
                        <option value="alumni">{{ t('admin.statusAlumni') }}</option>
                    </select>
                    <button type="submit" class="w-full rounded-lg bg-blue-700 py-2 text-sm font-semibold text-white">{{ t('admin.saveStudent') }}</button>
                </form>

                <div>
                    <h3 class="mb-2 text-sm font-semibold text-slate-800">{{ t('admin.linkedParents') }}</h3>
                    <ul v-if="student.parents?.length" class="mb-3 space-y-2">
                        <li v-for="parent in student.parents" :key="parent.id" class="rounded-lg bg-slate-50 p-3 text-sm">
                            <p class="font-medium">{{ parent.name }} · {{ parent.phone }}</p>
                            <p class="text-slate-500">{{ parent.relationship }}{{ parent.is_primary ? ` · ${t('admin.primaryParent')}` : '' }}</p>
                            <div class="mt-2 flex gap-2">
                                <button type="button" class="text-xs text-blue-800" @click="setPrimary(student, parent)">{{ t('admin.setPrimary') }}</button>
                                <button type="button" class="text-xs text-red-700" @click="detachParent(student, parent)">{{ t('admin.unlinkParent') }}</button>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="mb-3 text-sm text-slate-500">{{ t('admin.noParents') }}</p>

                    <form class="space-y-2 rounded-lg border border-dashed border-slate-200 p-3" @submit.prevent="attachParent(student)">
                        <p class="text-sm font-medium text-slate-700">{{ t('admin.linkParent') }}</p>
                        <input v-model="parentForm.name" type="text" required :placeholder="t('platform.form.adminName')" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <input v-model="parentForm.phone" type="tel" required :placeholder="t('platform.form.adminPhone')" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <select v-model="parentForm.relationship" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="father">{{ t('admin.relFather') }}</option>
                            <option value="mother">{{ t('admin.relMother') }}</option>
                            <option value="guardian">{{ t('admin.relGuardian') }}</option>
                            <option value="grandparent">{{ t('admin.relGrandparent') }}</option>
                            <option value="other">{{ t('admin.relOther') }}</option>
                        </select>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="parentForm.is_primary" type="checkbox" />
                            {{ t('admin.primaryParent') }}
                        </label>
                        <button type="submit" class="w-full rounded-lg bg-slate-800 py-2 text-sm font-semibold text-white">{{ t('admin.linkParent') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div v-if="meta.last_page > 1" class="flex justify-between gap-2">
            <button type="button" class="rounded-xl border px-4 py-2 text-sm" :disabled="page <= 1" @click="goPage(page - 1)">{{ t('admin.prevPage') }}</button>
            <span class="self-center text-sm text-slate-600">{{ page }} / {{ meta.last_page }}</span>
            <button type="button" class="rounded-xl border px-4 py-2 text-sm" :disabled="page >= meta.last_page" @click="goPage(page + 1)">{{ t('admin.nextPage') }}</button>
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
const students = ref([]);
const classes = ref([]);
const page = ref(1);
const meta = ref({ last_page: 1 });
const filters = reactive({ q: '', school_class_id: '', status: '' });
const expandedId = ref(null);
const editForm = ref({});
const parentForm = ref({ name: '', phone: '', relationship: 'father', is_primary: false });
const error = ref('');

function sectionsForClass(classId) {
    return classes.value.find((c) => c.id === Number(classId))?.sections ?? [];
}

async function loadClasses() {
    if (!activeSchoolId.value) return;
    const { data } = await axios.get('/api/admin/classes', { params: { school_id: activeSchoolId.value } });
    classes.value = data.classes;
}

async function loadStudents() {
    if (!activeSchoolId.value) return;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/api/admin/students', {
            params: {
                school_id: activeSchoolId.value,
                page: page.value,
                q: filters.q || undefined,
                school_class_id: filters.school_class_id || undefined,
                status: filters.status || undefined,
            },
        });
        students.value = data.students;
        meta.value = data.meta;
    } finally {
        loading.value = false;
    }
}

function search() {
    page.value = 1;
    loadStudents();
}

function goPage(next) {
    page.value = next;
    loadStudents();
}

function toggleStudent(student) {
    if (expandedId.value === student.id) {
        expandedId.value = null;
        return;
    }
    expandedId.value = student.id;
    editForm.value = {
        name: student.name,
        admission_number: student.admission_number,
        school_class_id: student.school_class_id,
        section_id: student.section_id,
        status: student.status,
    };
    parentForm.value = { name: '', phone: '', relationship: 'father', is_primary: false };
}

async function saveStudent(student) {
    try {
        const { data } = await axios.put(`/api/admin/students/${student.id}`, {
            school_id: activeSchoolId.value,
            ...editForm.value,
        });
        Object.assign(student, data.student);
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

async function attachParent(student) {
    try {
        await axios.post(`/api/admin/students/${student.id}/parents`, {
            school_id: activeSchoolId.value,
            ...parentForm.value,
        });
        const { data } = await axios.get(`/api/admin/students/${student.id}`, {
            params: { school_id: activeSchoolId.value },
        });
        Object.assign(student, data.student);
        parentForm.value = { name: '', phone: '', relationship: 'father', is_primary: false };
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

async function setPrimary(student, parent) {
    try {
        const { data } = await axios.put(`/api/admin/students/${student.id}/parents/${parent.id}`, {
            school_id: activeSchoolId.value,
            relationship: parent.relationship,
            is_primary: true,
        });
        Object.assign(student, data.student);
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

async function detachParent(student, parent) {
    if (!window.confirm(t('admin.confirmUnlinkParent'))) return;
    try {
        await axios.delete(`/api/admin/students/${student.id}/parents/${parent.id}`, {
            data: { school_id: activeSchoolId.value },
        });
        student.parents = student.parents.filter((p) => p.id !== parent.id);
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

watch(activeSchoolId, () => {
    loadClasses();
    loadStudents();
});
onMounted(() => {
    loadClasses();
    loadStudents();
});
</script>
