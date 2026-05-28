import { reactive } from 'vue';
import axios from 'axios';

export const authState = reactive({
    user: window.AppConfig?.user || null,
    loaded: Boolean(window.AppConfig?.user),
});

export const roleLabels = {
    superadmin: 'Super Admin',
    admin: 'Admin',
    encoder: 'Encoder',
};

export function appUrl(path = '') {
    const baseUrl = window.AppConfig?.baseUrl || window.location.origin;
    const normalizedPath = path.startsWith('/') ? path : `/${path}`;

    return `${baseUrl}${normalizedPath}`;
}

export function routerBasePath() {
    try {
        const url = new URL(window.AppConfig?.baseUrl || window.location.origin);
        return url.pathname === '/' ? '/' : `${url.pathname.replace(/\/$/, '')}/`;
    } catch (error) {
        return '/';
    }
}

export async function loadAuthUser(force = false) {
    if (authState.loaded && ! force) {
        return authState.user;
    }

    try {
        const response = await axios.get('/api/auth/user');
        authState.user = response.data?.user || null;
    } catch (error) {
        authState.user = null;
    } finally {
        authState.loaded = true;
    }

    return authState.user;
}

export function setAuthUser(user) {
    authState.user = user || null;
    authState.loaded = true;
}

export function hasAnyRole(roles = []) {
    if (! roles.length) {
        return Boolean(authState.user);
    }

    return roles.includes(authState.user?.role);
}

export function can(permission) {
    return Boolean(authState.user?.permissions?.[permission]);
}

export async function logout() {
    await axios.post('/logout');
    setAuthUser(null);
    window.location.href = appUrl('/login');
}
