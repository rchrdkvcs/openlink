import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import type { LinksPageProps, ShortLink } from './types';

export function useActivationCountdown(props: LinksPageProps) {
    const now = ref(Date.now());
    let timer: ReturnType<typeof setInterval> | null = null;
    let reloading = false;
    // Links already refreshed once their activation passed, so a server clock
    // slightly behind the client cannot trigger a reload loop.
    const reloadedIds = new Set<number>();

    function activationTime(link: ShortLink): number | null {
        if (link.status !== 'scheduled' || !link.activates_at) {
            return null;
        }

        const time = new Date(link.activates_at).getTime();

        return Number.isNaN(time) ? null : time;
    }

    function syncTimer() {
        const active = props.links.some((link) => activationTime(link) !== null);

        if (active && timer === null) {
            timer = setInterval(() => {
                now.value = Date.now();
            }, 1000);
        } else if (!active && timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    }

    watch(() => props.links, syncTimer, { immediate: true });

    // Refresh once a scheduled link reaches its activation date so its status flips.
    watch(now, (current) => {
        if (reloading) {
            return;
        }

        const due = props.links.filter((link) => {
            const time = activationTime(link);

            return time !== null && time <= current && !reloadedIds.has(link.id);
        });

        if (due.length === 0) {
            return;
        }

        due.forEach((link) => reloadedIds.add(link.id));
        reloading = true;
        router.reload({
            only: ['links'],
            onFinish: () => {
                reloading = false;
            },
        });
    });

    onBeforeUnmount(() => {
        if (timer !== null) {
            clearInterval(timer);
        }
    });

    function countdownFor(link: ShortLink): string | null {
        const time = activationTime(link);

        if (time === null) {
            return null;
        }

        const seconds = Math.max(0, Math.ceil((time - now.value) / 1000));
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (days > 0) return `${days}d ${hours}h`;
        if (hours > 0) return `${hours}h ${minutes}m`;
        if (minutes > 0) return `${minutes}m ${secs}s`;

        return `${secs}s`;
    }

    function activationTitle(link: ShortLink): string | undefined {
        return link.activates_at ? `Activates ${new Date(link.activates_at).toLocaleString()}` : undefined;
    }

    return { countdownFor, activationTitle };
}
