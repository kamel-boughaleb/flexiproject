import { enUS, fr } from 'date-fns/locale';
import { ref } from 'vue';
import en from '../../../lang/en.json';

/**
 * Traductions de l'interface. Les textes sont écrits en français dans le code et servent de clés :
 * lang/en.json (partagé avec Laravel) donne leur version anglaise.
 */
const MESSAGES: Record<string, Record<string, string>> = { en };

export type Locale = 'fr' | 'en';

export const locale = ref<Locale>((document.documentElement.lang?.slice(0, 2) as Locale) || 'fr');

let started = false;

/**
 * Langue reçue avec chaque page. Si elle change en cours de route (réglages, écran de connexion),
 * la page est rechargée pour que tous les textes, même ceux calculés une seule fois, passent dans la nouvelle langue.
 */
export function setLocale(value: string | undefined) {
    if (!value) return;
    if (started && value !== locale.value) {
        window.location.reload();
        return;
    }
    started = true;
    locale.value = value as Locale;
    document.documentElement.lang = value;
}

type Params = Record<string, string | number | null | undefined>;

function replace(text: string, params: Params) {
    // Les noms les plus longs d'abord, pour que « :countDone » ne soit pas remplacé par « :count ».
    return Object.keys(params)
        .sort((a, b) => b.length - a.length)
        .reduce((s, key) => s.split(`:${key}`).join(String(params[key] ?? '')), text);
}

/** Texte traduit dans la langue de l'interface, avec ses valeurs (« :name »). */
export function t(key: string, params: Params = {}): string {
    return replace(MESSAGES[locale.value]?.[key] ?? key, params);
}

/**
 * Singulier ou pluriel selon `count` : clé « une tâche|:count tâches ».
 * En français 0 et 1 sont au singulier, en anglais seulement 1.
 */
export function tc(key: string, count: number, params: Params = {}): string {
    const [one, many = one] = t(key, { count, ...params }).split('|');
    const singular = locale.value === 'fr' ? Math.abs(count) < 2 : Math.abs(count) === 1;
    return singular ? one : many;
}

/** Locale des dates (date-fns) et des nombres (Intl). */
export const dateLocale = () => (locale.value === 'en' ? enUS : fr);
export const intlLocale = () => (locale.value === 'en' ? 'en-US' : 'fr-FR');
