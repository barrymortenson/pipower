import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/',
        name: 'dashboard',
        component: () => import('@/pages/Dashboard.vue'),
    },
    {
        path: '/notifications',
        name: 'notifications',
        component: () => import('@/pages/Notifications.vue'),
    },
    {
        path: '/system',
        name: 'system',
        component: () => import('@/pages/System.vue'),
    },
    {
        path: '/setup',
        name: 'setup',
        component: () => import('@/pages/Setup.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
