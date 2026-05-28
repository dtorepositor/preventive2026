<template>
    <div>
        <div class="flex justify-between items-center mb-6">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-bold text-slate-800">Preventive Maintenance Checklist</h2>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">{{ checklist.checklist_type_label || 'PC' }}</span>
                </div>
                <p class="text-slate-600 text-sm mt-1">{{ checklist.asset_name || checklist.pc_name || 'No asset name' }} · {{ formatDate(checklist.checklist_date) }}</p>
            </div>
            <div class="flex gap-2">
                <router-link v-if="canEditCurrentChecklist" :to="`/preventive-maintenance/${checklist.psm_id}/edit`" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 font-medium">Edit</router-link>
                <router-link to="/preventive-maintenance" class="px-4 py-2 text-slate-600 hover:underline">Back to list</router-link>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_400px] gap-6">
            <!-- Left: Checklist details -->
            <div class="space-y-6">
        <!-- User and Asset -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">User and Asset</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-slate-500">User/Operator</dt>
                    <dd class="font-medium">{{ checklist.user_operator || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Office/College</dt>
                    <dd class="font-medium">{{ checklist.office_college || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Department</dt>
                    <dd class="font-medium">{{ checklist.department || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Date Acquired</dt>
                    <dd class="font-medium">{{ formatDate(checklist.date_acquired) || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">{{ checklist.asset_label || 'PC Name' }}</dt>
                    <dd class="font-medium">{{ checklist.asset_name || checklist.pc_name || '—' }}</dd>
                </div>
                <template v-if="isIpPhoneChecklist">
                    <div>
                        <dt class="text-slate-500">Brand Name</dt>
                        <dd class="font-medium">{{ checklist.brand_name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Model Name</dt>
                        <dd class="font-medium">{{ checklist.model_name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Serial Number</dt>
                        <dd class="font-medium">{{ checklist.serial_number || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">MAC Address</dt>
                        <dd class="font-medium">{{ checklist.mac_address || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Location / Office Located</dt>
                        <dd class="font-medium">{{ checklist.office_located || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">IP Address Tagged</dt>
                        <dd class="font-medium">{{ checklist.ip_address_tagged || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">VLAN</dt>
                        <dd class="font-medium">{{ checklist.vlan || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Telephone Number</dt>
                        <dd class="font-medium">{{ checklist.telephone_number || '—' }}</dd>
                    </div>
                </template>
            </dl>
        </div>

        <div v-if="maintenancePhotoUrls.length" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-lg font-semibold text-slate-800">Photos</h3>
                <button
                    type="button"
                    @click="openPhotoModal(0)"
                    class="px-4 py-2 text-sm font-semibold text-blue-700 bg-blue-50 rounded-lg border border-blue-100 hover:bg-blue-100"
                >
                    View Photos
                </button>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                <button
                    v-for="(url, index) in maintenancePhotoUrls"
                    :key="`${index}-${url}`"
                    type="button"
                    class="aspect-[4/3] overflow-hidden rounded border border-slate-200 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-300"
                    @click="openPhotoModal(index)"
                >
                    <img :src="url" :alt="`Maintenance photo ${index + 1}`" class="h-full w-full object-contain">
                </button>
            </div>
        </div>

        <!-- Network Device Fields -->
        <div v-if="isNetworkDeviceChecklist" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Network Device Information</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-slate-500">Category Type</dt>
                    <dd class="font-medium">{{ checklist.network_device_category_type || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Product Name</dt>
                    <dd class="font-medium">{{ checklist.network_device_product_name || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Model Name</dt>
                    <dd class="font-medium">{{ checklist.network_device_model_name || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Serial Number</dt>
                    <dd class="font-medium">{{ checklist.network_device_serial || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">MAC Address</dt>
                    <dd class="font-medium">{{ checklist.network_device_mac_address || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Office Location</dt>
                    <dd class="font-medium">{{ checklist.network_device_office_location || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">IP Address</dt>
                    <dd class="font-medium">{{ checklist.network_device_ip_address || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">VLAN</dt>
                    <dd class="font-medium">{{ checklist.network_device_vlan || '—' }}</dd>
                </div>
            </dl>
        </div>

        <div v-if="isWifiChecklist" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">WiFi Information</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-slate-500">Category Type</dt>
                    <dd class="font-medium">{{ checklist.wifi_category_type || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Product Name</dt>
                    <dd class="font-medium">{{ checklist.wifi_product_name || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Model Name</dt>
                    <dd class="font-medium">{{ checklist.wifi_model_name || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Serial</dt>
                    <dd class="font-medium">{{ checklist.wifi_serial || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">MAC Address</dt>
                    <dd class="font-medium">{{ checklist.wifi_mac_address || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Office Located</dt>
                    <dd class="font-medium">{{ checklist.wifi_office_location || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">IP Address</dt>
                    <dd class="font-medium">{{ checklist.wifi_ip_address || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">VLAN</dt>
                    <dd class="font-medium">{{ checklist.wifi_vlan || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">WiFi Name</dt>
                    <dd class="font-medium">{{ checklist.wifi_name || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">WiFi Password</dt>
                    <dd class="font-medium">{{ checklist.wifi_password || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Channel Supported</dt>
                    <dd class="font-medium">{{ checklist.wifi_channel_supported || '—' }}</dd>
                </div>
            </dl>
        </div>

        <div v-if="isUpsChecklist" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">UPS Information</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-slate-500">Category</dt>
                    <dd class="font-medium">{{ checklist.ups_category || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Brand Name</dt>
                    <dd class="font-medium">{{ checklist.ups_brand_name || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Model Name</dt>
                    <dd class="font-medium">{{ checklist.ups_model_name || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">MAC Address</dt>
                    <dd class="font-medium">{{ checklist.ups_mac_address || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Serial</dt>
                    <dd class="font-medium">{{ checklist.ups_serial || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Total Power or Capacity</dt>
                    <dd class="font-medium">{{ checklist.ups_total_power_capacity || '—' }}</dd>
                </div>
            </dl>
        </div>

        <div v-if="isCctvChecklist" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">CCTV Information</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-slate-500">Category Type</dt>
                    <dd class="font-medium">{{ checklist.cctv_category_type || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Product Name</dt>
                    <dd class="font-medium">{{ checklist.cctv_product_name || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Model Name</dt>
                    <dd class="font-medium">{{ checklist.cctv_model_name || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Serial</dt>
                    <dd class="font-medium">{{ checklist.cctv_serial || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">MAC Address</dt>
                    <dd class="font-medium">{{ checklist.cctv_mac_address || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Office Located</dt>
                    <dd class="font-medium">{{ checklist.cctv_office_location || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">IP Address</dt>
                    <dd class="font-medium">{{ checklist.cctv_ip_address || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">VLAN</dt>
                    <dd class="font-medium">{{ checklist.cctv_vlan || '—' }}</dd>
                </div>
            </dl>
        </div>

        <!-- Equipment & OS & Software -->
        <div v-if="!isIpPhoneChecklist && !isNetworkDeviceChecklist && !isWifiChecklist && !isUpsChecklist && !isCctvChecklist" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Equipment &amp; OS &amp; Software</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="md:col-span-2">
                    <dt class="text-slate-500 mb-2">Equipment</dt>
                    <dd>
                        <div class="flex flex-wrap gap-2">
                            <template v-for="item in equipment" :key="item.id">
                                <template v-if="checklist[getFieldName('equipment', item.name)]">
                                    <span class="inline-flex px-3 py-1.5 bg-slate-100 text-slate-700 rounded-md font-medium">{{ item.name }}</span>
                                </template>
                            </template>
                            <template v-if="checklist.equipment_others && checklist.equipment_others_specify">
                                <span class="inline-flex px-3 py-1.5 bg-slate-100 text-slate-700 rounded-md font-medium">Others: {{ checklist.equipment_others_specify }}</span>
                            </template>
                            <template v-if="!hasAnyEquipment">
                                <span class="text-slate-400">—</span>
                            </template>
                        </div>
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500 mb-2">OS</dt>
                    <dd>
                        <div class="flex flex-wrap gap-2">
                            <template v-for="item in operatingSystems" :key="item.id">
                                <template v-if="checklist[getFieldName('os', item.name)]">
                                    <span class="inline-flex px-3 py-1.5 bg-slate-100 text-slate-700 rounded-md font-medium">{{ item.name }}</span>
                                </template>
                            </template>
                            <template v-if="checklist.os_others && checklist.os_others_specify">
                                <span class="inline-flex px-3 py-1.5 bg-slate-100 text-slate-700 rounded-md font-medium">Others: {{ checklist.os_others_specify }}</span>
                            </template>
                            <template v-if="!hasAnyOs">
                                <span class="text-slate-400">—</span>
                            </template>
                        </div>
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500 mb-2">Software</dt>
                    <dd>
                        <div class="flex flex-wrap gap-2">
                            <template v-for="item in softwareApplications" :key="item.id">
                                <template v-if="checklist[getFieldName('software', item.name)]">
                                    <span class="inline-flex px-3 py-1.5 bg-slate-100 text-slate-700 rounded-md font-medium">{{ item.name }}</span>
                                </template>
                            </template>
                            <template v-if="checklist.software_others && checklist.software_others_specify">
                                <span class="inline-flex px-3 py-1.5 bg-slate-100 text-slate-700 rounded-md font-medium">Others: {{ checklist.software_others_specify }}</span>
                            </template>
                            <template v-if="!hasAnySoftware">
                                <span class="text-slate-400">—</span>
                            </template>
                        </div>
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Specifications -->
        <div v-if="!isIpPhoneChecklist && !isNetworkDeviceChecklist && !isWifiChecklist && !isUpsChecklist && !isCctvChecklist" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Specifications</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                <template v-for="field in specificationFields" :key="field.id">
                    <template v-if="field.name !== 'ip_address'">
                        <template v-if="field.name === 'mac_address'">
                            <template v-if="checklist[field.name] || checklist.ip_address">
                                <div>
                                    <dt class="text-slate-500">{{ field.label }} & IP</dt>
                                    <dd>{{ checklist[field.name] || '—' }}{{ checklist.ip_address ? ' / ' + checklist.ip_address : '' }}</dd>
                                </div>
                            </template>
                        </template>
                        <template v-else>
                            <template v-if="checklist[field.name]">
                                <div>
                                    <dt class="text-slate-500">{{ field.label }}</dt>
                                    <dd>{{ checklist[field.name] }}</dd>
                                </div>
                            </template>
                        </template>
                    </template>
                </template>
            </dl>
        </div>
            </div>

            <!-- Right: Item checklists list -->
            <div class="lg:order-2">
                <div class="space-y-6 lg:sticky lg:top-6 lg:self-start">
                <div class="overflow-visible rounded-xl bg-white shadow-sm border border-slate-200">
                    <div class="border-b border-slate-200 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-800">Item Checklists</h3>
                                <p class="mt-1 text-xs font-medium text-slate-500">
                                    {{ itemChecklists.length }} {{ itemChecklists.length === 1 ? 'record' : 'records' }}
                                </p>
                            </div>
                            <button
                                v-if="canDeleteItemChecklists"
                                type="button"
                                @click="toggleAllItemChecklistsLock"
                                :class="[
                                    'shrink-0 px-3 py-1.5 text-xs font-semibold rounded-lg flex items-center gap-2 border',
                                    itemChecklistsLocked
                                        ? 'text-slate-600 bg-slate-100 border-slate-200'
                                        : 'text-emerald-700 bg-emerald-50 border-emerald-100'
                                ]"
                                :title="itemChecklistsLocked ? 'Editing and deletion are locked' : 'Editing and deletion are enabled'"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path v-if="itemChecklistsLocked" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 2h12a1 1 0 001-1v-6a1 1 0 00-1-1h-1V9a5 5 0 10-10 0v2H7a1 1 0 00-1 1v6a1 1 0 001 1z" />
                                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8V7a5 5 0 10-10 0v1m-1 4h12a2 2 0 012 2v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                                </svg>
                                <span>{{ itemChecklistsLocked ? 'Locked' : 'Unlocked' }}</span>
                            </button>
                        </div>
                        <button
                            type="button"
                            :disabled="creatingItemChecklist"
                            class="mt-4 flex w-full items-center justify-center rounded-lg border border-purple-100 bg-purple-50 px-3 py-2 text-sm font-semibold text-purple-700 hover:bg-purple-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                            @click="goToCreateItemChecklist"
                        >
                            {{ creatingItemChecklist ? 'Opening...' : 'Create Item Checklist' }}
                        </button>
                    </div>

                    <div class="p-5">
                        <div v-if="itemChecklists.length === 0" class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                            No item checklists yet.
                        </div>
                        <ul v-else class="space-y-3">
                            <li
                                v-for="ic in itemChecklists"
                                :key="ic.psm_id"
                                class="rounded-lg border border-slate-200 bg-slate-50/70 p-4"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p v-if="ic.identifier" class="break-words text-sm font-bold uppercase leading-snug text-slate-700">
                                            {{ ic.identifier }}
                                        </p>
                                        <p v-else class="text-sm font-bold text-slate-700">Item checklist</p>
                                        <p class="mt-1 text-xs font-medium text-slate-500">
                                            {{ formatDate(ic.maintenance_date || ic.created_at) || 'No date' }}
                                            <span v-if="ic.maintenance_time"> at {{ ic.maintenance_time }}</span>
                                        </p>
                                        <p class="mt-1 text-xs font-semibold text-slate-600">
                                            {{ ic.commission_status_label || commissionStatusLabel(ic.commission_status) }}
                                        </p>
                                    </div>
                                    <button
                                        v-if="canDeleteItemChecklists"
                                        type="button"
                                        @click="toggleItemChecklistLock(ic.psm_id)"
                                        :class="[
                                            'shrink-0 px-2.5 py-1 text-xs font-semibold rounded-full flex items-center gap-1 border',
                                            isItemChecklistLocked(ic.psm_id)
                                                ? 'text-slate-700 bg-white border-slate-200 hover:bg-slate-100'
                                                : 'text-amber-700 bg-amber-50 border-amber-100 hover:bg-amber-100'
                                        ]"
                                        :title="isItemChecklistLocked(ic.psm_id) ? 'This item checklist is locked' : 'This item checklist is unlocked'"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path v-if="isItemChecklistLocked(ic.psm_id)" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 2h12a1 1 0 001-1v-6a1 1 0 00-1-1h-1V9a5 5 0 10-10 0v2H7a1 1 0 00-1 1v6a1 1 0 001 1z" />
                                            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8V7a5 5 0 10-10 0v1m-1 4h12a2 2 0 012 2v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                                        </svg>
                                        <span>{{ isItemChecklistLocked(ic.psm_id) ? 'Locked' : 'Unlocked' }}</span>
                                    </button>
                                </div>

                                <div class="mt-4 grid grid-cols-2 gap-2">
                                    <router-link
                                        :to="`/preventive-maintenance/${checklistId}/item-checklist/${ic.psm_id}`"
                                        class="rounded-md bg-emerald-50 px-3 py-2 text-center text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                                    >
                                        View full
                                    </router-link>
                                    <button
                                        type="button"
                                        @click="viewItemChecklistPdf(ic.psm_id)"
                                        class="rounded-md bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                                    >
                                        View PDF
                                    </button>
                                </div>

                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <div class="relative" v-click-outside="() => closeItemPrintDropdown(ic.psm_id)">
                                        <button
                                            type="button"
                                            @click="toggleItemPrintDropdown(ic.psm_id)"
                                            class="flex w-full items-center justify-center gap-1 rounded-md bg-white px-3 py-2 text-xs font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100"
                                        >
                                            Print
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                        <div
                                            v-if="openItemPrintDropdown === ic.psm_id"
                                            class="absolute right-0 mt-1 w-48 bg-white rounded-lg shadow-lg border border-slate-200 py-2 z-50"
                                        >
                                            <button
                                                @click="printItemChecklistWithPM(ic.psm_id, 'word')"
                                                class="w-full text-left px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                            >
                                                Print as Word
                                            </button>
                                            <button
                                                @click="printItemChecklistWithPM(ic.psm_id, 'pdf')"
                                                class="w-full text-left px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                            >
                                                Print as PDF
                                            </button>
                                            <button
                                                @click="printItemChecklistQrCode(ic.psm_id)"
                                                class="w-full text-left px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                            >
                                                Print QR Code
                                            </button>
                                            <button
                                                @click="printItemChecklistBarcode(ic.psm_id)"
                                                class="w-full text-left px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                            >
                                                Print Barcode
                                            </button>
                                        </div>
                                    </div>
                                    <template v-if="canEditItemChecklist(ic)">
                                        <div :class="canDeleteItemChecklists ? 'grid grid-cols-2 gap-2' : ''">
                                            <router-link
                                                :to="`/preventive-maintenance/${checklistId}/item-checklist/${ic.psm_id}/edit`"
                                                class="rounded-md bg-white px-3 py-2 text-center text-xs font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100"
                                            >
                                                Edit
                                            </router-link>
                                            <button
                                                v-if="canDeleteItemChecklists"
                                                type="button"
                                                class="rounded-md bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100"
                                                @click="deleteItemChecklist(ic.psm_id)"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </template>
                                    <div v-else class="rounded-md bg-slate-100 px-3 py-2 text-center text-xs font-semibold text-slate-500">
                                        Locked
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <h3 class="text-lg font-semibold text-slate-800 mb-4">Update Logs</h3>
                    <div v-if="maintenanceRevisions.length === 0" class="text-sm text-slate-500">
                        No previous versions yet.
                    </div>
                    <ul v-else class="space-y-3">
                        <li
                            v-for="revision in maintenanceRevisions"
                            :key="revision.id"
                            class="flex items-center justify-between gap-3"
                        >
                            <div>
                                <p class="text-sm font-medium text-slate-700">
                                    {{ formatDate(revision.revision_date) || 'Previous version' }}
                                </p>
                                <p v-if="revision.revision_time" class="mt-1 text-xs font-medium text-slate-500 leading-tight">
                                    {{ revision.revision_time }}
                                </p>
                            </div>
                            <router-link
                                :to="`/preventive-maintenance/${checklistId}/revisions/${revision.id}`"
                                class="px-2 py-1 text-xs font-medium text-purple-700 bg-purple-50 rounded hover:bg-purple-100"
                            >
                                View previous
                            </router-link>
                        </li>
                    </ul>
                </div>
                </div>
            </div>
        </div>

        <div
            v-if="showPhotoModal"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/75 p-4"
            @click.self="closePhotoModal"
        >
            <div class="w-full max-w-5xl rounded-lg bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h3 class="text-lg font-semibold text-slate-800">
                        Maintenance Photo {{ activePhotoIndex + 1 }} of {{ maintenancePhotoUrls.length }}
                    </h3>
                    <button
                        type="button"
                        @click="closePhotoModal"
                        class="px-3 py-1.5 text-sm font-semibold text-slate-700 bg-slate-100 rounded hover:bg-slate-200"
                    >
                        Close
                    </button>
                </div>
                <div class="p-5">
                    <div v-if="photoLoadFailed" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                        The photo could not be loaded here.
                        <a :href="activePhotoUrl" target="_blank" rel="noopener" class="font-semibold underline">Open it in a new tab</a>
                    </div>
                    <img
                        v-else
                        :src="activePhotoUrl"
                        :alt="`Maintenance photo ${activePhotoIndex + 1}`"
                        class="max-h-[75vh] w-full rounded border border-slate-200 bg-slate-50 object-contain"
                        @error="photoLoadFailed = true"
                    >
                    <div v-if="maintenancePhotoUrls.length > 1" class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-6">
                        <button
                            v-for="(url, index) in maintenancePhotoUrls"
                            :key="`modal-${index}-${url}`"
                            type="button"
                            class="aspect-[4/3] overflow-hidden rounded border bg-slate-50"
                            :class="index === activePhotoIndex ? 'border-blue-500 ring-2 ring-blue-200' : 'border-slate-200'"
                            @click="selectPhoto(index)"
                        >
                            <img :src="url" :alt="`Maintenance photo thumbnail ${index + 1}`" class="h-full w-full object-contain">
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { appUrl } from '../auth';

export default {
    name: 'PreventiveMaintenanceShow',
    props: {
        checklistId: {
            type: [String, Number],
            required: true,
        },
        authUser: {
            type: Object,
            default: null,
        },
    },
    data() {
        return {
            checklist: {},
            equipment: [],
            operatingSystems: [],
            softwareApplications: [],
            specificationFields: [],
            itemChecklists: [],
            maintenanceRevisions: [],
            openItemPrintDropdown: null,
            itemChecklistsLocked: false,
            itemChecklistLocks: {},
            creatingItemChecklist: false,
            showPhotoModal: false,
            activePhotoIndex: 0,
            photoLoadFailed: false,
        };
    },
    computed: {
        isIpPhoneChecklist() {
            return this.checklist.checklist_type === 'ip_phone';
        },
        isNetworkDeviceChecklist() {
            return this.checklist.checklist_type === 'network_device';
        },
        isWifiChecklist() {
            return this.checklist.checklist_type === 'wifi';
        },
        isUpsChecklist() {
            return this.checklist.checklist_type === 'ups';
        },
        isCctvChecklist() {
            return this.checklist.checklist_type === 'cctv';
        },
        hasAnyEquipment() {
            const fromList = this.equipment.some(item => this.checklist[this.getFieldName('equipment', item.name)]);
            return fromList || (this.checklist.equipment_others && this.checklist.equipment_others_specify);
        },
        hasAnyOs() {
            const fromList = this.operatingSystems.some(item => this.checklist[this.getFieldName('os', item.name)]);
            return fromList || (this.checklist.os_others && this.checklist.os_others_specify);
        },
        hasAnySoftware() {
            const fromList = this.softwareApplications.some(item => this.checklist[this.getFieldName('software', item.name)]);
            return fromList || (this.checklist.software_others && this.checklist.software_others_specify);
        },
        maintenancePhotoUrls() {
            if (Array.isArray(this.checklist.maintenance_photo_urls) && this.checklist.maintenance_photo_urls.length) {
                return this.checklist.maintenance_photo_urls.filter(Boolean);
            }

            return this.checklist.maintenance_photo_url ? [this.checklist.maintenance_photo_url] : [];
        },
        activePhotoUrl() {
            return this.maintenancePhotoUrls[this.activePhotoIndex] || this.maintenancePhotoUrls[0] || null;
        },
        canEditRecords() {
            return ['superadmin', 'admin', 'encoder'].includes(this.authUser?.role);
        },
        canEditCurrentChecklist() {
            return this.canEditRecords && !this.checklist.is_locked;
        },
        canDeleteItemChecklists() {
            return ['superadmin', 'admin'].includes(this.authUser?.role);
        },
    },
    mounted() {
        this.fetchData();
    },
    methods: {
        async fetchData() {
            try {
                const [checklistRes, refDataRes, itemChecklistsRes, revisionsRes] = await Promise.all([
                    axios.get(`/api/preventive-maintenance/${this.checklistId}`),
                    axios.get('/api/reference-data'),
                    axios.get(`/api/preventive-maintenance/${this.checklistId}/item-checklists`).catch(() => ({ data: [] })),
                    axios.get(`/api/preventive-maintenance/${this.checklistId}/revisions`).catch(() => ({ data: [] })),
                ]);
                
                this.checklist = checklistRes.data;
                this.equipment = refDataRes.data.equipment;
                this.operatingSystems = refDataRes.data.operatingSystems;
                this.softwareApplications = refDataRes.data.softwareApplications;
                this.specificationFields = refDataRes.data.specificationFields;
                const list = Array.isArray(itemChecklistsRes.data) ? itemChecklistsRes.data : (itemChecklistsRes.data?.itemChecklists || []);
                this.itemChecklists = list;
                this.syncItemChecklistLockState();
                this.maintenanceRevisions = Array.isArray(revisionsRes.data) ? revisionsRes.data : [];
            } catch (error) {
                console.error('Error fetching data:', error);
            }
        },
        async deleteItemChecklist(psmId) {
            let confirmed = false;

            if (window.Swal) {
                const result = await Swal.fire({
                    title: 'Delete this maintenance record?',
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
                confirmed = window.confirm('Delete this item checklist? This cannot be undone.');
            }

            if (!confirmed) {
                return;
            }

            try {
                await axios.delete(`/api/item-checklist/${psmId}`);
                this.itemChecklists = this.itemChecklists.filter(ic => ic.psm_id !== psmId);

                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted',
                        text: 'The maintenance record has been deleted.',
                    });
                }
            } catch (error) {
                console.error('Error deleting item checklist:', error);

                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'There was a problem deleting the maintenance record.',
                    });
                }
            }
        },
        getFieldName(prefix, name) {
            return prefix + '_' + name.toLowerCase().replace(/[\s.-]+/g, '_');
        },
        formatDate(date) {
            if (!date) return '';
            return new Date(date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
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
        toggleItemPrintDropdown(psmId) {
            this.openItemPrintDropdown = this.openItemPrintDropdown === psmId ? null : psmId;
        },
        closeItemPrintDropdown(psmId) {
            if (this.openItemPrintDropdown === psmId) {
                this.openItemPrintDropdown = null;
            }
        },
        isItemChecklistLocked(psmId) {
            return this.itemChecklistsLocked || Boolean(this.itemChecklistLocks[String(psmId)]);
        },
        canEditItemChecklist(itemChecklist) {
            if (!this.canEditRecords) {
                return false;
            }

            return !this.itemChecklistsLocked && !this.isItemChecklistLocked(itemChecklist.psm_id);
        },
        syncItemChecklistLockState() {
            this.itemChecklistLocks = this.itemChecklists.reduce((locks, itemChecklist) => ({
                ...locks,
                [String(itemChecklist.psm_id)]: Boolean(itemChecklist.is_locked),
            }), {});
            this.itemChecklistsLocked = this.itemChecklists.length > 0 && this.itemChecklists.every(itemChecklist => Boolean(itemChecklist.is_locked));
        },
        async setItemChecklistLock(psmId, locked) {
            const action = locked ? 'lock' : 'unlock';
            const response = await axios.patch(`/api/item-checklist/${psmId}/${action}`);
            const isLocked = Boolean(response.data?.is_locked);
            const key = String(psmId);

            this.itemChecklistLocks = {
                ...this.itemChecklistLocks,
                [key]: isLocked,
            };
            this.itemChecklists = this.itemChecklists.map(itemChecklist => String(itemChecklist.psm_id) === key
                ? { ...itemChecklist, is_locked: isLocked }
                : itemChecklist
            );
            this.itemChecklistsLocked = this.itemChecklists.length > 0 && this.itemChecklists.every(itemChecklist => Boolean(itemChecklist.is_locked));
        },
        async toggleAllItemChecklistsLock() {
            const locked = !this.itemChecklistsLocked;

            await Promise.all(this.itemChecklists.map(itemChecklist => this.setItemChecklistLock(itemChecklist.psm_id, locked)));
        },
        async toggleItemChecklistLock(psmId) {
            await this.setItemChecklistLock(psmId, !this.itemChecklistLocks[String(psmId)]);
        },
        async goToCreateItemChecklist() {
            if (this.creatingItemChecklist) {
                return;
            }

            this.creatingItemChecklist = true;
            try {
                await this.$router.push(`/preventive-maintenance/${this.checklist.psm_id || this.checklistId}/item-checklist/create`);
            } catch (error) {
                this.creatingItemChecklist = false;
            }
        },
        openPhotoModal(index = 0) {
            this.activePhotoIndex = index;
            this.photoLoadFailed = false;
            this.showPhotoModal = true;
        },
        selectPhoto(index) {
            this.activePhotoIndex = index;
            this.photoLoadFailed = false;
        },
        closePhotoModal() {
            this.showPhotoModal = false;
        },
        printItemChecklistWithPM(itemChecklistId, format) {
            this.openItemPrintDropdown = null;
            const url = `/api/item-checklist/${itemChecklistId}/print-with-pm?format=${format}`;
            window.open(appUrl(url), '_blank');
        },
        async printItemChecklistQrCode(itemChecklistId) {
            await this.printThermalLabel(
                `/api/item-checklist/${itemChecklistId}/print-qr-code`,
                'QR code label sent to Printer.'
            );
        },
        async printItemChecklistBarcode(itemChecklistId) {
            await this.printThermalLabel(
                `/api/item-checklist/${itemChecklistId}/print-barcode`,
                'Barcode label sent to Printer.'
            );
        },
        async printThermalLabel(url, successMessage) {
            this.openItemPrintDropdown = null;

            try {
                const { data } = await axios.post(url);
                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Print sent',
                        text: data?.message || successMessage,
                    });
                }
            } catch (error) {
                console.error('Error printing thermal label:', error);
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Unable to print label',
                        text: error.response?.data?.message || 'Please check that your printer is shared as the name of the device and the printer packages are installed.',
                    });
                }
            }
        },
        async viewItemChecklistPdf(itemChecklistId) {
            this.openItemPrintDropdown = null;
            try {
                const { data } = await axios.get(`/api/item-checklist/${itemChecklistId}/view-pdf-link`);
                if (data?.url) {
                    window.open(data.url, '_blank');
                    return;
                }

                throw new Error('Missing signed URL');
            } catch (error) {
                console.error('Error generating temporary PDF view link:', error);
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Unable to open PDF',
                        text: 'The temporary PDF link could not be created. Please try again.',
                    });
                }
            }
        },
        
    },
    
};
</script>
