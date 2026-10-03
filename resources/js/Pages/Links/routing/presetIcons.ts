import { CalendarClock, Globe2, Megaphone, MonitorSmartphone, Plus, Shuffle } from '@lucide/vue';

const PRESET_ICONS: Record<string, unknown> = {
  country: Globe2,
  device: MonitorSmartphone,
  campaign: Megaphone,
  time: CalendarClock,
  split: Shuffle,
  custom: Plus,
};

export function presetIcon(kind: string): unknown {
  return PRESET_ICONS[kind] ?? Plus;
}
