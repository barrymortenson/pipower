<template>
    <!-- Update Modal Overlay -->
    <div v-if="updateSuccess" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-md text-center shadow-xl">
            <div class="mb-4">
                <svg class="animate-spin h-12 w-12 text-blue-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-semibold mb-2">Updating System</h3>
            <p class="text-gray-600 mb-2">Installing updates and rebuilding assets...</p>
            <p class="text-sm text-gray-500">This may take 1-2 minutes. The page will reload automatically.</p>
            <button
                v-if="showManualRefresh"
                @click="refreshPage"
                class="mt-4 text-blue-600 hover:text-blue-800 underline text-sm"
            >
                Refresh page manually
            </button>
        </div>
    </div>

    <!-- Reboot Modal Overlay -->
    <div v-if="rebooting" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-md text-center shadow-xl">
            <div class="mb-4">
                <svg class="animate-spin h-12 w-12 text-blue-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-semibold mb-2">Rebooting Device</h3>
            <p class="text-gray-600 mb-2">The device is rebooting...</p>
            <p class="text-sm text-gray-500">This page will reload automatically when the device is back online.</p>
            <button
                v-if="showManualRefresh"
                @click="refreshPage"
                class="mt-4 text-blue-600 hover:text-blue-800 underline text-sm"
            >
                Refresh page manually
            </button>
        </div>
    </div>

    <!-- Shutdown Modal Overlay -->
    <div v-if="shuttingDown" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-md text-center shadow-xl">
            <div class="mb-4">
                <svg class="h-12 w-12 text-red-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold mb-2">Shutting Down</h3>
            <p class="text-gray-600 mb-2">The device is shutting down...</p>
            <p class="text-sm text-gray-500 mb-2">Wait 30 seconds before unplugging the power.</p>
            <p class="text-sm text-gray-500">You will need physical access to turn it back on.</p>
        </div>
    </div>

    <!-- Pi Connect Modal Overlay -->
    <div v-if="piConnectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-md shadow-xl">
            <div v-if="piConnectLoading" class="text-center">
                <div class="mb-4">
                    <svg class="animate-spin h-12 w-12 text-blue-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold mb-2">Linking device...</h3>
            </div>

            <div v-else-if="piConnectError" class="text-center">
                <div class="mb-4">
                    <svg class="h-12 w-12 text-red-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold mb-2">Setup Failed</h3>
                <p class="text-gray-600 mb-4">{{ piConnectError }}</p>
                <button
                    @click="closePiConnectModal"
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
                >
                    Close
                </button>
            </div>

            <div v-else>
                <div class="mb-4 text-center">
                    <svg class="h-12 w-12 text-blue-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold mb-2 text-center">Set Up Raspberry Pi Connect</h3>
                <p class="text-gray-600 mb-4 text-sm">
                    Create an auth key at
                    <a href="https://connect.raspberrypi.com" target="_blank" class="text-blue-600 hover:text-blue-800">connect.raspberrypi.com</a>
                    → Settings → Auth keys → New
                </p>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Auth Key
                    </label>
                    <input
                        v-model="piConnectAuthKey"
                        type="text"
                        class="w-full border rounded-lg px-3 py-2 font-mono text-sm"
                        placeholder="rpuak_..."
                        @keyup.enter="submitPiConnectAuthKey"
                    />
                </div>

                <div class="flex gap-3 justify-end">
                    <button
                        @click="closePiConnectModal"
                        class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitPiConnectAuthKey"
                        :disabled="!piConnectAuthKey"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                    >
                        Link Device
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <!-- System Settings -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">System Settings</h3>

            <div v-if="loading" class="text-gray-500">
                Loading...
            </div>

            <form v-else @submit.prevent="saveSettings" class="space-y-4">
                <div v-if="saveSuccess" class="p-3 bg-green-50 text-green-700 rounded">
                    Settings saved successfully
                </div>

                <div v-if="error" class="p-3 bg-red-50 text-red-700 rounded">
                    {{ error }}
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Hostname
                    </label>
                    <input
                        v-model="settings.hostname"
                        type="text"
                        class="w-full border rounded-lg px-3 py-2"
                        placeholder="power-setup"
                    />
                    <p class="text-sm text-gray-500 mt-1">
                        Recommended format: [Location]-Power (e.g., Oxnard-Power). Reboot required for changes.
                    </p>
                </div>

                <div class="pt-4 border-t">
                    <h4 class="font-medium mb-3">Static IP Configuration</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Static IP
                            </label>
                            <input
                                v-model="settings.static_ip"
                                type="text"
                                class="w-full border rounded-lg px-3 py-2"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Gateway
                            </label>
                            <input
                                v-model="settings.static_gateway"
                                type="text"
                                class="w-full border rounded-lg px-3 py-2"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Primary DNS
                            </label>
                            <input
                                v-model="settings.static_dns_primary"
                                type="text"
                                class="w-full border rounded-lg px-3 py-2"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Secondary DNS
                            </label>
                            <input
                                v-model="settings.static_dns_secondary"
                                type="text"
                                class="w-full border rounded-lg px-3 py-2"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-4">
                    <button
                        type="submit"
                        :disabled="saving"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ saving ? 'Saving...' : 'Save Settings' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- System Health -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">System Health</h3>
                <button
                    @click="checkHealth"
                    :disabled="checkingHealth"
                    class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded disabled:opacity-50"
                >
                    {{ checkingHealth ? 'Checking...' : 'Check Now' }}
                </button>
            </div>

            <div class="flex items-center space-x-3 h-7">
                <span
                    v-if="healthChecked"
                    class="px-3 py-1 rounded-full text-sm font-medium transition-colors duration-300"
                    :class="healthStatusClass"
                >
                    {{ healthStatus }}
                </span>
                <span v-else-if="checkingHealth" class="px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-400">
                    Checking...
                </span>
                <span v-if="healthError && healthChecked" class="text-sm text-gray-500">
                    {{ healthError }}
                </span>
            </div>
        </div>

        <!-- Software Updates -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Software Updates</h3>
                <button
                    @click="checkForUpdates"
                    :disabled="checkingUpdates"
                    class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded"
                >
                    {{ checkingUpdates ? 'Checking...' : 'Check for Updates' }}
                </button>
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-gray-600">Current Version:</span>
                    <span class="font-mono">{{ updateInfo.current_version || 'Unknown' }}</span>
                </div>

                <div v-if="updateInfo.update_available" class="p-3 bg-blue-50 text-blue-700 rounded">
                    <p class="font-medium">Update available!</p>
                    <p class="text-sm">{{ updateInfo.commits_behind }} commit(s) behind latest version.</p>
                </div>

                <div v-else-if="updateInfo.error && !checkingUpdates" class="p-3 bg-red-50 text-red-700 rounded">
                    Can't check for updates: {{ updateInfo.error }}
                </div>

                <div v-else-if="updateInfo.current_version && !checkingUpdates" class="p-3 bg-green-50 text-green-700 rounded">
                    You're running the latest version.
                </div>

                <div v-if="updateError" class="p-3 bg-red-50 text-red-700 rounded">
                    {{ updateError }}
                </div>


                <div v-if="updateInfo.update_available" class="pt-3">
                    <button
                        @click="applyUpdate"
                        :disabled="applyingUpdate"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ applyingUpdate ? 'Updating...' : 'Apply Update' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Remote Access -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Remote Access</h3>

            <div class="flex items-center justify-between">
                <div>
                    <p class="font-medium">Raspberry Pi Connect</p>
                    <p class="text-sm text-gray-500">
                        Access this device remotely via
                        <a href="https://connect.raspberrypi.com" target="_blank" class="text-blue-600 hover:text-blue-800">connect.raspberrypi.com</a>
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <span
                        v-if="piConnectStatus.signed_in"
                        class="px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800"
                    >
                        Connected
                    </span>
                    <span
                        v-else-if="!piConnectStatus.installed"
                        class="px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600"
                    >
                        Not Installed
                    </span>
                    <span
                        v-else
                        class="px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800"
                    >
                        Not Connected
                    </span>

                    <button
                        v-if="piConnectStatus.signed_in"
                        @click="confirmPiConnectSignout"
                        class="px-3 py-1 text-sm text-red-600 border border-red-600 rounded hover:bg-red-50"
                    >
                        Sign Out
                    </button>
                    <button
                        v-else-if="piConnectStatus.installed"
                        @click="startPiConnectSetup"
                        class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700"
                    >
                        Set Up
                    </button>
                </div>
            </div>

            <p v-if="!piConnectStatus.installed" class="text-sm text-gray-500 mt-3">
                To install Pi Connect, run <code class="bg-gray-100 px-1 rounded">pa power:deploy</code> via SSH.
            </p>
        </div>

        <!-- Danger Zone -->
        <div class="bg-white rounded-lg shadow p-6 border border-red-200">
            <h3 class="text-lg font-semibold text-red-600 mb-4">Danger Zone</h3>

            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium">Reboot Device</p>
                        <p class="text-sm text-gray-500">
                            Reboot the entire Raspberry Pi
                        </p>
                    </div>
                    <button
                        @click="confirmReboot"
                        class="px-4 py-2 text-red-600 border border-red-600 rounded-lg hover:bg-red-50"
                    >
                        Reboot
                    </button>
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium">Shutdown Device</p>
                        <p class="text-sm text-gray-500">
                            Shut down the Raspberry Pi completely
                        </p>
                    </div>
                    <button
                        @click="confirmShutdown"
                        class="px-4 py-2 text-red-600 border border-red-600 rounded-lg hover:bg-red-50"
                    >
                        Shutdown
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { api } from '@/services/api';

const settings = ref({
    hostname: '',
    static_ip: '',
    static_gateway: '',
    static_dns_primary: '',
    static_dns_secondary: '',
});
const loading = ref(true);
const saving = ref(false);
const saveSuccess = ref(false);
const error = ref(null);

const healthStatus = ref('OK');
const healthError = ref(null);
const checkingHealth = ref(true);
const healthChecked = ref(false);

const updateInfo = ref({});
const checkingUpdates = ref(true);
const applyingUpdate = ref(false);
const updateError = ref(null);
const updateSuccess = ref(false);

const networkStatus = ref({ ethernet: {} });
const originalSettings = ref({});
const rebooting = ref(false);
const shuttingDown = ref(false);
const showManualRefresh = ref(false);
let manualRefreshTimer = null;

// Pi Connect
const piConnectStatus = ref({ installed: false, signed_in: false });
const piConnectModal = ref(false);
const piConnectLoading = ref(false);
const piConnectAuthKey = ref('');
const piConnectError = ref(null);

function startManualRefreshTimer() {
    showManualRefresh.value = false;
    if (manualRefreshTimer) clearTimeout(manualRefreshTimer);
    manualRefreshTimer = setTimeout(() => {
        showManualRefresh.value = true;
    }, 180000); // 3 minutes
}

function refreshPage() {
    window.location.reload();
}
const rebootTargetIp = ref(null);

const healthStatusClass = computed(() => {
    switch (healthStatus.value) {
        case 'OK':
            return 'bg-green-100 text-green-800';
        case 'Unknown':
            return 'bg-gray-100 text-gray-600';
        default:
            return 'bg-red-100 text-red-800';
    }
});

async function fetchSettings() {
    loading.value = true;
    try {
        const data = await api.getSettings();
        settings.value = {
            hostname: data.hostname || '',
            static_ip: data.static_ip || '',
            static_gateway: data.static_gateway || '',
            static_dns_primary: data.static_dns_primary || '',
            static_dns_secondary: data.static_dns_secondary || '',
        };
        // Store original values to detect changes requiring reboot
        originalSettings.value = {
            hostname: settings.value.hostname,
            static_ip: settings.value.static_ip,
        };
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function saveSettings() {
    saving.value = true;
    saveSuccess.value = false;
    error.value = null;

    // Check if hostname or IP changed - these require reboot
    const hostnameChanged = settings.value.hostname !== originalSettings.value.hostname;
    const ipChanged = settings.value.static_ip !== originalSettings.value.static_ip;
    const needsReboot = hostnameChanged || ipChanged;

    if (needsReboot) {
        const changes = [];
        if (hostnameChanged) changes.push('hostname');
        if (ipChanged) changes.push('IP address');
        if (!confirm(`Changing ${changes.join(' and ')} requires a device reboot. Continue?`)) {
            saving.value = false;
            return;
        }
    }

    try {
        await api.updateSettings({
            hostname: settings.value.hostname,
            static_ip: settings.value.static_ip || null,
            static_gateway: settings.value.static_gateway || null,
            static_dns_primary: settings.value.static_dns_primary || null,
            static_dns_secondary: settings.value.static_dns_secondary || null,
        });

        if (needsReboot) {
            // Trigger reboot with network changes
            rebootTargetIp.value = ipChanged ? settings.value.static_ip : null;
            rebooting.value = true;
            startManualRefreshTimer();
            await api.applyNetworkAndReboot();
            waitForRebootAndRedirect();
        } else {
            saveSuccess.value = true;
            setTimeout(() => saveSuccess.value = false, 3000);
        }
    } catch (e) {
        error.value = e.message;
        rebooting.value = false;
    } finally {
        saving.value = false;
    }
}

async function waitForRebootAndRedirect() {
    if (rebootTargetIp.value) {
        // IP is changing - can't poll cross-origin, just wait and redirect
        setTimeout(() => {
            window.location.href = `http://${rebootTargetIp.value}`;
        }, 45000);
    } else {
        // IP same - poll until server is back
        const checkServer = async () => {
            try {
                const response = await fetch('/api/ping', { cache: 'no-store' });
                if (response.ok) {
                    window.location.reload();
                } else {
                    setTimeout(checkServer, 3000);
                }
            } catch (e) {
                // Server not ready yet, try again
                setTimeout(checkServer, 3000);
            }
        };
        // Start checking after 30 seconds (reboot takes time)
        setTimeout(checkServer, 30000);
    }
}

async function checkHealth() {
    checkingHealth.value = true;
    healthError.value = null;

    try {
        const data = await api.getHealth();
        healthStatus.value = data.status === 'ok' ? 'OK' : 'Error';
        if (data.status !== 'ok') {
            healthError.value = data.message || 'Unknown error';
        }
    } catch (e) {
        healthStatus.value = 'Error';
        healthError.value = 'Unable to check system health';
    } finally {
        checkingHealth.value = false;
        healthChecked.value = true;
    }
}

async function checkForUpdates() {
    checkingUpdates.value = true;
    updateError.value = null;

    try {
        updateInfo.value = await api.checkForUpdates();
    } catch (e) {
        updateError.value = e.message;
    } finally {
        checkingUpdates.value = false;
    }
}

async function applyUpdate() {
    if (!confirm('Are you sure you want to apply the update? The service will restart.')) {
        return;
    }

    applyingUpdate.value = true;
    updateError.value = null;
    updateSuccess.value = false;

    try {
        const result = await api.applyUpdate();
        if (result.success) {
            updateSuccess.value = true;
            startManualRefreshTimer();
            // Wait for server to come back up, then reload
            waitForServerAndReload();
        } else {
            updateError.value = result.message || 'Update failed';
            applyingUpdate.value = false;
        }
    } catch (e) {
        updateError.value = e.message;
        applyingUpdate.value = false;
    }
}

async function waitForServerAndReload() {
    // Check if update is complete by polling the status endpoint
    const checkUpdateComplete = async () => {
        try {
            const status = await api.getUpdateStatus();
            if (!status.updating) {
                // Update complete, reload the page
                window.location.reload();
            } else {
                // Update still in progress, check again
                setTimeout(checkUpdateComplete, 5000);
            }
        } catch (e) {
            // Server not responding yet (likely restarting), try again
            setTimeout(checkUpdateComplete, 5000);
        }
    };
    // Start checking after 30 seconds (give update time to start)
    setTimeout(checkUpdateComplete, 30000);
}

async function confirmReboot() {
    if (confirm('Are you sure you want to reboot the device? This will temporarily disconnect you.')) {
        rebooting.value = true;
        startManualRefreshTimer();
        rebootTargetIp.value = null; // Same IP, just reload when back
        await api.rebootDevice();
        waitForRebootAndRedirect();
    }
}

function confirmShutdown() {
    if (confirm('Are you sure you want to shut down the device? You will need physical access to turn it back on.')) {
        shuttingDown.value = true;
        api.shutdownDevice();
    }
}

async function fetchNetworkStatus() {
    try {
        networkStatus.value = await api.getNetworkStatus();
        // Pre-populate settings from current network if not already set
        if (!settings.value.hostname && networkStatus.value.hostname) {
            settings.value.hostname = networkStatus.value.hostname;
        }
        const eth = networkStatus.value.ethernet || {};
        if (!settings.value.static_ip && eth.ip) {
            settings.value.static_ip = eth.ip;
        }
        if (!settings.value.static_gateway && eth.gateway) {
            settings.value.static_gateway = eth.gateway;
        }
        if (!settings.value.static_dns_primary && eth.dns_primary) {
            settings.value.static_dns_primary = eth.dns_primary;
        }
        if (!settings.value.static_dns_secondary && eth.dns_secondary) {
            settings.value.static_dns_secondary = eth.dns_secondary;
        }
    } catch (e) {
        // Ignore errors
    }
}

// Pi Connect functions
async function fetchPiConnectStatus() {
    try {
        piConnectStatus.value = await api.getPiConnectStatus();
    } catch (e) {
        // Ignore errors - status will show as not installed
    }
}

function startPiConnectSetup() {
    piConnectModal.value = true;
    piConnectLoading.value = false;
    piConnectAuthKey.value = '';
    piConnectError.value = null;
}

function closePiConnectModal() {
    piConnectModal.value = false;
    piConnectAuthKey.value = '';
    piConnectError.value = null;
    piConnectLoading.value = false;
}

async function submitPiConnectAuthKey() {
    if (!piConnectAuthKey.value) return;

    piConnectLoading.value = true;
    piConnectError.value = null;

    try {
        const result = await api.piConnectSignin(piConnectAuthKey.value);
        if (result.success) {
            closePiConnectModal();
            await fetchPiConnectStatus();
        } else if (result.already_signed_in) {
            piConnectError.value = 'Already signed in to Pi Connect.';
            await fetchPiConnectStatus();
        } else {
            piConnectError.value = result.error || 'Failed to link device.';
        }
    } catch (e) {
        piConnectError.value = 'Failed to link device. Check that the auth key is valid.';
    } finally {
        piConnectLoading.value = false;
    }
}

async function confirmPiConnectSignout() {
    if (confirm('Are you sure you want to sign out of Raspberry Pi Connect?')) {
        try {
            await api.piConnectSignout();
            await fetchPiConnectStatus();
        } catch (e) {
            alert('Failed to sign out. Please try again.');
        }
    }
}

onMounted(() => {
    fetchSettings();
    fetchNetworkStatus();
    checkHealth();
    checkForUpdates();
    fetchPiConnectStatus();
});
</script>
