<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">About</h2>
            <p class="mt-1 text-sm text-slate-500">Preventive Maintenance Checklist System</p>
        </div>

        <section class="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(280px,0.65fr)]">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">System Purpose</p>
                <h3 class="mt-2 text-xl font-bold text-slate-900">Centralized preventive maintenance records</h3>
                <p class="mt-4 leading-7 text-slate-600">
                    This system helps Central Mindanao University organize preventive maintenance records,
                    asset details, item checklist results, reports, users, colleges, offices, and departments
                    in one shared database.
                </p>

                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Records</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Create, update, view, and print maintenance data.</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reports</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Summarize completed maintenance work by date and office.</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">References</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Maintain colleges, departments, and checklist items.</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Access</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Protect modules through authenticated user roles.</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">System Information</p>
                <dl class="mt-4 space-y-4">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Developed By</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">Developed by: John Axl Redondo</dd>
                        <dd class="text-sm text-slate-600">Central Mindanao University</dd>
                        <dd class="text-sm text-slate-600">Office of Digital Transformation</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Version</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">v{{ version }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Application</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ appName }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Signed In As</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ authUser?.name || 'User' }}</dd>
                        <dd class="text-sm text-slate-600">{{ authUser?.role_label || authUser?.role || 'Authenticated User' }}</dd>
                    </div>
                </dl>
            </div>
        </section>
    </div>
</template>

<script>
export default {
    name: 'About',
    props: {
        authUser: {
            type: Object,
            default: null,
        },
    },
    computed: {
        appName() {
            return window.AppConfig?.appName || 'Preventive Maintenance Checklist';
        },
        version() {
            return window.AppConfig?.version || '1.0.0';
        },
    },
};
</script>
