<template>
    <component :is="homeComponent" />
</template>

<script setup>
import { computed } from 'vue';
import { useAuth } from '@/composables/useAuth';
import { useRole } from '@/composables/useRole';
import GuestHomePage from '@/pages/GuestHomePage.vue';
import ParentHomePage from '@/pages/HomePage.vue';
import AdminHomePage from '@/pages/AdminHomePage.vue';
import TeacherHomePage from '@/pages/TeacherHomePage.vue';
import StudentHomePage from '@/pages/StudentHomePage.vue';
import AlumniHomePage from '@/pages/AlumniHomePage.vue';
import DriverHomePage from '@/pages/DriverHomePage.vue';
import SmcHomePage from '@/pages/SmcHomePage.vue';

const { isAuthenticated } = useAuth();
const { activeRole } = useRole();

const homeComponent = computed(() => {
    if (!isAuthenticated.value) {
        return GuestHomePage;
    }

    const map = {
        parent: ParentHomePage,
        grandparent: ParentHomePage,
        school_admin: AdminHomePage,
        super_admin: AdminHomePage,
        teacher: TeacherHomePage,
        student: StudentHomePage,
        smc_member: SmcHomePage,
        alumni: AlumniHomePage,
        transport_staff: DriverHomePage,
    };

    return map[activeRole.value] ?? ParentHomePage;
});
</script>
