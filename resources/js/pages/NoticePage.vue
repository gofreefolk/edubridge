<template>
    <section class="space-y-4">
        <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-slate-600">
            {{ t('common.loading') }}
        </div>

        <div
            v-else-if="error"
            class="rounded-2xl border border-red-200 bg-red-50 p-6 text-center text-red-800"
        >
            <p class="text-lg font-semibold">{{ t('notice.notFoundTitle') }}</p>
            <p class="mt-2 text-base">{{ error }}</p>
            <router-link
                :to="{ name: 'home' }"
                class="mt-4 inline-block rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white"
            >
                {{ t('notice.goHome') }}
            </router-link>
        </div>

        <template v-else-if="notice">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm font-medium text-blue-800">{{ notice.school.name }}</p>
                <p
                    v-if="notice.priority === 'urgent'"
                    class="mt-2 text-sm font-semibold uppercase tracking-wide text-red-700"
                >
                    {{ t('home.urgent') }}
                </p>
                <h1 class="mt-2 text-2xl font-bold leading-snug text-slate-900">
                    {{ localizedTitle }}
                </h1>
                <p class="mt-2 text-sm text-slate-500">
                    {{ formattedDate }}
                </p>
            </div>

            <article
                class="rounded-2xl border border-slate-200 bg-white p-5 text-lg leading-relaxed text-slate-800 whitespace-pre-wrap"
            >
                {{ localizedBody }}
            </article>
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';

const props = defineProps({
    token: {
        type: String,
        required: true,
    },
});

const { t, locale } = useI18n();
const { isAuthenticated, initialized, fetchUser } = useAuth();
const route = useRoute();
const router = useRouter();

const loading = ref(true);
const error = ref('');
const notice = ref(null);

const localizedTitle = computed(() => {
    if (!notice.value) {
        return '';
    }

    if (locale.value === 'en' && notice.value.title_en) {
        return notice.value.title_en;
    }

    return notice.value.title;
});

const localizedBody = computed(() => {
    if (!notice.value) {
        return '';
    }

    if (locale.value === 'en' && notice.value.body_en) {
        return notice.value.body_en;
    }

    return notice.value.body;
});

const formattedDate = computed(() => {
    if (!notice.value?.published_at) {
        return '';
    }

    return new Date(notice.value.published_at).toLocaleString(
        locale.value === 'ml' ? 'ml-IN' : 'en-IN',
        { dateStyle: 'medium', timeStyle: 'short' },
    );
});

async function ensureAuth() {
    if (!initialized.value) {
        await fetchUser();
    }

    if (!isAuthenticated.value) {
        await router.replace({
            name: 'login',
            query: { redirect: route.fullPath },
        });
        return false;
    }

    return true;
}

async function loadNotice() {
    loading.value = true;
    error.value = '';
    notice.value = null;

    const authed = await ensureAuth();
    if (!authed) {
        loading.value = false;
        return;
    }

    try {
        const { data } = await axios.get(`/api/notices/magic/${props.token}`);
        notice.value = data.notice;
        await axios.post(`/api/notices/magic/${props.token}/read`).catch(() => {});
    } catch (err) {
        error.value = err?.response?.data?.message ?? t('notice.notFoundTitle');
    } finally {
        loading.value = false;
    }
}

watch(isAuthenticated, (value) => {
    if (value && route.name === 'notice-magic') {
        loadNotice();
    }
});

onMounted(loadNotice);
</script>
