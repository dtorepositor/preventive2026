<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">System Settings</h2>
            <p class="mt-1 text-sm text-slate-500">Super Admin access</p>
        </div>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Super Admin creation</h3>
                    <p class="mt-1 text-sm text-slate-500">Controlled by the server config value <code>ALLOW_CREATING_SUPERADMINS</code>.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">Code controlled</span>
            </div>
            <p class="pt-4 text-sm text-slate-600">
                User, reference, checklist, report, and record permissions are enforced by Laravel middleware and controller checks.
            </p>
        </section>
    </div>
</template>

<script>
export default {
    name: 'Settings',
};
</script>
