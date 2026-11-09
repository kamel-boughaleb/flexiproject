<script setup lang="ts">
import ProjectAvatar from '@/components/project/ProjectAvatar.vue';
import UserAvatar from '@/components/project/UserAvatar.vue';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { t } from '@/lib/i18n';
import { type ProjectStatus, type SharedData, type SidebarProject } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { CheckSquare, ListFilter, Newspaper, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const path = computed(() => page.url.split('?')[0]);

// Accueil = logo ; Notifications, Équipe et Règles sont dans le menu de l'avatar (en haut à droite).
const mainNavItems = computed(() => [{ title: t('Mes tâches'), href: route('my-tasks'), icon: CheckSquare, badge: 0 }]);
const isActive = (href: string) => path.value === new URL(href).pathname;

// Filtre des projets par statut : par défaut, seuls les projets non terminés. Choix mémorisé sur cet appareil.
const STATUSES: { value: ProjectStatus; label: string }[] = [
    { value: 'active', label: t('En cours') },
    { value: 'done', label: t('Terminés') },
    { value: 'upcoming', label: t('Non commencés') },
];
const DEFAULT_FILTER: ProjectStatus[] = ['active', 'upcoming'];
const FILTER_KEY = 'sidebar-project-filter';
function loadFilter(): ProjectStatus[] {
    try {
        const saved = JSON.parse(localStorage.getItem(FILTER_KEY) ?? 'null');
        if (Array.isArray(saved)) return saved.filter((s) => STATUSES.some((o) => o.value === s));
    } catch {
        // Stockage indisponible : filtre par défaut.
    }
    return [...DEFAULT_FILTER];
}
const filter = ref<ProjectStatus[]>(loadFilter());
watch(filter, (value) => {
    try {
        localStorage.setItem(FILTER_KEY, JSON.stringify(value));
    } catch {
        // Sans stockage, le filtre vaut pour la session.
    }
});
function toggleStatus(status: ProjectStatus, on: boolean) {
    filter.value = on ? [...filter.value, status] : filter.value.filter((s) => s !== status);
}
const isFiltered = computed(() => filter.value.length !== DEFAULT_FILTER.length || !DEFAULT_FILTER.every((s) => filter.value.includes(s)));

// Ordre des projets : glisser-déposer un projet enregistre sa nouvelle place (propre à chaque utilisateur).
// Copie locale pour que le projet reste à sa place pendant l'enregistrement ; resynchronisée à chaque réponse.
const projects = ref<SidebarProject[]>([]);
watch(
    [() => page.props.sidebarProjects, filter],
    () => {
        projects.value = page.props.sidebarProjects.filter((p) => filter.value.includes(p.status ?? 'active'));
    },
    { immediate: true },
);
function reorder(list: SidebarProject[]) {
    projects.value = list;
    router.post(route('projects.reorder'), { ids: list.map((p) => p.id) }, { preserveScroll: true, preserveState: true, only: ['sidebarProjects'] });
}

// Tiroir mobile : sous-liens du projet ouvert et profil en bas (maquette « M-Menu »).
const { isMobile } = useSidebar();
const projectLinks = (id: number) => [
    { title: t('Collaborateurs'), href: route('projects.members', id) },
    { title: t('Paramètres'), href: route('projects.settings', id) },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup class="px-2 py-0">
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in mainNavItems" :key="item.title">
                        <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.title">
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                        <SidebarMenuBadge v-if="item.badge" class="rounded-full bg-brand-active px-1.5 font-bold text-white">{{
                            item.badge
                        }}</SidebarMenuBadge>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <SidebarGroup class="px-2 pt-5">
                <SidebarGroupLabel class="gap-1">
                    {{ $t('Projets') }}
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            class="flex size-6 items-center justify-center rounded-md hover:bg-sidebar-accent hover:text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                            :class="isFiltered ? 'text-brand-active' : 'text-sidebar-foreground/70'"
                            :aria-label="$t('Filtrer les projets')"
                            :title="$t('Filtrer les projets')"
                        >
                            <ListFilter class="size-3.5" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-48 rounded-2xl p-1.5">
                            <DropdownMenuLabel class="text-xs font-semibold text-ink-soft">{{ $t('Afficher les projets') }}</DropdownMenuLabel>
                            <DropdownMenuCheckboxItem
                                v-for="s in STATUSES"
                                :key="s.value"
                                :checked="filter.includes(s.value)"
                                class="rounded-icon font-semibold"
                                @update:checked="toggleStatus(s.value, $event)"
                                @select.prevent
                            >
                                {{ s.label }}
                            </DropdownMenuCheckboxItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarGroupLabel>
                <SidebarMenu>
                    <li v-if="!projects.length" class="px-2 py-1.5 text-xs text-ink-soft group-data-[collapsible=icon]:hidden">
                        {{ $t('Aucun projet pour ce filtre.') }}
                    </li>
                </SidebarMenu>
                <draggable
                    :model-value="projects"
                    tag="ul"
                    item-key="id"
                    data-sidebar="menu"
                    class="flex w-full min-w-0 flex-col gap-1"
                    :animation="150"
                    :delay="250"
                    :delay-on-touch-only="true"
                    ghost-class="opacity-40"
                    @update:model-value="reorder"
                >
                    <template #item="{ element: p }">
                        <SidebarMenuItem>
                            <SidebarMenuButton as-child :is-active="path.startsWith(`/projects/${p.id}/`)" :tooltip="p.name">
                                <Link :href="route('projects.show', p.id)">
                                    <ProjectAvatar :project="p" size="xs" />
                                    <span class="truncate">{{ p.name }}</span>
                                </Link>
                            </SidebarMenuButton>
                            <SidebarMenuSub v-if="isMobile && path.startsWith(`/projects/${p.id}/`)">
                                <SidebarMenuSubItem v-for="l in projectLinks(p.id)" :key="l.title">
                                    <SidebarMenuSubButton as-child :is-active="isActive(l.href)">
                                        <Link :href="l.href" class="font-semibold">{{ l.title }}</Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </SidebarMenuItem>
                    </template>
                </draggable>
                <SidebarMenu class="mt-1">
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :tooltip="$t('Nouveau projet')">
                            <Link :href="`${route('dashboard')}?new=1`" class="text-ink-soft">
                                <Plus />
                                <span>{{ $t('Nouveau projet') }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Fils d'actualité auxquels l'utilisateur est abonné ; « Tous les fils » pour en découvrir ou en créer. -->
            <SidebarGroup class="px-2 pt-5">
                <SidebarGroupLabel>{{ $t("Fils d'actualité") }}</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="f in page.props.sidebarFeeds" :key="f.id">
                        <SidebarMenuButton as-child :is-active="path === `/feeds/${f.id}` || path.startsWith(`/feeds/${f.id}/`)" :tooltip="f.name">
                            <Link :href="route('feeds.show', f.id)">
                                <ProjectAvatar :project="f" size="xs" />
                                <span class="truncate">{{ f.name }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="path === '/feeds'" :tooltip="$t('Tous les fils')">
                            <Link :href="route('feeds.index')" class="text-ink-soft">
                                <Newspaper />
                                <span>{{ $t('Tous les fils') }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter v-if="isMobile" class="border-t border-app-border p-4">
            <Link :href="route('profile.edit')" class="flex items-center gap-3">
                <UserAvatar :user="page.props.auth.user" size="lg" />
                <span class="min-w-0">
                    <span class="block truncate text-sm font-bold">{{ page.props.auth.user.name }}</span>
                    <span class="block truncate text-xs text-ink-soft">{{ page.props.auth.user.email }}</span>
                </span>
            </Link>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
