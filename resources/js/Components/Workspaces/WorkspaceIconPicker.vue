<script setup lang="ts">
import { Search } from '@lucide/vue';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'radix-vue';
import { computed, ref, watch } from 'vue';

import Input from '@/Components/ui/Input.vue';
import WorkspaceAvatar from '@/Components/WorkspaceAvatar.vue';
import { WORKSPACE_ICON_CATEGORIES } from '@/lib/workspaces';

withDefaults(
  defineProps<{
    name?: string;
    align?: 'start' | 'center' | 'end';
  }>(),
  { align: 'start' },
);

const icon = defineModel<string>('icon', { default: '' });

const open = ref(false);
const query = ref('');
const searchInput = ref<InstanceType<typeof Input> | null>(null);

const filteredCategories = computed(() => {
  const needle = query.value.trim().toLowerCase();

  return WORKSPACE_ICON_CATEGORIES.map((category) => ({
    label: category.label,
    icons: Object.entries(category.icons).filter(
      ([key]) => !needle || key.replace(/-/g, ' ').includes(needle) || category.label.toLowerCase().includes(needle),
    ),
  })).filter((category) => category.icons.length > 0);
});

watch(open, (value) => {
  if (value) query.value = '';
});

function onOpenAutoFocus(event: Event) {
  event.preventDefault();
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
  <PopoverRoot v-model:open="open">
    <PopoverTrigger as-child>
      <button
        type="button"
        title="Change icon"
        aria-label="Change icon"
        class="grid place-items-center rounded-lg transition-[box-shadow] duration-150 hover:ring-2 hover:ring-accent/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 data-[state=open]:ring-2 data-[state=open]:ring-accent/40"
      >
        <WorkspaceAvatar :name="name" :icon="icon || null" size="lg" />
      </button>
    </PopoverTrigger>
    <PopoverPortal>
      <PopoverContent
        :align="align"
        :side-offset="6"
        :collision-padding="12"
        class="z-[90] w-72 origin-[var(--radix-popover-content-transform-origin)] rounded-xl bg-overlay shadow-popover outline-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-[0.97] data-[state=open]:zoom-in-[0.97]"
        @open-auto-focus="onOpenAutoFocus"
      >
        <div class="flex items-center gap-1.5 border-b p-2">
          <div class="relative min-w-0 flex-1">
            <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-faint" />
            <Input ref="searchInput" v-model="query" type="search" size="sm" class="pl-8" placeholder="Search icons" />
          </div>
          <button
            v-if="icon"
            type="button"
            class="h-7 shrink-0 rounded-md px-2 text-xs text-muted transition-colors duration-100 hover:bg-elevated hover:text-foreground"
            @click="remove"
          >
            Remove
          </button>
        </div>

        <div class="max-h-64 overflow-y-auto overscroll-contain p-2">
          <template v-if="filteredCategories.length">
            <div v-for="category in filteredCategories" :key="category.label" class="mb-2 last:mb-0">
              <p class="px-0.5 pb-1 text-xs font-medium text-faint">{{ category.label }}</p>
              <div class="grid grid-cols-8 gap-0.5">
                <button
                  v-for="[key, component] in category.icons"
                  :key="key"
                  type="button"
                  :title="key.replace(/-/g, ' ')"
                  :aria-label="key.replace(/-/g, ' ')"
                  :aria-pressed="icon === key"
                  class="grid h-8 w-8 place-items-center rounded-md transition-colors duration-100"
                  :class="
                    icon === key ? 'bg-accent/15 text-accent' : 'text-muted hover:bg-elevated hover:text-foreground'
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
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>
