const API_BASE = '/api';
const API_TOKEN = document.querySelector('meta[name="api-token"]')?.content;

async function request(endpoint, options = {}) {
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    };

    if (API_TOKEN) {
        headers['X-API-Token'] = API_TOKEN;
    }

    const response = await fetch(`${API_BASE}${endpoint}`, {
        headers,
        ...options,
    });

    if (!response.ok) {
        let message = `API error: ${response.status}`;
        try {
            const body = await response.json();
            if (body?.message) {
                message = body.message;
            }
        } catch {
            // Non-JSON error body; keep the status message
        }
        throw new Error(message);
    }

    if (response.status === 204) {
        return null;
    }

    return response.json();
}

export const api = {
    // Status
    getStatus: () => request('/status'),
    getHealth: () => request('/health'),

    // Events
    getEvents: (limit = 50) => request(`/events?limit=${limit}`),

    // Recipients
    getRecipients: () => request('/recipients'),
    createRecipient: (data) => request('/recipients', {
        method: 'POST',
        body: JSON.stringify(data),
    }),
    updateRecipient: (id, data) => request(`/recipients/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    }),
    deleteRecipient: (id) => request(`/recipients/${id}`, {
        method: 'DELETE',
    }),

    // Notifications
    sendTestNotification: () => request('/notifications/test', { method: 'POST' }),

    // Notification delivery settings (SMTP, Plivo)
    getNotificationSettings: () => request('/settings/notifications'),
    updateNotificationSettings: (data) => request('/settings/notifications', {
        method: 'PUT',
        body: JSON.stringify(data),
    }),

    // Settings
    getSettings: () => request('/settings'),
    updateSettings: (data) => request('/settings', {
        method: 'PUT',
        body: JSON.stringify(data),
    }),

    // Network
    getNetworkStatus: () => request('/network/status'),
    applyNetworkAndReboot: () => request('/network/apply-and-reboot', { method: 'POST' }),

    // System
    rebootDevice: () => request('/system/reboot', { method: 'POST' }),
    shutdownDevice: () => request('/system/shutdown', { method: 'POST' }),

    // Updates
    checkForUpdates: () => request('/update/check'),
    getUpdateStatus: () => request('/update/status'),
    applyUpdate: () => request('/update/apply', { method: 'POST' }),

    // Pi Connect
    getPiConnectStatus: () => request('/pi-connect/status'),
    piConnectSignin: (authKey) => request('/pi-connect/signin', {
        method: 'POST',
        body: JSON.stringify({ auth_key: authKey }),
    }),
    piConnectSignout: () => request('/pi-connect/signout', { method: 'POST' }),
};
