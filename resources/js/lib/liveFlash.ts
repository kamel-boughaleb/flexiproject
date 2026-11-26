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

/** Bout du texte visible d'un élément (fin du titre), en coordonnées de la fenêtre. */
function textEnd(el: HTMLElement): { x: number; y: number } {
    const box = el.getBoundingClientRect();
    let right = box.right;
    let top = box.top;
    if (el instanceof HTMLTextAreaElement || el instanceof HTMLInputElement) {
        // Champ de saisie : on mesure la largeur du texte avec la police du champ.
        const style = getComputedStyle(el);
        const ctx = document.createElement('canvas').getContext('2d');
        if (ctx) {
            ctx.font = style.font;
            const pad = parseFloat(style.paddingLeft) || 0;
            right = Math.min(box.right, box.left + pad + ctx.measureText(el.value.split('\n')[0]).width);
        }
    } else {
        const range = document.createRange();
        range.selectNodeContents(el);
        const rects = range.getClientRects();
        const first = rects[0];
        if (first) {
            right = Math.min(box.right, first.right);
            top = first.top;
        }
    }
    return { x: Math.max(box.left, right), y: Math.max(box.top, top) };
}

/** Petite bulle « +1 » qui s'élève au bout du titre puis s'efface. */
function bubble(el: HTMLElement, diff: number) {
    if (reduceMotion() || !el.isConnected || !el.getClientRects().length) return;
    const { x, y } = textEnd(el);
    const b = document.createElement('span');
    b.className = 'live-plus';
    b.textContent = `+${diff}`;
    b.setAttribute('aria-hidden', 'true');
    b.style.left = `${x + 4}px`;
    b.style.top = `${y - 6}px`;
    document.body.appendChild(b);
    setTimeout(() => b.remove(), 1700);
}

type CountEl = HTMLElement & { __liveCount?: number };

/**
 * `v-live-count="nombre de commentaires"`, posé sur le titre d'une tâche (ou d'un projet) :
 * quand un collaborateur ajoute un commentaire, une bulle « +1 » s'élève au bout du titre.
 */
export const vLiveCount: Directive<CountEl, number | null | undefined> = {
    mounted(el, binding) {
        el.__liveCount = Number(binding.value ?? 0);
    },
    updated(el, binding) {
        const count = Number(binding.value ?? 0);
        const previous = el.__liveCount ?? 0;
        el.__liveCount = count;
        if (count > previous && isLive()) bubble(el, count - previous);
    },
};
