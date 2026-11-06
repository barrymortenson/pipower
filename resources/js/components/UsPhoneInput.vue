<template>
    <div>
        <div
            class="flex w-full border rounded-lg overflow-hidden focus-within:ring-2"
            :class="unrecognized ? 'border-red-400 focus-within:ring-red-400' : 'focus-within:ring-blue-500'"
        >
            <span class="px-3 py-2 bg-gray-100 text-gray-600 border-r select-none">+1</span>
            <input
                :value="display"
                @input="onInput"
                type="tel"
                inputmode="numeric"
                autocomplete="tel-national"
                :placeholder="placeholder"
                class="flex-1 min-w-0 px-3 py-2 outline-none"
            />
        </div>
        <p v-if="unrecognized" class="text-xs text-red-600 mt-1">
            Not a 10-digit US number. Only US numbers are supported.
        </p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { showsAsUsDigits, usPhoneDigits } from '@/utils/phone';

// v-model holds the number as +1XXXXXXXXXX (or +1 and fewer digits while
// typing), '' when the field is empty, or the text as written when it is
// not a US number.
const model = defineModel({ type: String, default: '' });

defineProps({
    placeholder: { type: String, default: '8055551234' },
});

// Values that can't be shown as 10 US digits are shown as written, with a
// warning, rather than trimmed into a different number.
const unrecognized = computed(() => model.value !== '' && !showsAsUsDigits(model.value));

const display = computed(() => (unrecognized.value ? model.value : usPhoneDigits(model.value)));

function onInput(event) {
    const raw = event.target.value;
    const digits = usPhoneDigits(raw);

    if (digits === null) {
        model.value = raw.trim();
        return;
    }

    // Keep the field showing digits only, even when the model value is unchanged.
    if (raw !== digits) {
        event.target.value = digits;
    }
    model.value = digits ? `+1${digits}` : '';
}
</script>
