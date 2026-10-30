<script setup lang="ts">
import { useSidebar } from '@/components/ui/sidebar';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, CircleCheck, FolderKanban, Home } from 'lucide-vue-next';
import { computed } from 'vue';

/** Barre d'onglets du bas sur mobile (Accueil, Mes tâches, Projets, Notifications). */
const page = usePage<SharedData>();
const { setOpenMobile } = useSidebar();
const path = computed(() => page.url.split('?')[0]);

const tabs = computed(() => [
    { label: 'Accueil', href: route('dashboard'), icon: Home },
    { label: 'Mes tâches', href: route('my-tasks'), icon: CircleCheck },
    { label: 'Notifications', href: route('notifications.index'), icon: Bell, badge: page.props.unreadNotifications },
]);
const isActive = (href: string) => path.value === new URL(href).pathname;
const tab = 'relative flex min-h-11 flex-1 flex-col items-center justify-center gap-0.5 text-[11px] font-bold';
</script>

<template>
    <nav
        aria-label="Navigation mobile"
        class="fixed inset-x-0 bottom-0 z-30 flex border-t border-app-border bg-background px-2 pb-[max(env(safe-area-inset-bottom),6px)] pt-1.5 md:hidden"
    >
        <Link v-for="t in tabs.slice(0, 2)" :key="t.label" :href="t.href" :class="[tab, isActive(t.href) ? 'text-brand-active' : 'text-ink-soft']">
            <component :is="t.icon" class="size-[22px]" />{{ t.label }}
        </Link>
        <button type="button" :class="[tab, path.startsWith('/projects/') ? 'text-brand-active' : 'text-ink-soft']" @click="setOpenMobile(true)">
            <FolderKanban class="size-[22px]" />Projets
        </button>
        <Link :href="tabs[2].href" :class="[tab, isActive(tabs[2].href) ? 'text-brand-active' : 'text-ink-soft']">
            <span class="relative">
                <Bell class="size-[22px]" />
                <span
                    v-if="tabs[2].badge"
                    class="absolute -right-2 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-active px-1 text-[10px] text-white"
                    >{{ tabs[2].badge }}</span
                >
            </span>
            Notifications
        </Link>
    </nav>
</template>
