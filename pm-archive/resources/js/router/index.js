import { createRouter, createWebHistory } from 'vue-router';
import { appUrl, authState, hasAnyRole, loadAuthUser, routerBasePath } from '../auth';
import Login from '../components/Login.vue';
import Dashboard from '../components/Dashboard.vue';
import PreventiveMaintenanceIndex from '../components/PreventiveMaintenanceIndex.vue';
import PreventiveMaintenancePlan from '../components/PreventiveMaintenancePlan.vue';
import PreventiveMaintenanceReport from '../components/PreventiveMaintenanceReport.vue';
import PreventiveMaintenanceForm from '../components/PreventiveMaintenanceForm.vue';
import PreventiveMaintenanceShow from '../components/PreventiveMaintenanceShow.vue';
import PreventiveMaintenanceRevisionShow from '../components/PreventiveMaintenanceRevisionShow.vue';
import ItemChecklistForm from '../components/ItemChecklistForm.vue';
import ItemChecklistShow from '../components/ItemChecklistShow.vue';
import UserManagement from '../components/UserManagement.vue';
import OrganizationManager from '../components/OrganizationManager.vue';
import ChecklistItemsManager from '../components/ChecklistItemsManager.vue';
import Settings from '../components/Settings.vue';
import About from '../components/About.vue';

const allRoles = ['superadmin', 'admin', 'encoder'];
const adminRoles = ['superadmin', 'admin'];

const routes = [
    {
        path: '/login',
        name: 'login',
        component: Login,
        meta: { guest: true },
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: Dashboard,
        meta: { roles: allRoles },
    },
    {
        path: '/about',
        name: 'about',
        component: About,
        meta: { roles: allRoles },
    },
    {
        path: '/maintenance-records',
        redirect: '/records',
    },
    {
        path: '/records',
        name: 'records',
        component: PreventiveMaintenanceIndex,
        meta: { roles: allRoles },
    },
    {
        path: '/preventive-maintenance',
        name: 'preventive-maintenance-index',
        component: PreventiveMaintenanceIndex,
        meta: { roles: allRoles },
    },
    {
        path: '/preventive-maintenance/create',
        name: 'preventive-maintenance-create',
        component: PreventiveMaintenanceForm,
        props: { isEdit: false },
        meta: { roles: allRoles },
    },
    {
        path: '/preventive-maintenance/plan',
        name: 'preventive-maintenance-plan',
        component: PreventiveMaintenancePlan,
        meta: { roles: adminRoles },
    },
    {
        path: '/preventive-maintenance/reports',
        name: 'preventive-maintenance-reports',
        component: PreventiveMaintenanceReport,
        meta: { roles: adminRoles },
    },
    {
        path: '/reports',
        redirect: '/preventive-maintenance/reports',
        meta: { roles: adminRoles },
    },
    {
        path: '/users',
        name: 'users',
        component: UserManagement,
        meta: { roles: adminRoles },
    },
    {
        path: '/colleges',
        name: 'colleges',
        component: OrganizationManager,
        meta: { roles: adminRoles },
    },
    {
        path: '/departments',
        redirect: '/colleges',
        meta: { roles: adminRoles },
    },
    {
        path: '/checklist-items',
        name: 'checklist-items',
        component: ChecklistItemsManager,
        meta: { roles: adminRoles },
    },
    {
        path: '/settings',
        name: 'settings',
        component: Settings,
        meta: { roles: ['superadmin'] },
    },
    {
        path: '/preventive-maintenance/:id/edit',
        name: 'preventive-maintenance-edit',
        component: PreventiveMaintenanceForm,
        props: route => ({ 
            isEdit: true, 
            submission: { psm_id: route.params.id }
        }),
        meta: { roles: allRoles },
    },
    {
        path: '/preventive-maintenance/:id/revisions/:revisionId',
        name: 'preventive-maintenance-revision-show',
        component: PreventiveMaintenanceRevisionShow,
        props: route => ({
            checklistId: route.params.id,
            revisionId: route.params.revisionId,
        }),
        meta: { roles: allRoles },
    },
    {
        path: '/preventive-maintenance/:id',
        name: 'preventive-maintenance-show',
        component: PreventiveMaintenanceShow,
        props: route => ({ checklistId: route.params.id }),
        meta: { roles: allRoles },
    },
    {
        path: '/preventive-maintenance/:id/item-checklist/create',
        name: 'item-checklist-create',
        component: ItemChecklistForm,
        props: route => ({ 
            preventiveMaintenanceId: route.params.id,
            isEdit: false 
        }),
        meta: { roles: allRoles },
    },
    {
        path: '/preventive-maintenance/:pmId/item-checklist/:id',
        name: 'item-checklist-show',
        component: ItemChecklistShow,
        props: route => ({ 
            preventiveMaintenanceId: route.params.pmId,
            itemChecklistId: route.params.id 
        }),
        meta: { roles: allRoles },
    },
    {
        path: '/preventive-maintenance/:pmId/item-checklist/:id/edit',
        name: 'item-checklist-edit',
        component: ItemChecklistForm,
        props: route => ({ 
            preventiveMaintenanceId: route.params.pmId,
            itemChecklistId: route.params.id,
            isEdit: true 
        }),
        meta: { roles: allRoles },
    },
    {
        path: '/',
        redirect: '/dashboard',
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/dashboard',
    },
];

const router = createRouter({
    history: createWebHistory(routerBasePath()),
    routes,
});

router.beforeEach(async (to) => {
    if (to.meta.guest) {
        await loadAuthUser();
        return authState.user ? { path: '/dashboard' } : true;
    }

    const requiredRoles = to.meta.roles || [];
    if (requiredRoles.length) {
        await loadAuthUser();

        if (!authState.user) {
            window.location.href = appUrl('/login');
            return false;
        }

        if (!hasAnyRole(requiredRoles)) {
            return { path: '/dashboard' };
        }
    }

    return true;
});

export default router;
