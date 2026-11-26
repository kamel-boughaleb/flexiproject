<script setup lang="ts">
import UserAvatar from '@/components/project/UserAvatar.vue';
import type { LiveNotification, SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { AtSign, Bell, FolderPlus, Newspaper, UserCheck, X } from 'lucide-vue-next';
import { onUnmounted, ref, watch } from 'vue';

/**
 * Bulles de notification en bas à droite de l'écran : une mention, une tâche affectée, un ajout à un projet
 * ou à un fil d'actualité s'affichent 10 secondes dès que le rafraîchissement automatique les ramène,
 * avec l'avatar du collègue à l'origine et un lien vers l'élément. Chaque notification n'est montrée
 * qu'une fois (mémorisé sur cet appareil).
 */
const DURATION = 10_000;
const SEEN_KEY = 'live-notifications-seen';

const page = usePage<SharedData>();
const toasts = ref<LiveNotification[]>([]);
const timers = new Map<string, ReturnType<typeof setTimeout>>();

function loadSeen(): string[] {
    try {
        const saved = JSON.parse(localStorage.getItem(SEEN_KEY) ?? '[]');
        return Array.isArray(saved) ? saved : [];
    } catch {
        return [];
    }
}
let seen = loadSeen();
function remember(id: string) {
    seen = [id, ...seen.filter((s) => s !== id)].slice(0, 100);
    try {
        localStorage.setItem(SEEN_KEY, JSON.stringify(seen));
    } catch {
        // Sans stockage, la bulle peut réapparaître au prochain chargement : sans gravité.
    }
}

function dismiss(id: string) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
    clearTimeout(timers.get(id));
    timers.delete(id);
}

watch(
    () => page.props.liveNotifications,
    (list) => {
        // Les plus anciennes d'abord, pour que la plus récente soit en haut de la pile.
        // La plus récente arrive en bas de la pile, au plus près du coin.
        for (const n of [...(list ?? [])].reverse()) {
            if (seen.includes(n.id) || toasts.value.some((t) => t.id === n.id)) continue;
            remember(n.id);
            toasts.value = [...toasts.value, n].slice(-4);
            timers.set(
                n.id,
                setTimeout(() => dismiss(n.id), DURATION),
            );
        }
    },
    { immediate: true },
);
onUnmounted(() => timers.forEach(clearTimeout));

/** Un clic sur la bulle la ferme et ouvre l'élément concerné (tâche, commentaire, projet, fil). */
function open(n: LiveNotification) {
    dismiss(n.id);
    router.visit(n.url);
}

const ICONS = { mention: AtSign, assigned: UserCheck, project_member: FolderPlus, feed_member: Newspaper } as const;
const icon = (n: LiveNotification) => ICONS[n.kind] ?? Bell;
</script>

<template>
    <div
        class="pointer-events-none fixed bottom-20 right-4 z-[45] flex w-[calc(100%-2rem)] max-w-sm flex-col items-end gap-2 md:bottom-6 md:right-6"
        aria-live="polite"
    >
        <TransitionGroup
            enter-active-class="transition duration-500 ease-out"
            enter-from-class="translate-y-8 opacity-0"
            leave-active-class="transition duration-200 ease-in"
            leave-to-class="translate-x-4 opacity-0"
        >
            <div
                v-for="n in toasts"
                :key="n.id"
                class="pointer-events-auto relative flex w-full cursor-pointer items-start gap-3 overflow-hidden rounded-2xl border border-app-border bg-background p-3 pr-9 shadow-hover transition hover:border-brand"
                role="link"
                tabindex="0"
                @click="open(n)"
                @keydown.enter="open(n)"
            >
                <span class="relative shrink-0">
                    <UserAvatar v-if="n.actor" :user="n.actor" size="lg" />
                    <span v-else class="flex size-11 items-center justify-center rounded-full bg-brand-tint text-brand-active"
                        ><component :is="icon(n)" class="size-5"
                    /></span>
                    <span
                        v-if="n.actor"
                        class="absolute -bottom-1 -right-1 flex size-5 items-center justify-center rounded-full bg-brand text-white ring-2 ring-background"
                        ><component :is="icon(n)" class="size-3"
                    /></span>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold leading-snug text-ink">{{ n.message }}</p>
                    <span class="mt-1 inline-block text-sm font-bold text-brand-active">{{ $t('Voir') }}</span>
                </div>
                <button
                    type="button"
                    class="absolute right-2 top-2 rounded-md p-1 text-ink-soft hover:bg-app-hover hover:text-ink"
                    :aria-label="$t('Fermer')"
                    @click.stop="dismiss(n.id)"
                    @keydown.enter.stop
                >
                    <X class="size-4" />
                </button>
                <!-- Temps restant avant la fermeture automatique. -->
                <span class="toast-timer absolute bottom-0 left-0 h-0.5 bg-brand" :style="{ animationDuration: `${DURATION}ms` }" />
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
@keyframes toast-timer {
    from {
        width: 100%;
    }
    to {
        width: 0;
    }
}
.toast-timer {
    animation: toast-timer linear forwards;
}
</style>
