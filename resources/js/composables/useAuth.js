import { computed, ref } from 'vue';
import axios from 'axios';

const user = ref(null);
const loading = ref(false);
const initialized = ref(false);

export function useAuth() {
    const isAuthenticated = computed(() => user.value !== null);

    async function fetchUser() {
        try {
            const { data } = await axios.get('/api/auth/me');
            user.value = data.user;
        } catch {
            user.value = null;
        } finally {
            initialized.value = true;
        }
    }

    async function requestOtp(phone) {
        loading.value = true;
        try {
            await axios.post('/api/auth/otp/request', { phone });
        } finally {
            loading.value = false;
        }
    }

    async function verifyOtp(phone, code) {
        loading.value = true;
        try {
            const { data } = await axios.post('/api/auth/otp/verify', { phone, code });
            user.value = data.user;
            return data.user;
        } finally {
            loading.value = false;
        }
    }

    async function logout() {
        loading.value = true;
        try {
            await axios.post('/api/auth/logout');
            user.value = null;
        } finally {
            loading.value = false;
        }
    }

    return {
        user,
        loading,
        initialized,
        isAuthenticated,
        fetchUser,
        requestOtp,
        verifyOtp,
        logout,
    };
}

export async function initAuth() {
    const { fetchUser } = useAuth();
    await fetchUser();
}
