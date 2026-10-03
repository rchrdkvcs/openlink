import { router } from '@inertiajs/vue3';

import type { CommandActions } from '@/Components/Shell/commands';
import { displayUrl } from '@/lib/links';
import { copyToClipboard, toast, writeClipboard } from '@/lib/toast';

function visit(name: string, params?: Record<string, unknown>) {
  router.visit(route(name, params));
}

function switchWorkspace(workspaceId: number) {
  router.post(route('workspaces.switch', workspaceId), { destination: 'dashboard' }, { preserveState: false });
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

export const commandActions: CommandActions = { visit, switchWorkspace, shorten };
