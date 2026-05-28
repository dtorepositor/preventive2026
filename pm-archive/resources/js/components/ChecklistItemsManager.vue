<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-800">Checklist Items</h2>
                <p class="mt-1 text-sm text-slate-500">Enable or disable task rows used by maintenance item checklists.</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Checklist Type</label>
                <select v-model="checklistType" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" @change="fetchItems">
                    <option v-for="type in checklistTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                </select>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div v-if="loading" class="px-6 py-10 text-center text-sm text-slate-500">Loading checklist items...</div>
            <table v-else class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">No.</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Task</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Description</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-if="items.length === 0">
                        <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">No checklist items found.</td>
                    </tr>
                    <tr v-for="item in items" :key="item.id" class="hover:bg-slate-50">
                        <td class="px-5 py-4 text-sm font-semibold text-slate-700">{{ item.item_no }}</td>
                        <td class="px-5 py-4 text-sm font-semibold text-slate-800">{{ item.task }}</td>
                        <td class="px-5 py-4 text-sm text-slate-600">{{ item.description }}</td>
                        <td class="px-5 py-4 text-sm">
                            <span
                                :class="[
                                    'rounded-full px-2.5 py-1 text-xs font-semibold',
                                    item.enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'
                                ]"
                            >
                                {{ item.enabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-sm">
                            <button type="button" class="font-semibold text-amber-700 hover:underline" @click="toggleItem(item)">
                                {{ item.enabled ? 'Disable' : 'Enable' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'ChecklistItemsManager',
    data() {
        return {
            loading: true,
            checklistType: 'pc',
            checklistTypes: [
                { value: 'pc', label: 'PC' },
                { value: 'server', label: 'Server' },
                { value: 'ip_phone', label: 'IP Phone' },
                { value: 'network_device', label: 'Network Device' },
                { value: 'wifi', label: 'WiFi' },
                { value: 'ups', label: 'UPS' },
                { value: 'cctv', label: 'CCTV' },
            ],
            items: [],
        };
    },
    mounted() {
        this.fetchItems();
    },
    methods: {
        async fetchItems() {
            this.loading = true;
            try {
                const response = await axios.get('/api/checklist-items', {
                    params: { checklist_type: this.checklistType },
                });
                this.items = response.data?.items || [];
            } finally {
                this.loading = false;
            }
        },
        async toggleItem(item) {
            const action = item.enabled ? 'disable' : 'enable';
            await axios.patch(`/api/item-checklist-items/${item.id}/${action}`, null, {
                params: { checklist_type: this.checklistType },
            });
            await this.fetchItems();
        },
    },
};
</script>
