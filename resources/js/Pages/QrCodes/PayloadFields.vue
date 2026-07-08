<script setup lang="ts">
import Field from '@/Components/ui/Field.vue';
import type { PayloadDescriptors, PayloadField } from './types';

const props = defineProps<{
    type: string;
    descriptors: PayloadDescriptors;
    errors?: Record<string, string>;
}>();

const payload = defineModel<Record<string, any>>({ required: true });

const fields = () => props.descriptors[props.type]?.fields ?? props.descriptors.raw?.fields ?? [];

function error(key: string) {
    return props.errors?.[`payload.${key}`];
}

function disabled(field: PayloadField) {
    return field.disabledWhen ? payload.value[field.disabledWhen.key] === field.disabledWhen.value : false;
}
</script>

<template>
    <div class="grid gap-4">
        <template v-for="field in fields()" :key="field.key">
            <label v-if="field.control === 'checkbox'" class="flex items-center justify-between gap-3 rounded-md border bg-elevated/40 px-3 py-2.5">
                <span class="text-[13px] font-medium text-foreground">{{ field.label }}</span>
                <input v-model="payload[field.key]" type="checkbox" class="h-4 w-4 rounded" />
            </label>

            <Field v-else :label="field.label" :error="error(field.key)">
                <select v-if="field.control === 'select'" v-model="payload[field.key]" class="h-9">
                    <option v-for="option in field.options ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>

                <textarea
                    v-else-if="field.control === 'textarea'"
                    v-model="payload[field.key]"
                    :rows="field.rows ?? 4"
                    :class="field.class"
                    :placeholder="field.placeholder"
                />

                <input
                    v-else
                    v-model="payload[field.key]"
                    :type="field.control"
                    :step="field.step"
                    :disabled="disabled(field)"
                    class="h-9"
                    :placeholder="field.placeholder"
                />
            </Field>
        </template>
    </div>
</template>
