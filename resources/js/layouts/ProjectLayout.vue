<script setup lang="ts">
import PostFormDialog from '@/components/feed/PostFormDialog.vue';
import ProjectAvatar from '@/components/project/ProjectAvatar.vue';
import UserAvatar from '@/components/project/UserAvatar.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenu from '@/components/UserMenu.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { t } from '@/lib/i18n';
import { dateRange } from '@/lib/labels';
import type { Member, Project, ProjectPageProps, ProjectTab, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CircleCheck, Newspaper } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import draggable from 'vuedraggable';

const props = defineProps<{ project: Project; members: Member[]; title: string; canEdit?: boolean }>();
const page = usePage<SharedData & Partial<ProjectPageProps>>();

// Bouton « Terminer » (administrateurs) ; sur un projet terminé, il le rouvre. La suppression est dans Paramètres.
const canManage = computed(() => !!page.props.can?.manage);
const done = computed(() => !!props.project.completed_at);
const toggleDone = () =>
    router.visit(route(done.value ? 'projects.reopen' : 'projects.complete', props.project.id), {
        method: done.value ? 'delete' : 'post',
        preserveScroll: true,
    });
// Nouvelle actualité à propos du projet : choix du fil dans la fenêtre, titre pré-rempli avec le nom du projet.
const posting = ref(false);

const TABS: Record<ProjectTab, { label: () => string; href: () => string }> = {
    board: { label: () => t('Tableau'), href: () => route('projects.view', [props.project.id, 'board']) },
    list: { label: () => t('Liste'), href: () => route('projects.view', [props.project.id, 'list']) },
    calendar: { label: () => t('Calendrier'), href: () => route('projects.view', [props.project.id, 'calendar']) },
    timeline: { label: () => t('Planning'), href: () => route('projects.view', [props.project.id, 'timeline']) },
    members: { label: () => t('Collaborateurs'), href: () => route('projects.members', props.project.id) },
    settings: { label: () => t('Paramètres'), href: () => route('projects.settings', props.project.id) },
};

// Onglets réordonnables par glisser-déposer (éditeurs et administrateurs) ; le premier devient la vue par défaut.
// Copie locale pour que le nouvel ordre s'affiche aussitôt ; resynchronisée à chaque réponse du serveur.
const order = ref<ProjectTab[]>([]);
watch(
    () => page.props.tabs,
    (tabs) => (order.value = tabs?.length ? [...tabs] : (Object.keys(TABS) as ProjectTab[])),
    { immediate: true },
);
const tabs = computed(() => order.value.map((key) => ({ key, label: TABS[key].label(), href: TABS[key].href() })));

function reorderTabs(list: { key: ProjectTab }[]) {
    const keys = list.map((tab) => tab.key);
    if (keys.every((k, i) => k === order.value[i])) return;
    order.value = keys;
    router.post(route('projects.tabs', props.project.id), { tabs: keys }, { preserveScroll: true, preserveState: true, only: ['tabs'] });
}

const isActive = (href: string) => page.url.split('?')[0] === new URL(href).pathname;

/** Les autres collaborateurs du projet : l'utilisateur connecté a déjà son avatar (menu du compte) juste à côté. */
const others = computed(() => props.members.filter((m) => m.id !== page.props.auth.user.id));
</script>

<template>
    <Head :title="`${title} · ${project.name}`" />
    <AppLayout bare>
        <div class="flex min-h-0 flex-1 flex-col">
            <header class="border-b border-app-border px-4 pt-3 md:px-7 md:pt-[18px]">
                <div class="flex items-center gap-3">
                    <SidebarTrigger class="-ml-1 md:hidden" />
                    <ProjectAvatar :project="project" size="md" />
                    <h1 class="truncate text-xl font-bold leading-tight md:text-2xl">{{ project.name }}</h1>
                    <button
                        v-if="canManage"
                        type="button"
                        class="flex size-8 shrink-0 items-center justify-center rounded-icon transition-colors"
                        :class="done ? 'text-success hover:bg-app-hover' : 'text-ink-soft hover:bg-success-tint hover:text-success'"
                        :aria-label="done ? $t('Rouvrir le projet') : $t('Terminer le projet')"
                        :title="done ? $t('Rouvrir le projet') : $t('Terminer le projet')"
                        @click="toggleDone"
                    >
                        <CircleCheck class="size-5" />
                    </button>
                    <button
                        type="button"
                        class="flex size-8 shrink-0 items-center justify-center rounded-icon text-ink-soft transition-colors hover:bg-app-hover hover:text-ink"
                        :aria-label="$t('Publier une actualité sur ce projet')"
                        :title="$t('Publier une actualité sur ce projet')"
                        @click="posting = true"
                    >
                        <Newspaper class="size-5" />
                    </button>
                    <span
                        v-if="done"
                        class="inline-flex shrink-0 items-center gap-1 rounded-full bg-success-tint px-2.5 py-0.5 text-xs font-bold text-success-text"
                    >
                        <CircleCheck class="size-3.5" /> {{ $t('Terminé') }}
                    </span>
                    <span
                        v-if="project.start_date || project.end_date"
                        class="hidden shrink-0 rounded-full bg-app-hover px-2.5 py-0.5 text-xs font-medium text-ink-soft sm:inline"
                    >
                        {{ dateRange(project.start_date, project.end_date) }}
                    </span>
                    <div class="ml-auto flex shrink-0 items-center gap-2.5">
                        <Link
                            v-if="others.length"
                            :href="route('projects.members', project.id)"
                            :title="$t('Voir les collaborateurs')"
                            :aria-label="$t('Voir les collaborateurs du projet')"
                            class="hidden -space-x-1.5 border-r border-app-border pr-3 transition-opacity hover:opacity-80 sm:flex"
                        >
                            <span v-for="m in others.slice(0, 5)" :key="m.id" class="rounded-full ring-2 ring-background">
                                <UserAvatar :user="m" size="md" />
                            </span>
                            <span
                                v-if="others.length > 5"
                                class="flex size-8 items-center justify-center rounded-full bg-info-tint text-xs font-bold text-info-deep ring-2 ring-background"
                            >
                                +{{ others.length - 5 }}
                            </span>
                        </Link>
                        <UserMenu />
                    </div>
                </div>
                <draggable
                    :model-value="tabs"
                    tag="nav"
                    item-key="key"
                    direction="horizontal"
                    :disabled="!canEdit"
                    :animation="150"
                    :delay="250"
                    :delay-on-touch-only="true"
                    ghost-class="opacity-40"
                    :aria-label="$t('Vues du projet')"
                    class="-mb-px mt-3 flex gap-1 overflow-x-auto md:gap-2"
                    @update:model-value="reorderTabs"
                >
                    <template #item="{ element: tab }">
                        <Link
                            :href="tab.href"
                            :aria-current="isActive(tab.href) ? 'page' : undefined"
                            :title="canEdit ? $t('Glisser pour réordonner : le premier onglet s’ouvre par défaut') : undefined"
                            class="whitespace-nowrap border-b-2 px-2.5 pb-2.5 pt-1.5 text-sm transition-colors md:px-3.5"
                            :class="
                                isActive(tab.href)
                                    ? 'border-brand font-bold text-ink'
                                    : 'border-transparent font-semibold text-ink-soft hover:text-ink'
                            "
                        >
                            {{ tab.label }}
                        </Link>
                    </template>
                </draggable>
            </header>
            <div class="relative flex min-h-0 flex-1 overflow-hidden">
                <div class="min-h-0 min-w-0 flex-1 overflow-auto">
                    <slot />
                </div>
                <slot name="aside" />
            </div>
        </div>
        <PostFormDialog v-model:open="posting" :initial-title="project.name" />
    </AppLayout>
</template>
