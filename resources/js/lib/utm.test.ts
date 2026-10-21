import { describe, expect, it } from 'vitest';

import {
  applyTemplate,
  emptyUtm,
  readUtm,
  templateChanges,
  templateForm,
  templateSummary,
  type UtmTemplateValues,
  writeUtm,
} from './utm';

function template(values: Partial<UtmTemplateValues> = {}): UtmTemplateValues {
  return { utm_source: null, utm_medium: null, utm_campaign: null, utm_term: null, utm_content: null, ...values };
}

describe('readUtm', () => {
  it('reads each supported UTM parameter from the destination URL', () => {
    expect(
      readUtm('https://example.com/launch?utm_source=newsletter&utm_medium=email&utm_campaign=spring%20sale#top'),
    ).toEqual({
      utm_source: 'newsletter',
      utm_medium: 'email',
      utm_campaign: 'spring sale',
      utm_term: '',
      utm_content: '',
    });
  });

  it('decodes plus signs, Unicode and percent-encoded values', () => {
    expect(readUtm('https://example.com/?utm_term=red+shoes&utm_content=%C3%A9t%C3%A9&utm_campaign=caf%C3%A9')).toEqual(
      {
        ...emptyUtm(),
        utm_term: 'red shoes',
        utm_content: 'été',
        utm_campaign: 'café',
      },
    );
  });

  it('uses the first occurrence when a UTM parameter is repeated', () => {
    expect(readUtm('https://example.com/?utm_source=first&utm_source=second').utm_source).toBe('first');
  });

  it('ignores the fragment, keys with other casing and malformed encoding', () => {
    expect(readUtm('https://example.com/?UTM_SOURCE=upper&utm_medium=%E0%A4%A#utm_source=hash')).toEqual({
      ...emptyUtm(),
      utm_medium: '%E0%A4%A',
    });
  });

  it('returns empty values for a URL without a query', () => {
    expect(readUtm('https://example.com/page')).toEqual(emptyUtm());
  });
});

describe('writeUtm', () => {
  const messy = 'https://example.com/caf%C3%A9/é?a=1&a=2&q=café&next=%2Fhome%3Fx%3D1&plus=a+b&flag&&#section/é';

  it('leaves a URL unchanged when the builder is empty', () => {
    expect(writeUtm(messy, emptyUtm())).toBe(messy);
    expect(writeUtm('https://example.com/?', emptyUtm())).toBe('https://example.com/?');
    expect(writeUtm('https://example.com', emptyUtm())).toBe('https://example.com');
  });

  it('round-trips a URL whose UTM parameters are unchanged', () => {
    const url = 'https://example.com/?utm_source=News+Letter&ref=x&utm_campaign=%C3%A9t%C3%A9#top';

    expect(writeUtm(url, readUtm(url))).toBe(url);
  });

  it('appends new UTM parameters before the fragment while keeping every other part verbatim', () => {
    expect(writeUtm(messy, { ...emptyUtm(), utm_source: 'newsletter', utm_campaign: 'spring sale' })).toBe(
      'https://example.com/caf%C3%A9/é?a=1&a=2&q=café&next=%2Fhome%3Fx%3D1&plus=a+b&flag&&utm_source=newsletter&utm_campaign=spring%20sale#section/é',
    );
  });

  it('adds a query to a URL that has none', () => {
    expect(writeUtm('https://example.com/page#top', { utm_medium: 'email' })).toBe(
      'https://example.com/page?utm_medium=email#top',
    );
  });

  it('replaces an edited parameter in place and drops its duplicates', () => {
    expect(writeUtm('https://example.com/?utm_source=old&ref=x&utm_source=dup&ref=y', { utm_source: 'new' })).toBe(
      'https://example.com/?utm_source=new&ref=x&ref=y',
    );
  });

  it('keeps one value per UTM parameter even when the value already matches', () => {
    expect(writeUtm('https://example.com/?utm_source=a&utm_source=a', { utm_source: 'a' })).toBe(
      'https://example.com/?utm_source=a',
    );
  });

  it('removes every occurrence of a cleared parameter and drops an empty query', () => {
    expect(writeUtm('https://example.com/?utm_source=a&ref=x&utm_source=b', { utm_source: '' })).toBe(
      'https://example.com/?ref=x',
    );
    expect(writeUtm('https://example.com/p?utm_source=a#f', { utm_source: '' })).toBe('https://example.com/p#f');
  });

  it('removes a parameter that is present without a value when its field is cleared', () => {
    expect(writeUtm('https://example.com/?utm_source=&ref=x&utm_medium', { utm_source: '', utm_medium: '' })).toBe(
      'https://example.com/?ref=x',
    );
  });

  it('only touches the parameters it is given', () => {
    expect(writeUtm('https://example.com/?utm_source=a&utm_medium=b', { utm_medium: 'c' })).toBe(
      'https://example.com/?utm_source=a&utm_medium=c',
    );
  });

  it('percent-encodes reserved characters and Unicode in values', () => {
    const url = writeUtm('https://example.com/', { utm_campaign: 'été & co=1 #2 +3 50%' });

    expect(url).toBe('https://example.com/?utm_campaign=%C3%A9t%C3%A9%20%26%20co%3D1%20%232%20%2B3%2050%25');
    expect(readUtm(url).utm_campaign).toBe('été & co=1 #2 +3 50%');
  });

  it('keeps values exactly as typed without changing case or whitespace', () => {
    const url = writeUtm('https://example.com/', { utm_source: ' NewsLetter ' });

    expect(readUtm(url).utm_source).toBe(' NewsLetter ');
  });

  it('treats differently cased keys as unrelated parameters', () => {
    expect(writeUtm('https://example.com/?UTM_SOURCE=x', { utm_source: 'y' })).toBe(
      'https://example.com/?UTM_SOURCE=x&utm_source=y',
    );
  });
});

describe('UTM templates', () => {
  const url = 'https://example.com/?ref=x&utm_source=old&utm_term=keep#top';
  const spring = template({ utm_source: 'newsletter', utm_medium: 'email', utm_campaign: 'spring' });

  it('lists the changes a template would make before it is applied', () => {
    expect(templateChanges(url, spring)).toEqual([
      { key: 'utm_source', from: 'old', to: 'newsletter' },
      { key: 'utm_medium', from: '', to: 'email' },
      { key: 'utm_campaign', from: '', to: 'spring' },
    ]);
  });

  it('reports no changes when the destination already matches the template', () => {
    expect(
      templateChanges('https://example.com/?utm_source=newsletter', template({ utm_source: 'newsletter' })),
    ).toEqual([]);
  });

  it('sets only the parameters the template defines and keeps the rest of the URL', () => {
    expect(applyTemplate(url, spring)).toBe(
      'https://example.com/?ref=x&utm_source=newsletter&utm_term=keep&utm_medium=email&utm_campaign=spring#top',
    );
  });

  it('produces one value per parameter when applied to a URL with duplicates', () => {
    const applied = applyTemplate('https://example.com/?utm_medium=a&utm_medium=b', spring);

    expect(applied.match(/utm_medium=/g)).toHaveLength(1);
    expect(readUtm(applied)).toEqual({
      ...emptyUtm(),
      utm_source: 'newsletter',
      utm_medium: 'email',
      utm_campaign: 'spring',
    });
  });

  it('still removes duplicates when the first values already match the template', () => {
    const repeated = 'https://example.com/?utm_source=newsletter&utm_source=old';

    expect(templateChanges(repeated, template({ utm_source: 'newsletter' }))).toEqual([]);
    expect(applyTemplate(repeated, template({ utm_source: 'newsletter' }))).toBe(
      'https://example.com/?utm_source=newsletter',
    );
  });

  it('leaves the URL unchanged when the template defines no values', () => {
    expect(applyTemplate(url, template())).toBe(url);
  });

  it('allows a field to be overridden after the template is applied', () => {
    const applied = applyTemplate(url, spring);

    expect(readUtm(writeUtm(applied, { utm_campaign: 'summer' }))).toEqual({
      utm_source: 'newsletter',
      utm_medium: 'email',
      utm_campaign: 'summer',
      utm_term: 'keep',
      utm_content: '',
    });
  });
});

describe('UTM template forms', () => {
  it('starts empty for a new template and copies an existing template', () => {
    expect(templateForm(null)).toEqual({ name: '', ...emptyUtm() });
    expect(templateForm({ id: 4, name: 'Ads', ...template({ utm_source: 'google', utm_medium: 'cpc' }) })).toEqual({
      name: 'Ads',
      ...emptyUtm(),
      utm_source: 'google',
      utm_medium: 'cpc',
    });
  });

  it('summarises only the values a template defines', () => {
    expect(templateSummary(template({ utm_source: 'google', utm_campaign: 'Spring' }))).toBe(
      'utm_source=google · utm_campaign=Spring',
    );
  });
});
