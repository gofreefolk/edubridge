import { onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';
import { useSchoolContext } from '@/composables/useSchoolContext';

const summary = ref(null);
const loading = ref(false);
let pollTimer = null;

export function useNotifications() {
    const { isAuthenticated } = useAuth();
    const { activeSchoolId } = useSchoolContext();

    async function refresh() {
        if (!isAuthenticated.value || !activeSchoolId.value) {
            summary.value = null;
            return;
        }

        loading.value = true;
        try {
            const { data } = await axios.get('/api/comms/summary', {
                params: { school_id: activeSchoolId.value },
            });
            summary.value = data;
        } catch {
            summary.value = null;
        } finally {
            loading.value = false;
        }
    }

    function startPolling(intervalMs = 60000) {
        stopPolling();
        pollTimer = setInterval(refresh, intervalMs);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    watch([isAuthenticated, activeSchoolId], () => {
        refresh();
    });

    onMounted(() => {
        refresh();
        startPolling();
    });

    onUnmounted(stopPolling);

    return {
        summary,
        loading,
        refresh,
    };
}
