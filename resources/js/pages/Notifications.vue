<template>
    <div class="space-y-6">
        <!-- Recipients List -->
        <div class="bg-white rounded-lg shadow p-4 sm:p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base sm:text-lg font-semibold">Notification Recipients</h3>
                <button
                    @click="showAddModal"
                    class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                >
                    Add
                </button>
            </div>

            <div v-if="loading" class="text-gray-500">
                Loading...
            </div>

            <div v-else-if="recipients.length === 0" class="text-gray-500">
                No recipients configured. Add a recipient to receive power event notifications.
            </div>

            <ul v-else class="divide-y">
                <li
                    v-for="recipient in recipients"
                    :key="recipient.id"
                    class="py-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span
                                class="px-2 py-0.5 text-xs rounded-full shrink-0"
                                :class="recipient.type === 'email' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'"
                            >
                                {{ recipient.type === 'email' ? 'Email' : 'SMS' }}
                            </span>
                            <span class="font-medium truncate">{{ recipient.name || recipient.value }}</span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                @click="toggleEnabled(recipient)"
                                class="px-2 py-0.5 text-xs rounded"
                                :class="recipient.enabled ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ recipient.enabled ? 'On' : 'Off' }}
                            </button>
                            <button
                                @click="showEditModal(recipient)"
                                class="px-2 py-0.5 text-xs bg-gray-100 hover:bg-gray-200 rounded"
                            >
                                Edit
                            </button>
                            <button
                                @click="deleteRecipient(recipient)"
                                class="px-2 py-0.5 text-xs text-red-600 hover:bg-red-50 rounded"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                    <div v-if="recipient.name" class="text-xs text-gray-500 mt-1 ml-14 truncate">
                        {{ recipient.value }}
                    </div>
                </li>
            </ul>
        </div>

        <!-- Email (SMTP) Settings -->
        <div class="bg-white rounded-lg shadow p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-semibold">Email (SMTP)</h3>
            <p class="text-xs sm:text-sm text-gray-500 mb-4">Mail server used to send email alerts</p>

            <div v-if="delivery.loading" class="text-gray-500">
                Loading...
            </div>

            <form v-else @submit.prevent="saveSmtp" class="space-y-4">
                <div v-if="delivery.smtpMessage" class="p-3 rounded" :class="delivery.smtpOk ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                    {{ delivery.smtpMessage }}
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Server</label>
                        <input v-model="smtp.smtp_host" type="text" placeholder="smtp.example.com" class="w-full border rounded-lg px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                        <input v-model.number="smtp.smtp_port" type="number" min="1" max="65535" placeholder="587" class="w-full border rounded-lg px-3 py-2" />
                    </div>
                </div>
                <p class="text-xs text-gray-500 -mt-2">Use port 587 for STARTTLS or 465 for SSL/TLS.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input v-model="smtp.smtp_username" type="text" autocomplete="off" class="w-full border rounded-lg px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input
                            v-model="smtp.smtp_password"
                            type="password"
                            autocomplete="new-password"
                            :disabled="smtp.clear_smtp_password"
                            :placeholder="smtp.smtp_password_set ? 'Saved (leave blank to keep)' : 'Not set'"
                            class="w-full border rounded-lg px-3 py-2 disabled:bg-gray-100"
                        />
                        <label v-if="smtp.smtp_password_set" class="flex items-center mt-1 text-xs text-gray-600">
                            <input v-model="smtp.clear_smtp_password" type="checkbox" class="mr-1" />
                            Remove saved password
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Address</label>
                        <input v-model="smtp.smtp_from_address" type="email" placeholder="pipower@example.com" class="w-full border rounded-lg px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
                        <input v-model="smtp.smtp_from_name" type="text" placeholder="PiPower" class="w-full border rounded-lg px-3 py-2" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" :disabled="delivery.savingSmtp" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                        {{ delivery.savingSmtp ? 'Saving...' : 'Save Email Settings' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- SMS (Plivo) Settings -->
        <div class="bg-white rounded-lg shadow p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-semibold">SMS (Plivo)</h3>
            <p class="text-xs sm:text-sm text-gray-500 mb-4">
                Plivo account used to send text alerts. Find these in the Plivo console.
            </p>

            <div v-if="delivery.loading" class="text-gray-500">
                Loading...
            </div>

            <form v-else @submit.prevent="savePlivo" class="space-y-4">
                <div v-if="delivery.plivoMessage" class="p-3 rounded" :class="delivery.plivoOk ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                    {{ delivery.plivoMessage }}
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Auth ID</label>
                        <input v-model="plivo.plivo_auth_id" type="text" autocomplete="off" class="w-full border rounded-lg px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Auth Token</label>
                        <input
                            v-model="plivo.plivo_auth_token"
                            type="password"
                            autocomplete="new-password"
                            :disabled="plivo.clear_plivo_auth_token"
                            :placeholder="plivo.plivo_auth_token_set ? 'Saved (leave blank to keep)' : 'Not set'"
                            class="w-full border rounded-lg px-3 py-2 disabled:bg-gray-100"
                        />
                        <label v-if="plivo.plivo_auth_token_set" class="flex items-center mt-1 text-xs text-gray-600">
                            <input v-model="plivo.clear_plivo_auth_token" type="checkbox" class="mr-1" />
                            Remove saved token
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From Number</label>
                    <input v-model="plivo.plivo_from_number" type="tel" placeholder="+15551234567" class="w-full border rounded-lg px-3 py-2" />
                    <p class="text-xs text-gray-500 mt-1">A Plivo number on your account, in international format.</p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" :disabled="delivery.savingPlivo" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                        {{ delivery.savingPlivo ? 'Saving...' : 'Save SMS Settings' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Test Notification -->
        <div class="bg-white rounded-lg shadow p-4 sm:p-6">
            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="text-base sm:text-lg font-semibold">Test Notifications</h3>
                    <p class="text-xs sm:text-sm text-gray-500">Send a test to all enabled recipients</p>
                </div>
                <button
                    @click="sendTest"
                    :disabled="sendingTest"
                    class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 shrink-0"
                >
                    {{ sendingTest ? 'Sending...' : 'Test' }}
                </button>
            </div>
            <div v-if="testResult" class="mt-4 p-3 rounded" :class="testResultClass">
                {{ testResult }}
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div
            v-if="modal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
        >
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">
                    {{ modal.id ? 'Edit Recipient' : 'Add Recipient' }}
                </h3>

                <div v-if="error" class="mb-4 p-3 bg-red-50 text-red-700 rounded">
                    {{ error }}
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Name
                        </label>
                        <input
                            v-model="modal.name"
                            type="text"
                            placeholder="e.g., IT Department, Barry"
                            class="w-full border rounded-lg px-3 py-2"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Type
                        </label>
                        <select
                            v-model="modal.type"
                            class="w-full border rounded-lg px-3 py-2"
                        >
                            <option value="email">Email</option>
                            <option value="phone">SMS (Phone)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ modal.type === 'email' ? 'Email Address' : 'Phone Number' }}
                        </label>
                        <input
                            v-model="modal.value"
                            :type="modal.type === 'email' ? 'email' : 'tel'"
                            :placeholder="modal.type === 'email' ? 'user@example.com' : '+1234567890'"
                            class="w-full border rounded-lg px-3 py-2"
                        />
                    </div>

                    <div class="flex items-center">
                        <input
                            v-model="modal.enabled"
                            type="checkbox"
                            id="enabled"
                            class="mr-2"
                        />
                        <label for="enabled" class="text-sm text-gray-700">
                            Enable notifications
                        </label>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button
                        @click="closeModal"
                        class="px-4 py-2 text-gray-600 hover:text-gray-800"
                    >
                        Cancel
                    </button>
                    <button
                        @click="saveRecipient"
                        :disabled="saving"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ saving ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { api } from '@/services/api';

const recipients = ref([]);
const loading = ref(true);
const modal = ref(null);
const saving = ref(false);
const error = ref(null);
const sendingTest = ref(false);
const testResult = ref(null);
const testResultSuccess = ref(false);

async function fetchRecipients() {
    loading.value = true;
    try {
        recipients.value = await api.getRecipients();
    } catch (e) {
        console.error('Failed to fetch recipients:', e);
    } finally {
        loading.value = false;
    }
}

function showAddModal() {
    modal.value = {
        name: '',
        type: 'email',
        value: '',
        enabled: true,
    };
    error.value = null;
}

function showEditModal(recipient) {
    modal.value = { ...recipient };
    error.value = null;
}

function closeModal() {
    modal.value = null;
    error.value = null;
}

async function saveRecipient() {
    if (!modal.value.value) {
        error.value = 'Value is required';
        return;
    }

    saving.value = true;
    error.value = null;

    try {
        if (modal.value.id) {
            await api.updateRecipient(modal.value.id, {
                name: modal.value.name || null,
                type: modal.value.type,
                value: modal.value.value,
                enabled: modal.value.enabled,
            });
        } else {
            await api.createRecipient({
                name: modal.value.name || null,
                type: modal.value.type,
                value: modal.value.value,
                enabled: modal.value.enabled,
            });
        }
        closeModal();
        await fetchRecipients();
    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}

async function toggleEnabled(recipient) {
    try {
        await api.updateRecipient(recipient.id, {
            enabled: !recipient.enabled,
        });
        recipient.enabled = !recipient.enabled;
    } catch (e) {
        console.error('Failed to toggle recipient:', e);
    }
}

async function deleteRecipient(recipient) {
    if (!confirm(`Delete ${recipient.value}?`)) {
        return;
    }

    try {
        await api.deleteRecipient(recipient.id);
        await fetchRecipients();
    } catch (e) {
        console.error('Failed to delete recipient:', e);
    }
}

const testResultClass = computed(() => {
    return testResultSuccess.value
        ? 'bg-green-50 text-green-700'
        : 'bg-red-50 text-red-700';
});

async function sendTest() {
    sendingTest.value = true;
    testResult.value = null;

    try {
        const results = await api.sendTestNotification();
        const emailCount = results.email?.filter(r => r.success).length || 0;
        const smsCount = results.sms?.filter(r => r.success).length || 0;
        const totalSent = emailCount + smsCount;

        if (totalSent > 0) {
            testResultSuccess.value = true;
            testResult.value = `Test notification sent to ${totalSent} recipient(s)`;
        } else {
            testResultSuccess.value = false;
            testResult.value = 'No notifications sent. Check that you have enabled recipients.';
        }
    } catch (e) {
        testResultSuccess.value = false;
        testResult.value = `Failed to send test: ${e.message}`;
    } finally {
        sendingTest.value = false;
    }
}

// --- Delivery settings (SMTP / Plivo) ---

const smtp = ref({});
const plivo = ref({});
const delivery = ref({
    loading: true,
    savingSmtp: false,
    savingPlivo: false,
    smtpMessage: null,
    smtpOk: false,
    plivoMessage: null,
    plivoOk: false,
});

function loadDeliveryForms(data) {
    smtp.value = {
        smtp_host: data.smtp_host ?? '',
        smtp_port: data.smtp_port ?? null,
        smtp_username: data.smtp_username ?? '',
        smtp_password: '',
        smtp_password_set: data.smtp_password_set,
        clear_smtp_password: false,
        smtp_from_address: data.smtp_from_address ?? '',
        smtp_from_name: data.smtp_from_name ?? '',
    };
    plivo.value = {
        plivo_auth_id: data.plivo_auth_id ?? '',
        plivo_auth_token: '',
        plivo_auth_token_set: data.plivo_auth_token_set,
        clear_plivo_auth_token: false,
        plivo_from_number: data.plivo_from_number ?? '',
    };
}

async function fetchDeliverySettings() {
    delivery.value.loading = true;
    try {
        loadDeliveryForms(await api.getNotificationSettings());
    } catch (e) {
        console.error('Failed to fetch notification settings:', e);
    } finally {
        delivery.value.loading = false;
    }
}

function blankToNull(value) {
    return value === '' || value === undefined ? null : value;
}

async function saveSmtp() {
    delivery.value.savingSmtp = true;
    delivery.value.smtpMessage = null;

    const payload = {
        smtp_host: blankToNull(smtp.value.smtp_host),
        smtp_port: blankToNull(smtp.value.smtp_port),
        smtp_username: blankToNull(smtp.value.smtp_username),
        smtp_from_address: blankToNull(smtp.value.smtp_from_address),
        smtp_from_name: blankToNull(smtp.value.smtp_from_name),
        clear_smtp_password: smtp.value.clear_smtp_password,
    };
    if (smtp.value.smtp_password && !smtp.value.clear_smtp_password) {
        payload.smtp_password = smtp.value.smtp_password;
    }

    try {
        const data = await api.updateNotificationSettings(payload);
        const current = plivo.value;
        loadDeliveryForms(data);
        plivo.value = current; // keep any unsaved SMS edits
        delivery.value.smtpOk = true;
        delivery.value.smtpMessage = 'Email settings saved. Use Test below to confirm they work.';
    } catch (e) {
        delivery.value.smtpOk = false;
        delivery.value.smtpMessage = e.message;
    } finally {
        delivery.value.savingSmtp = false;
    }
}

async function savePlivo() {
    delivery.value.savingPlivo = true;
    delivery.value.plivoMessage = null;

    const payload = {
        plivo_auth_id: blankToNull(plivo.value.plivo_auth_id),
        plivo_from_number: blankToNull(plivo.value.plivo_from_number),
        clear_plivo_auth_token: plivo.value.clear_plivo_auth_token,
    };
    if (plivo.value.plivo_auth_token && !plivo.value.clear_plivo_auth_token) {
        payload.plivo_auth_token = plivo.value.plivo_auth_token;
    }

    try {
        const data = await api.updateNotificationSettings(payload);
        const current = smtp.value;
        loadDeliveryForms(data);
        smtp.value = current; // keep any unsaved email edits
        delivery.value.plivoOk = true;
        delivery.value.plivoMessage = 'SMS settings saved. Use Test below to confirm they work.';
    } catch (e) {
        delivery.value.plivoOk = false;
        delivery.value.plivoMessage = e.message;
    } finally {
        delivery.value.savingPlivo = false;
    }
}

onMounted(() => {
    fetchRecipients();
    fetchDeliverySettings();
});
</script>
