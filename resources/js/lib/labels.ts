import { differenceInCalendarDays, format, isBefore, parseISO, startOfDay } from 'date-fns';
import { fr } from 'date-fns/locale';

/**
 * Priorités : `class` colore le badge, `border` l'option active du sélecteur, `bar` les barres du planning
 * et `dot` les légendes (design system Flexilivre).
 */
export const PRIORITIES = {
    none: { label: 'Aucune', class: 'bg-app-hover text-ink-soft', border: 'border-app-muted', bar: 'bg-app-muted', dot: 'bg-app-muted' },
    low: { label: 'Basse', class: 'bg-info-tint text-info-deep', border: 'border-info', bar: 'bg-info', dot: 'bg-info' },
    medium: { label: 'Moyenne', class: 'bg-warning-tint text-warning-text', border: 'border-warning', bar: 'bg-warning', dot: 'bg-warning' },
    high: { label: 'Haute', class: 'bg-brand-tint text-brand-active', border: 'border-brand', bar: 'bg-brand', dot: 'bg-brand' },
} as const;

export const ROLES = {
    admin: 'Administrateur',
    editor: 'Éditeur',
    viewer: 'Lecteur',
} as const;

export const TRIGGERS: Record<string, string> = {
    task_created: 'Une tâche est créée',
    task_moved: 'Une tâche est déplacée dans une section',
    task_completed: 'Une tâche est marquée comme terminée',
    assignee_changed: "Le responsable d'une tâche change",
    due_date_changed: "La date d'échéance d'une tâche change",
};

export const ACTIONS: Record<string, string> = {
    move_to_section: 'Déplacer dans la section',
    set_completed: 'Marquer comme terminée',
    set_incomplete: 'Marquer comme non terminée',
    assign_to: 'Assigner à',
    set_priority: 'Définir la priorité',
    set_due_in_days: "Fixer l'échéance à J + n jours",
    notify_assignee: 'Notifier le responsable',
    notify_user: 'Notifier une personne',
    add_comment: 'Ajouter un commentaire',
};

/** Les dates arrivent du serveur au format AAAA-MM-JJ. */
export const toDate = (d: string | null | undefined) => (d ? parseISO(d.slice(0, 10)) : null);

export const shortDate = (d: string | null | undefined) => {
    const date = toDate(d);
    return date ? format(date, 'd MMM', { locale: fr }) : '';
};

export const dateTime = (d: string) => format(parseISO(d), 'd MMM à HH:mm', { locale: fr });

export const dateRange = (start: string | null, due: string | null) => {
    if (start && due) return `${shortDate(start)} → ${shortDate(due)}`;
    if (due) return shortDate(due);
    if (start) return `dès le ${shortDate(start)}`;
    return '';
};

/** Échéance lisible : « Aujourd'hui », « Hier », « Demain » ou « 4 nov. ». */
export const dueLabel = (d: string | null | undefined) => {
    const date = toDate(d);
    if (!date) return '';
    const diff = differenceInCalendarDays(date, new Date());
    if (diff === 0) return "Aujourd'hui";
    if (diff === -1) return 'Hier';
    if (diff === 1) return 'Demain';
    return shortDate(d);
};

/** Couleur d'une section, choisie dans la palette de la marque selon sa place dans le projet. */
export const SECTION_COLORS = ['#8A8B8E', '#289DC9', '#FF9100', '#FF489C', '#44AF01', '#215D7D'];
export const sectionColor = (index: number) => SECTION_COLORS[Math.max(0, index) % SECTION_COLORS.length];

export const isOverdue = (due: string | null, completed: boolean) => {
    const date = toDate(due);
    return !!date && !completed && isBefore(date, startOfDay(new Date()));
};
