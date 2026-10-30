<script setup lang="ts">
import UserAvatar from '@/components/project/UserAvatar.vue';
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
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, CheckSquare, Home, Plus, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const path = computed(() => page.url.split('?')[0]);

const mainNavItems = computed(() => [
    { title: 'Accueil', href: route('dashboard'), icon: Home },
    { title: 'Mes tâches', href: route('my-tasks'), icon: CheckSquare },
    { title: 'Notifications', href: route('notifications.index'), icon: Bell, badge: page.props.unreadNotifications },
    { title: 'Équipe', href: route('team.index'), icon: Users },
]);
const isActive = (href: string) => path.value === new URL(href).pathname;

// Tiroir mobile : sous-liens du projet ouvert et profil en bas (maquette « M-Menu »).
const { isMobile } = useSidebar();
const projectLinks = (id: number) => [
    { title: 'Règles', href: route('projects.rules', id) },
    { title: 'Collaborateurs', href: route('projects.members', id) },
    { title: 'Paramètres', href: route('projects.settings', id) },
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
                <SidebarGroupLabel>Projets</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="p in page.props.sidebarProjects" :key="p.id">
                        <SidebarMenuButton as-child :is-active="path.startsWith(`/projects/${p.id}/`)" :tooltip="p.name">
                            <Link :href="route('projects.show', p.id)">
                                <span class="size-2.5 shrink-0 rounded-[3px]" :style="{ backgroundColor: p.color }" />
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
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child tooltip="Nouveau projet">
                            <Link :href="`${route('dashboard')}?new=1`" class="text-ink-soft">
                                <Plus />
                                <span>Nouveau projet</span>
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
