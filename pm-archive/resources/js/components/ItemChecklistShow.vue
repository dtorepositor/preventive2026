<template>
    <div>
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-slate-800">{{ isServerChecklist ? 'Server Item Checklist' : isIpPhoneChecklist ? 'IP Phone Item Checklist' : isNetworkDeviceChecklist ? 'Network Device Item Checklist' : isWifiChecklist ? 'WiFi Item Checklist' : isUpsChecklist ? 'UPS Item Checklist' : isCctvChecklist ? 'CCTV Item Checklist' : 'Item Checklist' }}</h2>
                <p class="text-slate-600 text-sm mt-1">
                    {{ assetTypeLabel }}: {{ preventiveMaintenance?.asset_name || preventiveMaintenance?.pc_name || preventiveMaintenance?.name || '—' }} · {{ displayScheduleLabel }}
                    <span v-if="itemChecklist.identifier" class="font-semibold uppercase tracking-wide text-slate-500">· {{ itemChecklist.identifier }}</span>
                </p>
            </div>
            <div class="flex gap-2">
                <router-link :to="`/preventive-maintenance/${preventiveMaintenanceId}`" class="px-4 py-2 text-slate-600 hover:underline">Back to checklist</router-link>
            </div>
        </div>

        <div v-if="loading" class="text-center py-8">
            <p class="text-slate-600">Loading item checklist...</p>
        </div>

        <div v-else class="space-y-6">
            <!-- Summary info -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 min-w-0">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm min-w-0">
                    <div v-if="usesMaintenanceMonth" class="min-w-0">
                        <dt class="text-slate-500">For the Month of</dt>
                        <dd class="font-medium break-words">{{ formatMonth(itemChecklist.maintenance_month) }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-slate-500">Commission Status</dt>
                        <dd class="font-medium">{{ commissionStatusLabel(itemChecklist.commission_status) }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-slate-500">Checked by</dt>
                        <dd class="font-medium">{{ itemChecklist.checked_by || '—' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-slate-500">{{ usesNotedBy ? 'Noted by' : 'Conforme' }}</dt>
                        <dd class="font-medium">{{ usesNotedBy ? (itemChecklist.noted_by || '—') : (itemChecklist.conforme_by || '—') }}</dd>
                    </div>
                    <div v-if="!isServerChecklist && !isIpPhoneChecklist && itemChecklist.summary_recommendation && itemChecklist.summary_enabled !== false" class="md:col-span-2 min-w-0">
                        <dt class="text-slate-500">Summary/Recommendation</dt>
                        <dd class="mt-1 whitespace-pre-wrap break-words [overflow-wrap:anywhere]">{{ itemChecklist.summary_recommendation }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Checklist items table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase w-16">{{ checklistTableLabels.item }}</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">{{ checklistTableLabels.task }}</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 uppercase">{{ checklistTableLabels.description }}</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold text-slate-600 uppercase w-24">Status</th>
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
                                        <span v-if="desc.status === 'ok'" class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">{{ statusLabels.ok }}</span>
                                        <span v-else-if="desc.status === 'repair'" class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">{{ statusLabels.repair }}</span>
                                        <span v-else-if="desc.status === 'na'" class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600">N/A</span>
                                        <span v-else class="text-slate-400">—</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Back button at bottom right -->
            <div class="flex justify-end">
                <router-link :to="`/preventive-maintenance/${preventiveMaintenanceId}`" class="px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 font-medium">Back to checklist</router-link>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'ItemChecklistShow',
    props: {
        preventiveMaintenanceId: [String, Number],
        itemChecklistId: [String, Number],
    },
    data() {
        return {
            itemChecklist: {
                entries: [],
                grouped_entries: [],
            },
            preventiveMaintenance: null,
            loading: true,
            groupedEntries: [],
        };
    },
    mounted() {
        this.fetchData();
    },
    computed: {
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
        statusLabels() {
            if (this.isServerChecklist) {
                return {
                    ok: 'Good',
                    repair: 'Near Maintenance',
                };
            }

            if (this.isIpPhoneChecklist) {
                return {
                    ok: 'Yes',
                    repair: 'No',
                };
            }

            if (this.isNetworkDeviceChecklist || this.isWifiChecklist || this.isUpsChecklist || this.isCctvChecklist) {
                return {
                    ok: 'Good',
                    repair: 'Near Maintenance',
                };
            }

            return {
                ok: 'OK',
                repair: 'Repair',
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
        displayScheduleLabel() {
            if (this.usesMaintenanceMonth) {
                return this.formatMonth(this.itemChecklist.maintenance_month);
            }

            return this.formatDate(this.itemChecklist.maintenance_date);
        },
    },
    methods: {
        async fetchData() {
            try {
                const [checklistRes, pmRes] = await Promise.all([
                    axios.get(`/api/item-checklist/${this.itemChecklistId}`),
                    axios.get(`/api/preventive-maintenance/${this.preventiveMaintenanceId}`).catch(() => ({ data: {} })),
                ]);
                this.itemChecklist = checklistRes.data;
                this.groupedEntries = checklistRes.data.grouped_entries || [];
                this.preventiveMaintenance = pmRes.data;
            } catch (error) {
                console.error('Error fetching item checklist:', error);
            } finally {
                this.loading = false;
            }
        },
        formatDate(date) {
            if (!date) return '—';
            return new Date(date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        },
        formatMonth(value) {
            if (!value) return '—';

            const date = new Date(`${value}-01T00:00:00`);
            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
            });
        },
        commissionStatusLabel(value) {
            return {
                active: 'Active',
                for_repair: 'For Repair',
                under_maintenance: 'Under Maintenance',
                defective: 'Defective',
                replaced: 'Replaced',
                decommissioned: 'Decommissioned',
                na: 'N/A',
            }[value] || 'Active';
        },
        
    },
    
};
</script>
