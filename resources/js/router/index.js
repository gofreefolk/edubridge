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
import PlatformDashboardPage from '@/pages/platform/PlatformDashboardPage.vue';
import PlatformSchoolsPage from '@/pages/platform/PlatformSchoolsPage.vue';
import { useAuth } from '@/composables/useAuth';
import { useRole } from '@/composables/useRole';
import { applyUserLocale, setLocale } from '@/i18n';

const routes = [
    { path: '/', name: 'home', component: RoleHomePage },
    { path: '/login', name: 'login', component: LoginPage, meta: { guestOnly: true, hideNav: true } },
    {
        path: '/platform/login',
        name: 'platform-login',
        component: { template: '<div />' },
        meta: { guestOnly: true, hideNav: true },
    },
    {
        path: '/platform',
        meta: { platformLayout: true, requiresSuperAdmin: true },
        children: [
            { path: '', name: 'platform-dashboard', component: PlatformDashboardPage },
            { path: 'schools', name: 'platform-schools', component: PlatformSchoolsPage },
        ],
    },
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

function isSuperAdmin(user) {
    return user?.roles?.includes('super_admin');
}

router.beforeEach(async (to) => {
    const { isAuthenticated, initialized, fetchUser, user } = useAuth();
    const { canAccessRoute, defaultRouteForRole, syncActiveRole } = useRole();

    if (!initialized.value) {
        await fetchUser();
    }

    syncActiveRole();

    if (to.meta.platformLayout || to.name === 'platform-login') {
        if (isAuthenticated.value && isSuperAdmin(user.value)) {
            applyUserLocale(user.value);
        } else if (!localStorage.getItem('edubridge.locale')) {
            setLocale('en');
        }
    }

    if (to.name === 'platform-login' && isAuthenticated.value && isSuperAdmin(user.value)) {
        return { name: 'platform-dashboard' };
    }

    if (to.meta.guestOnly && isAuthenticated.value) {
        if (to.name === 'platform-login') {
            return true;
        }

        if (isSuperAdmin(user.value) && to.name === 'login') {
            return { name: 'platform-dashboard' };
        }

        return { name: 'home' };
    }

    if (to.meta.requiresSuperAdmin) {
        if (!isAuthenticated.value) {
            return { name: 'platform-login', query: { redirect: to.fullPath } };
        }

        if (!isSuperAdmin(user.value)) {
            return { name: 'home' };
        }
    }

    if (isAuthenticated.value && to.name === 'home' && isSuperAdmin(user.value)) {
        return { name: 'platform-dashboard' };
    }

    if (isAuthenticated.value && to.name !== 'home' && to.name !== 'login' && to.name !== 'notice-magic' && !to.meta.platformLayout && to.name !== 'platform-login') {
        if (!canAccessRoute(to.name)) {
            return { name: defaultRouteForRole() };
        }
    }

    return true;
});

export default router;
