<script setup lang="ts">
import UserAvatar from '@/components/project/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenu from '@/components/UserMenu.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateRange } from '@/lib/labels';
import type { Member, Project } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ project: Project; members: Member[]; title: string; canEdit?: boolean }>();
const page = usePage();

const tabs = computed(() => [
    { label: 'Kanban', href: route('projects.view', [props.project.id, 'board']) },
    { label: 'Liste', href: route('projects.view', [props.project.id, 'list']) },
    { label: 'Calendrier', href: route('projects.view', [props.project.id, 'calendar']) },
    { label: 'Planning', href: route('projects.view', [props.project.id, 'timeline']) },
    { label: 'Règles', href: route('projects.rules', props.project.id) },
    { label: 'Collaborateurs', href: route('projects.members', props.project.id) },
    { label: 'Paramètres', href: route('projects.settings', props.project.id) },
]);

const isActive = (href: string) => page.url.split('?')[0] === new URL(href).pathname;

/** « Ajouter une tâche » : ouvre la saisie rapide du Kanban ou de la liste, sinon bascule sur le Kanban. */
function addTask() {
    const view = (page.props as { view?: string }).view;
    if (page.component === 'Projects/Show' && (view === 'board' || view === 'list')) {
        window.dispatchEvent(new CustomEvent('flexi:add-task'));
    } else {
        router.get(route('projects.view', [props.project.id, 'board']), { add: 1 });
    }
}
</script>

<template>
    <Head :title="`${title} · ${project.name}`" />
    <AppLayout bare>
        <div class="flex min-h-0 flex-1 flex-col">
            <header class="border-b border-app-border px-4 pt-3 md:px-7 md:pt-[18px]">
                <div class="flex items-center gap-3">
                    <SidebarTrigger class="-ml-1 md:hidden" />
                    <span class="size-[22px] shrink-0 rounded-md" :style="{ backgroundColor: project.color }" />
                    <h1 class="truncate text-xl font-bold leading-tight md:text-2xl">{{ project.name }}</h1>
                    <span
                        v-if="project.start_date || project.end_date"
                        class="hidden shrink-0 rounded-full bg-app-hover px-2.5 py-0.5 text-xs font-medium text-ink-soft sm:inline"
                    >
                        {{ dateRange(project.start_date, project.end_date) }}
                    </span>
                    <div class="ml-auto flex shrink-0 items-center gap-2.5">
                        <div class="hidden -space-x-1.5 sm:flex">
                            <span v-for="m in members.slice(0, 5)" :key="m.id" class="rounded-full ring-2 ring-background">
                                <UserAvatar :user="m" size="md" />
                            </span>
                            <span
                                v-if="members.length > 5"
                                class="flex size-8 items-center justify-center rounded-full bg-info-tint text-xs font-bold text-info-deep ring-2 ring-background"
                            >
                                +{{ members.length - 5 }}
                            </span>
                        </div>
                        <Button v-if="canEdit" variant="secondary" class="hidden h-9 md:inline-flex" @click="addTask">
                            <Plus /> Ajouter une tâche
                        </Button>
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
    </AppLayout>
</template>
