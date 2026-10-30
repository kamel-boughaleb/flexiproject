<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
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
        <AppContent variant="sidebar" :class="bare ? 'h-svh overflow-hidden md:h-[calc(100svh-20px)]' : ''">
            <AppSidebarHeader v-if="!bare" :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
    </AppShell>
</template>
