<template>
    <div>
        <header v-if="!isLoginRoute" class="bg-white shadow-sm ring-1 ring-slate-200">
            <div class="max-w-6xl mx-auto px-4 py-3">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 class="text-lg font-semibold text-slate-900">Central Mindanao University</h1>
                        <p class="text-sm text-slate-500">Office of Digital Transformation</p>
                    </div>
                    <nav class="flex flex-wrap items-center gap-2">
                        <router-link
                            v-for="item in visibleMenuItems"
                            :key="item.to"
                            :to="item.to"
                            class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900"
                            active-class="bg-amber-50 text-amber-700"
                        >
                            {{ item.label }}
                        </router-link>
                    </nav>
                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <p class="text-sm font-semibold text-slate-800">{{ authUser?.name }}</p>
                            <p class="text-xs text-slate-500">{{ authUser?.role_label }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                            @click="handleLogout"
                        >
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <main :class="isLoginRoute ? '' : 'max-w-6xl mx-auto px-4 py-6'">
            <router-view v-slot="{ Component }">
                <component :is="Component" :auth-user="authUser" />
            </router-view>
        </main>

        <footer v-if="!isLoginRoute" class="max-w-6xl mx-auto px-4 py-4 text-center text-slate-700 text-sm">
            Preventive Maintenance Checklist - CMU ODT
        </footer>
    </div>
</template>

<script>
import { authState, logout } from './auth';

export default {
    name: 'App',
    computed: {
        authUser() {
            return authState.user;
        },
        isLoginRoute() {
            return this.$route.name === 'login';
        },
        visibleMenuItems() {
            const role = this.authUser?.role;
            const items = [
                { label: 'Dashboard', to: '/dashboard', roles: ['superadmin', 'admin', 'encoder'] },
                { label: 'Records', to: '/records', roles: ['superadmin', 'admin', 'encoder'] },
                { label: 'Plan', to: '/preventive-maintenance/plan', roles: ['superadmin', 'admin'] },
                { label: 'Reports', to: '/preventive-maintenance/reports', roles: ['superadmin', 'admin'] },
                { label: 'Users', to: '/users', roles: ['superadmin', 'admin'] },
                { label: 'Colleges / Departments', to: '/colleges', roles: ['superadmin', 'admin'] },
                { label: 'Checklist Items', to: '/checklist-items', roles: ['superadmin', 'admin'] },
                { label: 'Settings', to: '/settings', roles: ['superadmin'] },
                { label: 'About', to: '/about', roles: ['superadmin', 'admin', 'encoder'] },
            ];

            return items.filter(item => item.roles.includes(role));
        },
    },
    methods: {
        async handleLogout() {
            await logout();
        },
    },
};
</script>
