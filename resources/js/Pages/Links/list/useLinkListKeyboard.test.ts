import { afterEach, describe, expect, it, vi } from 'vitest';

import { useLinkListKeyboard } from './useLinkListKeyboard';

vi.mock('vue', async (importOriginal) => ({
  ...(await importOriginal<typeof import('vue')>()),
  onMounted: (run: () => void) => run(),
  onUnmounted: () => {},
}));

afterEach(() => vi.unstubAllGlobals());

function setup(overlay: boolean, prevented = false, listbox = false) {
  let keydown: (event: KeyboardEvent) => void = () => {};
  vi.stubGlobal('document', {
    querySelector: () => (overlay ? {} : null),
    addEventListener: (_: string, handler: typeof keydown) => {
      keydown = handler;
    },
    removeEventListener: () => {},
  });
  const move = vi.fn();
  useLinkListKeyboard({ move, focusSearch: vi.fn() });
  const event = {
    key: 'ArrowDown',
    defaultPrevented: prevented,
    target: { closest: (selector: string) => (listbox && selector.includes('[role="listbox"]') ? {} : null) },
    metaKey: false,
    ctrlKey: false,
    altKey: false,
    preventDefault: vi.fn(),
  } as unknown as KeyboardEvent;
  keydown(event);
  return { move, event };
}

describe('link list keyboard isolation', () => {
  it('does not switch selected links when a routing dialog has focus in a body portal', () => {
    expect(setup(true).move).not.toHaveBeenCalled();
  });

  it('does not consume a Select arrow key or an already handled event', () => {
    expect(setup(false, false, true).move).not.toHaveBeenCalled();
    expect(setup(false, true).move).not.toHaveBeenCalled();
  });

  it('keeps list navigation available when overlays are closed', () => {
    expect(setup(false).move).toHaveBeenCalledWith(1);
  });
});
