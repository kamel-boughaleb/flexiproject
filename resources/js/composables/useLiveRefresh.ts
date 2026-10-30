import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

/**
 * Rafraîchissement automatique des pages : plusieurs personnes travaillent en même temps, on
 * redemande donc régulièrement les données de la page au serveur (rechargement partiel Inertia,
 * sans recharger la page ni perdre le défilement ou l'état des composants).
 *
 * Un rafraîchissement est reporté tant que l'utilisateur est en train d'agir : saisie dans un champ
 * ou dans l'éditeur, glisser-déposer en cours, enregistrement en attente de réponse. Il est fait
 * dès que l'action se termine. Rien n'est demandé quand l'onglet est en arrière-plan.
 */
const seconds = Number(import.meta.env.VITE_REFRESH_INTERVAL ?? 10);
const INTERVAL = Number.isFinite(seconds) ? seconds * 1000 : 10_000;

let users = 0; // layouts montés qui demandent le rafraîchissement
let timer: ReturnType<typeof setInterval> | undefined;
let pending = 0; // visites lancées par l'utilisateur (enregistrement, navigation…) en cours
let pointerDown = false;
let missed = false; // un rafraîchissement a été reporté et reste à faire
let cancelPoll: (() => void) | null = null;
let listening = false;

const TEXT_TYPES = ['checkbox', 'radio', 'button', 'submit', 'reset', 'file', 'color', 'range'];

/** Le curseur est-il dans un champ où l'utilisateur tape ? */
function isTyping(): boolean {
    const el = document.activeElement as HTMLElement | null;
    if (!el) return false;
    if (el.isContentEditable) return true;
    if (el instanceof HTMLTextAreaElement) return true;
    return el instanceof HTMLInputElement && !TEXT_TYPES.includes(el.type);
}

function isBusy(): boolean {
    return pending > 0 || pointerDown || isTyping() || !!document.querySelector('.sortable-chosen, .sortable-drag');
}

function refresh() {
    if (!users || document.hidden || cancelPoll) return;
    if (isBusy()) {
        missed = true;
        return;
    }
    missed = false;
    router.reload({
        // Laravel renvoie toutes les props de la page : tâches, fiche ouverte, membres, notifications…
        onCancelToken: (token) => (cancelPoll = () => token.cancel()),
        onFinish: () => (cancelPoll = null),
    });
}

/** Rattrape un rafraîchissement reporté, juste après la fin de l'action en cours. */
function catchUp() {
    if (missed) setTimeout(refresh, 300);
}

function listen() {
    if (listening) return;
    listening = true;

    router.on('start', (event) => {
        if (event.detail.visit.async) return;
        pending++;
        // Une réponse partie avant l'enregistrement de l'utilisateur ramènerait des données périmées.
        cancelPoll?.();
    });
    router.on('finish', (event) => {
        if (event.detail.visit.async) return;
        pending = Math.max(0, pending - 1);
        catchUp();
    });

    document.addEventListener('pointerdown', () => (pointerDown = true), { capture: true });
    const release = () => {
        pointerDown = false;
        catchUp();
    };
    document.addEventListener('pointerup', release, { capture: true });
    document.addEventListener('pointercancel', release, { capture: true });
    document.addEventListener('focusout', catchUp);
    // Retour sur l'onglet : on remet l'affichage à jour tout de suite.
    document.addEventListener('visibilitychange', () => !document.hidden && refresh());
}

export function useLiveRefresh() {
    if (INTERVAL <= 0) return;

    onMounted(() => {
        listen();
        if (users++ === 0) timer = setInterval(refresh, INTERVAL);
    });
    onUnmounted(() => {
        if (--users === 0) {
            clearInterval(timer);
            cancelPoll?.();
        }
    });
}
