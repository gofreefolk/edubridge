import { createRouter, createWebHistory } from 'vue-router';
import RoleHomePage from '@/pages/RoleHomePage.vue';
import LoginPage from '@/pages/LoginPage.vue';
import NoticePage from '@/pages/NoticePage.vue';
import NoticesPage from '@/pages/NoticesPage.vue';
import CalendarPage from '@/pages/CalendarPage.vue';
import MessagesPage from '@/pages/MessagesPage.vue';
import MyChildPage from '@/pages/MyChildPage.vue';
import SmcPage from '@/pages/SmcPage.vue';
import AlumniPage from '@/pages/AlumniPage.vue';
import TransportPage from '@/pages/TransportPage.vue';
import TeacherClassPage from '@/pages/TeacherClassPage.vue';
import StudentExamsPage from '@/pages/StudentExamsPage.vue';
import DriverTripPage from '@/pages/DriverTripPage.vue';
import AdminImportPage from '@/pages/AdminImportPage.vue';
import AdminCreateNoticePage from '@/pages/AdminCreateNoticePage.vue';
import { useAuth } from '@/composables/useAuth';
import { useRole } from '@/composables/useRole';

const routes = [
    { path: '/', name: 'home', component: RoleHomePage },
    { path: '/login', name: 'login', component: LoginPage, meta: { guestOnly: true, hideNav: true } },
    { path: '/notices', name: 'notices', component: NoticesPage },
    { path: '/calendar', name: 'calendar', component: CalendarPage },
    { path: '/messages', name: 'messages', component: MessagesPage },
    { path: '/my-child', name: 'my-child', component: MyChildPage },
    { path: '/smc', name: 'smc', component: SmcPage },
    { path: '/alumni', name: 'alumni', component: AlumniPage },
    { path: '/transport', name: 'transport', component: TransportPage },
    { path: '/teacher/class', name: 'teacher-class', component: TeacherClassPage },
    { path: '/student/exams', name: 'student-exams', component: StudentExamsPage },
    { path: '/driver', name: 'driver', component: DriverTripPage },
    { path: '/admin/import', name: 'admin-import', component: AdminImportPage },
    { path: '/admin/notices/create', name: 'admin-notice-create', component: AdminCreateNoticePage },
    { path: '/n/:token', name: 'notice-magic', component: NoticePage, props: true, meta: { hideNav: true } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    },
});

router.beforeEach(async (to) => {
    const { isAuthenticated, initialized, fetchUser } = useAuth();
    const { canAccessRoute, defaultRouteForRole, syncActiveRole } = useRole();

    if (!initialized.value) {
        await fetchUser();
    }

    syncActiveRole();

    if (to.meta.guestOnly && isAuthenticated.value) {
        return { name: 'home' };
    }

    if (isAuthenticated.value && to.name !== 'home' && to.name !== 'login' && to.name !== 'notice-magic') {
        if (!canAccessRoute(to.name)) {
            return { name: defaultRouteForRole() };
        }
    }

    return true;
});

export default router;
