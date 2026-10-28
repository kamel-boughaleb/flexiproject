import { format, isBefore, parseISO, startOfDay } from 'date-fns';
import { fr } from 'date-fns/locale';

export const PRIORITIES = {
    none: { label: 'Aucune', class: 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800' },
    low: { label: 'Basse', class: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300' },
    medium: { label: 'Moyenne', class: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' },
    high: { label: 'Haute', class: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' },
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

export const isOverdue = (due: string | null, completed: boolean) => {
    const date = toDate(due);
    return !!date && !completed && isBefore(date, startOfDay(new Date()));
};
