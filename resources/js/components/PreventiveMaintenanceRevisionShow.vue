<template>
    <div>
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-800">Previous Maintenance Checklist</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Saved before update
                    <span v-if="revision?.revision_date">on {{ formatDate(revision.revision_date) }}</span>
                    <span v-if="revision?.revision_time">at {{ revision.revision_time }}</span>
                </p>
            </div>
            <router-link
                :to="`/preventive-maintenance/${checklistId}`"
                class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 font-medium"
            >
                Back to checklist
            </router-link>
        </div>

        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600">
            Loading previous data...
        </div>

        <PreventiveMaintenanceForm
            v-else-if="revision"
            :initial-data="revision"
            :is-edit="false"
            :read-only="true"
        />

        <div v-else class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600">
            Previous version not found.
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import PreventiveMaintenanceForm from './PreventiveMaintenanceForm.vue';

export default {
    name: 'PreventiveMaintenanceRevisionShow',
    components: { PreventiveMaintenanceForm },
    props: {
        checklistId: {
            type: [String, Number],
            required: true,
        },
        revisionId: {
            type: [String, Number],
            required: true,
        },
    },
    data() {
        return {
            loading: true,
            revision: null,
        };
    },
    mounted() {
        this.fetchRevision();
    },
    methods: {
        async fetchRevision() {
            this.loading = true;

            try {
                const response = await axios.get(`/api/preventive-maintenance/${this.checklistId}/revisions/${this.revisionId}`);
                this.revision = response.data;
            } catch (error) {
                console.error('Error fetching previous maintenance checklist:', error);
                this.revision = null;
            } finally {
                this.loading = false;
            }
        },
        formatDate(date) {
            if (!date) return '';
            return new Date(date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        },
    },
};
</script>
