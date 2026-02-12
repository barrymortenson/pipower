<template>
    <div class="space-y-6">
        <!-- Power Status Card -->
        <div
            class="rounded-lg p-6 transition-colors duration-300"
            :class="statusClass"
        >
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium opacity-80">Power Status</p>
                    <p class="text-2xl font-bold h-8">{{ statusLabel }}</p>
                </div>
                <div class="text-4xl w-12 text-right">{{ statusIcon }}</div>
            </div>
        </div>

        <!-- UPS Info -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">UPS Information</h3>
            <div v-if="loading" class="grid grid-cols-2 gap-4">
                <div v-for="n in 4" :key="n">
                    <div class="h-4 w-20 bg-gray-200 rounded animate-pulse mb-1"></div>
                    <div class="h-5 w-24 bg-gray-100 rounded animate-pulse"></div>
                </div>
            </div>
            <div v-else-if="status?.ups?.connected" class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Model</p>
                    <p class="font-medium">{{ status.ups.model }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Battery Level</p>
                    <p class="font-medium">{{ status.ups.battery_level }}%</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Load</p>
                    <p v-if="status.ups.load_percent > 0" class="font-medium">{{ status.ups.load_percent }}%</p>
                    <p v-else class="text-gray-400">Unknown</p>
                </div>
                <div v-if="status.ups.on_battery && status.ups.battery_runtime">
                    <p class="text-sm text-gray-500">Runtime Remaining</p>
                    <p class="font-medium">{{ formatRuntime(status.ups.battery_runtime) }}</p>
                </div>
                <div v-else>
                    <p class="text-sm text-gray-500">Battery</p>
                    <p class="font-medium" :class="status.ups.charging ? 'text-yellow-600' : 'text-green-600'">
                        {{ status.ups.charging ? 'Charging' : 'Charged' }}
                    </p>
                </div>
            </div>
            <div v-else class="text-red-600">
                UPS not connected
            </div>
        </div>

        <!-- Recent Events -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Recent Events</h3>
            <div v-if="events.length === 0" class="text-gray-500">
                No events recorded
            </div>
            <ul v-else class="space-y-2">
                <li
                    v-for="event in events"
                    :key="event.id"
                    class="flex justify-between items-center py-2 border-b last:border-0"
                >
                    <span class="font-medium">{{ event.message }}</span>
                    <span class="text-sm text-gray-500">{{ formatTime(event.created_at) }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { api } from '@/services/api';

const status = ref(null);
const events = ref([]);
const error = ref(null);
const loading = ref(true);
let pollInterval = null;

const statusClass = computed(() => {
    if (loading.value) return 'bg-gray-100 text-gray-400';
    if (!status.value?.ups?.connected) return 'bg-red-100 text-red-800';
    if (status.value?.power?.status === 'on_battery') return 'bg-yellow-100 text-yellow-800';
    return 'bg-green-100 text-green-800';
});

const statusLabel = computed(() => {
    if (loading.value) return '';
    if (!status.value?.ups?.connected) return 'UPS Disconnected';
    if (status.value?.power?.status === 'on_battery') return 'On Battery';
    return 'On AC Power';
});

const statusIcon = computed(() => {
    if (loading.value) return '';
    if (!status.value?.ups?.connected) return '⚠️';
    if (status.value?.power?.status === 'on_battery') return '🔋';
    return '⚡';
});

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
}

function formatRuntime(seconds) {
    if (!seconds || seconds <= 0) return 'Unknown';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0) return `${hours}h ${minutes}m`;
    return `${minutes} min`;
}

async function fetchData() {
    try {
        [status.value, events.value] = await Promise.all([
            api.getStatus(),
            api.getEvents(10),
        ]);
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    fetchData();
    pollInterval = setInterval(fetchData, 5000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>
