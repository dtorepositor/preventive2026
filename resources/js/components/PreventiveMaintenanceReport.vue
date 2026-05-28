<template>
    <div>
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-800">Reporting Form</h2>
                <p v-if="report?.generated_at" class="mt-1 text-sm text-slate-500">Generated {{ report.generated_at }}</p>
            </div>
            <button
                type="button"
                :disabled="loading"
                class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-400"
                @click="fetchReport"
            >
                {{ loading ? 'Refreshing...' : 'Refresh' }}
            </button>
        </div>

        <form class="mb-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-5" @submit.prevent="fetchReport">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">From</label>
                <input
                    v-model="filters.from"
                    type="date"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200"
                >
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">To</label>
                <input
                    v-model="filters.to"
                    type="date"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200"
                >
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Checklist Type</label>
                <select
                    v-model="filters.checklist_type"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200"
                >
                    <option value="">All Types</option>
                    <option v-for="type in checklistTypes" :key="type.key" :value="type.key">
                        {{ type.label }}
                    </option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">College/Office</label>
                <select
                    v-model="filters.college_office_id"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200"
                >
                    <option value="">All Colleges/Offices</option>
                    <option v-for="office in collegeOffices" :key="office.id" :value="office.id">
                        {{ office.name }}
                    </option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button
                    type="submit"
                    :disabled="loading"
                    class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-70"
                    style="background-color: #fbc008;"
                >
                    Apply
                </button>
                <button
                    type="button"
                    :disabled="loading || !hasActiveFilters"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="resetFilters"
                >
                    Clear
                </button>
            </div>
        </form>

        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white px-6 py-10 text-center text-slate-600 shadow-sm">
            Loading report...
        </div>

        <div v-else-if="!report" class="rounded-xl border border-slate-200 bg-white px-6 py-10 text-center text-slate-600 shadow-sm">
            Report data could not be loaded.
        </div>

        <div v-else class="space-y-6">
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">PM Records</p>
                    <p class="mt-3 text-3xl font-bold text-slate-900">{{ formatNumber(summary.total_preventive_maintenance) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tagged</p>
                    <p class="mt-3 text-3xl font-bold text-emerald-700">{{ formatPercent(summary.tagged_percentage) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ formatNumber(summary.tagged_preventive_maintenance) }} of {{ formatNumber(summary.total_preventive_maintenance) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Item Checklists</p>
                    <p class="mt-3 text-3xl font-bold text-sky-700">{{ formatNumber(summary.total_item_checklists) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Attention</p>
                    <p class="mt-3 text-3xl font-bold text-rose-700">{{ formatPercent(summary.attention_percentage) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ formatNumber(summary.attention_item_checklists) }} categorized</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Office Coverage</p>
                    <p class="mt-3 text-3xl font-bold text-purple-700">{{ formatPercent(summary.office_coverage_percentage) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ formatNumber(summary.office_coverage_count) }} of {{ formatNumber(summary.office_coverage_total) }}</p>
                </div>
            </section>

            <div class="rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
                <div class="flex flex-wrap gap-1">
                    <button
                        v-for="tab in reportTabs"
                        :key="tab.key"
                        type="button"
                        :class="tabButtonClass(tab.key)"
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <div v-if="activeTab === 'pm'" class="space-y-6">
                <section class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-5 py-4">
                            <h3 class="text-lg font-semibold text-slate-800">Commission Categories</h3>
                        </div>
                        <div class="divide-y divide-slate-200">
                            <div v-for="row in commissionRows" :key="row.key" class="px-5 py-4">
                                <div class="flex items-center justify-between gap-4">
                                    <span :class="['rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide', commissionClass(row.key)]">{{ row.label }}</span>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-slate-800">{{ formatNumber(row.count) }}</p>
                                        <p class="text-xs text-slate-500">{{ formatPercent(row.percentage) }}</p>
                                    </div>
                                </div>
                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-sky-500" :style="{ width: barWidth(row.percentage) }"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-5 py-4">
                            <h3 class="text-lg font-semibold text-slate-800">Asset Categories</h3>
                        </div>
                        <div class="divide-y divide-slate-200">
                            <div v-for="row in assetCategoryRows" :key="row.key" class="px-5 py-4">
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm font-semibold text-slate-700">{{ row.label }}</span>
                                    <span class="text-sm text-slate-600">{{ formatNumber(row.count) }} - {{ formatPercent(row.percentage) }}</span>
                                </div>
                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-purple-500" :style="{ width: barWidth(row.percentage) }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h3 class="text-lg font-semibold text-slate-800">Item Results</h3>
                    </div>
                    <div class="divide-y divide-slate-200">
                        <div v-for="row in itemStatusRows" :key="row.key" class="px-5 py-4">
                            <div class="flex items-center justify-between gap-4">
                                <span :class="['rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide', itemStatusClass(row.key)]">{{ row.label }}</span>
                                <span class="text-sm text-slate-600">{{ formatNumber(row.count) }} - {{ formatPercent(row.percentage) }}</span>
                            </div>
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div :class="['h-full rounded-full', itemStatusBarClass(row.key)]" :style="{ width: barWidth(row.percentage) }"></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h3 class="text-lg font-semibold text-slate-800">Monthly Trend</h3>
                    </div>
                    <div v-if="monthlyTrendRows.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-100">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Month</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-600">PM Records</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-600">Item Checklists</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Volume</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr v-for="row in monthlyTrendRows" :key="row.month" class="hover:bg-slate-50">
                                    <td class="px-5 py-4 text-sm font-semibold text-slate-700">{{ row.label }}</td>
                                    <td class="px-5 py-4 text-right text-sm text-slate-600">{{ formatNumber(row.preventive_maintenance) }}</td>
                                    <td class="px-5 py-4 text-right text-sm text-slate-600">{{ formatNumber(row.item_checklists) }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex h-2 min-w-[220px] gap-1 overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full bg-sky-500" :style="{ width: trendWidth(row.preventive_maintenance) }"></div>
                                            <div class="h-full rounded-full bg-emerald-500" :style="{ width: trendWidth(row.item_checklists) }"></div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="px-5 py-8 text-center text-sm text-slate-500">No monthly records in this range.</div>
                </section>
            </div>

            <div v-else-if="activeTab === 'office'" class="space-y-6">
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h3 class="text-lg font-semibold text-slate-800">College/Office Percentages</h3>
                    </div>
                    <div v-if="officeRows.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-100">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">College/Office</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-600">PM Records</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-600">Percentage</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-600">Item Checklists</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-600">Attention</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr v-for="row in officeRows" :key="row.key" class="hover:bg-slate-50">
                                    <td class="px-5 py-4 text-sm font-medium text-slate-800">
                                        <div>{{ row.name }}</div>
                                        <div class="mt-2 h-2 w-full min-w-[220px] overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full bg-emerald-500" :style="{ width: barWidth(row.percentage) }"></div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-right text-sm text-slate-600">{{ formatNumber(row.count) }}</td>
                                    <td class="px-5 py-4 text-right text-sm font-semibold text-slate-800">{{ formatPercent(row.percentage) }}</td>
                                    <td class="px-5 py-4 text-right text-sm text-slate-600">{{ formatNumber(row.item_checklists) }}</td>
                                    <td class="px-5 py-4 text-right text-sm text-slate-600">
                                        {{ formatNumber(row.attention_item_checklists) }}
                                        <span class="text-xs text-slate-400">({{ formatPercent(row.attention_percentage) }})</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="px-5 py-8 text-center text-sm text-slate-500">No tagged records in this range.</div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h3 class="text-lg font-semibold text-slate-800">Department Counts</h3>
                    </div>
                    <div v-if="departmentRows.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-100">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Department</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-600">Records</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr v-for="row in departmentRows" :key="row.key" class="hover:bg-slate-50">
                                    <td class="px-5 py-4 text-sm text-slate-700">
                                        <div class="font-semibold">{{ row.department }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ row.college_office }}</div>
                                    </td>
                                    <td class="px-5 py-4 text-right text-sm text-slate-600">
                                        {{ formatNumber(row.count) }}
                                        <span class="text-xs text-slate-400">({{ formatPercent(row.percentage) }})</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="px-5 py-8 text-center text-sm text-slate-500">No department records in this range.</div>
                </section>
            </div>

            <div v-else-if="activeTab === 'users' && canViewUserReports" class="space-y-6">
                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Users</p>
                        <p class="mt-3 text-3xl font-bold text-slate-900">{{ formatNumber(userReportSummary.total_users) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Active Users</p>
                        <p class="mt-3 text-3xl font-bold text-emerald-700">{{ formatNumber(userReportSummary.active_users) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Inactive Users</p>
                        <p class="mt-3 text-3xl font-bold text-rose-700">{{ formatNumber(userReportSummary.inactive_users) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Admin Users</p>
                        <p class="mt-3 text-3xl font-bold text-amber-700">{{ formatNumber(userReportSummary.admin_users) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Staff/Regular Users</p>
                        <p class="mt-3 text-3xl font-bold text-sky-700">{{ formatNumber(userReportSummary.staff_users) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Users with Created PM Records</p>
                        <p class="mt-3 text-3xl font-bold text-purple-700">{{ formatNumber(userReportSummary.users_with_created_pm_records) }}</p>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h3 class="text-lg font-semibold text-slate-800">User Reports</h3>
                    </div>
                    <div v-if="userRows.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-100">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">User Name</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Email</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Role</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Status</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-600">Records Created</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-600">Records Updated</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Last Activity / Last Updated</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Created At</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr v-for="row in userRows" :key="row.id" class="hover:bg-slate-50">
                                    <td class="px-5 py-4 text-sm font-semibold text-slate-800">{{ row.name }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-600">{{ row.email }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-600">{{ row.role_label }}</td>
                                    <td class="px-5 py-4 text-sm">
                                        <span :class="['rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide', userStatusClass(row.is_active)]">
                                            {{ row.status_label }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-right text-sm text-slate-600">{{ formatNumber(row.records_created) }}</td>
                                    <td class="px-5 py-4 text-right text-sm text-slate-600">{{ formatNumber(row.records_updated) }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-600">{{ formatDateTime(row.last_activity_at) }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-600">{{ formatDateTime(row.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="px-5 py-8 text-center text-sm text-slate-500">No users found.</div>
                </section>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'PreventiveMaintenanceReport',
    props: {
        authUser: {
            type: Object,
            default: null,
        },
    },
    data() {
        return {
            report: null,
            loading: true,
            activeTab: 'pm',
            filters: {
                from: '',
                to: '',
                college_office_id: '',
                checklist_type: '',
            },
        };
    },
    computed: {
        summary() {
            return this.report?.summary || {
                total_preventive_maintenance: 0,
                tagged_preventive_maintenance: 0,
                tagged_percentage: 0,
                total_item_checklists: 0,
                attention_item_checklists: 0,
                attention_percentage: 0,
                office_coverage_count: 0,
                office_coverage_total: 0,
                office_coverage_percentage: 0,
            };
        },
        collegeOffices() {
            return this.report?.filters?.college_offices || [];
        },
        checklistTypes() {
            return this.report?.filters?.checklist_types || [];
        },
        officeRows() {
            return this.report?.college_offices || [];
        },
        commissionRows() {
            return this.report?.commission_statuses || [];
        },
        assetCategoryRows() {
            return this.report?.asset_categories || [];
        },
        itemStatusRows() {
            return this.report?.item_statuses || [];
        },
        departmentRows() {
            return this.report?.departments || [];
        },
        monthlyTrendRows() {
            return this.report?.monthly_trend || [];
        },
        canViewUserReports() {
            const canView = this.report?.permissions?.view_user_reports === true;
            return canView && ['superadmin', 'admin'].includes(this.authUser?.role);
        },
        reportTabs() {
            const tabs = [
                { key: 'pm', label: 'PM Records Report' },
                { key: 'office', label: 'College/Office Report' },
            ];

            if (this.canViewUserReports) {
                tabs.push({ key: 'users', label: 'User Reports' });
            }

            return tabs;
        },
        userReportSummary() {
            return this.report?.user_reports?.summary || {
                total_users: 0,
                active_users: 0,
                inactive_users: 0,
                admin_users: 0,
                staff_users: 0,
                users_with_created_pm_records: 0,
            };
        },
        userRows() {
            return this.report?.user_reports?.users || [];
        },
        hasActiveFilters() {
            return Boolean(this.filters.from || this.filters.to || this.filters.college_office_id || this.filters.checklist_type);
        },
        maxMonthlyVolume() {
            const max = this.monthlyTrendRows.reduce((highest, row) => {
                return Math.max(highest, Number(row.preventive_maintenance || 0), Number(row.item_checklists || 0));
            }, 0);

            return max > 0 ? max : 1;
        },
    },
    mounted() {
        this.fetchReport();
    },
    methods: {
        async fetchReport() {
            this.loading = true;

            try {
                const response = await axios.get('/api/reports/preventive-maintenance', {
                    params: this.reportParams(),
                });
                this.report = response.data;

                if (this.activeTab === 'users' && !this.canViewUserReports) {
                    this.activeTab = 'pm';
                }
            } catch (error) {
                console.error('Error loading report:', error);
                this.report = null;
            } finally {
                this.loading = false;
            }
        },
        reportParams() {
            return Object.entries(this.filters).reduce((params, [key, value]) => {
                if (value !== '' && value !== null && value !== undefined) {
                    params[key] = value;
                }

                return params;
            }, {});
        },
        resetFilters() {
            this.filters = {
                from: '',
                to: '',
                college_office_id: '',
                checklist_type: '',
            };
            this.fetchReport();
        },
        formatNumber(value) {
            return Number(value || 0).toLocaleString('en-US');
        },
        formatPercent(value) {
            const number = Number(value || 0);
            return `${number.toLocaleString('en-US', {
                minimumFractionDigits: Number.isInteger(number) ? 0 : 1,
                maximumFractionDigits: 1,
            })}%`;
        },
        formatDateTime(value) {
            return value || '-';
        },
        barWidth(value) {
            const number = Math.max(0, Math.min(100, Number(value || 0)));
            return `${number}%`;
        },
        trendWidth(value) {
            const number = Math.max(0, Number(value || 0));
            return `${Math.min(100, (number / this.maxMonthlyVolume) * 100)}%`;
        },
        tabButtonClass(key) {
            return [
                'rounded-lg px-4 py-2 text-sm font-semibold transition',
                this.activeTab === key
                    ? 'bg-slate-800 text-white shadow-sm'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
            ];
        },
        commissionClass(key) {
            return {
                active: 'bg-emerald-100 text-emerald-800',
                for_repair: 'bg-amber-100 text-amber-800',
                under_maintenance: 'bg-sky-100 text-sky-800',
                defective: 'bg-rose-100 text-rose-800',
                replaced: 'bg-purple-100 text-purple-800',
                decommissioned: 'bg-slate-200 text-slate-700',
                na: 'bg-slate-100 text-slate-600',
            }[key] || 'bg-slate-100 text-slate-600';
        },
        itemStatusClass(key) {
            return {
                ok: 'bg-emerald-100 text-emerald-800',
                repair: 'bg-amber-100 text-amber-800',
                na: 'bg-slate-100 text-slate-600',
                blank: 'bg-rose-100 text-rose-800',
            }[key] || 'bg-slate-100 text-slate-600';
        },
        itemStatusBarClass(key) {
            return {
                ok: 'bg-emerald-500',
                repair: 'bg-amber-500',
                na: 'bg-slate-400',
                blank: 'bg-rose-500',
            }[key] || 'bg-slate-400';
        },
        userStatusClass(isActive) {
            return isActive
                ? 'bg-emerald-100 text-emerald-800'
                : 'bg-slate-200 text-slate-700';
        },
    },
};
</script>
