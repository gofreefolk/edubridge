/** Role priority when user has multiple memberships */
export const ROLE_PRIORITY = [
    'super_admin',
    'school_admin',
    'teacher',
    'transport_staff',
    'smc_member',
    'parent',
    'grandparent',
    'student',
    'alumni',
];

/** Bottom navigation items per role (max 4 tabs for mobile) */
export const ROLE_NAVIGATION = {
    parent: [
        { key: 'home', label: 'nav.home', icon: '🏠', route: 'home' },
        { key: 'notices', label: 'nav.notices', icon: '📋', route: 'notices' },
        { key: 'calendar', label: 'nav.calendar', icon: '📅', route: 'calendar' },
        { key: 'my-child', label: 'nav.myChild', icon: '👤', route: 'my-child' },
    ],
    grandparent: [
        { key: 'home', label: 'nav.home', icon: '🏠', route: 'home' },
        { key: 'notices', label: 'nav.notices', icon: '📋', route: 'notices' },
        { key: 'calendar', label: 'nav.calendar', icon: '📅', route: 'calendar' },
        { key: 'my-child', label: 'nav.myChild', icon: '👤', route: 'my-child' },
    ],
    school_admin: [
        { key: 'home', label: 'nav.home', icon: '🏠', route: 'home' },
        { key: 'import', label: 'admin.importShort', icon: '📥', route: 'admin-import' },
        { key: 'notices', label: 'nav.notices', icon: '📋', route: 'notices' },
        { key: 'calendar', label: 'nav.calendar', icon: '📅', route: 'calendar' },
    ],
    teacher: [
        { key: 'home', label: 'nav.home', icon: '🏠', route: 'home' },
        { key: 'class', label: 'nav.class', icon: '👥', route: 'teacher-class' },
        { key: 'messages', label: 'nav.messages', icon: '💬', route: 'messages' },
        { key: 'calendar', label: 'nav.calendar', icon: '📅', route: 'calendar' },
    ],
    student: [
        { key: 'home', label: 'nav.home', icon: '🏠', route: 'home' },
        { key: 'homework', label: 'nav.homework', icon: '📝', route: 'my-child' },
        { key: 'calendar', label: 'nav.calendar', icon: '📅', route: 'calendar' },
        { key: 'exams', label: 'nav.exams', icon: '📖', route: 'student-exams' },
    ],
    smc_member: [
        { key: 'home', label: 'nav.home', icon: '🏠', route: 'home' },
        { key: 'notices', label: 'nav.notices', icon: '📋', route: 'notices' },
        { key: 'smc', label: 'nav.smc', icon: '🏛️', route: 'smc' },
        { key: 'calendar', label: 'nav.calendar', icon: '📅', route: 'calendar' },
    ],
    alumni: [
        { key: 'home', label: 'nav.home', icon: '🏠', route: 'home' },
        { key: 'alumni', label: 'nav.alumni', icon: '🎓', route: 'alumni' },
        { key: 'calendar', label: 'nav.calendar', icon: '📅', route: 'calendar' },
        { key: 'messages', label: 'nav.messages', icon: '💬', route: 'messages' },
    ],
    transport_staff: [
        { key: 'home', label: 'nav.home', icon: '🏠', route: 'home' },
        { key: 'driver', label: 'nav.driver', icon: '🚌', route: 'driver' },
        { key: 'calendar', label: 'nav.calendar', icon: '📅', route: 'calendar' },
        { key: 'notices', label: 'nav.notices', icon: '📋', route: 'notices' },
    ],
    super_admin: [
        { key: 'home', label: 'nav.home', icon: '🏠', route: 'home' },
        { key: 'notices', label: 'nav.notices', icon: '📋', route: 'notices' },
        { key: 'calendar', label: 'nav.calendar', icon: '📅', route: 'calendar' },
        { key: 'smc', label: 'nav.smc', icon: '🏛️', route: 'smc' },
    ],
};

/** Which roles can access each named route */
export const ROUTE_ROLES = {
    home: ROLE_PRIORITY,
    login: null,
    'notice-magic': null,
    notices: ['parent', 'grandparent', 'school_admin', 'teacher', 'smc_member', 'student', 'alumni', 'transport_staff', 'super_admin'],
    calendar: ['parent', 'grandparent', 'school_admin', 'teacher', 'smc_member', 'student', 'alumni', 'transport_staff', 'super_admin'],
    messages: ['parent', 'grandparent', 'teacher', 'alumni'],
    'my-child': ['parent', 'grandparent', 'student'],
    smc: ['school_admin', 'smc_member', 'parent', 'grandparent', 'super_admin'],
    alumni: ['alumni'],
    transport: ['parent', 'grandparent'],
    driver: ['transport_staff'],
    'teacher-class': ['teacher', 'school_admin', 'super_admin'],
    'student-exams': ['student'],
    'admin-import': ['school_admin', 'super_admin'],
    'admin-notice-create': ['school_admin', 'super_admin'],
};

export function resolvePrimaryRole(roles) {
    for (const role of ROLE_PRIORITY) {
        if (roles.includes(role)) {
            return role;
        }
    }

    return roles[0] ?? 'parent';
}

export function roleLabelKey(role) {
    return `roles.${role}`;
}
