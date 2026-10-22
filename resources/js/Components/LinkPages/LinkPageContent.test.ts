import { describe, expect, it } from 'vitest';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

import LinkPageContent from './LinkPageContent.vue';
import type { PublicLinkPage } from './types';

function render(page: PublicLinkPage) {
  return renderToString(createSSRApp({ render: () => h(LinkPageContent, { page }) }));
}

const base: PublicLinkPage = {
  title: 'Spring',
  description: null,
  logo: null,
  links: [],
  display: { show_icons: true, show_labels: true, show_urls: false },
};

describe('LinkPageContent', () => {
  it('renders markup in text fields as plain text', async () => {
    const html = await render({
      ...base,
      title: '<script>alert(1)</script>',
      description: '<img src=x onerror=alert(2)>',
      logo: null,
      links: [{ label: '<b onclick="alert(3)">Hi</b>', url: 'https://go.example.test/a', icon: null }],
    });

    expect(html).not.toContain('<script>');
    expect(html).not.toContain('<img src=x');
    expect(html).not.toContain('<b onclick');
    expect(html).toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
    expect(html).toContain('&lt;img src=x onerror=alert(2)&gt;');
    expect(html).toContain('&lt;b onclick=&quot;alert(3)&quot;&gt;Hi&lt;/b&gt;');
  });

  it('shows a neutral message when no links are available', async () => {
    const html = await render(base);

    expect(html).toContain('No links are available right now.');
    expect(html).not.toContain('<a ');
  });

  it('renders each link as a labelled anchor to its short URL', async () => {
    const html = await render({
      ...base,
      links: [
        { label: 'Tickets', url: 'https://go.example.test/tickets', icon: 'github' },
        { label: 'Schedule', url: 'https://go.example.test/schedule', icon: null },
      ],
    });

    expect(html).toContain('aria-label="Links"');
    expect(html.match(/<a /g)).toHaveLength(2);
    expect(html).toContain('href="https://go.example.test/tickets"');
    expect(html).toContain('>Tickets</span>');
    expect(html.indexOf('Tickets')).toBeLessThan(html.indexOf('Schedule'));
    expect(html).not.toContain('No links are available right now.');
  });

  it('keeps accessible names and short URL targets in icons-only mode', async () => {
    const html = await render({
      ...base,
      display: { show_icons: true, show_labels: false, show_urls: false },
      links: [{ label: 'Tickets', url: 'https://go.example.test/tickets', icon: 'github' }],
    });

    expect(html).toContain('href="https://go.example.test/tickets"');
    expect(html).toContain('aria-label="Tickets"');
    expect(html).toContain('title="Tickets"');
    expect(html).not.toContain('>Tickets</span>');
    expect(html).toContain('<img');
    expect(html).not.toContain('lucide-arrow-up-right');
  });

  it('renders URL-only entries with their short addresses and hides icons and labels', async () => {
    const html = await render({
      ...base,
      display: { show_icons: false, show_labels: false, show_urls: true },
      links: [{ label: 'Tickets', url: 'https://go.example.test/tickets', icon: 'github' }],
    });

    expect(html).toContain('>go.example.test/tickets</span>');
    expect(html).not.toContain('>Tickets</span>');
    expect(html).not.toContain('<img');
    expect(html).toContain('aria-label="Tickets (go.example.test/tickets)"');
  });
});
