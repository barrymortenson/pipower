<template>
    <div class="min-h-screen bg-gray-100">
        <nav class="bg-white shadow">
            <div class="max-w-4xl mx-auto px-4">
                <div class="flex flex-col md:flex-row md:justify-between md:h-14">
                    <div class="flex items-center justify-center md:justify-start py-2 md:py-0">
                        <span class="text-lg font-bold text-gray-900">{{ hostname }}</span>
                    </div>
                    <div class="flex justify-center md:justify-end space-x-1 md:space-x-4">
                        <router-link
                            v-for="link in navLinks"
                            :key="link.to"
                            :to="link.to"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 hover:text-gray-900 border-b-2 border-transparent hover:border-gray-300"
                            active-class="!text-blue-600 !border-blue-600"
                        >
                            {{ link.label }}
                        </router-link>
                    </div>
                </div>
            </div>
        </nav>
        <main class="max-w-4xl mx-auto px-4 py-6">
            <router-view />
        </main>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { api } from '@/services/api';

const hostname = ref('UPS Monitor');

const navLinks = [
    { to: '/', label: 'Dashboard' },
    { to: '/notifications', label: 'Notifications' },
    { to: '/system', label: 'System' },
];

async function fetchHostname() {
    try {
        const status = await api.getNetworkStatus();
        if (status.hostname) {
            hostname.value = status.hostname;
            document.title = status.hostname;
        }
    } catch (e) {
        // Keep default
    }
}

onMounted(fetchHostname);
</script>
