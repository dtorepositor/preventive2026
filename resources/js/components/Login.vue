<template>
    <div class="min-h-[calc(100vh-6rem)] flex items-center justify-center px-4 py-10">
        <form class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div class="mb-6">
                <p class="text-sm font-semibold uppercase tracking-wide text-amber-600">CMU ODT</p>
                <h1 class="mt-2 text-2xl font-bold text-slate-900">Preventive Maintenance Login</h1>
            </div>

            <div v-if="generalError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ generalError }}
            </div>

            <div class="space-y-4">
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200"
                        required
                    >
                    <p v-if="errors.email" class="mt-1 text-xs text-red-600">{{ errors.email[0] }}</p>
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200"
                        required
                    >
                    <p v-if="errors.password" class="mt-1 text-xs text-red-600">{{ errors.password[0] }}</p>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-amber-500 focus:ring-amber-300">
                    Remember me
                </label>
            </div>

            <button
                type="submit"
                :disabled="loading"
                class="mt-6 w-full rounded-lg px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-70"
                style="background-color: #fbc008;"
            >
                {{ loading ? 'Signing in...' : 'Sign in' }}
            </button>
        </form>
    </div>
</template>

<script>
import axios from 'axios';
import { authState, setAuthUser } from '../auth';

export default {
    name: 'Login',
    data() {
        return {
            loading: false,
            generalError: '',
            errors: {},
            form: {
                email: '',
                password: '',
                remember: false,
            },
        };
    },
    mounted() {
        if (authState.user) {
            this.$router.replace('/dashboard');
        }
    },
    methods: {
        async submit() {
            this.loading = true;
            this.generalError = '';
            this.errors = {};

            try {
                const response = await axios.post('/login', this.form);
                setAuthUser(response.data?.user || null);
                await this.$router.replace('/dashboard');
            } catch (error) {
                this.errors = error.response?.data?.errors || {};
                this.generalError = error.response?.data?.message || 'Unable to sign in. Please try again.';
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>
