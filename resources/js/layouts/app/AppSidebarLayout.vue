<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import GlobalSearch from '@/components/GlobalSearch.vue';
import MobileTabBar from '@/components/MobileTabBar.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenu from '@/components/UserMenu.vue';
import { useLiveRefresh } from '@/composables/useLiveRefresh';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    bare?: boolean;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

// Les données affichées se mettent à jour toutes seules quand d'autres personnes travaillent.
useLiveRefresh();
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <!-- Les pages de projet occupent toute la hauteur : le board et le panneau de tâche défilent chacun de leur côté. -->
        <AppContent variant="sidebar" :class="bare ? 'h-svh overflow-hidden max-md:pb-16 md:h-[calc(100svh-20px)]' : 'max-md:pb-16'">
            <!-- Barre de recherche globale et menu du compte, toujours visibles tout en haut. -->
            <div class="sticky top-0 z-30 flex shrink-0 items-center gap-3 border-b border-app-border bg-background px-4 py-2 md:px-7">
                <div class="flex flex-1 basis-0 items-center">
                    <!-- Sans fil d'Ariane (accueil), le bouton du menu de gauche remonte ici. -->
                    <SidebarTrigger v-if="!bare && !breadcrumbs.length" class="-ml-1" />
                </div>
                <GlobalSearch />
                <div class="flex flex-1 basis-0 justify-end">
                    <UserMenu />
                </div>
            </div>
            <AppSidebarHeader v-if="!bare && breadcrumbs.length" :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <MobileTabBar />
    </AppShell>
</template>
