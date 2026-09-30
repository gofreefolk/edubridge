<template>
    <section class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <h1 class="text-2xl font-bold text-slate-900">{{ t('ops.attendanceTitle') }}</h1>
            <router-link :to="{ name: 'attendance-report' }" class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800">
                📊 {{ t('ops.viewReport') }}
            </router-link>
        </div>

        <div class="grid grid-cols-1 gap-2 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-3">
            <template v-if="isAdmin">
                <select v-model="classId" class="rounded-xl border border-slate-300 px-3 py-2">
                    <option :value="null">{{ t('admin.chooseClass') }}</option>
                    <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                </select>
                <select v-model="sectionId" class="rounded-xl border border-slate-300 px-3 py-2">
                    <option :value="null">{{ t('ops.allSections') }}</option>
                    <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </template>
            <input v-model="date" type="date" :max="todayIso" class="rounded-xl border border-slate-300 px-3 py-2" :class="isAdmin ? '' : 'sm:col-span-3'" />
        </div>

        <p v-if="!classId" class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-600">{{ t('teacher.noClass') }}</p>
        <div v-else-if="loading" class="text-center text-slate-600">{{ t('common.loading') }}</div>

        <template v-else-if="students.length">
            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span class="text-slate-600">
                    {{ t('ops.present') }} {{ counts.present }} · {{ t('ops.absent') }} {{ counts.absent }} · {{ t('ops.notMarked') }} {{ counts.unmarked }}
                </span>
                <button type="button" class="rounded-lg border border-green-300 bg-green-50 px-3 py-1.5 font-semibold text-green-800" @click="markAllPresent">
                    ✓ {{ t('ops.markAll') }}
                </button>
            </div>

            <div v-for="s in students" :key="s.id" class="rounded-xl border border-slate-200 bg-white p-3">
                <div class="flex items-center justify-between gap-2">
                    <router-link :to="{ name: 'student-profile', params: { id: s.id } }" class="font-medium text-slate-900">
                        {{ s.name }}
                    </router-link>
                    <span v-if="s.absence_alert_sent" class="text-xs text-amber-700">📲 {{ t('ops.alertSent') }}</span>
                </div>
                <div class="mt-2 grid grid-cols-4 gap-1">
                    <button
                        v-for="st in statuses"
                        :key="st.value"
                        type="button"
                        class="rounded-lg border py-2 text-xs font-semibold"
                        :class="s.status === st.value ? st.active : 'border-slate-200 text-slate-600'"
                        @click="s.status = st.value"
                    >
                        {{ t(`ops.${st.value}`) }}
                    </button>
                </div>
            </div>

            <p v-if="message" class="rounded-xl bg-green-50 px-3 py-2 text-sm text-green-800">{{ message }}</p>
            <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

            <button type="button" class="sticky bottom-20 w-full rounded-xl bg-blue-700 py-3 text-base font-semibold text-white shadow-lg disabled:opacity-60" :disabled="saving || !counts.marked" @click="save">
                {{ saving ? t('common.loading') : t('ops.saveAttendance') }}
            </button>
        </template>

        <p v-else class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-600">{{ t('admin.noStudents') }}</p>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useClassPicker } from '@/composables/useClassPicker';

const { t } = useI18n();
const { classes, sections, classId, sectionId, isAdmin, loadClasses } = useClassPicker();

const statuses = [
    { value: 'present', active: 'border-green-600 bg-green-600 text-white' },
    { value: 'absent', active: 'border-red-600 bg-red-600 text-white' },
    { value: 'late', active: 'border-amber-500 bg-amber-500 text-white' },
    { value: 'excused', active: 'border-slate-600 bg-slate-600 text-white' },
];

const todayIso = new Date().toISOString().slice(0, 10);
const date = ref(todayIso);
const students = ref([]);
const schoolId = ref(null);
const loading = ref(false);
const saving = ref(false);
const message = ref('');
const error = ref('');

const counts = computed(() => {
    const present = students.value.filter((s) => s.status === 'present' || s.status === 'late').length;
    const absent = students.value.filter((s) => s.status === 'absent').length;
    const marked = students.value.filter((s) => s.status).length;
    return { present, absent, marked, unmarked: students.value.length - marked };
});

async function load() {
    message.value = '';
    error.value = '';
    if (!classId.value) {
        students.value = [];
        return;
    }
    loading.value = true;
    try {
        const { data } = await axios.get('/api/attendance/sheet', {
            params: { school_class_id: classId.value, section_id: sectionId.value || undefined, date: date.value },
        });
        students.value = data.students;
        schoolId.value = data.school_id;
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
        students.value = [];
    } finally {
        loading.value = false;
    }
}

function markAllPresent() {
    for (const s of students.value) {
        if (!s.status) s.status = 'present';
    }
}

async function save() {
    saving.value = true;
    message.value = '';
    error.value = '';
    try {
        const { data } = await axios.post('/api/teacher/attendance', {
            school_id: schoolId.value,
            date: date.value,
            records: students.value.filter((s) => s.status).map((s) => ({ student_id: s.id, status: s.status })),
        });
        await load(); // refresh "parent alerted" badges; load() clears the message
        message.value = t('ops.attendanceSaved', { count: data.absence_alerts_queued ?? 0 });
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    } finally {
        saving.value = false;
    }
}

watch([classId, sectionId, date], load);
onMounted(async () => {
    await loadClasses();
    await load();
});
</script>
