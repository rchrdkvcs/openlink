import { describe, expect, it } from 'vitest';
import { effectScope, nextTick, ref } from 'vue';

import type { UtmTemplate } from '@/lib/utm';

import { useUtmBuilder } from './useUtmBuilder';

const newsletter: UtmTemplate = {
  id: 1,
  name: 'Newsletter',
  utm_source: 'newsletter',
  utm_medium: 'email',
  utm_campaign: null,
  utm_term: null,
  utm_content: null,
};

const ads: UtmTemplate = { ...newsletter, id: 2, name: 'Ads', utm_source: 'google', utm_medium: 'cpc' };

function setup(options: { url?: string; templates?: UtmTemplate[]; disabled?: boolean; canManage?: boolean } = {}) {
  const destination = ref(options.url ?? 'https://example.com/?ref=x&utm_source=old#top');
  const templates = ref(options.templates ?? [newsletter, ads]);
  const disabled = ref(options.disabled ?? false);
  const canManage = ref(options.canManage ?? true);
  const builder = effectScope().run(() =>
    useUtmBuilder({
      destination,
      templates: () => templates.value,
      disabled: () => disabled.value,
      canManageTemplates: () => canManage.value,
    }),
  )!;

  return { destination, templates, disabled, canManage, builder };
}

describe('useUtmBuilder', () => {
  it('reads the destination into fields and summarises filled values', () => {
    const { builder } = setup({ url: 'https://example.com/?utm_source=newsletter&utm_medium=email' });

    expect(builder.values.value.utm_source).toBe('newsletter');
    expect(builder.summary.value).toBe('newsletter · email');
  });

  it('lists template options and previews the changes of the selected template', () => {
    const { builder, destination } = setup();

    expect(builder.templateOptions.value.map((option) => option.label)).toEqual([
      'Choose a template',
      'Newsletter',
      'Ads',
    ]);

    builder.selectedId.value = '1';

    expect(builder.selected.value?.name).toBe('Newsletter');
    expect(builder.changes.value).toEqual([
      { key: 'utm_source', from: 'old', to: 'newsletter' },
      { key: 'utm_medium', from: '', to: 'email' },
    ]);
    expect(builder.canApply.value).toBe(true);
    expect(destination.value).toBe('https://example.com/?ref=x&utm_source=old#top');
  });

  it('applies the selected template only when asked and then clears the selection', () => {
    const { builder, destination } = setup();

    builder.selectedId.value = '1';
    builder.apply();

    expect(destination.value).toBe('https://example.com/?ref=x&utm_source=newsletter&utm_medium=email#top');
    expect(builder.selectedId.value).toBe('');
    expect(builder.selected.value).toBeUndefined();
  });

  it('lets a field override a value after the template is applied', () => {
    const { builder, destination } = setup();

    builder.selectedId.value = '1';
    builder.apply();
    builder.update('utm_medium', 'sms');

    expect(destination.value).toBe('https://example.com/?ref=x&utm_source=newsletter&utm_medium=sms#top');
  });

  it('removes a copied template and keeps unrelated URL bytes after reopening', () => {
    const url = 'https://example.com/caf%C3%A9?a=1&a=2&q=café&next=%2fhome#route?utm_source=fragment';
    const { builder, destination } = setup({ url });

    builder.selectedId.value = '1';
    builder.apply();
    builder.update('utm_campaign', 'été sale');
    builder.removeAll();

    expect(destination.value).toBe(url);
    expect(builder.selectedId.value).toBe('');
    expect(builder.filled.value).toEqual([]);
    expect(setup({ url: destination.value }).builder.summary.value).toBe('None');
  });

  it('offers removal for empty and repeated parameters and removes one key at a time', () => {
    const { builder, destination } = setup({
      url: 'https://example.com/?ref=%2f&utm_source&utm_source=copy&utm_medium=email#top',
    });

    expect(builder.filled.value).toEqual(['utm_source', 'utm_medium']);
    builder.update('utm_source', '');
    expect(destination.value).toBe('https://example.com/?ref=%2f&utm_medium=email#top');
    expect(builder.filled.value).toEqual(['utm_medium']);
  });

  it('keeps removal disabled for viewers', () => {
    const { builder, destination } = setup({ disabled: true });

    builder.removeAll();

    expect(destination.value).toBe('https://example.com/?ref=x&utm_source=old#top');
  });

  it('disables Apply when the destination already matches the template', () => {
    const { builder } = setup({ url: 'https://example.com/?utm_source=newsletter&utm_medium=email' });

    builder.selectedId.value = '1';

    expect(builder.changes.value).toEqual([]);
    expect(builder.applicable.value).toBe(false);
    expect(builder.canApply.value).toBe(false);
  });

  it('disables Apply and the fields while the destination is empty', () => {
    const { builder, destination } = setup({ url: '' });

    builder.selectedId.value = '1';
    builder.apply();
    builder.update('utm_source', 'newsletter');

    expect(builder.hasDestination.value).toBe(false);
    expect(builder.fieldsDisabled.value).toBe(true);
    expect(builder.canApply.value).toBe(false);
    expect(destination.value).toBe('');
  });

  it('keeps a viewer from applying templates or editing fields', () => {
    const { builder, destination } = setup({ disabled: true });

    builder.selectedId.value = '1';
    builder.apply();
    builder.update('utm_source', 'hijack');

    expect(builder.fieldsDisabled.value).toBe(true);
    expect(builder.canApply.value).toBe(false);
    expect(destination.value).toBe('https://example.com/?ref=x&utm_source=old#top');
  });

  it('offers the create-template link only to managers when no templates exist', () => {
    expect(setup({ templates: [], canManage: true }).builder.showCreateTemplateLink.value).toBe(true);
    expect(setup({ templates: [], canManage: false }).builder.showCreateTemplateLink.value).toBe(false);
    expect(setup({ templates: [newsletter], canManage: true }).builder.showCreateTemplateLink.value).toBe(false);
  });

  it('drops the selection when the selected template disappears', async () => {
    const { builder, templates } = setup();

    builder.selectedId.value = '2';
    templates.value = [newsletter];
    await nextTick();

    expect(builder.selectedId.value).toBe('');
  });

  it('previews the destination exactly as it will be saved', () => {
    expect(setup({ url: ' example.com/?utm_source=a ' }).builder.finalUrl.value).toBe(
      'https://example.com/?utm_source=a',
    );
  });
});
