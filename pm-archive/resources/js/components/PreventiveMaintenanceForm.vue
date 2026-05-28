<template>
    <div v-if="formReady" class="relative max-w-4xl mx-auto">
        <div
            v-if="!readOnly"
            class="mb-3 flex items-center justify-end gap-3 md:absolute md:-right-20 md:top-24 md:z-20 md:mb-0 md:flex-col"
        >
            <input
                ref="maintenancePhotoInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                multiple
                class="sr-only"
                @change="handleMaintenancePhotoChange"
            >
            <button
                type="button"
                title="Preview photos"
                aria-label="Preview maintenance photos"
                class="relative flex h-16 w-16 items-center justify-center rounded-full bg-black text-white shadow-lg ring-4 ring-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300"
                @click="openPhotoPreviewModal"
            >
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 8h4l2-3h4l2 3h4v11H4z" />
                    <circle cx="12" cy="13" r="4" />
                </svg>
                <span
                    v-if="maintenancePhotoCount"
                    class="absolute -right-1 -top-1 flex h-6 min-w-6 items-center justify-center rounded-full bg-emerald-600 px-1 text-xs font-bold text-white"
                >
                    {{ maintenancePhotoCount }}
                </span>
            </button>
        </div>

        <div
            v-if="showPhotoPreviewModal"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4"
            @click.self="closePhotoPreviewModal"
        >
            <div class="w-full max-w-3xl rounded-lg bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h3 class="text-lg font-semibold text-slate-800">Maintenance Photos</h3>
                    <button
                        type="button"
                        class="px-3 py-1.5 text-sm font-semibold text-slate-700 bg-slate-100 rounded hover:bg-slate-200"
                        @click="closePhotoPreviewModal"
                    >
                        Close
                    </button>
                </div>
                <div class="p-5">
                    <div v-if="activeMaintenancePhotoPreviewItem" class="space-y-4">
                        <div class="relative flex min-h-[18rem] items-center justify-center rounded border border-slate-200 bg-slate-50">
                            <img
                                :src="activeMaintenancePhotoPreviewItem.url"
                                :alt="`Maintenance photo ${activePhotoPreviewIndex + 1}`"
                                class="max-h-[60vh] w-full object-contain"
                            >
                            <button
                                v-if="!readOnly"
                                type="button"
                                title="Remove photo"
                                aria-label="Remove photo"
                                class="absolute right-3 top-3 flex h-9 w-9 items-center justify-center rounded-full bg-red-600 text-xl font-bold leading-none text-white shadow hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-300"
                                @click="removeMaintenancePhoto(activeMaintenancePhotoPreviewItem)"
                            >
                                x
                            </button>
                        </div>
                        <div v-if="maintenancePhotoPreviewItems.length > 1" class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                            <button
                                v-for="(photo, index) in maintenancePhotoPreviewItems"
                                :key="photo.key"
                                type="button"
                                class="aspect-[4/3] overflow-hidden rounded border bg-slate-50"
                                :class="index === activePhotoPreviewIndex ? 'border-blue-500 ring-2 ring-blue-200' : 'border-slate-200'"
                                @click="activePhotoPreviewIndex = index"
                            >
                                <img :src="photo.url" :alt="`Maintenance photo thumbnail ${index + 1}`" class="h-full w-full object-contain">
                            </button>
                        </div>
                    </div>
                    <div v-else class="flex min-h-[14rem] items-center justify-center rounded border border-dashed border-slate-300 bg-slate-50 text-sm text-slate-500">
                        No photos selected.
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            class="px-4 py-2 bg-black text-sm font-medium text-white hover:bg-gray-800"
                            @click="triggerMaintenancePhotoPicker"
                        >
                            {{ maintenancePhotoCount ? 'Add Photos' : 'Select Photos' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <form
            @submit.prevent="readOnly ? null : submitForm()"
            class="bg-white text-black shadow-sm border-[1.5px] border-black p-4"
            :class="{ 'pointer-events-none': readOnly }"
        >
        <!-- Document header -->
        <div class="border-b border-black pb-3">
            <div class="flex items-start gap-4">
                <div class="shrink-0 w-16 h-16 rounded-full border-4 flex items-center justify-center bg-amber-50 text-amber-800 font-bold text-sm" style="border-color: #fbc008;">
                    CMU
                </div>
                <div class="flex-1">
                    <p class="text-sm tracking-wide">Republic of the Philippines</p>
                    <p class="text-base font-bold tracking-wide">CENTRAL MINDANAO UNIVERSITY</p>
                    <p class="text-sm">University Town, Musuan, Bukidnon</p>
                </div>
            </div>
            <p class="text-center font-semibold tracking-wide mt-2">OFFICE OF DIGITAL TRANSFORMATION</p>
            <div class="flex items-baseline justify-center gap-4 mt-2">
                <h1 class="text-xl font-bold tracking-wide">PREVENTIVE MAINTENANCE CHECKLIST</h1>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">{{ checklistTypeLabel }}</span>
            </div>
        </div>

        <!-- User/PC Information -->
        <div class="mt-3 flex items-end justify-between gap-4 text-sm">
            <span class="min-h-[1.5rem] py-0.5">{{ displayedIdentifier }}</span>
            <div class="flex items-center gap-2">
                <span>Date:</span>
                <input v-model="form.checklist_date" type="date" disabled class="border-0 border-b-2 border-black bg-transparent py-0 text-sm w-36 focus:ring-0 focus:border-black disabled:text-slate-500 disabled:cursor-not-allowed">
            </div>
        </div>

        <div class="border-2 border-black">
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">User/Operator:</span>
                <input v-model="form.user_operator" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Office/College:</span>
                <div v-if="readOnly" class="flex-1 min-h-[2rem] py-1 px-2 text-sm">
                    {{ displayOrganizationValue(form.office_college) }}
                </div>
                <select
                    v-else
                    v-model="form.college_office_id"
                    class="min-w-0 flex-1 max-w-full truncate py-1 px-2 text-sm border-0 bg-white focus:ring-0 focus:outline-none"
                    required
                    @change="handleCollegeOfficeChange"
                >
                    <option value="">Select College/Office</option>
                    <option v-for="collegeOffice in collegeOffices" :key="collegeOffice.id" :value="String(collegeOffice.id)">
                        {{ collegeOffice.name }}
                    </option>
                </select>
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Department:</span>
                <div v-if="readOnly" class="flex-1 min-h-[2rem] py-1 px-2 text-sm">
                    {{ displayOrganizationValue(form.department) }}
                </div>
                <select
                    v-else
                    v-model="form.department_id"
                    class="min-w-0 flex-1 max-w-full truncate py-1 px-2 text-sm border-0 bg-white focus:ring-0 focus:outline-none disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed"
                    :disabled="!form.college_office_id || departmentsLoading"
                    required
                    @change="handleDepartmentChange"
                >
                    <option value="">{{ departmentsLoading ? 'Loading departments...' : 'Select Department' }}</option>
                    <option v-for="department in departments" :key="department.id" :value="String(department.id)">
                        {{ department.name }}
                    </option>
                </select>
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Date Acquired:</span>
                <input v-model="form.date_acquired" type="date" disabled class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none disabled:text-slate-500 disabled:cursor-not-allowed">
            </div>
            <div v-if="showsAssetNameEntry" class="flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">{{ assetNameLabel }}:</span>
                <input v-model="form.pc_name" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
        </div>

        <div v-if="isIpPhoneChecklist" class="border-2 border-black mt-3">
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Brand Name:</span>
                <input v-model="form.brand_name" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Model Name:</span>
                <input v-model="form.model_name" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Serial Number:</span>
                <input v-model="form.serial_number" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">MAC Address:</span>
                <input v-model="form.mac_address" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Location / Office Located:</span>
                <input v-model="form.office_located" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">IP Address Tagged:</span>
                <input v-model="form.ip_address_tagged" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">VLAN:</span>
                <input v-model="form.vlan" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Telephone Number:</span>
                <input v-model="form.telephone_number" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
        </div>

        <div v-if="isNetworkDeviceChecklist" class="border-2 border-black mt-3">
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Category Type:</span>
                <input v-model="form.network_device_category_type" type="text" placeholder="e.g., POE, Standard, Managed..." class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Product Name:</span>
                <input v-model="form.network_device_product_name" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Model Name:</span>
                <input v-model="form.network_device_model_name" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Serial Number:</span>
                <input v-model="form.network_device_serial" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">MAC Address:</span>
                <input v-model="form.network_device_mac_address" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Office Location:</span>
                <input v-model="form.network_device_office_location" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">IP Address:</span>
                <input v-model="form.network_device_ip_address" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">VLAN:</span>
                <input v-model="form.network_device_vlan" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
        </div>

        <div v-if="isWifiChecklist" class="border-2 border-black mt-3">
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Category Type:</span>
                <input v-model="form.wifi_category_type" type="text" placeholder="e.g., POE, Standard" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Product Name:</span>
                <input v-model="form.wifi_product_name" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Model Name:</span>
                <input v-model="form.wifi_model_name" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Serial:</span>
                <input v-model="form.wifi_serial" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">MAC Address:</span>
                <input v-model="form.wifi_mac_address" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Office Located:</span>
                <input v-model="form.wifi_office_location" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">IP Address:</span>
                <input v-model="form.wifi_ip_address" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">VLAN:</span>
                <input v-model="form.wifi_vlan" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">WiFi Name:</span>
                <input v-model="form.wifi_name" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">WiFi Password:</span>
                <input v-model="form.wifi_password" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Channel Supported:</span>
                <input v-model="form.wifi_channel_supported" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
        </div>

        <div v-if="isUpsChecklist" class="border-2 border-black mt-3">
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Category:</span>
                <input v-model="form.ups_category" type="text" placeholder="e.g., Standard, LAN-supported" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Brand Name:</span>
                <input v-model="form.ups_brand_name" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Model Name:</span>
                <input v-model="form.ups_model_name" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">MAC Address:</span>
                <input v-model="form.ups_mac_address" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Serial:</span>
                <input v-model="form.ups_serial" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Total Power or Capacity:</span>
                <input v-model="form.ups_total_power_capacity" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
        </div>

        <div v-if="isCctvChecklist" class="border-2 border-black mt-3">
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Category Type:</span>
                <input v-model="form.cctv_category_type" type="text" placeholder="e.g., POE, Standard" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Product Name:</span>
                <input v-model="form.cctv_product_name" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Model Name:</span>
                <input v-model="form.cctv_model_name" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Serial:</span>
                <input v-model="form.cctv_serial" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">MAC Address:</span>
                <input v-model="form.cctv_mac_address" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">Office Located:</span>
                <input v-model="form.cctv_office_location" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="border-b border-black flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">IP Address:</span>
                <input v-model="form.cctv_ip_address" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
            <div class="flex">
                <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">VLAN:</span>
                <input v-model="form.cctv_vlan" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none">
            </div>
        </div>

        <!-- Categories Section -->
        <template v-if="!isIpPhoneChecklist && !isNetworkDeviceChecklist && !isWifiChecklist && !isUpsChecklist && !isCctvChecklist" v-for="category in categories" :key="category.id">
            <!-- Equipment Installed -->
            <template v-if="category.category_type === 'equipment'">
                <div class="mt-3">
                    <h2 class="text-sm font-bold mb-2">{{ category.label }}</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-1.5">
                        <template v-for="item in equipment" :key="item.id">
                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                <input v-model="form[getFieldName('equipment', item.name)]" type="checkbox" value="1" class="rounded border-black text-emerald-600">
                                <span>{{ item.name }}</span>
                            </label>
                        </template>
                        <label class="flex items-center gap-2 cursor-pointer text-sm col-span-2 sm:col-span-1">
                            <input v-model="form.equipment_others" type="checkbox" value="1" class="rounded border-black text-emerald-600">
                            <span>Others(Specify)</span>
                        </label>
                        <span v-if="form.equipment_others" class="col-span-2 sm:col-span-1 flex items-center">
                            <input v-model="form.equipment_others_specify" type="text" class="border-0 border-b border-black bg-transparent py-0 text-sm w-full focus:ring-0 focus:border-black">
                        </span>
                    </div>
                </div>
            </template>

            <!-- Operating System Installed -->
            <template v-if="category.category_type === 'operating_system'">
                <div class="mt-3">
                    <h2 class="text-sm font-bold mb-2">{{ category.label }}</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-1.5">
                        <template v-for="item in operatingSystems" :key="item.id">
                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                <input v-model="form[getFieldName('os', item.name)]" type="checkbox" value="1" class="rounded border-black text-emerald-600">
                                <span>{{ item.name }}</span>
                            </label>
                        </template>
                        <label class="flex items-center gap-2 cursor-pointer text-sm">
                            <input v-model="form.os_others" type="checkbox" value="1" class="rounded border-black text-emerald-600">
                            <span>Others(Specify)</span>
                        </label>
                        <span v-if="form.os_others" class="flex items-center">
                            <input v-model="form.os_others_specify" type="text" class="border-0 border-b border-black bg-transparent py-0 text-sm w-full focus:ring-0 focus:border-black">
                        </span>
                    </div>
                </div>
            </template>

            <!-- Software/Applications Installed -->
            <template v-if="category.category_type === 'software'">
                <div class="mt-3">
                    <h2 class="text-sm font-bold mb-2">{{ category.label }}</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-1.5">
                        <template v-for="item in softwareApplications" :key="item.id">
                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                <input v-model="form[getFieldName('software', item.name)]" type="checkbox" value="1" class="rounded border-black text-emerald-600">
                                <span>{{ item.name }}</span>
                            </label>
                        </template>
                        <label class="flex items-center gap-2 cursor-pointer text-sm">
                            <input v-model="form.software_others" type="checkbox" value="1" class="rounded border-black text-emerald-600">
                            <span>Others(Specify)</span>
                        </label>
                        <span v-if="form.software_others" class="flex items-center">
                            <input v-model="form.software_others_specify" type="text" class="border-0 border-b border-black bg-transparent py-0 text-sm w-full focus:ring-0 focus:border-black">
                        </span>
                    </div>
                </div>
            </template>

            <!-- Desktop/Laptop Specifications -->
            <template v-if="category.category_type === 'specification'">
                <div class="mt-3">
                    <h2 class="text-sm font-bold mb-2">{{ category.label }}</h2>
                    <div class="border border-black">
                        <template v-for="(field, index) in specificationFields" :key="field.id">
                            <template v-if="field.name !== 'ip_address'">
                                <div class="flex border-b border-black last:border-b-0">
                                    <template v-if="field.name === 'mac_address'">
                                        <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">{{ field.label }} & IP</span>
                                        <input v-model="form[field.name]" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none" placeholder="MAC Address / IP Address">
                                        <input v-model="form.ip_address" type="hidden">
                                    </template>
                                    <template v-else>
                                        <span class="w-40 shrink-0 py-1 px-2 text-sm font-medium border-r border-black">{{ field.label }}</span>
                                        <input v-model="form[field.name]" type="text" class="flex-1 py-1 px-2 text-sm border-0 focus:ring-0 focus:outline-none" :placeholder="field.placeholder">
                                    </template>
                                </div>
                            </template>
                        </template>
                    </div>
                </div>
            </template>
        </template>

        <!-- Submit buttons -->
        <div v-if="!readOnly" class="flex gap-3 mt-4">
            <button type="submit" class="px-4 py-2 bg-black text-white text-sm font-medium hover:bg-gray-800">
                {{ isEdit ? 'Update' : 'Save' }} Checklist
            </button>
            <router-link v-if="isEdit && submission?.psm_id" :to="`/preventive-maintenance/${submission.psm_id}`" class="px-4 py-2 border border-black text-sm hover:bg-gray-100">Cancel</router-link>
            <router-link v-else to="/preventive-maintenance" class="px-4 py-2 border border-black text-sm hover:bg-gray-100">Cancel</router-link>
        </div>
        </form>
    </div>
    <div
        v-else
        class="bg-white text-black max-w-4xl mx-auto shadow-sm border-[1.5px] border-black p-8 text-center text-sm text-slate-600"
    >
        Loading checklist...
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'PreventiveMaintenanceForm',
    props: {
        initialData: Object,
        isEdit: Boolean,
        submission: Object,
        readOnly: Boolean,
    },
    data() {
        return {
            form: {
                checklist_type: 'pc',
                checklist_date: null,
                user_operator: '',
                college_office_id: '',
                office_college: '',
                department_id: '',
                department: '',
                date_acquired: null,
                pc_name: '',
                equipment_others: false,
                equipment_others_specify: '',
                os_others: false,
                os_others_specify: '',
                software_others: false,
                software_others_specify: '',
                mac_address: '',
                ip_address: '',
                brand_name: '',
                model_name: '',
                serial_number: '',
                office_located: '',
                ip_address_tagged: '',
                vlan: '',
                telephone_number: '',
                // Network Device Fields
                network_device_category_type: '',
                network_device_product_name: '',
                network_device_model_name: '',
                network_device_serial: '',
                network_device_mac_address: '',
                network_device_office_location: '',
                network_device_ip_address: '',
                network_device_vlan: '',
                // WiFi Fields
                wifi_category_type: '',
                wifi_product_name: '',
                wifi_model_name: '',
                wifi_serial: '',
                wifi_mac_address: '',
                wifi_office_location: '',
                wifi_ip_address: '',
                wifi_vlan: '',
                wifi_name: '',
                wifi_password: '',
                wifi_channel_supported: '',
                ups_category: '',
                ups_brand_name: '',
                ups_model_name: '',
                ups_mac_address: '',
                ups_serial: '',
                ups_total_power_capacity: '',
                cctv_category_type: '',
                cctv_product_name: '',
                cctv_model_name: '',
                cctv_serial: '',
                cctv_mac_address: '',
                cctv_office_location: '',
                cctv_ip_address: '',
                cctv_vlan: '',
                maintenance_photo: '',
                maintenance_photos: [],
                maintenance_photo_url: '',
                maintenance_photo_urls: [],
                maintenance_photo_files: [],
            },
            categories: [],
            equipment: [],
            operatingSystems: [],
            softwareApplications: [],
            specificationFields: [],
            collegeOffices: [],
            departments: [],
            departmentsLoading: false,
            loading: true,
            formReady: false,
            currentIdentifier: null,
            maintenancePhotoPreviewObjectUrls: [],
            showPhotoPreviewModal: false,
            activePhotoPreviewIndex: 0,
        };
    },
    computed: {
        isIpPhoneChecklist() {
            return this.form.checklist_type === 'ip_phone';
        },
        isNetworkDeviceChecklist() {
            return this.form.checklist_type === 'network_device';
        },
        isWifiChecklist() {
            return this.form.checklist_type === 'wifi';
        },
        isUpsChecklist() {
            return this.form.checklist_type === 'ups';
        },
        isCctvChecklist() {
            return this.form.checklist_type === 'cctv';
        },
        showsAssetNameEntry() {
            return ['pc', 'server'].includes(this.form.checklist_type);
        },
        checklistTypeLabel() {
            if (this.form.checklist_type === 'server') {
                return 'Server';
            }

            if (this.form.checklist_type === 'ip_phone') {
                return 'IP Phone';
            }

            if (this.form.checklist_type === 'network_device') {
                return 'Network Device';
            }

            if (this.form.checklist_type === 'wifi') {
                return 'WiFi';
            }

            if (this.form.checklist_type === 'ups') {
                return 'UPS';
            }

            if (this.form.checklist_type === 'cctv') {
                return 'CCTV';
            }

            return 'PC';
        },
        assetNameLabel() {
            if (this.form.checklist_type === 'server') {
                return 'Server Name';
            }

            if (this.form.checklist_type === 'network_device') {
                return 'Device Name';
            }

            if (this.form.checklist_type === 'wifi') {
                return 'Product Name';
            }

            if (this.form.checklist_type === 'ups') {
                return 'Brand / Model';
            }

            if (this.form.checklist_type === 'cctv') {
                return 'Product Name';
            }

            return 'PC Name';
        },
        displayedIdentifier() {
            return this.currentIdentifier;
        },
        maintenancePhotoPreviewItems() {
            const urls = Array.isArray(this.form.maintenance_photo_urls)
                ? this.form.maintenance_photo_urls
                : [];
            const paths = Array.isArray(this.form.maintenance_photos)
                ? this.form.maintenance_photos
                : [];
            const existingUrls = urls.length ? urls : (this.form.maintenance_photo_url ? [this.form.maintenance_photo_url] : []);
            const existingItems = existingUrls.filter(Boolean).map((url, index) => ({
                key: `existing-${index}-${url}`,
                source: 'existing',
                sourceIndex: index,
                path: paths[index] || '',
                url,
            }));
            const selectedItems = this.maintenancePhotoPreviewObjectUrls.map((photo, index) => ({
                key: `selected-${index}-${photo.url}`,
                source: 'selected',
                sourceIndex: index,
                url: photo.url,
            }));

            return [...existingItems, ...selectedItems];
        },
        maintenancePhotoCount() {
            return this.maintenancePhotoPreviewItems.length;
        },
        activeMaintenancePhotoPreviewItem() {
            return this.maintenancePhotoPreviewItems[this.activePhotoPreviewIndex]
                || this.maintenancePhotoPreviewItems[0]
                || null;
        },
    },
    watch: {
        'form.checklist_type'(newValue, oldValue) {
            if (newValue === oldValue || this.isEdit || this.readOnly || !this.formReady) {
                return;
            }

            this.fetchNextIdentifier();
        },
    },
    async mounted() {
        console.log('Form mounted. isEdit:', this.isEdit, 'submission:', this.submission);
        if (!this.isEdit || this.$route.query.type) {
            this.form.checklist_type = this.normalizeChecklistType(this.$route.query.type);
        }
        
        try {
            await this.fetchReferenceData();
            await this.fetchCollegeOffices();
            
            // Initialize dynamic checkbox fields (Vue 3 - use direct assignment)
            if (this.equipment && this.equipment.length > 0) {
                this.equipment.forEach(item => {
                    const fieldName = this.getFieldName('equipment', item.name);
                    this.form[fieldName] = false;
                });
            }
            
            if (this.operatingSystems && this.operatingSystems.length > 0) {
                this.operatingSystems.forEach(item => {
                    const fieldName = this.getFieldName('os', item.name);
                    this.form[fieldName] = false;
                });
            }
            
            if (this.softwareApplications && this.softwareApplications.length > 0) {
                this.softwareApplications.forEach(item => {
                    const fieldName = this.getFieldName('software', item.name);
                    this.form[fieldName] = false;
                });
            }
            
            if (this.specificationFields && this.specificationFields.length > 0) {
                this.specificationFields.forEach(field => {
                    if (field.name !== 'ip_address') {
                        this.form[field.name] = '';
                    }
                });
            }
            
            console.log('About to check if should fetch data. isEdit:', this.isEdit, 'psm_id:', this.submission?.psm_id);
            if (this.isEdit && this.submission?.psm_id) {
                console.log('Fetching data for edit mode');
                await this.fetchChecklistData(this.submission.psm_id);
            } else if (this.initialData) {
                console.log('Using initial data');
                this.form = { ...this.form, ...this.initialData };
                this.form.checklist_type = this.normalizeChecklistType(this.form.checklist_type);
                this.form.college_office_id = this.initialData.college_office_id ? String(this.initialData.college_office_id) : '';
                this.form.department_id = this.initialData.department_id ? String(this.initialData.department_id) : '';
                if (this.form.college_office_id) {
                    await this.fetchDepartments(this.form.college_office_id, this.form.department_id);
                }
                this.form.maintenance_photos = this.maintenancePhotoPathsFromData(this.initialData);
                this.form.maintenance_photo_urls = this.maintenancePhotoUrlsFromData(this.initialData);
                this.form.maintenance_photo_files = [];
                if (this.initialData?.psm_id) {
                    this.currentIdentifier = this.initialData.identifier || this.formatIdentifier(this.initialData.psm_id, this.form.checklist_type);
                }
            } else {
                console.log('Create mode - no initial data');
                this.form.checklist_date = this.currentDateString();
                this.form.date_acquired = this.currentDateString();
                await this.fetchNextIdentifier();
            }
        } finally {
            this.formReady = true;
            this.loading = false;
        }
    },
    methods: {
        currentDateString() {
            const date = new Date();
            date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
            return date.toISOString().slice(0, 10);
        },
        displayOrganizationValue(value) {
            const normalizedValue = String(value || '').trim();
            const placeholders = ['select college/office', 'select department'];

            return placeholders.includes(normalizedValue.toLowerCase()) ? '' : normalizedValue;
        },
        async fetchCollegeOffices() {
            try {
                const response = await axios.get('/api/college-offices');
                this.collegeOffices = Array.isArray(response.data)
                    ? response.data.map((office) => ({
                        ...office,
                        id: String(office.id),
                    }))
                    : [];
            } catch (error) {
                console.error('Error fetching college/offices:', error);
                this.showError('Failed to load college/offices');
            }
        },
        async fetchDepartments(collegeOfficeId, selectedDepartmentId = '') {
            if (!collegeOfficeId) {
                this.departments = [];
                return;
            }

            this.departmentsLoading = true;

            try {
                const response = await axios.get(`/api/college-offices/${collegeOfficeId}/departments`);
                this.departments = Array.isArray(response.data)
                    ? response.data.map((department) => ({
                        ...department,
                        id: String(department.id),
                        college_office_id: String(department.college_office_id),
                    }))
                    : [];

                const departmentId = selectedDepartmentId ? String(selectedDepartmentId) : '';
                if (departmentId && this.departments.some((department) => department.id === departmentId)) {
                    this.form.department_id = departmentId;
                    this.handleDepartmentChange();
                } else if (departmentId) {
                    this.form.department_id = '';
                    this.form.department = '';
                }
            } catch (error) {
                console.error('Error fetching departments:', error);
                this.departments = [];
                this.form.department_id = '';
                this.form.department = '';
                this.showError('Failed to load departments');
            } finally {
                this.departmentsLoading = false;
            }
        },
        selectedCollegeOffice() {
            return this.collegeOffices.find((office) => office.id === String(this.form.college_office_id)) || null;
        },
        selectedDepartment() {
            return this.departments.find((department) => department.id === String(this.form.department_id)) || null;
        },
        async handleCollegeOfficeChange() {
            const collegeOffice = this.selectedCollegeOffice();
            this.form.office_college = collegeOffice?.name || '';
            this.form.department_id = '';
            this.form.department = '';
            this.departments = [];

            if (this.form.college_office_id) {
                await this.fetchDepartments(this.form.college_office_id);
            }
        },
        handleDepartmentChange() {
            const department = this.selectedDepartment();
            this.form.department = department?.name || '';
        },
        syncOrganizationSelectionNames() {
            const collegeOffice = this.selectedCollegeOffice();
            const department = this.selectedDepartment();
            this.form.office_college = collegeOffice?.name || '';
            this.form.department = department?.name || '';
        },
        normalizeChecklistType(type) {
            const normalized = String(type || '').toLowerCase().trim();

            if (normalized === 'server') {
                return 'server';
            }

            if (['ip_phone', 'ip-phone', 'ipphone', 'ip phones', 'ip_phones'].includes(normalized)) {
                return 'ip_phone';
            }

            if (['network_device', 'network-device', 'networkdevice', 'network device', 'network devices'].includes(normalized)) {
                return 'network_device';
            }

            if (['wifi', 'wi-fi', 'wireless'].includes(normalized)) {
                return 'wifi';
            }

            if (normalized === 'ups') {
                return 'ups';
            }

            if (normalized === 'cctv') {
                return 'cctv';
            }

            return 'pc';
        },
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
        maintenancePhotoUrlsFromData(data) {
            if (Array.isArray(data?.maintenance_photo_urls)) {
                return data.maintenance_photo_urls.filter(Boolean);
            }

            return data?.maintenance_photo_url ? [data.maintenance_photo_url] : [];
        },
        maintenancePhotoPathsFromData(data) {
            if (Array.isArray(data?.maintenance_photos)) {
                return data.maintenance_photos.filter(Boolean);
            }

            return [];
        },
        revokeMaintenancePhotoObjectUrls() {
            this.maintenancePhotoPreviewObjectUrls.forEach(photo => {
                URL.revokeObjectURL(photo.url);
            });
            this.maintenancePhotoPreviewObjectUrls = [];
        },
        triggerMaintenancePhotoPicker() {
            if (this.readOnly) {
                return;
            }

            this.$refs.maintenancePhotoInput?.click();
        },
        openPhotoPreviewModal() {
            this.activePhotoPreviewIndex = Math.min(
                this.activePhotoPreviewIndex,
                Math.max(this.maintenancePhotoPreviewItems.length - 1, 0)
            );
            this.showPhotoPreviewModal = true;
        },
        closePhotoPreviewModal() {
            this.showPhotoPreviewModal = false;
        },
        removeMaintenancePhoto(photo) {
            if (!photo) {
                return;
            }

            if (photo.source === 'existing') {
                const existingUrls = Array.isArray(this.form.maintenance_photo_urls)
                    ? [...this.form.maintenance_photo_urls]
                    : [];
                const existingPaths = Array.isArray(this.form.maintenance_photos)
                    ? [...this.form.maintenance_photos]
                    : [];

                existingUrls.splice(photo.sourceIndex, 1);
                existingPaths.splice(photo.sourceIndex, 1);
                this.form.maintenance_photo_urls = existingUrls;
                this.form.maintenance_photos = existingPaths;
                this.form.maintenance_photo_url = existingUrls[0] || '';
            } else if (photo.source === 'selected') {
                const selectedFiles = Array.isArray(this.form.maintenance_photo_files)
                    ? [...this.form.maintenance_photo_files]
                    : [];
                const selectedPreviews = [...this.maintenancePhotoPreviewObjectUrls];
                const removedPreview = selectedPreviews[photo.sourceIndex];

                if (removedPreview) {
                    URL.revokeObjectURL(removedPreview.url);
                }

                selectedFiles.splice(photo.sourceIndex, 1);
                selectedPreviews.splice(photo.sourceIndex, 1);
                this.form.maintenance_photo_files = selectedFiles;
                this.maintenancePhotoPreviewObjectUrls = selectedPreviews;
            }

            const nextCount = this.maintenancePhotoPreviewItems.length;
            this.activePhotoPreviewIndex = nextCount === 0
                ? 0
                : Math.min(this.activePhotoPreviewIndex, nextCount - 1);
        },
        async fetchReferenceData() {
            try {
                const response = await axios.get('/api/reference-data');
                const data = response.data;
                this.categories = data.categories || [];
                this.equipment = data.equipment || [];
                this.operatingSystems = data.operatingSystems || [];
                this.softwareApplications = data.softwareApplications || [];
                this.specificationFields = data.specificationFields || [];
            } catch (error) {
                console.error('Error fetching reference data:', error);
                this.showError('Failed to load form data');
            }
        },
        async fetchNextIdentifier() {
            try {
                const response = await axios.get('/api/preventive-maintenance/next-identifier', {
                    params: {
                        checklist_type: this.form.checklist_type,
                    },
                });
                const data = response.data || {};
                this.currentIdentifier = data.identifier || this.formatIdentifier(data.psm_id, this.form.checklist_type);
            } catch (error) {
                console.error('Error fetching next checklist identifier:', error);
                this.currentIdentifier = null;
            }
        },
        async fetchChecklistData(id) {
            try {
                this.loading = true;
                console.log('Fetching checklist data for ID:', id);
                const response = await axios.get(`/api/preventive-maintenance/${id}`);
                const data = response.data;
                console.log('API Response:', data);
                
                // Populate basic fields
                this.form.checklist_type = this.normalizeChecklistType(data.checklist_type);
                this.currentIdentifier = data.identifier || this.formatIdentifier(id, this.form.checklist_type);
                this.form.checklist_date = this.currentDateString();
                this.form.user_operator = data.user_operator || '';
                this.form.college_office_id = data.college_office_id ? String(data.college_office_id) : '';
                this.form.department_id = data.department_id ? String(data.department_id) : '';
                this.form.office_college = data.office_college || '';
                this.form.department = data.department || '';
                if (this.form.college_office_id) {
                    await this.fetchDepartments(this.form.college_office_id, this.form.department_id);
                } else {
                    this.departments = [];
                    this.form.department_id = '';
                }
                this.form.date_acquired = data.date_acquired || this.currentDateString();
                this.form.pc_name = data.pc_name || data.name || '';
                this.form.mac_address = data.mac_address || '';
                this.form.ip_address = data.ip_address || '';
                this.form.brand_name = data.brand_name || '';
                this.form.model_name = data.model_name || '';
                this.form.serial_number = data.serial_number || '';
                this.form.office_located = data.office_located || '';
                this.form.ip_address_tagged = data.ip_address_tagged || '';
                this.form.vlan = data.vlan || '';
                this.form.telephone_number = data.telephone_number || '';
                this.form.network_device_category_type = data.network_device_category_type || '';
                this.form.network_device_product_name = data.network_device_product_name || '';
                this.form.network_device_model_name = data.network_device_model_name || '';
                this.form.network_device_serial = data.network_device_serial || '';
                this.form.network_device_mac_address = data.network_device_mac_address || '';
                this.form.network_device_office_location = data.network_device_office_location || '';
                this.form.network_device_ip_address = data.network_device_ip_address || '';
                this.form.network_device_vlan = data.network_device_vlan || '';
                this.form.wifi_category_type = data.wifi_category_type || '';
                this.form.wifi_product_name = data.wifi_product_name || '';
                this.form.wifi_model_name = data.wifi_model_name || '';
                this.form.wifi_serial = data.wifi_serial || '';
                this.form.wifi_mac_address = data.wifi_mac_address || '';
                this.form.wifi_office_location = data.wifi_office_location || '';
                this.form.wifi_ip_address = data.wifi_ip_address || '';
                this.form.wifi_vlan = data.wifi_vlan || '';
                this.form.wifi_name = data.wifi_name || '';
                this.form.wifi_password = data.wifi_password || '';
                this.form.wifi_channel_supported = data.wifi_channel_supported || '';
                this.form.ups_category = data.ups_category || '';
                this.form.ups_brand_name = data.ups_brand_name || '';
                this.form.ups_model_name = data.ups_model_name || '';
                this.form.ups_mac_address = data.ups_mac_address || '';
                this.form.ups_serial = data.ups_serial || '';
                this.form.ups_total_power_capacity = data.ups_total_power_capacity || '';
                this.form.cctv_category_type = data.cctv_category_type || '';
                this.form.cctv_product_name = data.cctv_product_name || '';
                this.form.cctv_model_name = data.cctv_model_name || '';
                this.form.cctv_serial = data.cctv_serial || '';
                this.form.cctv_mac_address = data.cctv_mac_address || '';
                this.form.cctv_office_location = data.cctv_office_location || '';
                this.form.cctv_ip_address = data.cctv_ip_address || '';
                this.form.cctv_vlan = data.cctv_vlan || '';
                this.form.maintenance_photo = data.maintenance_photo || '';
                this.form.maintenance_photos = this.maintenancePhotoPathsFromData(data);
                this.form.maintenance_photo_url = data.maintenance_photo_url || '';
                this.form.maintenance_photo_urls = this.maintenancePhotoUrlsFromData(data);
                this.form.maintenance_photo_files = [];
                this.revokeMaintenancePhotoObjectUrls();
                
                // Set equipment checkboxes from structured data
                if (data.equipment_data && Array.isArray(data.equipment_data)) {
                    data.equipment_data.forEach(item => {
                        const fieldName = this.getFieldName('equipment', item.name);
                        this.form[fieldName] = true;
                    });
                }
                // Also check individual equipment fields
                if (this.equipment && this.equipment.length > 0) {
                    this.equipment.forEach(item => {
                        const fieldName = this.getFieldName('equipment', item.name);
                        if (data[fieldName]) {
                            this.form[fieldName] = true;
                        }
                    });
                }
                this.form.equipment_others = data.equipment_others || false;
                this.form.equipment_others_specify = data.equipment_others_specify || '';
                
                // Set OS checkboxes from structured data
                if (data.os_data && Array.isArray(data.os_data)) {
                    data.os_data.forEach(item => {
                        const fieldName = this.getFieldName('os', item.name);
                        this.form[fieldName] = true;
                    });
                }
                // Also check individual OS fields
                if (this.operatingSystems && this.operatingSystems.length > 0) {
                    this.operatingSystems.forEach(item => {
                        const fieldName = this.getFieldName('os', item.name);
                        if (data[fieldName]) {
                            this.form[fieldName] = true;
                        }
                    });
                }
                this.form.os_others = data.os_others || false;
                this.form.os_others_specify = data.os_others_specify || '';
                
                // Set software checkboxes from structured data
                if (data.software_data && Array.isArray(data.software_data)) {
                    data.software_data.forEach(item => {
                        const fieldName = this.getFieldName('software', item.name);
                        this.form[fieldName] = true;
                    });
                }
                // Also check individual software fields
                if (this.softwareApplications && this.softwareApplications.length > 0) {
                    this.softwareApplications.forEach(item => {
                        const fieldName = this.getFieldName('software', item.name);
                        if (data[fieldName]) {
                            this.form[fieldName] = true;
                        }
                    });
                }
                this.form.software_others = data.software_others || false;
                this.form.software_others_specify = data.software_others_specify || '';
                
                // Populate specification fields
                if (this.specificationFields && this.specificationFields.length > 0) {
                    this.specificationFields.forEach(field => {
                        if (field.name !== 'ip_address') {
                            this.form[field.name] = data[field.name] || '';
                        }
                    });
                }
                
                console.log('Form data populated:', this.form);
                
            } catch (error) {
                console.error('Error fetching checklist data:', error);
                this.showError('Failed to load checklist data');
            } finally {
                this.loading = false;
            }
        },
        getFieldName(prefix, name) {
            return prefix + '_' + name.toLowerCase().replace(/[\s.-]+/g, '_');
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
        formatIdentifier(id, type = this.form.checklist_type) {
            if (!id) return null;
            const padded = String(id).padStart(4, '0');
            return `PM${this.checklistCategoryCode(type)}-${padded}`;
        },
        handleMaintenancePhotoChange(event) {
            const files = Array.from(event.target.files || []);

            if (!files.length) {
                return;
            }

            const maxBytes = 2 * 1024 * 1024;
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            const existingPhotoCount = Array.isArray(this.form.maintenance_photo_urls)
                ? this.form.maintenance_photo_urls.filter(Boolean).length
                : (this.form.maintenance_photo_url ? 1 : 0);
            const selectedPhotoCount = Array.isArray(this.form.maintenance_photo_files)
                ? this.form.maintenance_photo_files.length
                : 0;

            if (existingPhotoCount + selectedPhotoCount + files.length > 10) {
                event.target.value = '';
                this.showError('You can upload up to 10 photos.');
                return;
            }

            if (files.some(file => !allowedTypes.includes(file.type))) {
                event.target.value = '';
                this.showError('Please upload JPG, PNG, or WebP photos.');
                return;
            }

            if (files.some(file => file.size > maxBytes)) {
                event.target.value = '';
                this.showError('Each photo must be 2 MB or smaller.');
                return;
            }

            this.activePhotoPreviewIndex = existingPhotoCount + selectedPhotoCount;
            this.form.maintenance_photo_files = [
                ...(Array.isArray(this.form.maintenance_photo_files) ? this.form.maintenance_photo_files : []),
                ...files,
            ];
            this.maintenancePhotoPreviewObjectUrls = [
                ...this.maintenancePhotoPreviewObjectUrls,
                ...files.map(file => ({
                    name: file.name,
                    url: URL.createObjectURL(file),
                })),
            ];
            event.target.value = '';
            this.showPhotoPreviewModal = true;
        },
        buildSubmitFormData(payload) {
            const formData = new FormData();

            Object.entries(payload).forEach(([key, value]) => {
                if (['maintenance_photo', 'maintenance_photos', 'maintenance_photo_url', 'maintenance_photo_urls', 'maintenance_photo_file', 'maintenance_photo_files'].includes(key)) {
                    return;
                }

                if (value === null || value === undefined || value === '' || value === false) {
                    return;
                }

                formData.append(key, value === true ? '1' : value);
            });

            if (Array.isArray(this.form.maintenance_photo_files)) {
                this.form.maintenance_photo_files.forEach(file => {
                    formData.append('maintenance_photo[]', file);
                });
            }

            formData.append('maintenance_photo_keep_list_present', '1');
            if (Array.isArray(this.form.maintenance_photos)) {
                this.form.maintenance_photos.forEach(path => {
                    formData.append('maintenance_existing_photos[]', path);
                });
            }

            if (this.isEdit) {
                formData.append('_method', 'PUT');
            }

            return formData;
        },
        async submitForm() {
            try {
                // Ask confirmation only when creating (not editing)
                if (!this.isEdit) {
                    let confirmed = true;

                    if (window.Swal) {
                        const result = await Swal.fire({
                            title: 'Create checklist?',
                            text: 'Do you want to save this new preventive maintenance checklist?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, save',
                            cancelButtonText: 'Cancel',
                        });
                        confirmed = result.isConfirmed;
                    } else {
                        confirmed = window.confirm(
                            'Do you want to save this new preventive maintenance checklist?'
                        );
                    }

                    if (!confirmed) {
                        return; // stop if user cancels
                    }
                }

                const url = this.isEdit 
                    ? `/api/preventive-maintenance/${this.submission.psm_id}`
                    : '/api/preventive-maintenance';
                const method = 'POST';

                this.syncOrganizationSelectionNames();

                const payload = {
                    ...this.form,
                    pc_name: this.isIpPhoneChecklist
                        ? [this.form.brand_name, this.form.model_name].filter(Boolean).join(' ').trim()
                        : this.isNetworkDeviceChecklist
                            ? this.form.network_device_product_name
                            : this.isWifiChecklist
                                ? this.form.wifi_product_name
                                : this.isUpsChecklist
                                    ? [this.form.ups_brand_name, this.form.ups_model_name].filter(Boolean).join(' ').trim()
                                    : this.isCctvChecklist
                                        ? this.form.cctv_product_name
                        : this.form.pc_name,
                };

                delete payload.checklist_date;
                delete payload.date_acquired;

                const response = await axios({
                    method,
                    url,
                    data: this.buildSubmitFormData(payload),
                });

                if (response?.data?.psm_id) {
                    this.currentIdentifier = response.data.identifier || this.formatIdentifier(response.data.psm_id, this.form.checklist_type);
                }

                const msg = this.isEdit
                    ? 'Checklist updated successfully.'
                    : 'Checklist created successfully.';

                await this.showSuccess(msg);
                this.$router.push(`/preventive-maintenance/${response.data.psm_id}`);
            } catch (error) {
                console.error('Error submitting form:', error);
                this.showError('Failed to save checklist');
            }
        },
    },
    beforeUnmount() {
        this.revokeMaintenancePhotoObjectUrls();
    },
};
</script>
