<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-800">User Management</h2>
                <p class="mt-1 text-sm text-slate-500">{{ scopeText }}</p>
            </div>
            <button
                type="button"
                class="rounded-lg px-4 py-2 text-sm font-semibold text-white"
                style="background-color: #fbc008;"
                @click="openCreate"
            >
                New User
            </button>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div v-if="loading" class="px-6 py-10 text-center text-sm text-slate-500">Loading users...</div>
            <table v-else class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Name</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Email</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Role</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-if="users.length === 0">
                        <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">No manageable users found.</td>
                    </tr>
                    <tr v-for="user in users" :key="user.id" class="hover:bg-slate-50">
                        <td class="px-5 py-4 text-sm font-semibold text-slate-800">{{ user.name }}</td>
                        <td class="px-5 py-4 text-sm text-slate-600">{{ user.email }}</td>
                        <td class="px-5 py-4 text-sm text-slate-600">{{ user.role_label }}</td>
                        <td class="px-5 py-4 text-sm">
                            <span
                                :class="[
                                    'rounded-full px-2.5 py-1 text-xs font-semibold',
                                    user.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'
                                ]"
                            >
                                {{ user.is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-sm">
                            <div class="flex flex-wrap gap-3">
                                <button type="button" class="font-semibold text-blue-600 hover:underline" @click="openEdit(user)">Edit</button>
                                <button
                                    type="button"
                                    class="font-semibold text-amber-700 hover:underline"
                                    @click="toggleActive(user)"
                                >
                                    {{ user.is_active ? 'Disable' : 'Enable' }}
                                </button>
                                <button type="button" class="font-semibold text-red-600 hover:underline" @click="deleteUser(user)">Delete</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4" @click.self="closeModal">
            <form class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" @submit.prevent="save">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">{{ editingId ? 'Edit User' : 'New User' }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Use a strong temporary password for new accounts.</p>
                    </div>
                    <button type="button" class="rounded-lg px-2 py-1 text-slate-500 hover:bg-slate-100" @click="closeModal">Close</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                        <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                        <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name[0] }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                        <input v-model="form.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                        <p v-if="errors.email" class="mt-1 text-xs text-red-600">{{ errors.email[0] }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Role</label>
                        <select v-model="form.role" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" required>
                            <option v-for="role in allowedRoles" :key="role.value" :value="role.value">{{ role.label }}</option>
                        </select>
                        <p v-if="errors.role" class="mt-1 text-xs text-red-600">{{ errors.role[0] }}</p>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-amber-500">
                        Active
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ editingId ? 'New Password' : 'Password' }}</label>
                            <input v-model="form.password" type="password" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :required="!editingId">
                            <p v-if="errors.password" class="mt-1 text-xs text-red-600">{{ errors.password[0] }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Confirm Password</label>
                            <input v-model="form.password_confirmation" type="password" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :required="!editingId || Boolean(form.password)">
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600" @click="closeModal">Cancel</button>
                    <button type="submit" :disabled="saving" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">
                        {{ saving ? 'Saving...' : 'Save User' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'UserManagement',
    props: {
        authUser: {
            type: Object,
            default: null,
        },
    },
    data() {
        return {
            loading: true,
            saving: false,
            showModal: false,
            users: [],
            allowedRoles: [],
            errors: {},
            editingId: null,
            form: this.blankForm(),
        };
    },
    computed: {
        scopeText() {
            return this.authUser?.role === 'admin'
                ? 'Admins can manage Encoder accounts only.'
                : 'Super Admins can manage Admin and Encoder accounts.';
        },
    },
    mounted() {
        this.fetchUsers();
    },
    methods: {
        blankForm() {
            return {
                name: '',
                email: '',
                role: 'encoder',
                is_active: true,
                password: '',
                password_confirmation: '',
            };
        },
        async fetchUsers() {
            this.loading = true;
            try {
                const response = await axios.get('/api/users');
                this.users = response.data?.users || [];
                this.allowedRoles = response.data?.allowed_roles || [];
                if (this.allowedRoles.length && !this.allowedRoles.some(role => role.value === this.form.role)) {
                    this.form.role = this.allowedRoles[0].value;
                }
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.errors = {};
            this.form = this.blankForm();
            if (this.allowedRoles.length) {
                this.form.role = this.allowedRoles[0].value;
            }
            this.showModal = true;
        },
        openEdit(user) {
            this.editingId = user.id;
            this.errors = {};
            this.form = {
                name: user.name,
                email: user.email,
                role: user.role,
                is_active: Boolean(user.is_active),
                password: '',
                password_confirmation: '',
            };
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
        },
        async save() {
            this.saving = true;
            this.errors = {};

            const payload = { ...this.form };
            if (this.editingId && !payload.password) {
                delete payload.password;
                delete payload.password_confirmation;
            }

            try {
                if (this.editingId) {
                    await axios.put(`/api/users/${this.editingId}`, payload);
                } else {
                    await axios.post('/api/users', payload);
                }
                this.closeModal();
                await this.fetchUsers();
            } catch (error) {
                this.errors = error.response?.data?.errors || {};
                if (!Object.keys(this.errors).length && window.Swal) {
                    Swal.fire({ icon: 'error', title: 'Unable to save user', text: error.response?.data?.message || 'Please try again.' });
                }
            } finally {
                this.saving = false;
            }
        },
        async toggleActive(user) {
            const action = user.is_active ? 'disable' : 'enable';
            await axios.patch(`/api/users/${user.id}/${action}`);
            await this.fetchUsers();
        },
        async deleteUser(user) {
            let confirmed = true;
            if (window.Swal) {
                const result = await Swal.fire({
                    title: `Delete ${user.name}?`,
                    text: 'This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    confirmButtonText: 'Yes, delete it',
                });
                confirmed = result.isConfirmed;
            }

            if (!confirmed) {
                return;
            }

            await axios.delete(`/api/users/${user.id}`);
            await this.fetchUsers();
        },
    },
};
</script>
