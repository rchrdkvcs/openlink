// Date helpers for the custom date-time picker (no native datetime-local inputs).
// Values travel as `YYYY-MM-DDTHH:mm` local strings, the format the backend accepts.

export type CalendarDay = { date: Date; inMonth: boolean; key: string; isToday: boolean };

const pad = (n: number) => String(n).padStart(2, '0');

export function dateKey(d: Date): string {
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export function toInputValue(d: Date): string {
    return `${dateKey(d)}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function fromInputValue(value: string): Date | null {
    if (!value) return null;

    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/);
    if (!match) return null;

    const [, year, month, day, hours, minutes] = match;
    const d = new Date(Number(year), Number(month) - 1, Number(day), Number(hours), Number(minutes));

    return Number.isNaN(d.getTime()) ? null : d;
}

export function monthLabel(view: Date): string {
    return view.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
}

export const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

/** 6-week month grid starting Monday. */
export function monthGrid(view: Date): CalendarDay[] {
    const first = new Date(view.getFullYear(), view.getMonth(), 1);
    const start = new Date(first);
    start.setDate(first.getDate() - ((first.getDay() + 6) % 7));
    const todayKey = dateKey(new Date());

    return Array.from({ length: 42 }, (_, i) => {
        const date = new Date(start);
        date.setDate(start.getDate() + i);
        const key = dateKey(date);
        return { date, inMonth: date.getMonth() === view.getMonth(), key, isToday: key === todayKey };
    });
}

export function humanize(value: string): string {
    const d = fromInputValue(value);
    if (!d) return '';
    return `${d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} · ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function addDays(base: Date, days: number): Date {
    const d = new Date(base);
    d.setDate(d.getDate() + days);
    return d;
}
