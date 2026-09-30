<template>
    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-medium text-slate-500">{{ t('roles.school_admin') }}</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ t('admin.homeTitle') }}</h1>
            <p class="mt-2 text-slate-600">{{ schoolName }}</p>
        </div>

        <div>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ t('dashboard.today') }}</h2>
            <div v-if="loading && !summary" class="text-center text-slate-600">{{ t('common.loading') }}</div>
            <p v-else-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

            <div v-else-if="summary" class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <router-link :to="{ name: 'teacher-class' }" class="rounded-2xl border bg-white p-3" :class="summary.attendance.unmarked_classes.length ? 'border-amber-300' : 'border-slate-200'">
                        <p class="text-2xl font-bold text-slate-900">
                            {{ summary.attendance.percent ?? '—' }}<span v-if="summary.attendance.percent !== null" class="text-base">%</span>
                        </p>
                        <p class="text-xs text-slate-500">{{ t('dashboard.attendance') }}</p>
                        <p class="mt-1 text-xs text-slate-600">
                            {{ t('dashboard.markedOf', { marked: summary.attendance.marked, total: summary.attendance.students }) }}
                        </p>
                        <p v-if="summary.attendance.absent" class="text-xs font-semibold text-red-700">
                            {{ t('dashboard.absentToday', { count: summary.attendance.absent }) }}
                        </p>
                    </router-link>

                    <router-link :to="{ name: 'notices' }" class="rounded-2xl border border-slate-200 bg-white p-3">
                        <p class="text-2xl font-bold text-slate-900">
                            {{ summary.notices.read_percent ?? '—' }}<span v-if="summary.notices.read_percent !== null" class="text-base">%</span>
                        </p>
                        <p class="text-xs text-slate-500">{{ t('dashboard.noticeReads') }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ t('dashboard.noticesThisWeek', { count: summary.notices.published_recent }) }}</p>
                        <p v-if="summary.notices.scheduled" class="text-xs text-slate-600">{{ t('dashboard.scheduled', { count: summary.notices.scheduled }) }}</p>
                    </router-link>

                    <router-link :to="{ name: 'messages' }" class="rounded-2xl border bg-white p-3" :class="summary.feedback.open ? 'border-amber-300' : 'border-slate-200'">
                        <p class="text-2xl font-bold text-slate-900">{{ summary.feedback.open + summary.feedback.acknowledged }}</p>
                        <p class="text-xs text-slate-500">{{ t('dashboard.openFeedback') }}</p>
                        <p v-if="summary.feedback.oldest_open_days !== null" class="mt-1 text-xs text-slate-600">
                            {{ t('dashboard.oldestDays', { days: summary.feedback.oldest_open_days }) }}
                        </p>
                    </router-link>

                    <router-link :to="{ name: 'checklists' }" class="rounded-2xl border bg-white p-3" :class="summary.checklists.pending.length ? 'border-amber-300' : 'border-slate-200'">
                        <p class="text-2xl font-bold text-slate-900">{{ summary.checklists.done }}/{{ summary.checklists.total }}</p>
                        <p class="text-xs text-slate-500">{{ t('dashboard.checklistsDone') }}</p>
                    </router-link>

                    <router-link :to="{ name: 'centre-logs' }" class="rounded-2xl border bg-white p-3" :class="summary.centre_logs.incidents_recent ? 'border-red-200' : 'border-slate-200'">
                        <p class="text-2xl font-bold" :class="summary.centre_logs.incidents_recent ? 'text-red-700' : 'text-slate-900'">
                            {{ summary.centre_logs.incidents_recent }}
                        </p>
                        <p class="text-xs text-slate-500">{{ t('dashboard.incidentsWeek') }}</p>
                        <p v-if="summary.centre_logs.health_recent" class="mt-1 text-xs text-slate-600">
                            {{ t('dashboard.healthWeek', { count: summary.centre_logs.health_recent }) }}
                        </p>
                    </router-link>

                    <div class="rounded-2xl border border-slate-200 bg-white p-3">
                        <p class="text-2xl font-bold text-slate-900">
                            {{ summary.adoption.percent ?? '—' }}<span v-if="summary.adoption.percent !== null" class="text-base">%</span>
                        </p>
                        <p class="text-xs text-slate-500">{{ t('dashboard.adoption') }}</p>
                        <p class="mt-1 text-xs text-slate-600">
                            {{ t('dashboard.adoptionDetail', { readers: summary.adoption.readers, parents: summary.adoption.parents }) }}
                        </p>
                    </div>
                </div>

                <p v-if="summary.attendance.unmarked_classes.length" class="rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    {{ t('dashboard.unmarkedClasses', { classes: summary.attendance.unmarked_classes.join(', ') }) }}
                </p>
                <p v-if="summary.checklists.pending.length" class="rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    {{ t('dashboard.pendingChecklists', { names: summary.checklists.pending.map((c) => c.name).join(', ') }) }}
                </p>
                <p v-if="summary.delivery.failed" class="rounded-2xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    {{ t('dashboard.deliveryFailed', { count: summary.delivery.failed }) }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <router-link
                :to="{ name: 'notices' }"
                class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
            >
                📋 {{ t('nav.notices') }}
            </router-link>
            <router-link
                :to="{ name: 'calendar' }"
                class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
            >
                📅 {{ t('nav.calendar') }}
            </router-link>
            <router-link
                :to="{ name: 'admin-notice-create' }"
                class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
            >
                ✏️ {{ t('admin.createNoticeShort') }}
            </router-link>
            <router-link
                :to="{ name: 'admin-import' }"
                class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
            >
                📥 {{ t('admin.importShort') }}
            </router-link>
        </div>

        <div>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ t('ops.dailyOpsTitle') }}</h2>
            <div class="grid grid-cols-2 gap-3">
                <router-link :to="{ name: 'teacher-class' }" class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800">
                    🗓️ {{ t('ops.attendanceTitle') }}
                </router-link>
                <router-link :to="{ name: 'attendance-report' }" class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800">
                    📊 {{ t('ops.reportTitle') }}
                </router-link>
                <router-link :to="{ name: 'centre-logs' }" class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800">
                    📓 {{ t('ops.logsTitle') }}
                </router-link>
                <router-link :to="{ name: 'checklists' }" class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800">
                    ✅ {{ t('ops.checklistsTitle') }}
                </router-link>
            </div>
        </div>

        <div>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ t('admin.setupTitle') }}</h2>
            <div class="grid grid-cols-2 gap-3">
                <router-link
                    :to="{ name: 'admin-school' }"
                    class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
                >
                    🏫 {{ t('admin.schoolProfileShort') }}
                </router-link>
                <router-link
                    :to="{ name: 'admin-classes' }"
                    class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
                >
                    📚 {{ t('admin.classesShort') }}
                </router-link>
                <router-link
                    :to="{ name: 'admin-staff' }"
                    class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
                >
                    👥 {{ t('admin.staffShort') }}
                </router-link>
                <router-link
                    :to="{ name: 'admin-students' }"
                    class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
                >
                    🎒 {{ t('admin.studentsShort') }}
                </router-link>
                <router-link
                    :to="{ name: 'smc' }"
                    class="rounded-2xl border border-slate-200 bg-white p-4 text-center font-semibold text-blue-800"
                >
                    🏛️ {{ t('nav.smc') }}
                </router-link>
            </div>
        </div>

        <p class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            {{ t('admin.publishHint') }}
        </p>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';

const { t } = useI18n();
const { schools, activeSchoolId } = useSchoolContext();

const schoolName = computed(() => schools.value.find((s) => s.id === activeSchoolId.value)?.name ?? schools.value[0]?.name ?? '');

const summary = ref(null);
const loading = ref(false);
const error = ref('');

async function load() {
    if (!activeSchoolId.value) return;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/api/admin/dashboard', { params: { school_id: activeSchoolId.value } });
        summary.value = data;
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    } finally {
        loading.value = false;
    }
}

watch(activeSchoolId, load);
onMounted(load);
</script>
