<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { ref } from 'vue';

defineProps<{
    modelValue: string;
    placeholder?: string;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const show = ref(false);
</script>

<template>
    <div class="relative">
        <input
            :value="modelValue"
            :type="show ? 'text' : 'password'"
            class="h-9 !pr-10"
            :placeholder="placeholder"
            autocomplete="new-password"
            @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        />
        <button
            type="button"
            class="absolute right-1 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-md text-faint transition-colors hover:text-foreground"
            :title="show ? 'Hide password' : 'Show password'"
            @click="show = !show"
        >
            <component :is="show ? EyeOff : Eye" class="h-3.5 w-3.5" />
        </button>
    </div>
</template>
