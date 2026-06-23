import { computed, ref, watch } from 'vue';
import { useAuth } from '@/composables/useAuth';

const activeSchoolId = ref(null);
const activeStudentId = ref(null);

export function useSchoolContext() {
    const { user } = useAuth();

    const schools = computed(() => user.value?.schools ?? []);
    const children = computed(() => user.value?.children ?? []);

    function initFromUser() {
        if (!activeSchoolId.value && schools.value.length) {
            activeSchoolId.value = schools.value[0].id;
        }
        if (!activeStudentId.value && children.value.length) {
            const child = children.value.find((c) => c.school_id === activeSchoolId.value) ?? children.value[0];
            activeStudentId.value = child?.id ?? null;
            if (child?.school_id) {
                activeSchoolId.value = child.school_id;
            }
        }
    }

    watch(user, initFromUser, { immediate: true });

    return {
        activeSchoolId,
        activeStudentId,
        schools,
        children,
        initFromUser,
    };
}
