<script setup lang="ts">
import ProjectAvatar from '@/components/project/ProjectAvatar.vue';
import UserAvatar from '@/components/project/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenu from '@/components/UserMenu.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateRange } from '@/lib/labels';
import type { Member, Project, ProjectPageProps, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CircleCheck, Ellipsis, RotateCcw, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ project: Project; members: Member[]; title: string; canEdit?: boolean }>();
const page = usePage<SharedData & Partial<ProjectPageProps>>();

// Menu « … » du projet (administrateurs) : terminer / rouvrir, supprimer.
const canManage = computed(() => !!page.props.can?.manage);
const done = computed(() => !!props.project.completed_at);
const toggleDone = () =>
    router.visit(route(done.value ? 'projects.reopen' : 'projects.complete', props.project.id), {
        method: done.value ? 'delete' : 'post',
        preserveScroll: true,
    });
const deleteOpen = ref(false);
const deleting = ref(false);
const destroy = () =>
    router.delete(route('projects.destroy', props.project.id), {
        onStart: () => (deleting.value = true),
        onFinish: () => (deleting.value = false),
    });

const tabs = computed(() => [
    { label: 'Tableau', href: route('projects.view', [props.project.id, 'board']) },
    { label: 'Liste', href: route('projects.view', [props.project.id, 'list']) },
    { label: 'Calendrier', href: route('projects.view', [props.project.id, 'calendar']) },
    { label: 'Planning', href: route('projects.view', [props.project.id, 'timeline']) },
    { label: 'Collaborateurs', href: route('projects.members', props.project.id) },
    { label: 'Paramètres', href: route('projects.settings', props.project.id) },
]);

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
                    <DropdownMenu v-if="canManage">
                        <DropdownMenuTrigger
                            class="flex size-8 shrink-0 items-center justify-center rounded-icon text-ink-soft hover:bg-app-hover hover:text-ink data-[state=open]:bg-app-hover data-[state=open]:text-ink"
                            aria-label="Actions du projet"
                            title="Actions du projet"
                        >
                            <Ellipsis class="size-5" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-52 rounded-2xl p-1.5">
                            <DropdownMenuItem class="rounded-icon font-semibold" @select="toggleDone">
                                <template v-if="done"><RotateCcw class="size-4" /> Rouvrir le projet</template>
                                <template v-else><CircleCheck class="size-4" /> Terminer</template>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="rounded-icon font-semibold text-destructive focus:bg-destructive/10 focus:text-destructive"
                                @select="deleteOpen = true"
                            >
                                <Trash2 class="size-4" /> Supprimer
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <span
                        v-if="done"
                        class="inline-flex shrink-0 items-center gap-1 rounded-full bg-success-tint px-2.5 py-0.5 text-xs font-bold text-success-text"
                    >
                        <CircleCheck class="size-3.5" /> Terminé
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
                            title="Voir les collaborateurs"
                            aria-label="Voir les collaborateurs du projet"
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
                <nav aria-label="Vues du projet" class="-mb-px mt-3 flex gap-1 overflow-x-auto md:gap-2">
                    <Link
                        v-for="tab in tabs"
                        :key="tab.label"
                        :href="tab.href"
                        :aria-current="isActive(tab.href) ? 'page' : undefined"
                        class="whitespace-nowrap border-b-2 px-2.5 pb-2.5 pt-1.5 text-sm transition-colors md:px-3.5"
                        :class="
                            isActive(tab.href) ? 'border-brand font-bold text-ink' : 'border-transparent font-semibold text-ink-soft hover:text-ink'
                        "
                    >
                        {{ tab.label }}
                    </Link>
                </nav>
            </header>
            <div class="relative flex min-h-0 flex-1 overflow-hidden">
                <div class="min-h-0 min-w-0 flex-1 overflow-auto">
                    <slot />
                </div>
                <slot name="aside" />
            </div>
        </div>

        <Dialog v-model:open="deleteOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Supprimer le projet « {{ project.name }} » ?</DialogTitle>
                    <DialogDescription
                        >Toutes ses tâches, commentaires et pièces jointes seront supprimés. Cette action est définitive.</DialogDescription
                    >
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="deleteOpen = false">Annuler</Button>
                    <Button variant="destructive" :disabled="deleting" @click="destroy">Supprimer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
