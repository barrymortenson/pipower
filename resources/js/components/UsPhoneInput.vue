<template>
    <div class="flex w-full border rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-blue-500">
        <span class="px-3 py-2 bg-gray-100 text-gray-600 border-r select-none">+1</span>
        <input
            :value="digits"
            @input="onInput"
            type="tel"
            inputmode="numeric"
            autocomplete="tel-national"
            maxlength="14"
            :placeholder="placeholder"
            class="flex-1 min-w-0 px-3 py-2 outline-none"
        />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usPhoneDigits } from '@/utils/phone';

// v-model holds the full number (+1XXXXXXXXXX), or '' when the field is empty.
const model = defineModel({ type: String, default: '' });

defineProps({
    placeholder: { type: String, default: '8055551234' },
});

const digits = computed(() => usPhoneDigits(model.value));

function onInput(event) {
    const cleaned = usPhoneDigits(event.target.value);

    // Keep the field showing digits only, even when the model value is unchanged.
    event.target.value = cleaned;
    model.value = cleaned ? `+1${cleaned}` : '';
}
</script>
