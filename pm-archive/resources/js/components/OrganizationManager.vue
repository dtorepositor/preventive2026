<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Colleges, Offices, and Departments</h2>
            <p class="mt-1 text-sm text-slate-500">Reference records used by maintenance forms and reports.</p>
        </div>

        <section class="grid gap-6 lg:grid-cols-[360px_1fr]">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Colleges and Offices</h3>
                <form class="mt-4 flex gap-2" @submit.prevent="saveOffice">
                    <input
                        v-model="officeForm.name"
                        type="text"
                        class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        placeholder="College or office name"
                        required
                    >
                    <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">
                        {{ officeForm.id ? 'Update' : 'Add' }}
                    </button>
                </form>

                <div class="mt-5 divide-y divide-slate-200">
                    <div
                        v-for="office in offices"
                        :key="office.id"
                        :class="[
                            'flex w-full items-center justify-between gap-3 px-2 py-3 text-left text-sm',
                            String(selectedOfficeId) === String(office.id) ? 'bg-amber-50 text-amber-800' : 'text-slate-700 hover:bg-slate-50'
                        ]"
                    >
                        <button type="button" class="min-w-0 flex-1 truncate text-left font-semibold" @click="selectOffice(office)">
                            {{ office.name }}
                        </button>
                        <span class="flex gap-2">
                            <button type="button" class="text-blue-600 hover:underline" @click="editOffice(office)">Edit</button>
                            <button type="button" class="text-red-600 hover:underline" @click="deleteOffice(office)">Delete</button>
                        </span>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Departments</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ selectedOfficeName || 'Select a college or office first.' }}</p>
                    </div>
                    <button
                        v-if="departmentForm.id"
                        type="button"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-600"
                        @click="resetDepartmentForm"
                    >
                        Cancel Edit
                    </button>
                </div>

                <form class="mt-4 flex gap-2" @submit.prevent="saveDepartment">
                    <input
                        v-model="departmentForm.name"
                        type="text"
                        class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        placeholder="Department name"
                        :disabled="!selectedOfficeId"
                        required
                    >
                    <button
                        type="submit"
                        :disabled="!selectedOfficeId"
                        class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {{ departmentForm.id ? 'Update' : 'Add' }}
                    </button>
                </form>

                <div v-if="departmentsLoading" class="px-4 py-8 text-center text-sm text-slate-500">Loading departments...</div>
                <div v-else class="mt-5 overflow-hidden rounded-lg border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">Department</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-if="departments.length === 0">
                                <td colspan="2" class="px-4 py-8 text-center text-sm text-slate-500">No departments found.</td>
                            </tr>
                            <tr v-for="department in departments" :key="department.id" class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ department.name }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <button type="button" class="mr-3 font-semibold text-blue-600 hover:underline" @click="editDepartment(department)">Edit</button>
                                    <button type="button" class="font-semibold text-red-600 hover:underline" @click="deleteDepartment(department)">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'OrganizationManager',
    data() {
        return {
            offices: [],
            departments: [],
            departmentsLoading: false,
            selectedOfficeId: '',
            officeForm: { id: null, name: '' },
            departmentForm: { id: null, name: '' },
        };
    },
    computed: {
        selectedOfficeName() {
            return this.offices.find(office => String(office.id) === String(this.selectedOfficeId))?.name || '';
        },
    },
    mounted() {
        this.fetchOffices();
    },
    methods: {
        async fetchOffices() {
            const response = await axios.get('/api/college-offices');
            this.offices = Array.isArray(response.data) ? response.data : [];
            if (!this.selectedOfficeId && this.offices.length) {
                this.selectOffice(this.offices[0]);
            }
        },
        async selectOffice(office) {
            this.selectedOfficeId = office.id;
            this.resetDepartmentForm();
            await this.fetchDepartments();
        },
        editOffice(office) {
            this.officeForm = { id: office.id, name: office.name };
        },
        async saveOffice() {
            const payload = { name: this.officeForm.name };
            if (this.officeForm.id) {
                await axios.put(`/api/college-offices/${this.officeForm.id}`, payload);
            } else {
                await axios.post('/api/college-offices', payload);
            }
            this.officeForm = { id: null, name: '' };
            await this.fetchOffices();
        },
        async deleteOffice(office) {
            if (!await this.confirm(`Delete ${office.name}?`)) {
                return;
            }

            await axios.delete(`/api/college-offices/${office.id}`);
            if (String(this.selectedOfficeId) === String(office.id)) {
                this.selectedOfficeId = '';
                this.departments = [];
            }
            await this.fetchOffices();
        },
        async fetchDepartments() {
            if (!this.selectedOfficeId) {
                this.departments = [];
                return;
            }

            this.departmentsLoading = true;
            try {
                const response = await axios.get(`/api/college-offices/${this.selectedOfficeId}/departments`);
                this.departments = Array.isArray(response.data) ? response.data : [];
            } finally {
                this.departmentsLoading = false;
            }
        },
        editDepartment(department) {
            this.departmentForm = { id: department.id, name: department.name };
        },
        resetDepartmentForm() {
            this.departmentForm = { id: null, name: '' };
        },
        async saveDepartment() {
            const payload = { name: this.departmentForm.name };
            if (this.departmentForm.id) {
                await axios.put(`/api/college-offices/${this.selectedOfficeId}/departments/${this.departmentForm.id}`, payload);
            } else {
                await axios.post(`/api/college-offices/${this.selectedOfficeId}/departments`, payload);
            }
            this.resetDepartmentForm();
            await this.fetchDepartments();
        },
        async deleteDepartment(department) {
            if (!await this.confirm(`Delete ${department.name}?`)) {
                return;
            }

            await axios.delete(`/api/college-offices/${this.selectedOfficeId}/departments/${department.id}`);
            await this.fetchDepartments();
        },
        async confirm(text) {
            if (!window.Swal) {
                return window.confirm(text);
            }

            const result = await Swal.fire({
                title: text,
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'Yes, delete it',
            });

            return result.isConfirmed;
        },
    },
};
</script>
