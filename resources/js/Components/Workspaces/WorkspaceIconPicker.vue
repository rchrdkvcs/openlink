<script setup lang="ts">
import { Search } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';

import Popover from '@/Components/ui/Popover.vue';
import WorkspaceAvatar from '@/Components/WorkspaceAvatar.vue';
import { WORKSPACE_ICON_CATEGORIES } from '@/lib/workspaces';

defineProps<{
  name?: string;
  color?: string | null;
}>();

const icon = defineModel<string>('icon', { default: '' });

const open = ref(false);
const query = ref('');
const searchInput = ref<HTMLInputElement | null>(null);

const filteredCategories = computed(() => {
  const needle = query.value.trim().toLowerCase();

  return WORKSPACE_ICON_CATEGORIES.map((category) => ({
    label: category.label,
    icons: Object.entries(category.icons).filter(
      ([key]) => !needle || key.replace(/-/g, ' ').includes(needle) || category.label.toLowerCase().includes(needle),
    ),
  })).filter((category) => category.icons.length > 0);
});

async function focusSearch(event: Event) {
  event.preventDefault();
  query.value = '';
  await nextTick();
  searchInput.value?.focus();
}

function pick(key: string) {
  icon.value = icon.value === key ? '' : key;
  open.value = false;
}

function remove() {
  icon.value = '';
  open.value = false;
}
</script>

<template>
  <Popover v-model:open="open" class="w-72" aria-label="Workspace icon" @open-auto-focus="focusSearch">
    <template #trigger>
      <button
        type="button"
        title="Change icon"
        aria-label="Change icon"
        class="group/picker grid place-items-center rounded-lg transition-shadow duration-150 hover:ring-2 hover:ring-accent/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
      >
        <WorkspaceAvatar :name="name" :icon="icon || null" :color="color || null" size="lg" />
      </button>
    </template>
    <div class="flex items-center gap-2 border-b p-2">
      <div class="relative min-w-0 flex-1">
        <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-faint" />
        <input
          ref="searchInput"
          v-model="query"
          type="search"
          class="h-8 w-full pl-8 text-[13px]"
          aria-label="Search icons"
          placeholder="Search icons…"
        />
      </div>
      <button
        v-if="icon"
        type="button"
        class="shrink-0 rounded-md px-2 py-1.5 text-xs text-muted transition-colors duration-100 hover:bg-elevated hover:text-foreground"
        @click="remove"
      >
        Remove
      </button>
    </div>

    <div class="max-h-64 overflow-y-auto p-2">
      <template v-if="filteredCategories.length">
        <div v-for="category in filteredCategories" :key="category.label" class="mb-2 last:mb-0">
          <p class="px-0.5 pb-1 text-[11px] font-medium uppercase tracking-wide text-faint">
            {{ category.label }}
          </p>
          <div class="flex flex-wrap gap-1">
            <button
              v-for="[key, component] in category.icons"
              :key="key"
              type="button"
              :title="key.replace(/-/g, ' ')"
              class="grid h-8 w-8 place-items-center rounded-md transition-colors duration-100"
              :class="
                icon === key
                  ? 'bg-accent/15 text-foreground ring-1 ring-inset ring-accent'
                  : 'text-muted hover:bg-elevated hover:text-foreground'
              "
              @click="pick(key)"
            >
              <component :is="component" class="h-4 w-4" />
            </button>
          </div>
        </div>
      </template>
      <p v-else class="px-1 py-4 text-center text-xs text-faint">No icons match “{{ query }}”.</p>
    </div>
  </Popover>
</template>
