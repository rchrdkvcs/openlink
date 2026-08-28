import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch, type Ref } from 'vue';

import type { LinksPageProps, RoutingRuleDraft, ShortLink } from './types';

export function useLinkForms(props: LinksPageProps, selectedLink: Ref<ShortLink | null>, createOpen: Ref<boolean>) {
  const copiedLinkId = ref<number | null>(null);
  const usableDomains = computed(() => props.domains.filter((domain) => domain.status === 'active'));
  const PASSWORD_MASK = '********';

  const linkForm = useForm({
    domain_id: props.currentWorkspace.preferred_domain_id ?? usableDomains.value[0]?.id ?? '',
    folder_id: '',
    slug: '',
    destination_url: '',
    fallback_url: '',
    is_enabled: true,
    activates_at: '',
    expires_at: '',
    visit_limit: '',
    password: '',
    tags: '',
    routing_rules: [] as RoutingRuleDraft[],
  });

  const editForm = useForm({
    folder_id: '',
    domain_id: '' as number | string,
    slug: '',
    destination_url: '',
    fallback_url: '',
    is_enabled: true,
    activates_at: '',
    expires_at: '',
    visit_limit: '',
    password: '',
    routing_rules: [] as RoutingRuleDraft[],
  });

  watch(selectedLink, (link) => {
    if (!link) {
      return;
    }

    editForm.defaults({
      folder_id: link.folder?.id ? String(link.folder.id) : '',
      domain_id: link.domain.id,
      slug: link.slug,
      destination_url: link.destination_url,
      fallback_url: link.fallback_url ?? '',
      is_enabled: link.is_enabled,
      activates_at: link.activates_at ? String(link.activates_at).slice(0, 16) : '',
      expires_at: link.expires_at ? String(link.expires_at).slice(0, 16) : '',
      visit_limit: link.visit_limit ? String(link.visit_limit) : '',
      password: link.has_password ? PASSWORD_MASK : '',
      routing_rules: cloneRoutingRules(link.routing_rules ?? []),
    });
    editForm.reset();
  });

  watch(
    () => props.links,
    (links) => {
      if (!selectedLink.value) {
        return;
      }

      selectedLink.value = links.find((link) => link.id === selectedLink.value?.id) ?? null;
    },
  );

  function submitLink() {
    linkForm.post(route('short-links.store'), {
      preserveScroll: true,
      onSuccess: () => {
        linkForm.reset('slug', 'destination_url', 'fallback_url', 'password', 'tags');
        linkForm.routing_rules = [];
        createOpen.value = false;
      },
    });
  }

  function updateLink() {
    if (!selectedLink.value) {
      return;
    }

    editForm
      .transform((data) => {
        if (selectedLink.value?.has_password && data.password === PASSWORD_MASK) {
          const { password: _password, ...payload } = data;
          return payload;
        }

        return data;
      })
      .patch(route('short-links.update', selectedLink.value.id), { preserveScroll: true });
  }

  function archiveLink(link: ShortLink) {
    useForm({}).post(route('short-links.archive', link.id), { preserveScroll: true });
  }

  function deleteLink(link: ShortLink) {
    if (confirm(`Permanently delete ${link.short_url}? This frees its slug.`)) {
      useForm({}).delete(route('short-links.destroy', link.id), { preserveScroll: true });
    }
  }

  async function copyShortUrl(link: ShortLink) {
    try {
      await navigator.clipboard.writeText(link.short_url);
      copiedLinkId.value = link.id;
      setTimeout(() => {
        if (copiedLinkId.value === link.id) {
          copiedLinkId.value = null;
        }
      }, 1500);
    } catch {
      // Clipboard unavailable in insecure contexts.
    }
  }

  function statusVariant(status: string) {
    if (status === 'active') return 'success';
    if (status === 'scheduled') return 'accent';
    if (status === 'expired') return 'warning';
    if (status === 'archived') return 'default';
    return 'danger';
  }

  return {
    copiedLinkId,
    usableDomains,
    linkForm,
    editForm,
    submitLink,
    updateLink,
    archiveLink,
    deleteLink,
    copyShortUrl,
    statusVariant,
  };
}

function cloneRoutingRules(rules: RoutingRuleDraft[]): RoutingRuleDraft[] {
  return rules.map((rule) => ({
    ...rule,
    conditions: JSON.parse(JSON.stringify(rule.conditions ?? [])),
    variants: (rule.variants ?? []).map((variant) => ({ ...variant })),
  }));
}
