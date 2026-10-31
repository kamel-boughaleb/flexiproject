<script setup lang="ts">
import ProjectAvatar from '@/components/project/ProjectAvatar.vue';
import UserAvatar from '@/components/project/UserAvatar.vue';
import type { MiniUser } from '@/types';
import { router } from '@inertiajs/vue3';
import { Check, CornerDownRight, Loader2, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface ProjectResult {
    id: number;
    name: string;
    color: string;
    icon?: string | null;
    image?: string | null;
    archived: boolean;
    tasks_total: number;
    tasks_done: number;
    members: MiniUser[];
    members_count: number;
    url: string;
}

interface TaskResult {
    id: number;
    title: string;
    completed: boolean;
    due_date: string | null;
    section: string | null;
    parent: string | null;
    project: { id: number; name: string; color: string };
    participants: MiniUser[];
    participants_count: number;
    url: string;
}

type Item = { kind: 'project'; data: ProjectResult } | { kind: 'task'; data: TaskResult };

/** Barre de recherche globale : tâches et projets, résultats proposés au fil de la frappe. */
const query = ref('');
const open = ref(false);
const loading = ref(false);
const projects = ref<ProjectResult[]>([]);
const tasks = ref<TaskResult[]>([]);
const active = ref(0);
const searched = ref('');
const input = ref<HTMLInputElement>();
const root = ref<HTMLElement>();

const items = computed<Item[]>(() => [
    ...projects.value.map((data) => ({ kind: 'project' as const, data })),
    ...tasks.value.map((data) => ({ kind: 'task' as const, data })),
]);
const showPanel = computed(() => open.value && query.value.trim() !== '');

let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;

watch(query, (q) => {
    clearTimeout(timer);
    if (!q.trim()) {
        controller?.abort();
        projects.value = [];
        tasks.value = [];
        searched.value = '';
        loading.value = false;
        return;
    }
    loading.value = true;
    timer = setTimeout(() => search(q.trim()), 200);
});

async function search(q: string) {
    controller?.abort();
    controller = new AbortController();
    try {
        const response = await fetch(route('search', { q }), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
        });
        if (!response.ok) throw new Error(String(response.status));
        const data = (await response.json()) as { projects: ProjectResult[]; tasks: TaskResult[] };
        projects.value = data.projects;
        tasks.value = data.tasks;
        searched.value = q;
        active.value = 0;
        loading.value = false;
    } catch (e) {
        if ((e as Error).name !== 'AbortError') loading.value = false;
    }
}

function go(item: Item | undefined) {
    if (!item) return;
    open.value = false;
    input.value?.blur();
    router.visit(item.data.url);
}

function move(step: number) {
    if (!items.value.length) return;
    active.value = (active.value + step + items.value.length) % items.value.length;
    nextTick(() => root.value?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' }));
}

function clear() {
    query.value = '';
    input.value?.focus();
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        open.value = true;
        move(1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        move(-1);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        go(items.value[active.value]);
    } else if (e.key === 'Escape') {
        if (query.value) query.value = '';
        else input.value?.blur();
        open.value = false;
    }
}

/** Raccourcis : ⌘K / Ctrl+K partout, ou « / » quand on n'est pas déjà en train d'écrire. */
function onGlobalKeydown(e: KeyboardEvent) {
    const target = e.target as HTMLElement | null;
    const typing = !!target && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
    if ((e.key === 'k' && (e.metaKey || e.ctrlKey)) || (e.key === '/' && !typing)) {
        e.preventDefault();
        input.value?.focus();
        input.value?.select();
        open.value = true;
    }
}

function onClickOutside(e: MouseEvent) {
    if (root.value && !root.value.contains(e.target as Node)) open.value = false;
}

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);

onMounted(() => {
    window.addEventListener('keydown', onGlobalKeydown);
    document.addEventListener('mousedown', onClickOutside);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onGlobalKeydown);
    document.removeEventListener('mousedown', onClickOutside);
    clearTimeout(timer);
    controller?.abort();
});

/** Découpe un texte pour mettre en gras les mots recherchés. */
function highlight(text: string): { text: string; match: boolean }[] {
    const words = searched.value
        .split(/\s+/)
        .filter(Boolean)
        .map((w) => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    if (!words.length) return [{ text, match: false }];
    const re = new RegExp(`(${words.join('|')})`, 'gi');
    return text
        .split(re)
        .filter(Boolean)
        .map((part) => ({ text: part, match: words.some((w) => new RegExp(`^${w}$`, 'i').test(part)) }));
}

const dueLabel = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
const indexOf = (item: Item) => items.value.indexOf(item);
</script>

<template>
    <div ref="root" class="relative w-full max-w-xl">
        <div
            class="flex h-9 items-center gap-2 rounded-full border border-app-border bg-app-surface px-3.5 transition focus-within:border-brand focus-within:bg-background focus-within:ring-2 focus-within:ring-brand-tint"
        >
            <Search class="size-4 shrink-0 text-app-muted" />
            <input
                ref="input"
                v-model="query"
                type="search"
                role="combobox"
                aria-label="Rechercher des tâches ou des projets"
                aria-autocomplete="list"
                aria-controls="global-search-results"
                :aria-expanded="showPanel"
                :aria-activedescendant="showPanel && items.length ? `global-search-${active}` : undefined"
                placeholder="Rechercher une tâche ou un projet"
                autocomplete="off"
                class="h-full min-w-0 flex-1 bg-transparent text-sm text-ink outline-none placeholder:text-ink-soft [&::-webkit-search-cancel-button]:hidden"
                @focus="open = true"
                @input="open = true"
                @keydown="onKeydown"
            />
            <Loader2 v-if="loading" class="size-4 shrink-0 animate-spin text-app-muted" />
            <button v-else-if="query" type="button" class="shrink-0 text-app-muted hover:text-ink" aria-label="Effacer la recherche" @click="clear">
                <X class="size-4" />
            </button>
            <kbd
                v-else
                class="hidden shrink-0 rounded-md border border-app-border px-1.5 py-0.5 font-sans text-[11px] font-semibold text-ink-soft sm:inline"
                >{{ isMac ? '⌘K' : 'Ctrl K' }}</kbd
            >
        </div>

        <div
            v-if="showPanel"
            id="global-search-results"
            role="listbox"
            class="absolute inset-x-0 top-full z-50 mt-2 max-h-[min(70vh,560px)] overflow-y-auto rounded-2xl border border-app-border bg-background p-1.5 shadow-product"
        >
            <p v-if="!items.length && (loading || searched !== query.trim())" class="px-3 py-6 text-center text-sm text-ink-soft">Recherche…</p>
            <p v-else-if="!items.length" class="px-3 py-6 text-center text-sm text-ink-soft">
                Aucune tâche ni projet ne correspond à « {{ searched }} ».
            </p>

            <template v-if="projects.length">
                <p class="px-3 pb-1 pt-2 text-xs font-bold uppercase tracking-wide text-ink-soft">Projets</p>
                <button
                    v-for="item in items.filter((i) => i.kind === 'project')"
                    :id="`global-search-${indexOf(item)}`"
                    :key="`p${item.data.id}`"
                    type="button"
                    role="option"
                    :aria-selected="indexOf(item) === active"
                    :data-active="indexOf(item) === active"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left data-[active=true]:bg-app-hover"
                    @mouseenter="active = indexOf(item)"
                    @click="go(item)"
                >
                    <ProjectAvatar :project="item.data" size="md" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[15px] font-semibold text-ink">
                            <template v-for="(part, i) in highlight(item.data.name)" :key="i"
                                ><mark v-if="part.match" class="bg-transparent font-bold text-brand-active">{{ part.text }}</mark
                                ><template v-else>{{ part.text }}</template></template
                            >
                        </span>
                        <span class="block truncate text-[13px] text-ink-soft">
                            {{ item.data.tasks_done }}/{{ item.data.tasks_total }} tâche{{ item.data.tasks_total > 1 ? 's' : '' }} terminée{{
                                item.data.tasks_done > 1 ? 's' : ''
                            }}
                            <template v-if="item.data.archived"> · Archivé</template>
                        </span>
                    </span>
                    <span class="flex shrink-0 -space-x-1.5">
                        <span v-for="m in item.data.members" :key="m.id" class="rounded-full ring-2 ring-background"
                            ><UserAvatar :user="m" size="xs"
                        /></span>
                        <span
                            v-if="item.data.members_count > item.data.members.length"
                            class="flex size-6 items-center justify-center rounded-full bg-info-tint text-[10px] font-bold text-info-deep ring-2 ring-background"
                            >+{{ item.data.members_count - item.data.members.length }}</span
                        >
                    </span>
                </button>
            </template>

            <template v-if="tasks.length">
                <p class="px-3 pb-1 pt-2 text-xs font-bold uppercase tracking-wide text-ink-soft">Tâches</p>
                <button
                    v-for="item in items.filter((i) => i.kind === 'task')"
                    :id="`global-search-${indexOf(item)}`"
                    :key="`t${item.data.id}`"
                    type="button"
                    role="option"
                    :aria-selected="indexOf(item) === active"
                    :data-active="indexOf(item) === active"
                    class="flex w-full items-start gap-3 rounded-xl px-3 py-2.5 text-left data-[active=true]:bg-app-hover"
                    @mouseenter="active = indexOf(item)"
                    @click="go(item)"
                >
                    <span
                        class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border-[1.5px]"
                        :class="
                            item.kind === 'task' && item.data.completed ? 'border-success bg-success text-white' : 'border-app-muted text-app-muted'
                        "
                        :title="item.kind === 'task' && item.data.completed ? 'Terminée' : 'À faire'"
                    >
                        <Check class="size-3" :stroke-width="3" />
                    </span>
                    <span v-if="item.kind === 'task'" class="min-w-0 flex-1">
                        <span class="flex items-center gap-2">
                            <span class="truncate text-[15px] font-semibold" :class="item.data.completed ? 'text-ink-soft' : 'text-ink'">
                                <template v-for="(part, i) in highlight(item.data.title)" :key="i"
                                    ><mark v-if="part.match" class="bg-transparent font-bold text-brand-active">{{ part.text }}</mark
                                    ><template v-else>{{ part.text }}</template></template
                                >
                            </span>
                            <span
                                v-if="item.data.completed"
                                class="shrink-0 rounded-full bg-success-tint px-2 py-px text-[11px] font-bold text-success-text"
                                >Terminée</span
                            >
                        </span>
                        <span v-if="item.data.parent" class="flex items-center gap-1 truncate text-xs text-ink-soft">
                            <CornerDownRight class="size-3 shrink-0" /> Sous-tâche de {{ item.data.parent }}
                        </span>
                        <span class="mt-0.5 flex min-w-0 items-center gap-2 text-[13px] text-ink-soft">
                            <span class="size-2.5 shrink-0 rounded-[3px]" :style="{ backgroundColor: item.data.project.color }" />
                            <span class="truncate">{{ item.data.project.name }}</span>
                            <template v-if="item.data.section"
                                ><span aria-hidden="true">·</span><span class="shrink-0">{{ item.data.section }}</span></template
                            >
                            <template v-if="item.data.due_date && !item.data.completed"
                                ><span aria-hidden="true">·</span><span class="shrink-0">{{ dueLabel(item.data.due_date) }}</span></template
                            >
                        </span>
                    </span>
                    <span v-if="item.kind === 'task' && item.data.participants.length" class="flex shrink-0 -space-x-1.5 self-center">
                        <span v-for="m in item.data.participants" :key="m.id" class="rounded-full ring-2 ring-background"
                            ><UserAvatar :user="m" size="xs"
                        /></span>
                        <span
                            v-if="item.data.participants_count > item.data.participants.length"
                            class="flex size-6 items-center justify-center rounded-full bg-info-tint text-[10px] font-bold text-info-deep ring-2 ring-background"
                            >+{{ item.data.participants_count - item.data.participants.length }}</span
                        >
                    </span>
                </button>
            </template>
        </div>
    </div>
</template>
