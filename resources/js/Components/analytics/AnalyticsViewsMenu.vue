<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Bookmark, BookmarkPlus, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuLabel from '@/Components/ui/MenuLabel.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import { viewMatches, viewPeriod, type ActiveView, type SavedView } from '@/lib/analyticsViews';
import { confirmAction } from '@/lib/confirm';
import { toast } from '@/lib/toast';

const props = defineProps<{
  views: SavedView[];
  activeView: ActiveView | null;
  query: Record<string, string>;
}>();

const dialog = ref<'save' | 'rename' | null>(null);
const form = useForm({ name: '', filters: {} as Record<string, string> });

const current = computed(() => props.activeView ?? props.views.find((view) => viewMatches(view, props.query)) ?? null);

function openSave() {
  form.reset();
  form.clearErrors();
  dialog.value = 'save';
}

function openRename() {
  form.clearErrors();
  form.name = current.value?.name ?? '';
  dialog.value = 'rename';
}

function submit() {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      toast({ title: dialog.value === 'save' ? 'View saved' : 'View renamed', tone: 'success' });
      dialog.value = null;
    },
  };

  if (dialog.value === 'rename' && current.value) {
    form.transform((data) => ({ name: data.name })).patch(route('analytics-views.update', current.value.id), options);
    return;
  }

  form.transform((data) => ({ name: data.name, filters: props.query })).post(route('analytics-views.store'), options);
}

function open(view: SavedView) {
  router.get(route('analytics.index', { view: view.id }));
}

async function destroy() {
  const view = current.value;
  if (!view) return;

  const confirmed = await confirmAction({
    title: `Delete ${view.name}?`,
    message: 'Only this saved view is deleted. Analytics data is not affected.',
    confirmLabel: 'Delete view',
    destructive: true,
  });

  if (confirmed)
    router.delete(route('analytics-views.destroy', view.id), { onSuccess: () => toast({ title: 'View deleted' }) });
}
</script>

<template>
  <Menu align="end" width="w-64">
    <template #trigger>
      <Button variant="secondary" size="sm" type="button">
        <Bookmark />
        <span class="max-w-40 truncate">{{ current?.name ?? 'Views' }}</span>
      </Button>
    </template>
    <template v-if="views.length > 0">
      <MenuLabel>Your saved views</MenuLabel>
      <MenuItem
        v-for="view in views"
        :key="view.id"
        :checked="current?.id === view.id"
        :title="viewPeriod(view.filters)"
        @select="open(view)"
      >
        {{ view.name }}
      </MenuItem>
      <MenuSeparator />
    </template>
    <MenuItem :icon="BookmarkPlus" @select="openSave">Save current view…</MenuItem>
    <template v-if="current">
      <MenuItem :icon="Pencil" @select="openRename">Rename {{ current.name }}…</MenuItem>
      <MenuItem :icon="Trash2" destructive @select="destroy">Delete {{ current.name }}</MenuItem>
    </template>
  </Menu>

  <Dialog
    :open="dialog !== null"
    :title="dialog === 'rename' ? 'Rename view' : 'Save view'"
    :description="
      dialog === 'rename'
        ? undefined
        : `Saves these filters for you only. ${viewPeriod(query)}: rolling ranges move forward each time you open the view.`
    "
    size="sm"
    @update:open="dialog = $event ? dialog : null"
  >
    <form class="space-y-5 px-5 pb-5 pt-4" @submit.prevent="submit">
      <Field label="Name" :error="form.errors.name ?? Object.values(form.errors).find(Boolean)">
        <Input v-model="form.name" maxlength="80" placeholder="Launch campaign" autofocus />
      </Field>
      <div class="flex justify-end gap-2">
        <Button variant="ghost" type="button" @click="dialog = null">Cancel</Button>
        <Button :loading="form.processing">{{ dialog === 'rename' ? 'Rename' : 'Save view' }}</Button>
      </div>
    </form>
  </Dialog>
</template>
