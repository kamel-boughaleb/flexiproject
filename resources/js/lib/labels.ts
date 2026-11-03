import { differenceInCalendarDays, format, isBefore, parseISO, startOfDay } from 'date-fns';
import { dateLocale, t } from './i18n';

/**
 * Priorités : `class` colore le badge, `border` l'option active du sélecteur, `bar` les barres du planning
 * et `dot` les légendes (design system Flexilivre).
 */
export const PRIORITIES = {
    none: {
        get label() {
            return t('Aucune');
        },
        class: 'bg-app-hover text-ink-soft',
        border: 'border-app-muted',
        bar: 'bg-app-muted',
        dot: 'bg-app-muted',
    },
    low: {
        get label() {
            return t('Basse');
        },
        class: 'bg-info-tint text-info-deep',
        border: 'border-info',
        bar: 'bg-info',
        dot: 'bg-info',
    },
    medium: {
        get label() {
            return t('Moyenne');
        },
        class: 'bg-warning-tint text-warning-text',
        border: 'border-warning',
        bar: 'bg-warning',
        dot: 'bg-warning',
    },
    high: {
        get label() {
            return t('Haute');
        },
        class: 'bg-brand-tint text-brand-active',
        border: 'border-brand',
        bar: 'bg-brand',
        dot: 'bg-brand',
    },
} as const;

export const ROLES = {
    get admin() {
        return t('Administrateur');
    },
    get editor() {
        return t('Éditeur');
    },
    get viewer() {
        return t('Lecteur');
    },
} as const;

export const TRIGGERS: Record<string, string> = {
    get task_created() {
        return t('Une tâche est créée');
    },
    get task_moved() {
        return t('Une tâche est déplacée dans une section');
    },
    get task_completed() {
        return t('Une tâche est marquée comme terminée');
    },
    get assignee_changed() {
        return t("Le responsable d'une tâche change");
    },
    get due_date_changed() {
        return t("La date d'échéance d'une tâche change");
    },
};

export const ACTIONS: Record<string, string> = {
    get move_to_section() {
        return t('Déplacer dans la section');
    },
    get set_completed() {
        return t('Marquer comme terminée');
    },
    get set_incomplete() {
        return t('Marquer comme non terminée');
    },
    get assign_to() {
        return t('Assigner à');
    },
    get set_priority() {
        return t('Définir la priorité');
    },
    get set_due_in_days() {
        return t("Fixer l'échéance à J + n jours");
    },
    get notify_assignee() {
        return t('Notifier le responsable');
    },
    get notify_user() {
        return t('Notifier une personne');
    },
    get add_comment() {
        return t('Ajouter un commentaire');
    },
};

/** Les dates arrivent du serveur au format AAAA-MM-JJ. */
export const toDate = (d: string | null | undefined) => (d ? parseISO(d.slice(0, 10)) : null);

export const shortDate = (d: string | null | undefined) => {
    const date = toDate(d);
    return date ? format(date, t('d MMM'), { locale: dateLocale() }) : '';
};

export const dateTime = (d: string) => format(parseISO(d), t("d MMM 'à' HH:mm"), { locale: dateLocale() });

export const dateRange = (start: string | null, due: string | null) => {
    if (start && due) return `${shortDate(start)} → ${shortDate(due)}`;
    if (due) return shortDate(due);
    if (start) return t('dès le :date', { date: shortDate(start) });
    return '';
};

/** Échéance lisible : « Aujourd'hui », « Hier », « Demain » ou « 4 nov. ». */
export const dueLabel = (d: string | null | undefined) => {
    const date = toDate(d);
    if (!date) return '';
    const diff = differenceInCalendarDays(date, new Date());
    if (diff === 0) return t("Aujourd'hui");
    if (diff === -1) return t('Hier');
    if (diff === 1) return t('Demain');
    return shortDate(d);
};

/** Couleur d'une section, choisie dans la palette de la marque selon sa place dans le projet. */
export const SECTION_COLORS = ['#8A8B8E', '#289DC9', '#FF9100', '#FF489C', '#44AF01', '#215D7D'];
export const sectionColor = (index: number) => SECTION_COLORS[Math.max(0, index) % SECTION_COLORS.length];

export const isOverdue = (due: string | null, completed: boolean) => {
    const date = toDate(due);
    return !!date && !completed && isBefore(date, startOfDay(new Date()));
};
