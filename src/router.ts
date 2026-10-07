import {createRouter, createWebHashHistory} from 'vue-router'
import type {Component} from 'vue'

import SettingsPage from './pages/Settings.vue'
import ConnectPage from './pages/Connect.vue'
import DashboardPage from './pages/Dashboard.vue'
import OnboardingLayout from "./layouts/OnboardingLayout.vue";
import AppLayout from "./layouts/AppLayout.vue";
import {state} from './state.ts'

declare module 'vue-router' {
    interface RouteMeta {
        layout?: Component
    }
}

const routes = [
    {
        path: '/',
        component: ConnectPage,
        meta: {layout: OnboardingLayout}
    },
    {
        path: '/dashboard',
        component: DashboardPage,
        meta: {layout: AppLayout}
    },
    {
        path: '/settings',
        component: SettingsPage,
        meta: {layout: AppLayout}
    }
]

export const router = createRouter({
    history: createWebHashHistory(),
    routes,
})

router.beforeEach((to) => {
    const connected = state.value.state.connected

    if (to.path === '/' && connected) {
        return '/dashboard'
    }

    if (to.path !== '/' && !connected) {
        return '/'
    }
})
