<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Dashboard</h2>
            <p class="mt-1 text-sm text-slate-500">{{ userName }} - {{ roleLabel }}</p>
        </div>

        <section class="grid gap-4 md:grid-cols-3">
            <router-link
                v-for="link in visibleLinks"
                :key="link.to"
                :to="link.to"
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-amber-200 hover:shadow"
            >
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ link.kicker }}</p>
                <h3 class="mt-2 text-lg font-bold text-slate-900">{{ link.label }}</h3>
                <p class="mt-2 text-sm text-slate-500">{{ link.description }}</p>
            </router-link>
        </section>
    </div>
</template>

<script>
export default {
    name: 'Dashboard',
    props: {
        authUser: {
            type: Object,
            default: null,
        },
    },
    computed: {
        role() {
            return this.authUser?.role || '';
        },
        userName() {
            return this.authUser?.name || 'User';
        },
        roleLabel() {
            return this.authUser?.role_label || this.role;
        },
        visibleLinks() {
            const links = [
                {
                    to: '/records',
                    label: 'Maintenance Records',
                    kicker: 'Records',
                    description: 'Create, view, and update preventive maintenance records.',
                    roles: ['superadmin', 'admin', 'encoder'],
                },
                {
                    to: '/preventive-maintenance/reports',
                    label: 'Reports',
                    kicker: 'Reporting',
                    description: 'View maintenance summaries and exportable report data.',
                    roles: ['superadmin', 'admin'],
                },
                {
                    to: '/users',
                    label: 'Users',
                    kicker: 'Access',
                    description: 'Create and manage accounts allowed for your role.',
                    roles: ['superadmin', 'admin'],
                },
                {
                    to: '/colleges',
                    label: 'Colleges and Departments',
                    kicker: 'References',
                    description: 'Maintain college, office, and department lists.',
                    roles: ['superadmin', 'admin'],
                },
                {
                    to: '/checklist-items',
                    label: 'Checklist Items',
                    kicker: 'Templates',
                    description: 'Enable or disable maintenance checklist tasks.',
                    roles: ['superadmin', 'admin'],
                },
                {
                    to: '/settings',
                    label: 'Settings',
                    kicker: 'System',
                    description: 'System-level controls for Super Admin only.',
                    roles: ['superadmin'],
                },
            ];

            return links.filter(link => link.roles.includes(this.role));
        },
    },
};
</script>
