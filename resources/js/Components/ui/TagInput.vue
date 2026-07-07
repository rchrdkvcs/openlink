<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        /** Comma-separated tag list — the wire format the backend accepts. */
        modelValue: string;
        suggestions?: { id: number; name: string }[];
        placeholder?: string;
    }>(),
    { suggestions: () => [], placeholder: 'Type a tag, press Enter' },
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const draft = ref('');

const pills = computed(() =>
    props.modelValue.split(',').map((t) => t.trim()).filter(Boolean),
);

const availableSuggestions = computed(() => props.suggestions.filter((s) => !pills.value.includes(s.name)).slice(0, 6));

function update(tags: string[]) {
    emit('update:modelValue', tags.join(', '));
}

function commit() {
    const value = draft.value.trim().replace(/,+$/, '');
    draft.value = '';
    if (value && !pills.value.includes(value)) {
        update([...pills.value, value]);
    }
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === ',') {
        e.preventDefault();
        commit();
    }
}

function remove(tag: string) {
    update(pills.value.filter((t) => t !== tag));
}

function popLast() {
    if (draft.value === '' && pills.value.length > 0) {
        update(pills.value.slice(0, -1));
    }
}
</script>

<template>
    <div>
        <div
            class="flex min-h-9 flex-wrap items-center gap-1.5 rounded-md border bg-surface px-2 py-1.5 transition-colors focus-within:border-accent/60 focus-within:ring-2 focus-within:ring-accent/25"
        >
            <span v-for="tag in pills" :key="tag" class="inline-flex items-center gap-1 rounded bg-elevated px-1.5 py-0.5 text-xs text-muted">
                #{{ tag }}
                <button type="button" class="text-faint hover:text-foreground" @click="remove(tag)">
                    <X class="h-3 w-3" />
                </button>
            </span>
            <input
                v-model="draft"
                class="h-6 min-w-24 flex-1 !border-0 !bg-transparent !p-0 !text-[13px] !shadow-none !ring-0"
                :placeholder="pills.length ? '' : placeholder"
                @keydown.enter.prevent="commit"
                @keydown="onKeydown"
                @keydown.delete="popLast"
                @blur="commit"
            />
        </div>
        <div v-if="availableSuggestions.length" class="mt-1.5 flex flex-wrap gap-1">
            <button
                v-for="suggestion in availableSuggestions"
                :key="suggestion.id"
                type="button"
                class="rounded-full border border-dashed px-2 py-0.5 text-[11px] text-faint transition-colors hover:border-accent/50 hover:text-foreground"
                @click="update([...pills, suggestion.name])"
            >
                + {{ suggestion.name }}
            </button>
        </div>
    </div>
</template>
