<script setup lang="ts">
import UserAvatar from '@/components/project/UserAvatar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateRange } from '@/lib/labels';
import type { Member, Project } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, GanttChart, KanbanSquare, List, Settings, Users, Workflow } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ project: Project; members: Member[]; title: string }>();
const page = usePage();

const tabs = computed(() => [
    { label: 'Kanban', href: route('projects.view', [props.project.id, 'board']), icon: KanbanSquare },
    { label: 'Liste', href: route('projects.view', [props.project.id, 'list']), icon: List },
    { label: 'Calendrier', href: route('projects.view', [props.project.id, 'calendar']), icon: CalendarDays },
    { label: 'Planning', href: route('projects.view', [props.project.id, 'timeline']), icon: GanttChart },
    { label: 'Règles', href: route('projects.rules', props.project.id), icon: Workflow },
    { label: 'Collaborateurs', href: route('projects.members', props.project.id), icon: Users },
    { label: 'Paramètres', href: route('projects.settings', props.project.id), icon: Settings },
]);

const isActive = (href: string) => page.url.split('?')[0] === new URL(href).pathname;
</script>

<template>
    <Head :title="`${title} · ${project.name}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Projets', href: route('dashboard') },
            { title: project.name, href: route('projects.show', project.id) },
        ]"
    >
        <div class="flex min-h-0 flex-1 flex-col">
            <header class="border-b px-6 pt-4">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="size-4 rounded" :style="{ backgroundColor: project.color }" />
                    <h1 class="text-xl font-semibold">{{ project.name }}</h1>
                    <span v-if="project.start_date || project.end_date" class="rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                        {{ dateRange(project.start_date, project.end_date) }}
                    </span>
                    <div class="ml-auto flex -space-x-1.5">
                        <span v-for="m in members.slice(0, 6)" :key="m.id" class="rounded-full ring-2 ring-background">
                            <UserAvatar :user="m" size="md" />
                        </span>
                        <span
                            v-if="members.length > 6"
                            class="flex size-8 items-center justify-center rounded-full bg-muted text-xs ring-2 ring-background"
                        >
                            +{{ members.length - 6 }}
                        </span>
                    </div>
                </div>
                <nav class="mt-2 flex gap-1 overflow-x-auto">
                    <Link
                        v-for="tab in tabs"
                        :key="tab.label"
                        :href="tab.href"
                        class="flex items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium"
                        :class="
                            isActive(tab.href)
                                ? 'border-orange-500 text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                    >
                        <component :is="tab.icon" class="size-4" />
                        {{ tab.label }}
                    </Link>
                </nav>
            </header>
            <div class="relative min-h-0 flex-1 overflow-auto">
                <slot />
            </div>
        </div>
    </AppLayout>
</template>
