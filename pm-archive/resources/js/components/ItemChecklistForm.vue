<template>
    <div>
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
            <h2 class="text-2xl font-bold text-slate-800">{{ formTitle }}</h2>
            <p class="text-slate-600 text-sm mt-1">{{ assetTypeLabel }}: {{ preventiveMaintenance?.asset_name || preventiveMaintenance?.pc_name || '—' }} · To be filled by Technician attending to ICT Equipment.</p>
            </div>
        </div>

        <form @submit.prevent="submitForm">
            <!-- Maintenance Date / Month -->
            <div class="mb-4 flex gap-4 flex-wrap">
                <template v-if="usesMaintenanceMonth">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">For the Month of</label>
                        <input v-model="form.maintenance_month" type="month" disabled class="rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed">
                    </div>
                </template>
                <template v-else>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Maintenance Date</label>
                        <input v-model="form.maintenance_date" type="date" disabled class="rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed">
                    </div>
                </template>
                <div v-if="displayedIdentifier">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Identifier</label>
                    <div class="rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm font-semibold uppercase tracking-wide text-slate-600">
                        {{ displayedIdentifier }}
                    </div>
                </div>
                <div class="min-w-[320px] max-w-2xl flex-1">
                    <label class="block text-sm font-medium text-slate-700 mb-1">View Tagged Plan</label>
                    <div class="flex flex-wrap gap-2 sm:flex-nowrap">
                        <div class="relative min-w-0 flex-1" @click.stop>
                            <button
                                type="button"
                                :disabled="taggedPlansLoading || !matchingTaggedPlans.length"
                                class="flex w-full min-w-0 items-center justify-between rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                                @click="toggleTaggedPlanDropdown"
                            >
                                <span class="min-w-0 flex-1 truncate pr-4">
                                    {{ selectedTaggedPlansLabel }}
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div
                                v-if="openTaggedPlanDropdown"
                                class="absolute left-0 right-0 z-30 mt-2 overflow-hidden rounded-xl border border-slate-300 bg-white shadow-xl"
                            >
                                <div class="border-b border-slate-200 p-3">
                                    <input
                                        v-model.trim="taggedPlanSearch"
                                        type="text"
                                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                        placeholder="Search tagged plans"
                                    >
                                </div>

                                <div v-if="filteredMatchingTaggedPlans.length" class="max-h-64 overflow-y-auto py-1">
                                    <button
                                        v-for="plan in filteredMatchingTaggedPlans"
                                        :key="plan.id"
                                        type="button"
                                        class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-slate-100"
                                        @click="toggleTaggedPlanSelection(plan.id)"
                                    >
                                        <span class="block min-w-0 pr-3 text-slate-700 truncate">{{ taggedPlanOptionLabel(plan) }}</span>
                                        <span v-if="isTaggedPlanSelected(plan.id)" class="text-slate-900">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                    </button>
                                </div>

                                <div v-else class="px-3 py-4 text-sm text-slate-600">
                                    No tagged plan matches this search.
                                </div>
                            </div>
                        </div>
                        <button
                            type="button"
                            :disabled="!selectedTaggedPlans.length"
                            @click="openTaggedPlanModal"
                            class="rounded-lg border border-emerald-600 px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:border-slate-300 disabled:text-slate-400"
                        >
                            View
                        </button>
                    </div>
                    <p v-if="preventiveMaintenanceOffice" class="mt-2 text-xs text-slate-500">
                        Matching plans for {{ preventiveMaintenanceOffice }}.
                    </p>
                </div>
                <div class="min-w-[220px]">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Commission Status</label>
                    <select
                        v-model="form.commission_status"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                    >
                        <option
                            v-for="option in commissionStatusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Checklist Table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase w-16">{{ checklistTableLabels.item }}</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">{{ checklistTableLabels.task }}</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">{{ checklistTableLabels.description }}</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold text-slate-600 uppercase w-20">{{ statusLabels.ok }}</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold text-slate-600 uppercase w-20">{{ statusLabels.repair }}</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold text-slate-600 uppercase w-20">{{ statusLabels.na }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <template v-for="group in groupedEntries" :key="group.item_no">
                                <tr v-for="(desc, idx) in group.descriptions" :key="desc.entry_index" class="hover:bg-slate-50">
                                    <template v-if="idx === 0">
                                        <td class="px-3 py-2 text-sm text-slate-600 font-medium" :rowspan="group.descriptions.length">{{ group.item_no }}</td>
                                        <td class="px-3 py-2 text-sm font-medium text-slate-900" :rowspan="group.descriptions.length">{{ group.task }}</td>
                                    </template>
                                    <td class="px-3 py-2 text-sm text-slate-600">{{ desc.description }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <input
                                            v-model="form[`item_${desc.entry_index}_status`]"
                                            :name="`item_${desc.entry_index}_status`"
                                            type="radio"
                                            value="ok"
                                            class="w-4 h-4 cursor-pointer"
                                        >
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <input
                                            v-model="form[`item_${desc.entry_index}_status`]"
                                            :name="`item_${desc.entry_index}_status`"
                                            type="radio"
                                            value="repair"
                                            class="w-4 h-4 cursor-pointer"
                                        >
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <input
                                            v-model="form[`item_${desc.entry_index}_status`]"
                                            :name="`item_${desc.entry_index}_status`"
                                            type="radio"
                                            value="na"
                                            class="w-4 h-4 cursor-pointer"
                                        >
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary/Recommendation -->
            <div v-if="summaryEnabled" class="mb-6">
                <label class="block text-sm font-medium text-slate-700 mb-2">Summary/Recommendation</label>
                <textarea v-model="form.summary_recommendation" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-emerald-500 resize-none whitespace-pre-wrap break-words [overflow-wrap:anywhere]"></textarea>
            </div>

            <!-- Signature Fields for IP Phone -->
            <div v-if="isIpPhoneChecklist" class="grid grid-cols-1 gap-6 mb-6 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Technical Staff</label>
                    <input v-model="form.checked_by" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">DTO Chief</label>
                    <input type="text" :value="directorName" disabled class="w-full rounded-lg border border-slate-300 px-3 py-2 bg-slate-100 text-slate-600 cursor-not-allowed">
                </div>
            </div>

            <!-- Signature Fields for Server and Network Device -->
            <div v-else-if="isServerChecklist || isNetworkDeviceChecklist || isWifiChecklist || isUpsChecklist || isCctvChecklist" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Technical Staff</label>
                    <input v-model="form.checked_by" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Director</label>
                    <input type="text" :value="directorName" disabled class="w-full rounded-lg border border-slate-300 px-3 py-2 bg-slate-100 text-slate-600 cursor-not-allowed">
                </div>
            </div>

            <!-- Signature Fields for Other Types -->
            <div v-else class="grid gap-6 mb-6" :class="{ 'grid-cols-1 md:grid-cols-2': !isNetworkDeviceChecklist }">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Checked by</label>
                    <input v-model="form.checked_by" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Conforme</label>
                    <input v-model="form.conforme_by" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <!-- Submit buttons -->
            <div class="flex gap-3">
                <button
                    type="submit"
                    :disabled="isSubmitting"
                    class="px-4 py-2 bg-black text-white text-sm font-medium hover:bg-gray-800 disabled:cursor-not-allowed disabled:bg-slate-500"
                >
                    {{ isSubmitting ? (isEdit ? 'Updating...' : 'Creating...') : (isEdit ? 'Update' : 'Create') + ' Item Checklist' }}
                </button>
                <router-link :to="`/preventive-maintenance/${preventiveMaintenanceId}`" class="px-4 py-2 border border-black text-sm hover:bg-gray-100">Cancel</router-link>
            </div>
        </form>

        <div v-if="taggedPlanModalOpen && selectedTaggedPlans.length" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 px-4 py-4" @click.self="closeTaggedPlanModal">
            <div class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-800">Tagged Preventive Maintenance Plans</h3>
                        <p class="text-sm text-slate-500">{{ selectedTaggedPlans.length }} selected plan{{ selectedTaggedPlans.length === 1 ? '' : 's' }}</p>
                    </div>
                    <button type="button" @click="closeTaggedPlanModal" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div class="overflow-y-auto px-6 py-5">
                    <div class="mb-4 grid gap-3 md:grid-cols-1">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Office / College</p>
                            <p class="mt-1 text-sm font-medium text-slate-800">{{ preventiveMaintenanceOffice || 'Not set' }}</p>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <section v-for="plan in paginatedSelectedTaggedPlans" :key="plan.id" class="overflow-hidden rounded-xl border border-slate-200">
                            <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                                <h4 class="text-base font-semibold text-slate-800">{{ plan.name }}</h4>
                                <p class="text-sm text-slate-500">{{ plan.year }} · {{ categoryLabel(plan.schedule_category) }}</p>
                            </div>
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-100">
                                    <tr>
                                        <th class="w-48 px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">Schedule Period</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">Tagged College / Office</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <tr v-for="period in matchingPlanPeriods(plan)" :key="`${plan.id}-${period.value}`" class="bg-emerald-50/70">
                                        <td class="px-4 py-3 align-top text-sm font-medium text-slate-800">
                                            <div>{{ period.label }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-600">
                                            <div v-if="period.matchedOffices.length" class="flex flex-wrap gap-2">
                                                <span
                                                    v-for="office in period.matchedOffices"
                                                    :key="`${plan.id}-${period.value}-${office}`"
                                                    class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800"
                                                >
                                                    {{ office }}
                                                </span>
                                            </div>
                                            <span v-else class="text-slate-400">No tagged office.</span>
                                        </td>
                                    </tr>
                                    <tr v-if="!matchingPlanPeriods(plan).length">
                                        <td colspan="2" class="px-4 py-4 text-sm text-slate-400">No matching tagged period for this checklist.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </section>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-slate-200 px-6 py-4">
                    <div v-if="selectedTaggedPlansLastPage > 1" class="flex items-center gap-3 text-sm text-slate-600">
                        <span>Page {{ selectedTaggedPlansPage }} of {{ selectedTaggedPlansLastPage }}</span>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-2 font-medium text-slate-600 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="selectedTaggedPlansPage <= 1"
                            @click="selectedTaggedPlansPage -= 1"
                        >
                            Previous
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-2 font-medium text-slate-600 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="selectedTaggedPlansPage >= selectedTaggedPlansLastPage"
                            @click="selectedTaggedPlansPage += 1"
                        >
                            Next
                        </button>
                    </div>
                    <button type="button" @click="closeTaggedPlanModal" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

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

const commissionStatusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'for_repair', label: 'For Repair' },
    { value: 'under_maintenance', label: 'Under Maintenance' },
    { value: 'defective', label: 'Defective' },
    { value: 'replaced', label: 'Replaced' },
    { value: 'decommissioned', label: 'Decommissioned' },
    { value: 'na', label: 'N/A' },
];

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

const TAGGED_PLANS_PER_PAGE = 5;

export default {
    name: 'ItemChecklistForm',
    props: {
        preventiveMaintenanceId: [String, Number],
        itemChecklistId: [String, Number],
        isEdit: Boolean,
    },
    data() {
        return {
            form: {
                maintenance_date: '',
                maintenance_month: '',
                summary_recommendation: '',
                checked_by: '',
                conforme_by: '',
                noted_by: '',
                commission_status: 'active',
            },
            commissionStatusOptions,
            currentIdentifier: null,
            preventiveMaintenance: null,
            entriesFlat: [],
            groupedEntries: [],
            summaryEnabled: true,
            taggedPlansLoading: false,
            taggedPlans: [],
            selectedTaggedPlanIds: [],
            selectedTaggedPlansPage: 1,
            taggedPlanModalOpen: false,
            taggedPlanSearch: '',
            openTaggedPlanDropdown: false,
            isSubmitting: false,
        };
    },
    computed: {
        isServerChecklist() {
            return this.preventiveMaintenance?.checklist_type === 'server';
        },
        isIpPhoneChecklist() {
            return this.preventiveMaintenance?.checklist_type === 'ip_phone';
        },
        isNetworkDeviceChecklist() {
            return this.preventiveMaintenance?.checklist_type === 'network_device';
        },
        isWifiChecklist() {
            return this.preventiveMaintenance?.checklist_type === 'wifi';
        },
        isUpsChecklist() {
            return this.preventiveMaintenance?.checklist_type === 'ups';
        },
        isCctvChecklist() {
            return this.preventiveMaintenance?.checklist_type === 'cctv';
        },
        usesMaintenanceMonth() {
            return this.isServerChecklist || this.isIpPhoneChecklist || this.isNetworkDeviceChecklist || this.isWifiChecklist || this.isUpsChecklist || this.isCctvChecklist;
        },
        usesNotedBy() {
            return this.isServerChecklist || this.isIpPhoneChecklist || this.isNetworkDeviceChecklist || this.isWifiChecklist || this.isUpsChecklist || this.isCctvChecklist;
        },
        directorName() {
            return 'Carlo Matin A. Sarausa';
        },
        formTitle() {
            return this.isServerChecklist
                ? 'PREVENTIVE MAINTENANCE CHECKLIST FOR SERVERS/DATACENTER'
                : this.isIpPhoneChecklist
                    ? 'IP PHONE ITEM CHECKLIST'
                    : this.isNetworkDeviceChecklist
                        ? 'NETWORK DEVICE PREVENTIVE MAINTENANCE CHECKLIST'
                        : this.isWifiChecklist
                            ? 'WIFI PREVENTIVE MAINTENANCE CHECKLIST'
                            : this.isUpsChecklist
                                ? 'UPS PREVENTIVE MAINTENANCE CHECKLIST'
                                : this.isCctvChecklist
                                    ? 'CCTV PREVENTIVE MAINTENANCE CHECKLIST'
                        : 'ITEM CHECKLIST';
        },
        assetTypeLabel() {
            if (this.preventiveMaintenance?.checklist_type === 'server') {
                return 'Server';
            }

            if (this.preventiveMaintenance?.checklist_type === 'ip_phone') {
                return 'IP Phone';
            }

            if (this.preventiveMaintenance?.checklist_type === 'network_device') {
                return 'Network Device';
            }

            if (this.preventiveMaintenance?.checklist_type === 'wifi') {
                return 'WiFi';
            }

            if (this.preventiveMaintenance?.checklist_type === 'ups') {
                return 'UPS';
            }

            if (this.preventiveMaintenance?.checklist_type === 'cctv') {
                return 'CCTV';
            }

            return 'PC';
        },
        statusLabels() {
            if (this.isServerChecklist) {
                return {
                    ok: 'Good',
                    repair: 'Near Maintenance',
                    na: 'N/A',
                };
            }

            if (this.isIpPhoneChecklist) {
                return {
                    ok: 'Yes',
                    repair: 'No',
                    na: 'N/A',
                };
            }

            if (this.isNetworkDeviceChecklist || this.isWifiChecklist || this.isUpsChecklist || this.isCctvChecklist) {
                return {
                    ok: 'Good',
                    repair: 'Near Maintenance',
                    na: 'N/A',
                };
            }

            return {
                ok: 'OK',
                repair: 'Repair',
                na: 'N/A',
            };
        },
        checklistTableLabels() {
            if (this.isServerChecklist || this.isIpPhoneChecklist || this.isNetworkDeviceChecklist || this.isWifiChecklist || this.isUpsChecklist || this.isCctvChecklist) {
                return {
                    item: 'Item',
                    task: 'Maintenance',
                    description: 'Specification',
                };
            }

            return {
                item: 'Item #',
                task: 'Task',
                description: 'Description',
            };
        },
        preventiveMaintenanceOffice() {
            return String(this.preventiveMaintenance?.office_college || '').trim();
        },
        matchingTaggedPlans() {
            const office = this.normalizeOfficeName(this.preventiveMaintenanceOffice);

            if (!office) {
                return [];
            }

            return this.taggedPlans
                .filter((plan) => this.planIncludesOffice(plan, office))
                .sort((left, right) => {
                    if ((right.year || 0) !== (left.year || 0)) {
                        return (right.year || 0) - (left.year || 0);
                    }

                    return String(left.name || '').localeCompare(String(right.name || ''));
                });
        },
        filteredMatchingTaggedPlans() {
            const searchTerm = String(this.taggedPlanSearch || '').trim().toLowerCase();

            if (!searchTerm) {
                return this.matchingTaggedPlans;
            }

            return this.matchingTaggedPlans.filter((plan) =>
                this.taggedPlanOptionLabel(plan).toLowerCase().includes(searchTerm)
            );
        },
        selectedTaggedPlans() {
            const selectedIds = new Set(this.selectedTaggedPlanIds.map((id) => String(id)));
            return this.matchingTaggedPlans.filter((plan) => selectedIds.has(String(plan.id)));
        },
        selectedTaggedPlansLastPage() {
            return Math.max(1, Math.ceil(this.selectedTaggedPlans.length / TAGGED_PLANS_PER_PAGE));
        },
        paginatedSelectedTaggedPlans() {
            const startIndex = (this.selectedTaggedPlansPage - 1) * TAGGED_PLANS_PER_PAGE;
            return this.selectedTaggedPlans.slice(startIndex, startIndex + TAGGED_PLANS_PER_PAGE);
        },
        selectedTaggedPlansLabel() {
            if (this.taggedPlansLoading) {
                return 'Loading tagged plans...';
            }

            if (!this.matchingTaggedPlans.length) {
                return 'No tagged plans for this office/college';
            }

            if (!this.selectedTaggedPlans.length) {
                return 'Select tagged plans';
            }

            if (this.selectedTaggedPlans.length === 1) {
                return this.taggedPlanOptionLabel(this.selectedTaggedPlans[0]);
            }

            if (this.selectedTaggedPlans.length === 2) {
                return this.selectedTaggedPlans.map((plan) => plan.name).join(', ');
            }

            return `${this.selectedTaggedPlans[0].name}, ${this.selectedTaggedPlans[1].name} +${this.selectedTaggedPlans.length - 2} more`;
        },
        displayedIdentifier() {
            return this.currentIdentifier;
        },
    },
    mounted() {
        this.fetchData();
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
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: message,
                });
            } else {
                alert(message);
            }
        },
        normalizeOfficeName(value) {
            return String(value || '').trim().toLowerCase();
        },
        normalizeCategory(category) {
            return scheduleCategories.some((option) => option.value === category)
                ? category
                : 'monthly';
        },
        categoryLabel(category) {
            const normalizedCategory = this.normalizeCategory(category);
            return scheduleCategories.find((option) => option.value === normalizedCategory)?.label || 'Monthly';
        },
        schedulePeriodsForCategory(category) {
            return schedulePeriodsByCategory[this.normalizeCategory(category)] || schedulePeriodsByCategory.monthly;
        },
        normalizeScheduleMap(scheduleMap = {}) {
            return months.reduce((map, month) => {
                const offices = Array.isArray(scheduleMap[month.value])
                    ? scheduleMap[month.value]
                    : Array.isArray(scheduleMap[String(month.value)])
                        ? scheduleMap[String(month.value)]
                        : [];

                map[month.value] = [...new Set(
                    offices
                        .map((office) => String(office || '').trim())
                        .filter(Boolean)
                )].sort((left, right) => left.localeCompare(right));

                return map;
            }, {});
        },
        planIncludesOffice(plan, normalizedOffice) {
            const scheduleMap = this.normalizeScheduleMap(plan.schedule_map || {});

            return Object.values(scheduleMap).some((offices) =>
                offices.some((office) => this.normalizeOfficeName(office) === normalizedOffice)
            );
        },
        taggedPlanOptionLabel(plan) {
            return `${plan.name} (${plan.year} · ${this.categoryLabel(plan.schedule_category)})`;
        },
        isTaggedPlanSelected(planId) {
            return this.selectedTaggedPlanIds.includes(String(planId));
        },
        toggleTaggedPlanSelection(planId) {
            const normalizedId = String(planId);

            if (this.isTaggedPlanSelected(normalizedId)) {
                this.selectedTaggedPlanIds = this.selectedTaggedPlanIds.filter((id) => id !== normalizedId);
                this.ensureTaggedPlansPaginationBounds();
                return;
            }

            this.selectedTaggedPlanIds = [...this.selectedTaggedPlanIds, normalizedId];
            this.ensureTaggedPlansPaginationBounds();
        },
        toggleTaggedPlanDropdown() {
            if (this.taggedPlansLoading || !this.matchingTaggedPlans.length) {
                return;
            }

            this.openTaggedPlanDropdown = !this.openTaggedPlanDropdown;
        },
        handleDocumentClick() {
            this.openTaggedPlanDropdown = false;
        },
        planPeriods(plan) {
            const normalizedScheduleMap = this.normalizeScheduleMap(plan.schedule_map || {});
            const currentOffice = this.normalizeOfficeName(this.preventiveMaintenanceOffice);

            return this.schedulePeriodsForCategory(plan.schedule_category).map((period) => {
                const offices = [...new Set(
                    period.months.flatMap((month) => normalizedScheduleMap[month] || [])
                )].sort((left, right) => left.localeCompare(right));
                const matchedOffices = offices.filter((office) => this.normalizeOfficeName(office) === currentOffice);

                return {
                    ...period,
                    offices,
                    matchedOffices,
                    matchesOffice: matchedOffices.length > 0,
                };
            });
        },
        matchingPlanPeriods(plan) {
            return this.planPeriods(plan).filter((period) => period.matchesOffice);
        },
        async fetchTaggedPlans() {
            const office = this.normalizeOfficeName(this.preventiveMaintenanceOffice);

            this.taggedPlans = [];
            this.selectedTaggedPlanIds = [];
            this.selectedTaggedPlansPage = 1;
            this.taggedPlanSearch = '';
            this.openTaggedPlanDropdown = false;

            if (!office) {
                return;
            }

            this.taggedPlansLoading = true;

            try {
                const response = await axios.get('/api/preventive-maintenance-plans', {
                    params: {
                        page: 1,
                        per_page: 100,
                    },
                });

                this.taggedPlans = Array.isArray(response.data?.data)
                    ? response.data.data.map((plan) => ({
                        ...plan,
                        schedule_category: this.normalizeCategory(plan.schedule_category),
                        schedule_map: this.normalizeScheduleMap(plan.schedule_map || {}),
                    }))
                    : [];

                if (this.matchingTaggedPlans.length === 1) {
                    this.selectedTaggedPlanIds = [String(this.matchingTaggedPlans[0].id)];
                }

                this.ensureTaggedPlansPaginationBounds();
            } catch (error) {
                console.error('Error fetching tagged plans:', error);
                this.showError('Failed to load tagged preventive maintenance plans');
            } finally {
                this.taggedPlansLoading = false;
            }
        },
        openTaggedPlanModal() {
            if (!this.selectedTaggedPlans.length) {
                return;
            }

            this.selectedTaggedPlansPage = 1;
            this.taggedPlanModalOpen = true;
        },
        closeTaggedPlanModal() {
            this.taggedPlanModalOpen = false;
        },
        ensureTaggedPlansPaginationBounds() {
            if (this.selectedTaggedPlansPage > this.selectedTaggedPlansLastPage) {
                this.selectedTaggedPlansPage = this.selectedTaggedPlansLastPage;
            }

            if (this.selectedTaggedPlansPage < 1) {
                this.selectedTaggedPlansPage = 1;
            }
        },
        async fetchData() {
            try {
                const response = await axios.get(`/api/item-checklist-entries/${this.preventiveMaintenanceId}`);
                this.preventiveMaintenance = response.data.preventiveMaintenance;
                this.form.maintenance_date = response.data.default_maintenance_date || this.currentDateString();
                this.form.maintenance_month = response.data.default_maintenance_month || this.currentMonthString();
                this.entriesFlat = response.data.entries || [];
                this.groupedEntries = response.data.grouped_entries || [];
                this.summaryEnabled = response.data.summary_enabled !== false;
                await this.fetchTaggedPlans();
                
                // Initialize form with entries using entry_index to align with backend
                this.entriesFlat.forEach((entry) => {
                    const key = `item_${entry.entry_index}_status`;
                    if (typeof this.$set === 'function') {
                        this.$set(this.form, key, '');
                    } else {
                        this.form[key] = '';
                    }
                });
                
                if (this.isEdit && this.itemChecklistId) {
                    const checklistRes = await axios.get(`/api/item-checklist/${this.itemChecklistId}`);
                    const data = checklistRes.data;
                    this.currentIdentifier = data.identifier || null;
                    this.summaryEnabled = data.summary_enabled !== false;
                    this.form.maintenance_date = data.maintenance_date || response.data.default_maintenance_date || this.currentDateString();
                    this.form.maintenance_month = data.maintenance_month || response.data.default_maintenance_month || this.currentMonthString();
                    this.form.summary_recommendation = data.summary_recommendation || '';
                    this.form.checked_by = data.checked_by || '';
                    this.form.conforme_by = data.conforme_by || '';
                    this.form.noted_by = data.noted_by || '';
                    this.form.commission_status = data.commission_status || 'active';
                    this.entriesFlat = data.entries || [];
                    this.groupedEntries = data.grouped_entries || [];
                    (this.entriesFlat || []).forEach((entry) => {
                        const key = `item_${entry.entry_index}_status`;
                        if (typeof this.$set === 'function') {
                            this.$set(this.form, key, entry.status || '');
                        } else {
                            this.form[key] = entry.status || '';
                        }
                    });
                }
            } catch (error) {
                console.error('Error fetching data:', error);
                this.showError('Failed to load item checklist data');
            }
        },
        currentDateString() {
            const date = new Date();
            date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
            return date.toISOString().slice(0, 10);
        },
        currentMonthString() {
            return this.currentDateString().slice(0, 7);
        },
        async submitForm() {
            if (this.isSubmitting) {
                return;
            }

            this.isSubmitting = true;

            try {
                const url = this.isEdit
                    ? `/api/item-checklist/${this.itemChecklistId}`
                    : `/api/item-checklist`;
                const method = this.isEdit ? 'PUT' : 'POST';
                
                const payload = {
                    ...this.form,
                    preventive_maintenance_id: this.preventiveMaintenanceId,
                };

                if (this.usesNotedBy) {
                    payload.noted_by = payload.noted_by || this.directorName;
                }

                delete payload.maintenance_date;
                delete payload.maintenance_month;

                const response = await axios({
                    method,
                    url,
                    data: payload,
                });

                const msg = this.isEdit
                    ? 'Item checklist updated successfully.'
                    : 'Item checklist created successfully.';

                await this.showSuccess(msg);
                await this.$router.push(`/preventive-maintenance/${this.preventiveMaintenanceId}`);
            } catch (error) {
                console.error('Error submitting form:', error);
                this.showError('Failed to save item checklist');
                this.isSubmitting = false;
            }
        },
    },
};
</script>
