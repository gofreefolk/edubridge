import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';
import { useRole } from '@/composables/useRole';
import { useSchoolContext } from '@/composables/useSchoolContext';

/**
 * Class / section selection for staff screens. School admins choose from every class
 * in the school; teachers are pinned to the class they are assigned to.
 */
export function useClassPicker() {
    const { user } = useAuth();
    const { activeRole } = useRole();
    const { activeSchoolId } = useSchoolContext();

    const classes = ref([]);
    const classId = ref(null);
    const sectionId = ref(null);

    const isAdmin = computed(() => ['school_admin', 'super_admin'].includes(activeRole.value));

    const sections = computed(() => classes.value.find((c) => c.id === Number(classId.value))?.sections ?? []);

    async function loadClasses() {
        if (isAdmin.value && activeSchoolId.value) {
            const { data } = await axios.get('/api/admin/classes', { params: { school_id: activeSchoolId.value } });
            classes.value = data.classes;
            if (!classes.value.some((c) => c.id === classId.value)) {
                classId.value = classes.value[0]?.id ?? null;
                sectionId.value = null;
            }
            return;
        }

        classes.value = [];
        classId.value = user.value?.teacher_class_id ?? null;
        sectionId.value = user.value?.teacher_section_id ?? null;
    }

    watch(classId, (next, prev) => {
        if (prev !== null && next !== prev && isAdmin.value) {
            sectionId.value = null;
        }
    });

    watch([activeSchoolId, activeRole], loadClasses);

    return { classes, sections, classId, sectionId, isAdmin, loadClasses };
}
