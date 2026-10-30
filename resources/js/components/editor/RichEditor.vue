<script setup lang="ts">
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { MiniUser } from '@/types';
import { Extension } from '@tiptap/core';
import Highlight from '@tiptap/extension-highlight';
import Image from '@tiptap/extension-image';
import { TaskItem, TaskList } from '@tiptap/extension-list';
import { Color, FontSize, TextStyle } from '@tiptap/extension-text-style';
import { Placeholder } from '@tiptap/extensions';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { useEventListener } from '@vueuse/core';
import {
    AtSign,
    Bold,
    ChevronDown,
    Code,
    Code2,
    Eraser,
    Highlighter,
    Italic,
    Link2,
    List,
    ListChecks,
    ListOrdered,
    Minus,
    MoreHorizontal,
    Plus,
    Quote,
    Smile,
    Strikethrough,
    Type,
    Underline,
} from 'lucide-vue-next';
import { PopoverAnchor, PopoverContent, PopoverPortal, PopoverRoot } from 'radix-vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import EmojiPicker from './EmojiPicker.vue';
import { mentionExtension, slashExtension, slashItems } from './suggestion';

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        projectId: number;
        members: MiniUser[];
        placeholder?: string;
        /** Affiche le bouton d'envoi (commentaires). Ctrl/⌘ + Entrée envoie aussi. */
        submittable?: boolean;
        busy?: boolean;
        minHeight?: string;
        /** Édition en place : pas de cadre ni de barre d'outils visibles tant que l'on ne survole ou n'édite pas. */
        bare?: boolean;
    }>(),
    { placeholder: 'Saisissez « / » pour afficher le menu', submittable: false, busy: false, minHeight: '4rem', bare: false },
);
const emit = defineEmits<{ 'update:modelValue': [html: string]; submit: [html: string]; change: [html: string] }>();

const root = ref<HTMLElement>();
const fileInput = ref<HTMLInputElement>();
const uploading = ref(false);

/** HTML de l'éditeur, ou chaîne vide s'il ne contient rien. */
const html = () => (!editor.value || editor.value.isEmpty ? '' : editor.value.getHTML());

const SubmitShortcut = Extension.create({
    name: 'submitShortcut',
    addKeyboardShortcuts: () => ({
        'Mod-Enter': () => {
            if (props.submittable) submit();
            return props.submittable;
        },
    }),
});

const editor = useEditor({
    content: props.modelValue ?? '',
    extensions: [
        StarterKit.configure({
            heading: { levels: [1, 2, 3] },
            link: {
                openOnClick: false,
                autolink: true,
                defaultProtocol: 'https',
                HTMLAttributes: { target: '_blank', rel: 'noopener noreferrer nofollow' },
            },
        }),
        Placeholder.configure({ placeholder: props.placeholder }),
        Highlight.configure({ multicolor: true }),
        Image.configure({ inline: false }),
        TaskList,
        TaskItem.configure({ nested: true }),
        TextStyle,
        Color,
        FontSize,
        mentionExtension(
            () => props.members,
            () => root.value,
        ),
        slashExtension(
            () => slashItems({ pickImage }),
            () => root.value,
        ),
        SubmitShortcut,
    ],
    editorProps: {
        attributes: { class: 'rich-text min-h-[var(--editor-min-height)] px-3.5 py-2.5 outline-none' },
        handlePaste: (_view, event) => uploadFrom(event.clipboardData?.files),
        handleDrop: (_view, event) => uploadFrom((event as DragEvent).dataTransfer?.files),
    },
    onCreate: ({ editor: created }) => (committed = created.isEmpty ? '' : created.getHTML()),
    onUpdate: () => emit('update:modelValue', html()),
    onBlur: ({ event }) => {
        if (!isInside(event.relatedTarget)) commit();
    },
});

/**
 * « change » est émis quand on quitte l'éditeur après l'avoir modifié (comme un champ classique).
 * La barre d'outils et ses menus (affichés hors de l'éditeur) ne comptent pas comme une sortie.
 */
let committed = '';
function commit() {
    if (!editor.value || html() === committed) return;
    committed = html();
    emit('change', committed);
}
function isInside(target: EventTarget | null): boolean {
    if (!(target instanceof Element)) return false;
    return !!root.value?.contains(target) || !!target.closest('[data-radix-popper-content-wrapper]');
}
// Clic ailleurs alors que le curseur était dans un menu de la barre d'outils (emoji, lien…).
useEventListener(document, 'pointerdown', (e: PointerEvent) => !editor.value?.isFocused && !isInside(e.target) && commit(), { capture: true });

// Valeur modifiée de l'extérieur (enregistrement, autre tâche…) : on ne touche pas au texte en cours de saisie.
watch(
    () => props.modelValue,
    (value) => {
        if (!editor.value || editor.value.isFocused || (value ?? '') === html()) return;
        editor.value.commands.setContent(value ?? '', { emitUpdate: false });
        committed = html();
    },
);

onBeforeUnmount(commit);

function submit() {
    if (!editor.value || editor.value.isEmpty || props.busy) return;
    emit('submit', html());
}

function clear() {
    editor.value?.commands.clearContent(true);
    committed = '';
}

defineExpose({ clear, focus: () => editor.value?.commands.focus() });

/* ---------- Images ---------- */

function pickImage() {
    fileInput.value?.click();
}

function uploadFrom(files: FileList | null | undefined): boolean {
    const images = Array.from(files ?? []).filter((f) => f.type.startsWith('image/'));
    if (!images.length) return false;
    images.forEach(upload);
    return true;
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function upload(file: File) {
    const data = new FormData();
    data.append('image', file);
    uploading.value = true;
    try {
        const response = await fetch(route('editor-images.store', props.projectId), {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(json.errors?.image?.[0] ?? json.message ?? 'Envoi impossible.');
        editor.value?.chain().focus().setImage({ src: json.url, alt: file.name }).run();
    } catch (e) {
        alert(`Image « ${file.name} » non ajoutée : ${(e as Error).message}`);
    } finally {
        uploading.value = false;
    }
}

function onPick(e: Event) {
    const input = e.target as HTMLInputElement;
    uploadFrom(input.files);
    input.value = '';
}

/* ---------- Liens ---------- */

const linkOpen = ref(false);
const linkUrl = ref('');
function openLink() {
    linkUrl.value = editor.value?.getAttributes('link').href ?? '';
    linkOpen.value = true;
}
function applyLink() {
    const chain = editor.value?.chain().focus().extendMarkRange('link');
    const url = linkUrl.value.trim();
    if (!chain) return;
    if (url) {
        const href = /^(https?:\/\/|mailto:|\/)/i.test(url) ? url : `https://${url}`;
        if (editor.value?.state.selection.empty && !editor.value.isActive('link')) {
            chain.insertContent({ type: 'text', text: url, marks: [{ type: 'link', attrs: { href } }] }).run();
        } else {
            chain.setLink({ href }).run();
        }
    } else {
        chain.unsetLink().run();
    }
    linkOpen.value = false;
}

/* ---------- Barre d'outils ---------- */

const STYLES = [
    { label: 'Texte normal', level: 0, class: 'text-sm' },
    { label: 'Titre 1', level: 1, class: 'text-xl font-bold' },
    { label: 'Titre 2', level: 2, class: 'text-lg font-semibold' },
    { label: 'Titre 3', level: 3, class: 'text-base font-semibold' },
] as const;
const SIZES = [
    { label: 'Petit', value: '12px' },
    { label: 'Normal', value: null },
    { label: 'Grand', value: '18px' },
    { label: 'Très grand', value: '24px' },
] as const;
const COLORS = ['#111827', '#6b7280', '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#2563eb', '#7c3aed', '#db2777'];
const HIGHLIGHTS = ['#fef08a', '#bbf7d0', '#bfdbfe', '#fbcfe8', '#fed7aa'];

const currentStyle = computed(() => STYLES.find((s) => s.level && editor.value?.isActive('heading', { level: s.level }))?.label ?? 'Texte normal');

function setStyle(level: 0 | 1 | 2 | 3) {
    const chain = editor.value?.chain().focus();
    if (level === 0) chain?.setParagraph().run();
    else chain?.setHeading({ level }).run();
}
function setSize(value: string | null) {
    const chain = editor.value?.chain().focus();
    if (value) chain?.setFontSize(value).run();
    else chain?.unsetFontSize().run();
}

type Mark = 'bold' | 'italic' | 'strike' | 'underline' | 'code';
const MARKS: { mark: Mark; icon: typeof Bold; title: string }[] = [
    { mark: 'bold', icon: Bold, title: 'Gras (⌘B)' },
    { mark: 'italic', icon: Italic, title: 'Italique (⌘I)' },
    { mark: 'strike', icon: Strikethrough, title: 'Barré' },
    { mark: 'underline', icon: Underline, title: 'Souligné (⌘U)' },
];
function toggle(mark: Mark) {
    const chain = editor.value?.chain().focus();
    if (!chain) return;
    ({
        bold: () => chain.toggleBold(),
        italic: () => chain.toggleItalic(),
        strike: () => chain.toggleStrike(),
        underline: () => chain.toggleUnderline(),
        code: () => chain.toggleCode(),
    })
        [mark]()
        .run();
}

const active = (name: string, attrs?: Record<string, unknown>) => editor.value?.isActive(name, attrs) ?? false;
const btn = 'inline-flex size-8 items-center justify-center rounded-icon text-ink-soft hover:bg-app-hover hover:text-ink disabled:opacity-40';
const on = 'bg-app-hover text-ink';
// Classe liée dynamiquement : un bouton statique serait partagé entre les éditeurs et perdrait ses classes (as-child de radix-vue).
const styleBtn = 'inline-flex h-8 items-center gap-1 rounded-icon px-2 text-sm font-semibold text-ink-soft hover:bg-app-hover hover:text-ink';

function insertEmoji(emoji: string) {
    editor.value?.chain().focus().insertContent(emoji).run();
}
function mention() {
    const ed = editor.value;
    if (!ed) return;
    // Espace devant le « @ » si on est collé à un mot, sinon la suggestion ne s'ouvre pas.
    const before = ed.state.doc.textBetween(Math.max(0, ed.state.selection.from - 1), ed.state.selection.from);
    ed.chain()
        .focus()
        .insertContent(before && !/\s/.test(before) ? ' @' : '@')
        .run();
}
</script>

<template>
    <div
        ref="root"
        class="group/editor relative rounded-2xl border-2 bg-background transition-colors focus-within:border-info focus-within:ring-4 focus-within:ring-info-tint"
        :class="bare ? 'border-transparent hover:border-app-border' : 'border-app-border'"
        :style="{ '--editor-min-height': minHeight }"
    >
        <div
            v-if="editor"
            class="flex flex-wrap items-center gap-0.5 border-b border-app-border px-1.5 py-1 transition-opacity"
            :class="{
                'opacity-0 group-focus-within/editor:opacity-100 group-hover/editor:opacity-100 group-has-[[data-state=open]]/editor:opacity-100': bare,
            }"
        >
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button type="button" :class="styleBtn">
                        <Type class="size-4" /> <span class="hidden sm:inline">{{ currentStyle }}</span> <ChevronDown class="size-3.5" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="z-[60] w-48">
                    <DropdownMenuItem v-for="s in STYLES" :key="s.level" :class="s.class" @select="setStyle(s.level)">{{ s.label }}</DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuLabel class="text-xs font-normal text-muted-foreground">Taille du texte</DropdownMenuLabel>
                    <DropdownMenuItem v-for="s in SIZES" :key="s.label" @select="setSize(s.value)">
                        <span :style="{ fontSize: s.value ?? '14px' }">{{ s.label }}</span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <span class="mx-1 h-5 w-px bg-border" />

            <button v-for="m in MARKS" :key="m.mark" type="button" :title="m.title" :class="[btn, active(m.mark) && on]" @click="toggle(m.mark)">
                <component :is="m.icon" class="size-4" />
            </button>

            <PopoverRoot v-model:open="linkOpen">
                <PopoverAnchor as-child>
                    <button type="button" title="Lien (⌘K)" :class="[btn, active('link') && on]" @click="openLink"><Link2 class="size-4" /></button>
                </PopoverAnchor>
                <PopoverPortal>
                    <PopoverContent align="start" :side-offset="6" class="z-[60] w-72 rounded-md border bg-popover p-2 shadow-lg">
                        <form class="flex gap-1.5" @submit.prevent="applyLink">
                            <input
                                v-model="linkUrl"
                                autofocus
                                placeholder="https://…"
                                class="min-w-0 flex-1 rounded border bg-background px-2 py-1 text-sm outline-none focus:border-primary/50"
                            />
                            <button type="submit" class="rounded bg-primary px-2 text-xs text-primary-foreground">OK</button>
                        </form>
                        <p class="mt-1 text-[11px] text-muted-foreground">Laissez vide pour retirer le lien.</p>
                    </PopoverContent>
                </PopoverPortal>
            </PopoverRoot>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button type="button" title="Surligner" :class="[btn, active('highlight') && on]"><Highlighter class="size-4" /></button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="z-[60]">
                    <div class="flex gap-1 p-1">
                        <DropdownMenuItem
                            v-for="c in HIGHLIGHTS"
                            :key="c"
                            class="size-6 rounded border p-0"
                            :style="{ background: c }"
                            @select="editor?.chain().focus().setHighlight({ color: c }).run()"
                        />
                    </div>
                    <DropdownMenuItem @select="editor?.chain().focus().unsetHighlight().run()">Retirer le surlignage</DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <button type="button" title="Code" :class="[btn, active('code') && on]" @click="toggle('code')"><Code class="size-4" /></button>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button type="button" title="Listes" :class="[btn, (active('bulletList') || active('orderedList') || active('taskList')) && on]">
                        <List class="size-4" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="z-[60]">
                    <DropdownMenuItem @select="editor?.chain().focus().toggleBulletList().run()"
                        ><List class="size-4" /> Liste à puces</DropdownMenuItem
                    >
                    <DropdownMenuItem @select="editor?.chain().focus().toggleOrderedList().run()"
                        ><ListOrdered class="size-4" /> Liste numérotée</DropdownMenuItem
                    >
                    <DropdownMenuItem @select="editor?.chain().focus().toggleTaskList().run()"
                        ><ListChecks class="size-4" /> Liste de tâches</DropdownMenuItem
                    >
                </DropdownMenuContent>
            </DropdownMenu>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button type="button" title="Plus d'options" :class="btn"><MoreHorizontal class="size-4" /></button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="z-[60] w-52">
                    <DropdownMenuItem @select="editor?.chain().focus().toggleBlockquote().run()"><Quote class="size-4" /> Citation</DropdownMenuItem>
                    <DropdownMenuItem @select="editor?.chain().focus().toggleCodeBlock().run()"
                        ><Code2 class="size-4" /> Bloc de code</DropdownMenuItem
                    >
                    <DropdownMenuItem @select="editor?.chain().focus().setHorizontalRule().run()"
                        ><Minus class="size-4" /> Séparateur</DropdownMenuItem
                    >
                    <DropdownMenuSeparator />
                    <DropdownMenuLabel class="text-xs font-normal text-muted-foreground">Couleur du texte</DropdownMenuLabel>
                    <div class="flex flex-wrap gap-1 px-2 pb-1">
                        <DropdownMenuItem
                            v-for="c in COLORS"
                            :key="c"
                            class="size-5 rounded-full p-0"
                            :style="{ background: c }"
                            @select="editor?.chain().focus().setColor(c).run()"
                        />
                    </div>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem @select="editor?.chain().focus().unsetAllMarks().clearNodes().run()">
                        <Eraser class="size-4" /> Effacer la mise en forme
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <EditorContent :editor="editor" />

        <div class="flex items-center gap-0.5 px-1.5 pb-1.5">
            <button type="button" title="Insérer une image" :class="btn" :disabled="uploading" @click="pickImage"><Plus class="size-4" /></button>
            <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/gif,image/webp" multiple class="hidden" @change="onPick" />
            <EmojiPicker @pick="insertEmoji">
                <button type="button" title="Emoji" :class="btn"><Smile class="size-4" /></button>
            </EmojiPicker>
            <button type="button" title="Mentionner un collaborateur" :class="btn" @click="mention"><AtSign class="size-4" /></button>
            <span v-if="uploading" class="ml-2 text-xs text-muted-foreground">Envoi de l'image…</span>
            <button
                v-if="submittable"
                type="button"
                title="Envoyer (⌘ + Entrée)"
                class="ml-auto inline-flex h-9 items-center gap-1.5 rounded-full bg-brand-active px-4 text-sm font-bold text-white transition-colors hover:bg-brand-hover disabled:opacity-40"
                :disabled="busy || uploading || !editor || editor.isEmpty"
                @click="submit"
            >
                Envoyer
            </button>
        </div>
    </div>
</template>
