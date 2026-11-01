import type { Section } from '@/types';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const opts = { preserveScroll: true, preserveState: true };

/**
 * Renommage (clic sur le titre) et réordonnancement (glisser-déposer du titre) des sections d'un projet,
 * communs aux vues Tableau et Liste.
 */
export function useSectionEditing(projectId: () => number, sections: () => Section[]) {
    // Copie locale pour que le nouvel ordre s'affiche aussitôt ; resynchronisée à chaque réponse du serveur.
    const ordered = ref<Section[]>([]);
    watch(sections, (list) => (ordered.value = [...list]), { immediate: true });

    function reorder(list: Section[]) {
        if (list.every((s, i) => s.id === ordered.value[i]?.id)) return;
        ordered.value = list;
        router.post(route('sections.reorder', projectId()), { ids: list.map((s) => s.id) }, opts);
    }

    const renaming = ref<number | null>(null);
    function rename(section: Section, value: string) {
        // Entrée puis perte du focus : on n'enregistre qu'une fois.
        if (renaming.value !== section.id) return;
        renaming.value = null;
        const name = value.trim();
        if (name && name !== section.name) router.patch(route('sections.update', section.id), { name }, opts);
    }

    return { ordered, reorder, renaming, rename };
}

/** Place le curseur dans le champ dès son apparition, texte sélectionné. */
export const vFocusSelect = {
    mounted: (el: HTMLInputElement) => {
        el.focus();
        el.select();
    },
};
