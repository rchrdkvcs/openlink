export type StateScene = 'locked' | 'scheduled' | 'ready' | 'unlinked' | 'offline' | 'expired' | 'payload';

export type StateTone = 'neutral' | 'accent' | 'success' | 'warning' | 'danger';

export const stateToneColor: Record<StateTone, string> = {
  neutral: 'var(--muted)',
  accent: 'var(--accent)',
  success: 'var(--success)',
  warning: 'var(--warning)',
  danger: 'var(--danger)',
};

export type ErrorState = {
  status: number;
  scene: StateScene;
  tone: StateTone;
  title: string;
  description: string;
  canRetry: boolean;
};

const errorStates: Record<number, Omit<ErrorState, 'status'>> = {
  403: {
    scene: 'locked',
    tone: 'warning',
    title: 'Access denied',
    description: 'You don’t have permission to open this page. Ask a workspace admin if you think this is a mistake.',
    canRetry: false,
  },
  404: {
    scene: 'unlinked',
    tone: 'neutral',
    title: 'Page not found',
    description: 'This page doesn’t exist or has moved. Check the address and try again.',
    canRetry: false,
  },
  419: {
    scene: 'expired',
    tone: 'accent',
    title: 'Page expired',
    description: 'Your session timed out for security. Refresh the page and try again.',
    canRetry: true,
  },
  429: {
    scene: 'expired',
    tone: 'warning',
    title: 'Too many requests',
    description: 'You’re going a little fast. Wait a moment, then try again.',
    canRetry: true,
  },
  500: {
    scene: 'offline',
    tone: 'danger',
    title: 'Something went wrong',
    description: 'An unexpected error stopped this page from loading. Try again in a moment.',
    canRetry: true,
  },
  503: {
    scene: 'offline',
    tone: 'warning',
    title: 'Back shortly',
    description: 'Openlink is down for maintenance. Check back in a few minutes.',
    canRetry: true,
  },
};

export function errorState(status: number): ErrorState {
  return { status, ...(errorStates[status] ?? errorStates[500]) };
}
