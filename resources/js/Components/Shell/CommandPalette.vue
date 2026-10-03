<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
  ArrowRight,
  BarChart3,
  Building2,
  Folder,
  Globe2,
  Home,
  KeyRound,
  Link2,
  QrCode,
  Search,
  Server,
  Settings,
  ShieldCheck,
  Sparkles,
  User,
  Users,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';

import Dialog from '@/Components/ui/Dialog.vue';
import Kbd from '@/Components/ui/Kbd.vue';
import WorkspaceAvatar from '@/Components/WorkspaceAvatar.vue';
import { displayUrl, isLikelyUrl, normalizeUrl } from '@/lib/links';
import { useShell } from '@/lib/shell';
import { copyToClipboard, toast, writeClipboard } from '@/lib/toast';

const open = defineModel<boolean>('open', { default: false });

const { user, workspace, workspaces, navigation, canManage, canEdit } = useShell();

type Command = {
  id: string;
  group: string;
  label: string;
  hint?: string;
  icon?: unknown;
  workspace?: { name: string; icon?: string | null };
  keywords?: string;
  run: () => void;
};

const query = ref('');
const activeIndex = ref(0);
const input = ref<HTMLInputElement | null>(null);
const list = ref<HTMLElement | null>(null);

function visit(name: string, params?: Record<string, unknown>) {
  router.visit(route(name, params));
}

function shorten(url: string) {
  router.post(
    route('short-links.store'),
    { destination_url: url, is_enabled: true },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: async (page) => {
        const link = (page.flash as { createdLink?: { id: number; short_url: string } }).createdLink;
        if (!link) return;
        const copied = await writeClipboard(link.short_url);
        toast({
          title: copied ? 'Short link created and copied' : 'Short link created',
          description: displayUrl(link.short_url),
          tone: 'success',
          duration: 6000,
          action: copied
            ? { label: 'Show', run: () => visit('links.index', { link: link.id }) }
            : { label: 'Copy', run: () => void copyToClipboard(link.short_url) },
        });
      },
      onError: (errors) => toast({ title: Object.values(errors)[0] ?? 'Could not create link', tone: 'danger' }),
    },
  );
}

const staticCommands = computed<Command[]>(() => {
  const commands: Command[] = [
    { id: 'home', group: 'Go to', label: 'Home', icon: Home, run: () => visit('dashboard') },
    { id: 'links', group: 'Go to', label: 'All links', icon: Link2, run: () => visit('links.index') },
    { id: 'qr', group: 'Go to', label: 'QR codes', icon: QrCode, run: () => visit('qr-codes.index') },
    { id: 'analytics', group: 'Go to', label: 'Analytics', icon: BarChart3, run: () => visit('analytics.index') },
    ...(navigation.value?.folders ?? []).map((folder) => ({
      id: `folder-${folder.id}`,
      group: 'Folders',
      label: folder.name,
      hint: `${folder.links_count} link${folder.links_count === 1 ? '' : 's'}`,
      icon: Folder,
      run: () => visit('links.index', { folder: folder.id }),
    })),
  ];

  if (canManage.value) {
    commands.push(
      {
        id: 'ws-settings',
        group: 'Settings',
        label: 'Workspace settings',
        icon: Building2,
        keywords: 'name icon color preferred domain',
        run: () => visit('settings.workspace'),
      },
      {
        id: 'domains',
        group: 'Settings',
        label: 'Domains',
        icon: Globe2,
        keywords: 'dns hostname',
        run: () => visit('domains.index'),
      },
      {
        id: 'members',
        group: 'Settings',
        label: 'Members',
        icon: Users,
        keywords: 'invite team people roles',
        run: () => visit('members.index'),
      },
    );
  }

  commands.push(
    {
      id: 'profile',
      group: 'Settings',
      label: 'Profile',
      icon: User,
      run: () => visit('profile.edit', { tab: 'profile' }),
    },
    {
      id: 'security',
      group: 'Settings',
      label: 'Security',
      icon: ShieldCheck,
      keywords: 'password two-factor 2fa',
      run: () => visit('profile.edit', { tab: 'security' }),
    },
    {
      id: 'tokens',
      group: 'Settings',
      label: 'API tokens',
      icon: KeyRound,
      run: () => visit('profile.edit', { tab: 'api-tokens' }),
    },
  );

  if (user.value.is_instance_admin) {
    commands.push({
      id: 'instance',
      group: 'Settings',
      label: 'Instance settings',
      icon: Server,
      keywords: 'registration updates reserved slugs',
      run: () => visit('settings.index'),
    });
  }

  for (const item of workspaces.value) {
    if (item.id === workspace.value?.id) continue;
    commands.push({
      id: `ws-${item.id}`,
      group: 'Switch workspace',
      label: item.name,
      workspace: item,
      run: () =>
        router.post(route('workspaces.switch', item.id), { destination: 'dashboard' }, { preserveState: false }),
    });
  }

  return commands;
});

const commands = computed<Command[]>(() => {
  const term = query.value.trim().toLowerCase();
  const url = normalizeUrl(query.value);
  const result: Command[] = [];

  if (canEdit.value && isLikelyUrl(url)) {
    result.push({
      id: 'shorten',
      group: 'Create',
      label: `Shorten ${displayUrl(url)}`,
      hint: 'Creates and copies the short link',
      icon: Sparkles,
      run: () => shorten(url),
    });
  }

  if (!term) return [...result, ...staticCommands.value];

  if (canEdit.value && !isLikelyUrl(url)) {
    result.push({
      id: 'search-links',
      group: 'Search',
      label: `Search links for “${query.value.trim()}”`,
      icon: Search,
      run: () => visit('links.index', { search: query.value.trim() }),
    });
  }

  return [
    ...result,
    ...staticCommands.value.filter((command) =>
      `${command.label} ${command.group} ${command.keywords ?? ''}`.toLowerCase().includes(term),
    ),
  ];
});

const grouped = computed(() => {
  const groups = new Map<string, { command: Command; index: number }[]>();
  commands.value.forEach((command, index) => {
    const bucket = groups.get(command.group) ?? [];
    bucket.push({ command, index });
    groups.set(command.group, bucket);
  });
  return [...groups.entries()];
});

watch(query, () => (activeIndex.value = 0));
watch(open, (value) => {
  if (!value) return;
  query.value = '';
  activeIndex.value = 0;
  nextTick(() => input.value?.focus());
});

function run(command: Command | undefined) {
  if (!command) return;
  open.value = false;
  command.run();
}

function move(delta: number) {
  const count = commands.value.length;
  if (!count) return;
  activeIndex.value = (activeIndex.value + delta + count) % count;
  nextTick(() => list.value?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' }));
}
</script>

<template>
  <Dialog v-model:open="open" size="lg" label="Command palette" class="top-[12vh] overflow-hidden">
    <div class="flex h-14 items-center gap-3 border-b px-4">
      <Search class="h-4 w-4 shrink-0 text-faint" />
      <input
        ref="input"
        v-model="query"
        type="text"
        spellcheck="false"
        autocomplete="off"
        aria-label="Search or paste a URL"
        placeholder="Paste a URL to shorten, or search…"
        class="h-full min-w-0 flex-1 bg-transparent text-[15px] text-foreground outline-none placeholder:text-faint"
        @keydown.down.prevent="move(1)"
        @keydown.up.prevent="move(-1)"
        @keydown.enter.prevent="run(commands[activeIndex])"
      />
      <Kbd>Esc</Kbd>
    </div>

    <div ref="list" class="max-h-[min(420px,60vh)] overflow-y-auto p-1.5" role="listbox">
      <div v-for="[group, items] in grouped" :key="group" class="pb-1">
        <p class="px-2.5 pb-1 pt-2 text-xs font-medium text-faint">{{ group }}</p>
        <button
          v-for="{ command, index } in items"
          :key="command.id"
          type="button"
          role="option"
          :aria-selected="index === activeIndex"
          :data-active="index === activeIndex"
          class="flex h-10 w-full items-center gap-3 rounded-lg px-2.5 text-left text-sm transition-colors duration-75"
          :class="index === activeIndex ? 'bg-elevated text-foreground' : 'text-muted'"
          @mousemove="activeIndex = index"
          @click="run(command)"
        >
          <WorkspaceAvatar
            v-if="command.workspace"
            :name="command.workspace.name"
            :icon="command.workspace.icon"
            size="sm"
          />
          <component
            :is="command.icon"
            v-else
            class="h-4 w-4 shrink-0"
            :class="command.id === 'shorten' ? 'text-accent' : ''"
          />
          <span class="min-w-0 flex-1 truncate" :class="index === activeIndex ? 'text-foreground' : ''">{{
            command.label
          }}</span>
          <span v-if="command.hint" class="shrink-0 text-xs text-faint">{{ command.hint }}</span>
          <ArrowRight v-if="index === activeIndex" class="h-3.5 w-3.5 shrink-0 text-faint" />
        </button>
      </div>
      <p v-if="!commands.length" class="px-3 py-10 text-center text-sm text-faint">No results</p>
    </div>

    <div class="flex items-center gap-4 border-t px-4 py-2.5 text-xs text-faint">
      <span class="flex items-center gap-1.5"><Kbd>↑</Kbd><Kbd>↓</Kbd> navigate</span>
      <span class="flex items-center gap-1.5"><Kbd>↵</Kbd> open</span>
      <span class="ml-auto hidden items-center gap-1.5 sm:flex"
        ><Settings class="h-3 w-3" /> Tip: paste any URL here</span
      >
    </div>
  </Dialog>
</template>
