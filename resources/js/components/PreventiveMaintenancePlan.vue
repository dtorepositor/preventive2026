<template>
    <div>
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-slate-800">Preventive Maintenance Plan</h2>
            <button
                type="button"
                @click="openCreateModal"
                class="px-4 py-2 rounded-lg font-medium text-white"
                style="background-color: #fbc008; box-shadow: 0 6px 12px rgba(0,0,0,0.08);"
            >
                + New Preventive Plan
            </button>
        </div>

        <div v-if="loading" class="text-center py-8">
            <p class="text-slate-600">Loading preventive maintenance plans...</p>
        </div>

        <div v-else-if="plans.length === 0" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 text-center">
            <p class="text-slate-600 mb-4">No preventive maintenance plans found.</p>
            <button type="button" @click="openCreateModal" class="font-semibold" style="color: #d99b00;">
                Create your first preventive maintenance plan
            </button>
        </div>

        <div v-else class="bg-white rounded-xl shadow-sm border border-slate-200">
            <div class="overflow-x-auto md:overflow-visible">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Plan Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Year</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Tagged Colleges / Offices</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr
                        v-for="plan in plans"
                        :key="plan.id"
                        :class="[
                            'hover:bg-slate-50',
                            plan.is_deleted ? 'bg-red-50/60 text-slate-400' : ''
                        ]"
                    >
                        <td class="px-6 py-4 text-sm font-semibold" :class="plan.is_deleted ? 'line-through text-slate-400' : 'text-slate-900'">
                            <div class="flex items-center gap-2">
                                <span>{{ plan.name }}</span>
                                <span v-if="plan.is_deleted" class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-red-600">Deleted</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm" :class="plan.is_deleted ? 'line-through text-slate-400' : 'text-slate-600'">{{ plan.year }}</td>
                        <td class="px-6 py-4 text-sm">
                            <span
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                                :class="plan.is_deleted ? 'bg-slate-100 text-slate-400' : 'bg-amber-100 text-amber-800'"
                            >
                                {{ categoryLabel(plan.schedule_category) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">
                            <div class="font-medium" :class="plan.is_deleted ? 'line-through text-slate-400' : 'text-slate-700'">
                                {{ plan.tag_count }} tag{{ plan.tag_count === 1 ? '' : 's' }} across {{ plan.months_used }} month{{ plan.months_used === 1 ? '' : 's' }}
                            </div>
                            <div class="text-xs mt-1" :class="plan.is_deleted ? 'line-through text-slate-400' : 'text-slate-500'">{{ schedulePreview(plan.schedule_map, plan.schedule_category) }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="flex flex-wrap gap-3">
                                <button type="button" @click="openEditModal(plan)" :disabled="plan.is_deleted" :class="plan.is_deleted ? 'cursor-not-allowed text-slate-300' : 'text-blue-600 hover:underline'">Edit</button>
                                <button
                                    v-if="!plan.is_deleted"
                                    type="button"
                                    @click="deletePlan(plan)"
                                    class="text-red-600 hover:underline"
                                >
                                    Delete
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    @click="recoverPlan(plan)"
                                    class="text-green-600 hover:underline"
                                >
                                    Recover
                                </button>
                                <button type="button" @click="openScheduleModal(plan)" :disabled="plan.is_deleted" :class="plan.is_deleted ? 'cursor-not-allowed text-slate-300' : 'text-purple-600 hover:underline'">Set Schedule</button>
                                <div class="relative" @click.stop>
                                    <button
                                        v-if="!plan.is_deleted"
                                        type="button"
                                        @click.stop="togglePrintDropdown(plan.id)"
                                        class="px-2 py-1 text-xs font-medium text-slate-700 bg-slate-100 rounded hover:bg-slate-200 flex items-center gap-1"
                                    >
                                        Print
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    <div
                                        v-if="!plan.is_deleted && openPrintDropdownPlanId === plan.id"
                                        class="absolute right-0 top-full mt-1 w-48 bg-white rounded-lg shadow-lg border border-slate-200 py-2 z-50"
                                    >
                                        <button
                                            type="button"
                                            @click="printPlan(plan, 'word')"
                                            class="w-full text-left px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
                                        >
                                            Print as Word
                                        </button>
                                        <button
                                            type="button"
                                            @click="printPlan(plan, 'pdf')"
                                            class="w-full text-left px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
                                        >
                                            Print as PDF
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>

            <div v-if="pagination.lastPage > 1" class="flex items-center justify-between border-t border-slate-200 px-6 py-4">
                <p class="text-sm text-slate-600">
                    Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }} plans
                </p>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="pagination.currentPage <= 1 || loading"
                        @click="fetchPlans(pagination.currentPage - 1)"
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
                        @click="fetchPlans(pagination.currentPage + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <div v-if="planModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 px-4" @click.self="handlePlanModalBackdropClick">
            <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                <div v-if="savingPlan" class="absolute inset-0 z-10 flex items-center justify-center rounded-2xl bg-white/75 backdrop-blur-[1px]">
                    <div class="flex items-center gap-3 rounded-full border border-amber-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm">
                        <svg class="h-5 w-5 animate-spin text-amber-500" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        Saving plan...
                    </div>
                </div>
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-slate-800">{{ editingPlanId ? 'Edit Plan' : 'New Plan' }}</h3>
                    <button type="button" @click="closePlanModal" :disabled="savingPlan" class="text-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50">✕</button>
                </div>

                <form class="space-y-4 px-6 py-5" @submit.prevent="savePlan">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Plan Name</label>
                        <input
                            v-model.trim="planForm.name"
                            type="text"
                            :disabled="savingPlan"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200"
                            placeholder="Enter plan name"
                            required
                        >
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Year</label>
                        <div class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                            {{ planForm.year || currentPlanYear() }}
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Category</label>
                        <select
                            v-model="planForm.scheduleCategory"
                            :disabled="savingPlan"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200"
                            required
                        >
                            <option v-for="category in scheduleCategories" :key="category.value" :value="category.value">
                                {{ category.label }}
                            </option>
                        </select>
                        <p class="mt-2 text-xs text-slate-500">
                            The set schedule screen will use this category to show monthly, quarterly, half quarter, or yearly schedule rows.
                        </p>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="closePlanModal" :disabled="savingPlan" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">Cancel</button>
                        <button type="submit" :disabled="savingPlan" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-70" style="background-color: #fbc008;">
                            <svg v-if="savingPlan" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            {{ savingPlan ? 'Saving...' : 'Save Plan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="scheduleModalOpen && activePlan" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 px-4 py-4 sm:px-6" @click.self="handleScheduleModalBackdropClick">
            <div class="relative flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div v-if="savingSchedule" class="absolute inset-0 z-30 flex items-center justify-center rounded-2xl bg-white/65 backdrop-blur-[1px]">
                    <div class="flex items-center gap-3 rounded-full border border-amber-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm">
                        <svg class="h-5 w-5 animate-spin text-amber-500" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        Saving schedule...
                    </div>
                </div>
                <div class="sticky top-0 z-20 flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-800">Set Schedule</h3>
                        <p class="text-sm text-slate-500">{{ activePlan.name }} · {{ activePlan.year }} · {{ categoryLabel(activePlan.schedule_category) }}</p>
                    </div>
                    <button type="button" @click="closeScheduleModal" :disabled="savingSchedule" class="text-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50">✕</button>
                </div>

                <div class="overflow-y-auto px-6 py-5">
                    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-slate-700">
                        Select one or more colleges or offices for each {{ scheduleUnitLabel(activePlan.schedule_category) }}. Use the search box to filter the available options.
                    </div>

                    <div class="max-h-[58vh] overflow-y-auto rounded-xl border border-slate-200">
                        <table class="w-full table-fixed border-collapse">
                            <thead class="sticky top-0 z-10">
                                <tr>
                                    <th class="w-44 px-4 py-3 text-left text-sm font-bold border-2 border-slate-700 bg-yellow-400">Schedule Period</th>
                                    <th class="px-3 py-3 text-left text-sm font-bold border-2 border-slate-700 bg-yellow-400">Colleges / Offices</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="period in currentSchedulePeriods" :key="`schedule-${period.value}`">
                                    <td class="sticky left-0 z-[1] w-44 px-4 py-2 align-top text-sm font-bold border-2 border-slate-700 bg-yellow-300">
                                        <div>{{ period.label }}</div>
                                        <div v-if="period.description" class="mt-1 text-xs font-medium text-slate-700">{{ period.description }}</div>
                                    </td>
                                    <td class="min-w-0 px-3 py-3 border-2 border-slate-700 bg-lime-400">
                                        <div class="flex flex-wrap gap-2 mb-3" v-if="scheduleDraft[period.value]?.length">
                                            <span
                                                v-for="office in scheduleDraft[period.value]"
                                                :key="`${period.value}-${office}`"
                                                class="inline-flex max-w-full items-center gap-2 rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-slate-700 shadow-sm"
                                            >
                                                <span class="truncate">{{ office }}</span>
                                                <button type="button" class="text-red-500 hover:text-red-700" @click="removeOffice(period.value, office)">✕</button>
                                            </span>
                                        </div>
                                        <div v-else class="mb-3 text-sm text-slate-700">No tagged colleges/offices yet.</div>

                                        <div class="mb-3 flex items-center justify-between gap-3">
                                            <label :for="`office-search-${period.value}`" class="text-xs font-semibold uppercase tracking-wide text-slate-700">
                                                Search Offices
                                            </label>
                                            <span class="text-xs font-medium text-slate-700">
                                                {{ scheduleDraft[period.value]?.length || 0 }} selected
                                            </span>
                                        </div>

                                        <div class="flex flex-col gap-3">
                                            <div class="relative" @click.stop>
                                                <button
                                                    type="button"
                                                    class="flex w-full min-w-0 items-center justify-between rounded-xl border border-slate-300 bg-white px-3 py-2 text-left text-sm text-slate-700 shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200"
                                                    @click="togglePeriodDropdown(period.value)"
                                                >
                                                    <span class="min-w-0 flex-1 truncate pr-4">
                                                        {{ selectedOfficesLabel(period.value) }}
                                                    </span>
                                                    <svg class="h-4 w-4 shrink-0 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </button>

                                                <div
                                                    v-if="openOfficeDropdownPeriod === period.value"
                                                    class="absolute left-0 right-0 z-30 mt-2 overflow-hidden rounded-xl border border-slate-300 bg-white shadow-xl"
                                                >
                                                    <div class="border-b border-slate-200 p-3">
                                                        <input
                                                            :id="`office-search-${period.value}`"
                                                            v-model.trim="officeSearches[period.value]"
                                                            type="text"
                                                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200"
                                                            placeholder="Search colleges or offices"
                                                        >
                                                    </div>

                                                    <div v-if="filteredOfficeOptions(period.value).length" class="max-h-64 overflow-y-auto py-1">
                                                        <button
                                                            v-for="office in filteredOfficeOptions(period.value)"
                                                            :key="`${period.value}-option-${office}`"
                                                            type="button"
                                                            class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-slate-100"
                                                            @click="toggleOfficeSelection(period.value, office)"
                                                        >
                                                            <span class="block min-w-0 pr-3 text-slate-700 truncate">{{ office }}</span>
                                                            <span v-if="isOfficeSelected(period.value, office)" class="text-slate-900">
                                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                                </svg>
                                                            </span>
                                                        </button>
                                                    </div>

                                                    <div v-else class="px-3 py-4 text-sm text-slate-600">
                                                        No office matches this search.
                                                    </div>
                                                </div>
                                            </div>

                                        </div>

                                        <div v-if="availableOfficeCount(period.value)" class="mt-3 text-xs text-slate-700">
                                            Showing {{ filteredOfficeOptions(period.value).length }} of {{ availableOfficeCount(period.value) }} available offices.
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="sticky bottom-0 z-20 mt-auto flex justify-end gap-3 border-t border-slate-200 bg-white px-6 py-4">
                    <button type="button" @click="closeScheduleModal" :disabled="savingSchedule" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">Cancel</button>
                    <button type="button" @click="saveSchedule" :disabled="savingSchedule" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-70" style="background-color: #fbc008;">
                            <svg v-if="savingSchedule" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            {{ savingSchedule ? 'Saving...' : 'Save Schedule' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { appUrl } from '../auth';

const months = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

const scheduleCategories = [
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'half_quarter', label: 'Half Quarter' },
    { value: 'yearly', label: 'Yearly' },
];

const monthLabelMap = months.reduce((map, month) => {
    map[month.value] = month.label;
    return map;
}, {});

const schedulePeriodsByCategory = {
    monthly: months.map((month) => ({
        value: month.value,
        label: month.label,
        months: [month.value],
    })),
    quarterly: [
        { value: 'quarter_1', label: '1st Quarter', description: 'January to March', months: [1, 2, 3] },
        { value: 'quarter_2', label: '2nd Quarter', description: 'April to June', months: [4, 5, 6] },
        { value: 'quarter_3', label: '3rd Quarter', description: 'July to September', months: [7, 8, 9] },
        { value: 'quarter_4', label: '4th Quarter', description: 'October to December', months: [10, 11, 12] },
    ],
    half_quarter: [
        { value: 'half_quarter_1', label: '1st Quarter', description: 'January to June', months: [1, 2, 3, 4, 5, 6] },
        { value: 'half_quarter_2', label: '2nd Quarter', description: 'July to December', months: [7, 8, 9, 10, 11, 12] },
    ],
    yearly: [
        { value: 'yearly', label: 'Yearly', description: 'January to December', months: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] },
    ],
};

export default {
    name: 'PreventiveMaintenancePlan',
    data() {
        return {
            loading: true,
            plans: [],
            officeSuggestions: [],
            pagination: {
                currentPage: 1,
                lastPage: 1,
                perPage: 5,
                total: 0,
                from: 0,
                to: 0,
            },
            months,
            scheduleCategories,
            planModalOpen: false,
            scheduleModalOpen: false,
            savingPlan: false,
            savingSchedule: false,
            editingPlanId: null,
            activePlan: null,
            currentYear: null,
            planForm: {
                name: '',
                year: null,
                scheduleCategory: 'monthly',
            },
            scheduleDraft: {},
            officeSearches: {},
            openOfficeDropdownPeriod: null,
            openPrintDropdownPlanId: null,
        };
    },
    computed: {
        currentSchedulePeriods() {
            const category = this.activePlan?.schedule_category || this.planForm.scheduleCategory;
            return this.schedulePeriodsForCategory(category);
        },
    },
    mounted() {
        this.initialize();
        document.addEventListener('click', this.handleDocumentClick);
    },
    beforeUnmount() {
        document.removeEventListener('click', this.handleDocumentClick);
    },
    methods: {
        showSuccess(message) {
            if (window.Swal) {
                return Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: message,
                });
            }

            alert(message);
            return Promise.resolve();
        },
        showError(message) {
            if (window.Swal) {
                return Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: message,
                });
            }

            alert(message);
            return Promise.resolve();
        },
        async confirmAction(title, text, confirmButtonText) {
            if (window.Swal) {
                const result = await Swal.fire({
                    title,
                    text,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    confirmButtonText,
                });

                return result.isConfirmed;
            }

            return window.confirm(text);
        },
        async initialize() {
            await this.fetchPlans(1);
        },
        currentPlanYear() {
            return Number(this.currentYear) || new Date().getFullYear();
        },
        normalizeCategory(category) {
            return this.scheduleCategories.some((option) => option.value === category)
                ? category
                : 'monthly';
        },
        categoryLabel(category) {
            const normalizedCategory = this.normalizeCategory(category);
            return this.scheduleCategories.find((option) => option.value === normalizedCategory)?.label || 'Monthly';
        },
        scheduleUnitLabel(category) {
            return this.normalizeCategory(category) === 'monthly' ? 'month' : 'schedule period';
        },
        createEmptyScheduleMap() {
            return this.months.reduce((map, month) => {
                map[month.value] = [];
                return map;
            }, {});
        },
        schedulePeriodsForCategory(category) {
            return schedulePeriodsByCategory[this.normalizeCategory(category)] || schedulePeriodsByCategory.monthly;
        },
        createEmptyScheduleDraft(category = 'monthly') {
            return this.schedulePeriodsForCategory(category).reduce((map, period) => {
                map[period.value] = [];
                return map;
            }, {});
        },
        normalizeScheduleMap(scheduleMap = {}) {
            const normalized = this.createEmptyScheduleMap();

            this.months.forEach((month) => {
                const offices = Array.isArray(scheduleMap[month.value])
                    ? scheduleMap[month.value]
                    : Array.isArray(scheduleMap[String(month.value)])
                        ? scheduleMap[String(month.value)]
                        : [];

                normalized[month.value] = [...new Set(
                    offices
                        .map((office) => String(office || '').trim())
                        .filter(Boolean)
                )].sort((a, b) => a.localeCompare(b));
            });

            return normalized;
        },
        scheduleDraftFromMap(scheduleMap = {}, category = 'monthly') {
            const normalizedMap = this.normalizeScheduleMap(scheduleMap);
            const draft = this.createEmptyScheduleDraft(category);

            this.schedulePeriodsForCategory(category).forEach((period) => {
                draft[period.value] = [...new Set(
                    period.months.flatMap((month) => normalizedMap[month] || [])
                )].sort((left, right) => left.localeCompare(right));
            });

            return draft;
        },
        createOfficeSearches(category = 'monthly') {
            return this.schedulePeriodsForCategory(category).reduce((map, period) => {
                map[period.value] = '';
                return map;
            }, {});
        },
        officeOptionsForPeriod(periodKey) {
            return [...new Set([
                ...this.officeSuggestions,
                ...(this.scheduleDraft[periodKey] || []),
            ])].sort((left, right) => left.localeCompare(right));
        },
        filteredOfficeOptions(periodKey) {
            const searchTerm = String(this.officeSearches[periodKey] || '').trim().toLowerCase();
            const options = this.officeOptionsForPeriod(periodKey);

            if (!searchTerm) {
                return options;
            }

            return options.filter((office) => office.toLowerCase().includes(searchTerm));
        },
        availableOfficeCount(periodKey) {
            return this.officeOptionsForPeriod(periodKey).length;
        },
        selectedOfficesLabel(periodKey) {
            const offices = this.scheduleDraft[periodKey] || [];

            if (!offices.length) {
                return 'Select colleges or offices';
            }

            if (offices.length === 1) {
                return offices[0];
            }

            if (offices.length === 2) {
                return `${offices[0]}, ${offices[1]}`;
            }

            return `${offices[0]}, ${offices[1]} +${offices.length - 2} more`;
        },
        isOfficeSelected(periodKey, office) {
            return (this.scheduleDraft[periodKey] || []).includes(office);
        },
        toggleOfficeSelection(periodKey, office) {
            const current = this.scheduleDraft[periodKey] || [];

            if (current.includes(office)) {
                this.scheduleDraft[periodKey] = current.filter((item) => item !== office);
                return;
            }

            this.scheduleDraft[periodKey] = [...current, office].sort((left, right) => left.localeCompare(right));
        },
        togglePeriodDropdown(periodKey) {
            this.openOfficeDropdownPeriod = this.openOfficeDropdownPeriod === periodKey ? null : periodKey;
        },
        handleDocumentClick() {
            this.openOfficeDropdownPeriod = null;
            this.openPrintDropdownPlanId = null;
        },
        togglePrintDropdown(planId) {
            this.openPrintDropdownPlanId = this.openPrintDropdownPlanId === planId ? null : planId;
        },
        sortPlans() {
            this.plans = [...this.plans].sort((a, b) => {
                if (b.year !== a.year) {
                    return b.year - a.year;
                }

                return a.name.localeCompare(b.name);
            });
        },
        updatePagination(payload = {}) {
            this.pagination = {
                currentPage: Number(payload.current_page || 1),
                lastPage: Number(payload.last_page || 1),
                perPage: Number(payload.per_page || this.pagination.perPage || 5),
                total: Number(payload.total || 0),
                from: Number(payload.from || 0),
                to: Number(payload.to || 0),
            };
        },
        async fetchPlans(page = 1) {
            this.loading = true;

            try {
                const response = await axios.get('/api/preventive-maintenance-plans', {
                    params: {
                        page,
                        per_page: this.pagination.perPage,
                    },
                });
                const plans = Array.isArray(response.data?.data) ? response.data.data : [];
                const officeSuggestions = Array.isArray(response.data?.office_suggestions) ? response.data.office_suggestions : [];
                const currentYear = Number(response.data?.current_year);

                if (currentYear) {
                    this.currentYear = currentYear;
                }

                this.updatePagination(response.data);

                if (!plans.length && this.pagination.currentPage > this.pagination.lastPage && this.pagination.lastPage > 0) {
                    await this.fetchPlans(this.pagination.lastPage);
                    return;
                }

                this.plans = plans.map((plan) => ({
                    ...plan,
                    schedule_category: this.normalizeCategory(plan.schedule_category),
                    schedule_map: this.normalizeScheduleMap(plan.schedule_map),
                    is_deleted: Boolean(plan.is_deleted),
                }));
                this.officeSuggestions = officeSuggestions
                    .map((office) => String(office || '').trim())
                    .filter(Boolean)
                    .sort((a, b) => a.localeCompare(b));
                this.sortPlans();
            } catch (error) {
                console.error('Error fetching preventive maintenance plans:', error);
            } finally {
                this.loading = false;
            }
        },
        openCreateModal() {
            this.editingPlanId = null;
            this.planForm = {
                name: '',
                year: this.currentPlanYear(),
                scheduleCategory: 'monthly',
            };
            this.planModalOpen = true;
        },
        openEditModal(plan) {
            if (plan.is_deleted) {
                return;
            }

            this.editingPlanId = plan.id;
            this.planForm = {
                name: plan.name,
                year: plan.year,
                scheduleCategory: this.normalizeCategory(plan.schedule_category),
            };
            this.planModalOpen = true;
        },
        handlePlanModalBackdropClick() {
            if (this.savingPlan) {
                return;
            }

            this.closePlanModal();
        },
        closePlanModal(force = false) {
            if (this.savingPlan && !force) {
                return;
            }

            this.planModalOpen = false;
            this.editingPlanId = null;
        },
        async savePlan() {
            this.savingPlan = true;

            try {
                const isEditing = Boolean(this.editingPlanId);
                const payload = {
                    name: this.planForm.name,
                    schedule_category: this.normalizeCategory(this.planForm.scheduleCategory),
                };

                isEditing
                    ? await axios.put(`/api/preventive-maintenance-plans/${this.editingPlanId}`, payload)
                    : await axios.post('/api/preventive-maintenance-plans', payload);

                const targetPage = isEditing ? this.pagination.currentPage : 1;
                await this.fetchPlans(targetPage);
                this.savingPlan = false;
                this.closePlanModal(true);
                await this.showSuccess(isEditing ? 'Plan updated successfully.' : 'Plan created successfully.');
            } catch (error) {
                console.error('Error saving plan:', error);
                await this.showError(error.response?.data?.message || 'Unable to save the plan.');
            } finally {
                this.savingPlan = false;
            }
        },
        openScheduleModal(plan) {
            if (plan.is_deleted) {
                return;
            }

            this.activePlan = {
                ...plan,
                schedule_category: this.normalizeCategory(plan.schedule_category),
                schedule_map: this.normalizeScheduleMap(plan.schedule_map),
            };
            this.scheduleDraft = this.scheduleDraftFromMap(plan.schedule_map, plan.schedule_category);
            this.officeSearches = this.createOfficeSearches(plan.schedule_category);
            this.openOfficeDropdownPeriod = null;
            this.scheduleModalOpen = true;
        },
        handleScheduleModalBackdropClick() {
            if (this.savingSchedule) {
                return;
            }

            this.closeScheduleModal();
        },
        closeScheduleModal(force = false) {
            if (this.savingSchedule && !force) {
                return;
            }

            this.scheduleModalOpen = false;
            this.activePlan = null;
            this.scheduleDraft = {};
            this.officeSearches = {};
            this.openOfficeDropdownPeriod = null;
        },
        removeOffice(periodKey, office) {
            this.scheduleDraft[periodKey] = (this.scheduleDraft[periodKey] || []).filter((item) => item !== office);
        },
        buildSchedulePayload(scheduleDraft = {}, category = 'monthly') {
            const monthScheduleMap = this.createEmptyScheduleMap();

            this.schedulePeriodsForCategory(category).forEach((period) => {
                const offices = [...new Set(
                    (scheduleDraft[period.value] || [])
                        .map((office) => String(office || '').trim())
                        .filter(Boolean)
                )].sort((left, right) => left.localeCompare(right));

                period.months.forEach((month) => {
                    monthScheduleMap[month] = offices;
                });
            });

            return this.months.map((month) => ({
                month: month.value,
                offices: monthScheduleMap[month.value] || [],
            }));
        },
        async saveSchedule() {
            if (!this.activePlan) {
                return;
            }

            this.savingSchedule = true;

            try {
                const payload = {
                    schedule: this.buildSchedulePayload(this.scheduleDraft, this.activePlan.schedule_category),
                };

                await axios.put(`/api/preventive-maintenance-plans/${this.activePlan.id}/schedule`, payload);
                await this.fetchPlans(this.pagination.currentPage);
                this.savingSchedule = false;
                this.closeScheduleModal(true);
                await this.showSuccess('Schedule saved successfully.');
            } catch (error) {
                console.error('Error saving schedule:', error);
                await this.showError(error.response?.data?.message || 'Unable to save the schedule.');
            } finally {
                this.savingSchedule = false;
            }
        },
        async deletePlan(plan) {
            const confirmed = await this.confirmAction(
                'Delete this plan?',
                `Delete the plan "${plan.name}"? This action cannot be undone.`,
                'Yes, delete it'
            );
            if (!confirmed) {
                return;
            }

            try {
                const targetPage = this.plans.length === 1 && this.pagination.currentPage > 1
                    ? this.pagination.currentPage - 1
                    : this.pagination.currentPage;

                await axios.delete(`/api/preventive-maintenance-plans/${plan.id}`);
                await this.fetchPlans(targetPage);
                await this.showSuccess('Plan deleted successfully.');
            } catch (error) {
                console.error('Error deleting plan:', error);
                await this.showError(error.response?.data?.message || 'Unable to delete the plan.');
            }
        },
        async recoverPlan(plan) {
            const confirmed = await this.confirmAction(
                'Recover this plan?',
                `Recover the plan "${plan.name}"?`,
                'Yes, recover it'
            );
            if (!confirmed) {
                return;
            }

            try {
                await axios.post(`/api/preventive-maintenance-plans/${plan.id}/restore`);
                await this.fetchPlans(this.pagination.currentPage);
                await this.showSuccess('Plan recovered successfully.');
            } catch (error) {
                console.error('Error recovering plan:', error);
                await this.showError(error.response?.data?.message || 'Unable to recover the plan.');
            }
        },
        printPlan(plan, format = 'pdf') {
            this.openPrintDropdownPlanId = null;
            const normalizedFormat = format === 'word' ? 'word' : 'pdf';
            const url = `/api/preventive-maintenance-plans/${plan.id}/print?format=${normalizedFormat}`;
            window.open(appUrl(url), '_blank');
        },
        schedulePreview(scheduleMap = {}, category = 'monthly') {
            const previews = this.schedulePeriodsForCategory(category)
                .map((period) => {
                    const offices = [...new Set(
                        period.months.flatMap((month) => Array.isArray(scheduleMap[month]) ? scheduleMap[month] : [])
                    )];

                    if (!offices.length) {
                        return null;
                    }

                    return `${period.label}: ${offices.join(', ')}`;
                })
                .filter(Boolean);

            if (!previews.length) {
                return 'No schedule set yet.';
            }

            return previews.slice(0, 2).join(' • ');
        },
    },
};
</script>
