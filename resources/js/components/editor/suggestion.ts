import type { MiniUser } from '@/types';
import { Extension, type Editor, type Range } from '@tiptap/core';
import Mention from '@tiptap/extension-mention';
import { PluginKey } from '@tiptap/pm/state';
import Suggestion, { type SuggestionKeyDownProps, type SuggestionOptions, type SuggestionProps } from '@tiptap/suggestion';
import { VueRenderer } from '@tiptap/vue-3';
import {
    AtSign,
    Code2,
    Heading1,
    Heading2,
    Heading3,
    Image,
    List,
    ListChecks,
    ListOrdered,
    Minus,
    Quote,
    Type,
    type LucideIcon,
} from 'lucide-vue-next';
import SuggestionList from './SuggestionList.vue';

export interface SlashItem {
    title: string;
    hint?: string;
    icon: LucideIcon;
    keywords?: string;
    run: (editor: Editor) => void;
}

/**
 * Affiche la liste de suggestions (mentions, menu « / ») sous le curseur.
 * La liste est placée dans l'éditeur lui-même et non dans <body> : le panneau de tâche
 * est une fenêtre modale qui ignore les clics faits en dehors d'elle.
 */
function renderList<T>(host: () => HTMLElement | null | undefined) {
    let component: VueRenderer | null = null;

    const place = (props: SuggestionProps<T>) => {
        const el = component?.element as HTMLElement | undefined;
        const container = host();
        const rect = props.clientRect?.();
        if (!el || !container || !rect) return;
        const box = container.getBoundingClientRect();
        const below = window.innerHeight - rect.bottom > 280;
        el.style.left = `${Math.max(0, rect.left - box.left)}px`;
        el.style.top = below ? `${rect.bottom - box.top + 6}px` : '';
        el.style.bottom = below ? '' : `${box.bottom - rect.top + 6}px`;
    };

    return {
        onStart(props: SuggestionProps<T>) {
            component = new VueRenderer(SuggestionList, { props, editor: props.editor });
            const el = component.element as HTMLElement;
            el.style.position = 'absolute';
            el.style.zIndex = '60';
            host()?.appendChild(el);
            place(props);
        },
        onUpdate(props: SuggestionProps<T>) {
            component?.updateProps(props);
            place(props);
        },
        onKeyDown(props: SuggestionKeyDownProps) {
            if (props.event.key === 'Escape') {
                component?.element?.remove();
                return true;
            }
            return (component?.ref as { onKeyDown?: (e: KeyboardEvent) => boolean } | undefined)?.onKeyDown?.(props.event) ?? false;
        },
        onExit() {
            component?.element?.remove();
            component?.destroy();
            component = null;
        },
    };
}

const normalize = (s: string) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

/** Mentions « @ » des membres du projet. */
export function mentionExtension(members: () => MiniUser[], host: () => HTMLElement | null | undefined) {
    return Mention.configure({
        HTMLAttributes: { class: 'mention' },
        renderText: ({ node }) => `@${node.attrs.label ?? node.attrs.id}`,
        suggestion: {
            items: ({ query }) =>
                members()
                    .filter((m) => normalize(m.name).includes(normalize(query)))
                    .slice(0, 8),
            render: () => renderList<MiniUser>(host),
        } as Partial<SuggestionOptions<MiniUser>>,
    });
}

export function slashItems(actions: { pickImage: () => void }): SlashItem[] {
    return [
        { title: 'Texte', icon: Type, keywords: 'paragraphe normal', run: (e) => e.chain().focus().setParagraph().run() },
        { title: 'Titre 1', icon: Heading1, keywords: 'h1 grand', run: (e) => e.chain().focus().setHeading({ level: 1 }).run() },
        { title: 'Titre 2', icon: Heading2, keywords: 'h2', run: (e) => e.chain().focus().setHeading({ level: 2 }).run() },
        { title: 'Titre 3', icon: Heading3, keywords: 'h3 petit', run: (e) => e.chain().focus().setHeading({ level: 3 }).run() },
        { title: 'Liste à puces', icon: List, keywords: 'puce ul', run: (e) => e.chain().focus().toggleBulletList().run() },
        { title: 'Liste numérotée', icon: ListOrdered, keywords: 'numero ol', run: (e) => e.chain().focus().toggleOrderedList().run() },
        { title: 'Liste de tâches', icon: ListChecks, keywords: 'checklist case cocher todo', run: (e) => e.chain().focus().toggleTaskList().run() },
        { title: 'Citation', icon: Quote, keywords: 'quote', run: (e) => e.chain().focus().toggleBlockquote().run() },
        { title: 'Bloc de code', icon: Code2, keywords: 'code pre', run: (e) => e.chain().focus().toggleCodeBlock().run() },
        { title: 'Séparateur', icon: Minus, keywords: 'ligne hr', run: (e) => e.chain().focus().setHorizontalRule().run() },
        { title: 'Image', icon: Image, keywords: 'photo upload', run: () => actions.pickImage() },
        { title: 'Mentionner un collaborateur', icon: AtSign, keywords: 'mention personne', run: (e) => e.chain().focus().insertContent('@').run() },
    ];
}

/** Menu « / » : insère titres, listes, citations, images… */
export function slashExtension(items: () => SlashItem[], host: () => HTMLElement | null | undefined) {
    return Extension.create({
        name: 'slashCommand',
        addProseMirrorPlugins() {
            return [
                Suggestion<SlashItem>({
                    editor: this.editor,
                    char: '/',
                    pluginKey: new PluginKey('slashCommand'),
                    allowSpaces: false,
                    items: ({ query }) => {
                        const q = normalize(query);
                        return items().filter((i) => normalize(`${i.title} ${i.keywords ?? ''}`).includes(q));
                    },
                    command: ({ editor, range, props }: { editor: Editor; range: Range; props: SlashItem }) => {
                        editor.chain().focus().deleteRange(range).run();
                        props.run(editor);
                    },
                    render: () => renderList<SlashItem>(host),
                }),
            ];
        },
    });
}
