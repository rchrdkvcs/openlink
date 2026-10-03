import { reactive } from 'vue';

export type ToastTone = 'default' | 'success' | 'danger';

export type ToastAction = { label: string; run: () => void };

export type Toast = {
  id: number;
  title: string;
  description?: string;
  tone: ToastTone;
  action?: ToastAction;
};

type ToastInput = Omit<Toast, 'id' | 'tone'> & { tone?: ToastTone; duration?: number };

export const toasts = reactive<Toast[]>([]);

let nextId = 1;
const timers = new Map<number, ReturnType<typeof setTimeout>>();

export function dismissToast(id: number) {
  clearTimeout(timers.get(id));
  timers.delete(id);
  const index = toasts.findIndex((item) => item.id === id);
  if (index !== -1) toasts.splice(index, 1);
}

export function toast(input: ToastInput | string) {
  const { duration = 4000, tone = 'default', ...rest } = typeof input === 'string' ? { title: input } : input;
  const id = nextId++;

  toasts.push({ id, tone, ...rest });
  if (toasts.length > 3) dismissToast(toasts[0].id);
  timers.set(
    id,
    setTimeout(() => dismissToast(id), duration),
  );

  return id;
}

export function pauseToast(id: number) {
  clearTimeout(timers.get(id));
}

export function resumeToast(id: number) {
  timers.set(
    id,
    setTimeout(() => dismissToast(id), 2500),
  );
}

function legacyCopy(value: string) {
  const field = document.createElement('textarea');
  field.value = value;
  field.setAttribute('readonly', '');
  field.style.position = 'fixed';
  field.style.opacity = '0';
  document.body.appendChild(field);
  field.select();
  const copied = document.execCommand('copy');
  field.remove();
  return copied;
}

export async function writeClipboard(value: string) {
  try {
    await navigator.clipboard.writeText(value);
    return true;
  } catch {
    try {
      return legacyCopy(value);
    } catch {
      return false;
    }
  }
}

export async function copyToClipboard(value: string, title = 'Copied to clipboard', fallbackTitle?: string) {
  if (await writeClipboard(value)) {
    toast({ title, description: value, tone: 'success', duration: 2500 });
    return true;
  }

  toast({
    title: fallbackTitle ?? 'Copy blocked by the browser',
    description: value,
    tone: fallbackTitle ? 'success' : 'danger',
    duration: 8000,
    action: { label: 'Copy', run: () => void copyToClipboard(value) },
  });
  return false;
}
