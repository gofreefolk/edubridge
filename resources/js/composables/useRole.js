import { computed, ref, watch } from 'vue';
import { useAuth } from '@/composables/useAuth';
import {
    ROLE_NAVIGATION,
    ROUTE_ROLES,
    resolvePrimaryRole,
} from '@/config/roleNavigation';

const STORAGE_KEY = 'edubridge_active_role';
const activeRole = ref(null);

function loadStoredRole() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

function storeRole(role) {
    try {
        if (role) {
            localStorage.setItem(STORAGE_KEY, role);
        } else {
            localStorage.removeItem(STORAGE_KEY);
        }
    } catch {
        // ignore
    }
}

export function useRole() {
    const { user, isAuthenticated } = useAuth();

    const availableRoles = computed(() => user.value?.roles ?? []);

    function syncActiveRole() {
        if (!isAuthenticated.value || !availableRoles.value.length) {
            activeRole.value = null;
            storeRole(null);
            return;
        }

        const stored = loadStoredRole();
        if (stored && availableRoles.value.includes(stored)) {
            activeRole.value = stored;
            return;
        }

        activeRole.value = user.value?.primary_role ?? resolvePrimaryRole(availableRoles.value);
        storeRole(activeRole.value);
    }

    watch([user, isAuthenticated], syncActiveRole, { immediate: true });

    const navigationItems = computed(() => {
        if (!activeRole.value) {
            return [];
        }

        return ROLE_NAVIGATION[activeRole.value] ?? ROLE_NAVIGATION.parent;
    });

    const hasMultipleRoles = computed(() => availableRoles.value.length > 1);

    function setActiveRole(role) {
        if (!availableRoles.value.includes(role)) {
            return;
        }
        activeRole.value = role;
        storeRole(role);
    }

    function canAccessRoute(routeName) {
        const allowed = ROUTE_ROLES[routeName];

        if (allowed === null || allowed === undefined) {
            return true;
        }

        if (!isAuthenticated.value) {
            return routeName === 'home' || routeName === 'login' || routeName === 'notice-magic';
        }

        if (!activeRole.value) {
            return false;
        }

        return allowed.includes(activeRole.value);
    }

    function defaultRouteForRole() {
        const items = navigationItems.value;
        return items[0]?.route ?? 'home';
    }

    return {
        activeRole,
        availableRoles,
        navigationItems,
        hasMultipleRoles,
        setActiveRole,
        canAccessRoute,
        defaultRouteForRole,
        syncActiveRole,
    };
}
