<template>
    <div>
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-slate-800">Preventive Maintenance Checklists</h2>
            <button
                type="button"
                class="px-4 py-2 rounded-lg font-medium text-white"
                style="background-color: #fbc008; box-shadow: 0 6px 12px rgba(0,0,0,0.08);"
                @click="showCreateModal = true"
                @mouseover="hovering = true"
                @mouseleave="hovering = false"
                :style="hovering ? { backgroundColor: '#e5ac00' } : { backgroundColor: '#fbc008' }"
            >
                + New Preventive Checklist
            </button>
        </div>

        <div v-if="loading" class="text-center py-8">
            <p class="text-slate-600">Loading checklists...</p>
        </div>

        <div v-else-if="checklists.length === 0" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 text-center">
            <p class="text-slate-600 mb-4">No checklists found</p>
            <button type="button" class="font-semibold" style="color: #d99b00;" @click="showCreateModal = true">Create your first preventive checklist</button>
        </div>

        <div v-else class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden relative">
            <button
                v-if="canDeleteRecords"
                type="button"
                @click="toggleAllChecklistsLock"
                :class="[
                    'absolute top-3 right-3 px-3 py-1.5 text-xs font-semibold rounded-lg flex items-center gap-2 border shadow-sm',
                    checklistsLocked
                        ? 'text-slate-600 bg-slate-100 border-slate-200'
                        : 'text-amber-700 bg-amber-50 border-amber-100'
                ]"
                :title="checklistsLocked ? 'Editing and deletion are locked' : 'Editing and deletion are enabled'"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path v-if="checklistsLocked" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 2h12a1 1 0 001-1v-6a1 1 0 00-1-1h-1V9a5 5 0 10-10 0v2H7a1 1 0 00-1 1v6a1 1 0 001 1z" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8V7a5 5 0 10-10 0v1m-1 4h12a2 2 0 012 2v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                </svg>
                <span>{{ checklistsLocked ? 'Locked' : 'Unlocked' }}</span>
            </button>

            <table class="min-w-full table-fixed divide-y divide-slate-200">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="w-[28%] px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Asset</th>
                        <th class="w-[22%] px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">User/Operator</th>
                        <th class="w-[14%] px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Date</th>
                        <th class="w-[36%] px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="checklist in checklists" :key="checklist.psm_id" class="hover:bg-slate-50">
                        <td class="px-6 py-4 text-sm font-medium text-slate-900">
                            <div class="max-w-full break-words leading-snug">{{ checklist.asset_name || checklist.pc_name || 'Untitled' }}</div>
                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                <span class="inline-flex max-w-full items-center rounded-md bg-slate-50 px-2 py-0.5 font-mono text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                                    {{ checklist.identifier || formatIdentifier(checklist.psm_id, checklist.checklist_type) }}
                                </span>
                                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                    {{ checklist.checklist_type_label || 'PC' }}
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">{{ checklist.user_operator || '—' }}</td>
                        <td class="px-6 py-4 text-sm text-slate-600">
                            <div>{{ formatDate(checklist.checklist_date) }}</div>
                            <div v-if="checklist.checklist_time" class="mt-1 text-xs font-medium text-slate-500 leading-tight">
                                {{ checklist.checklist_time }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm space-x-2">
                            <router-link :to="`/preventive-maintenance/${checklist.psm_id}`" class="text-emerald-600 hover:underline">View</router-link>
                            <router-link :to="`/preventive-maintenance/${checklist.psm_id}/item-checklist/create`" class="text-purple-600 hover:underline">Create Item Checklist</router-link>
                            <button
                                v-if="canDeleteRecords"
                                type="button"
                                @click="toggleChecklistLock(checklist.psm_id)"
                                :class="[
                                    'inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium',
                                    isChecklistLocked(checklist.psm_id)
                                        ? 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                                        : 'bg-amber-50 text-amber-700 hover:bg-amber-100'
                                ]"
                                :title="isChecklistLocked(checklist.psm_id) ? 'This preventive maintenance checklist is locked' : 'This preventive maintenance checklist is unlocked'"
                            >
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path v-if="isChecklistLocked(checklist.psm_id)" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 2h12a1 1 0 001-1v-6a1 1 0 00-1-1h-1V9a5 5 0 10-10 0v2H7a1 1 0 00-1 1v6a1 1 0 001 1z" />
                                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8V7a5 5 0 10-10 0v1m-1 4h12a2 2 0 012 2v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                                </svg>
                                <span>{{ isChecklistLocked(checklist.psm_id) ? 'Locked' : 'Unlocked' }}</span>
                            </button>
                            <template v-if="canEditChecklist(checklist)">
                                <router-link :to="`/preventive-maintenance/${checklist.psm_id}/edit`" class="text-blue-600 hover:underline">Edit</router-link>
                                <button v-if="canDeleteRecords" @click="deleteChecklist(checklist.psm_id)" class="text-red-600 hover:underline">Delete</button>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="pagination.lastPage > 1" class="flex items-center justify-between border-t border-slate-200 px-6 py-4">
                <p class="text-sm text-slate-600">
                    Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }} checklists
                </p>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="pagination.currentPage <= 1 || loading"
                        @click="fetchChecklists(pagination.currentPage - 1)"
                    >
                        Previous
                    </button>
                    <span class="text-sm text-slate-600">
                        Page {{ pagination.currentPage }} of {{ pagination.lastPage }}
                    </span>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="pagination.currentPage >= pagination.lastPage || loading"
                        @click="fetchChecklists(pagination.currentPage + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4" @click.self="showCreateModal = false">
            <div class="w-full max-w-6xl overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200">
                <div class="border-b border-slate-200 bg-slate-50/80 px-6 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-500 ring-1 ring-slate-200">
                                New Record
                            </div>
                            <h3 class="mt-3 text-2xl font-bold text-slate-900">Create New Preventive Checklist</h3>
                            <p class="mt-1 text-sm text-slate-500">Select the asset category to start with the right form.</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-white hover:text-slate-600"
                            @click="showCreateModal = false"
                            aria-label="Close"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="px-6 py-6">
                    <div class="overflow-x-auto pb-2">
                    <div class="grid auto-cols-[220px] grid-flow-col gap-4 min-w-max">
                        <button
                            type="button"
                            class="grid min-h-[168px] grid-rows-[auto_auto_1fr] rounded-xl border border-amber-200 bg-amber-50 p-5 text-left transition hover:-translate-y-0.5 hover:border-amber-300 hover:bg-amber-100"
                            @click="openChecklistForm('pc')"
                        >
                            <div class="flex items-center justify-between">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white/80 text-sm font-bold text-amber-700 ring-1 ring-amber-200">PC</span>
                                <span class="text-xs font-semibold uppercase tracking-wide text-amber-700">Desktop</span>
                            </div>
                            <div class="mt-4 text-lg font-bold text-slate-900">PC</div>
                            <div class="mt-2 text-sm text-slate-600">Desktop and workstation checklist</div>
                        </button>
                        <button
                            type="button"
                            class="grid min-h-[168px] grid-rows-[auto_auto_1fr] rounded-xl border border-sky-200 bg-sky-50 p-5 text-left transition hover:-translate-y-0.5 hover:border-sky-300 hover:bg-sky-100"
                            @click="openChecklistForm('server')"
                        >
                            <div class="flex items-center justify-between">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white/80 text-sm font-bold text-sky-700 ring-1 ring-sky-200">SV</span>
                                <span class="text-xs font-semibold uppercase tracking-wide text-sky-700">Datacenter</span>
                            </div>
                            <div class="mt-4 text-lg font-bold text-slate-900">Server</div>
                            <div class="mt-2 text-sm text-slate-600">Rack and compute infrastructure</div>
                        </button>
                        <button
                            type="button"
                            class="grid min-h-[168px] grid-rows-[auto_auto_1fr] rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-left transition hover:-translate-y-0.5 hover:border-emerald-300 hover:bg-emerald-100"
                            @click="openChecklistForm('ip_phone')"
                        >
                            <div class="flex items-center justify-between">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white/80 text-sm font-bold text-emerald-700 ring-1 ring-emerald-200">IP</span>
                                <span class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Voice</span>
                            </div>
                            <div class="mt-4 text-lg font-bold text-slate-900">IP Phone</div>
                            <div class="mt-2 text-sm text-slate-600">Desk phone and handset checks</div>
                        </button>
                        <button
                            type="button"
                            class="grid min-h-[168px] grid-rows-[auto_auto_1fr] rounded-xl border border-purple-200 bg-purple-50 p-5 text-left transition hover:-translate-y-0.5 hover:border-purple-300 hover:bg-purple-100"
                            @click="openChecklistForm('network_device')"
                        >
                            <div class="flex items-center justify-between">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white/80 text-sm font-bold text-purple-700 ring-1 ring-purple-200">NW</span>
                                <span class="text-xs font-semibold uppercase tracking-wide text-purple-700">Switching</span>
                            </div>
                            <div class="mt-4 text-lg font-bold text-slate-900">Network Device</div>
                            <div class="mt-2 text-sm text-slate-600">Switches, routers, and similar gear</div>
                        </button>
                        <button
                            type="button"
                            class="grid min-h-[168px] grid-rows-[auto_auto_1fr] rounded-xl border border-cyan-200 bg-cyan-50 p-5 text-left transition hover:-translate-y-0.5 hover:border-cyan-300 hover:bg-cyan-100"
                            @click="openChecklistForm('wifi')"
                        >
                            <div class="flex items-center justify-between">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white/80 text-sm font-bold text-cyan-700 ring-1 ring-cyan-200">WF</span>
                                <span class="text-xs font-semibold uppercase tracking-wide text-cyan-700">Wireless</span>
                            </div>
                            <div class="mt-4 text-lg font-bold text-slate-900">WiFi</div>
                            <div class="mt-2 text-sm text-slate-600">Access point and SSID maintenance</div>
                        </button>
                        <button
                            type="button"
                            class="grid min-h-[168px] grid-rows-[auto_auto_1fr] rounded-xl border border-orange-200 bg-orange-50 p-5 text-left transition hover:-translate-y-0.5 hover:border-orange-300 hover:bg-orange-100"
                            @click="openChecklistForm('ups')"
                        >
                            <div class="flex items-center justify-between">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white/80 text-sm font-bold text-orange-700 ring-1 ring-orange-200">UP</span>
                                <span class="text-xs font-semibold uppercase tracking-wide text-orange-700">Power</span>
                            </div>
                            <div class="mt-4 text-lg font-bold text-slate-900">UPS</div>
                            <div class="mt-2 text-sm text-slate-600">Backup power and battery health</div>
                        </button>
                        <button
                            type="button"
                            class="grid min-h-[168px] grid-rows-[auto_auto_1fr] rounded-xl border border-rose-200 bg-rose-50 p-5 text-left transition hover:-translate-y-0.5 hover:border-rose-300 hover:bg-rose-100"
                            @click="openChecklistForm('cctv')"
                        >
                            <div class="flex items-center justify-between">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white/80 text-sm font-bold text-rose-700 ring-1 ring-rose-200">CC</span>
                                <span class="text-xs font-semibold uppercase tracking-wide text-rose-700">Security</span>
                            </div>
                            <div class="mt-4 text-lg font-bold text-slate-900">CCTV</div>
                            <div class="mt-2 text-sm text-slate-600">Camera, video, and recording checks</div>
                        </button>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'PreventiveMaintenanceIndex',
    props: {
        authUser: {
            type: Object,
            default: null,
        },
    },
    data() {
        return {
            checklists: [],
            loading: true,
            pagination: {
                currentPage: 1,
                lastPage: 1,
                perPage: 20,
                total: 0,
                from: 0,
                to: 0,
            },
            checklistsLocked: false,
            checklistLocks: {},
            hovering: false,
            showCreateModal: false,
        };
    },
    mounted() {
        this.fetchChecklists();
    },
    computed: {
        canDeleteRecords() {
            return Boolean(this.authUser?.permissions?.delete_records);
        },
        canEditRecords() {
            return ['superadmin', 'admin', 'encoder'].includes(this.authUser?.role);
        },
    },
    methods: {
        openChecklistForm(type) {
            this.showCreateModal = false;
            this.$router.push({
                name: 'preventive-maintenance-create',
                query: { type },
            });
        },
        updatePagination(payload = {}) {
            this.pagination = {
                currentPage: Number(payload.current_page || 1),
                lastPage: Number(payload.last_page || 1),
                perPage: Number(payload.per_page || this.pagination.perPage || 20),
                total: Number(payload.total || 0),
                from: Number(payload.from || 0),
                to: Number(payload.to || 0),
            };
        },
        async fetchChecklists(page = 1) {
            this.loading = true;

            try {
                const response = await axios.get('/api/preventive-maintenance', {
                    params: {
                        page,
                        per_page: this.pagination.perPage,
                    },
                });

                this.updatePagination(response.data);

                if (!Array.isArray(response.data?.data) && response.data?.data !== undefined) {
                    this.checklists = [];
                    return;
                }

                this.checklists = Array.isArray(response.data?.data) ? response.data.data : [];
                this.syncLockState();

                if (!this.checklists.length && this.pagination.currentPage > this.pagination.lastPage && this.pagination.lastPage > 0) {
                    await this.fetchChecklists(this.pagination.lastPage);
                    return;
                }
            } catch (error) {
                console.error('Error fetching checklists:', error);
            } finally {
                this.loading = false;
            }
        },
        async deleteChecklist(id) {
            if (!this.canDeleteRecords) {
                return;
            }

            let confirmed = false;

            if (window.Swal) {
                const result = await Swal.fire({
                    title: 'Delete this checklist?',
                    text: 'This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, delete it',
                });
                confirmed = result.isConfirmed;
            } else {
                // Fallback to native confirm if SweetAlert is not available
                confirmed = confirm('Are you sure you want to delete this checklist?');
            }

            if (!confirmed) {
                return;
            }

            try {
                await axios.delete(`/api/preventive-maintenance/${id}`);
                const targetPage = this.checklists.length === 1 && this.pagination.currentPage > 1
                    ? this.pagination.currentPage - 1
                    : this.pagination.currentPage;

                await this.fetchChecklists(targetPage);

                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted',
                        text: 'The checklist has been deleted.',
                    });
                }
            } catch (error) {
                console.error('Error deleting checklist:', error);

                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'There was a problem deleting the checklist.',
                    });
                }
            }
        },
        isChecklistLocked(id) {
            return this.checklistsLocked || Boolean(this.checklistLocks[String(id)]);
        },
        canEditChecklist(checklist) {
            if (!this.canEditRecords) {
                return false;
            }

            return !this.checklistsLocked && !this.isChecklistLocked(checklist.psm_id);
        },
        syncLockState() {
            this.checklistLocks = this.checklists.reduce((locks, checklist) => ({
                ...locks,
                [String(checklist.psm_id)]: Boolean(checklist.is_locked),
            }), {});
            this.checklistsLocked = this.checklists.length > 0 && this.checklists.every(checklist => Boolean(checklist.is_locked));
        },
        async setChecklistLock(id, locked) {
            const action = locked ? 'lock' : 'unlock';
            const response = await axios.patch(`/api/preventive-maintenance/${id}/${action}`);
            const isLocked = Boolean(response.data?.is_locked);
            const key = String(id);

            this.checklistLocks = {
                ...this.checklistLocks,
                [key]: isLocked,
            };
            this.checklists = this.checklists.map(checklist => String(checklist.psm_id) === key
                ? { ...checklist, is_locked: isLocked }
                : checklist
            );
            this.checklistsLocked = this.checklists.length > 0 && this.checklists.every(checklist => Boolean(checklist.is_locked));
        },
        async toggleAllChecklistsLock() {
            const locked = !this.checklistsLocked;

            await Promise.all(this.checklists.map(checklist => this.setChecklistLock(checklist.psm_id, locked)));
        },
        async toggleChecklistLock(id) {
            await this.setChecklistLock(id, !this.checklistLocks[String(id)]);
        },
        formatDate(date) {
            if (!date) return '—';
            return new Date(date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        },
        checklistCategoryCode(type) {
            const normalized = String(type || '').toLowerCase().trim();

            if (normalized === 'server') return 'SERVER';
            if (['ip_phone', 'ip-phone', 'ipphone'].includes(normalized)) return 'IPPHONE';
            if (['network_device', 'network-device', 'networkdevice'].includes(normalized)) return 'NETWORK';
            if (['wifi', 'wi-fi'].includes(normalized)) return 'WIFI';
            if (normalized === 'ups') return 'UPS';
            if (normalized === 'cctv') return 'CCTV';

            return 'PC';
        },
        formatIdentifier(id, type = 'pc') {
            if (!id) return 'ID pending';
            const padded = String(id).padStart(4, '0');
            return `PM${this.checklistCategoryCode(type)}-${padded}`;
        },
    },
};
</script>
b   
