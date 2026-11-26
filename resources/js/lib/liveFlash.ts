import type { Directive } from 'vue';

/**
 * Mise en valeur des changements apportés par d'autres collaborateurs.
 *
 * Le rafraîchissement automatique (useLiveRefresh) signale qu'une mise à jour arrive ; pendant ce court
 * instant, un élément portant `v-live="valeur"` :
 * - clignote doucement si sa valeur a changé (tâche renommée, déplacée, échéance modifiée…) ;
 * - glisse en douceur à l'écran s'il vient d'apparaître (nouvelle tâche, nouveau projet, nouvelle actualité).
 * Les changements faits par l'utilisateur lui-même ne déclenchent rien.
 */
let liveUntil = 0;
let liveInFlight = false;

/** Appelé par useLiveRefresh autour de chaque rafraîchissement automatique. */
export function liveRefreshStarted() {
    liveInFlight = true;
}
export function liveRefreshFinished() {
    // Le rendu suit la réponse de quelques millisecondes : on laisse une petite marge.
    liveUntil = Date.now() + 800;
    liveInFlight = false;
}
const isLive = () => liveInFlight || Date.now() < liveUntil;

const reduceMotion = () => typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

type LiveEl = HTMLElement & { __liveKey?: string; __liveTimer?: ReturnType<typeof setTimeout> };

function play(el: LiveEl, cls: 'live-changed' | 'live-new') {
    if (reduceMotion()) return;
    el.classList.remove('live-changed', 'live-new');
    // Force le navigateur à rejouer l'animation si elle était déjà en cours.
    void el.offsetWidth;
    el.classList.add(cls);
    clearTimeout(el.__liveTimer);
    el.__liveTimer = setTimeout(() => el.classList.remove(cls), 2200);
}

const keyOf = (value: unknown) => {
    try {
        return JSON.stringify(value) ?? '';
    } catch {
        return String(value);
    }
};

export const vLive: Directive<LiveEl, unknown> = {
    mounted(el, binding) {
        el.__liveKey = keyOf(binding.value);
        if (isLive()) play(el, 'live-new');
    },
    updated(el, binding) {
        const key = keyOf(binding.value);
        if (key === el.__liveKey) return;
        el.__liveKey = key;
        if (isLive()) play(el, 'live-changed');
    },
    beforeUnmount(el) {
        clearTimeout(el.__liveTimer);
    },
};
