<script setup lang="ts">
import UserAvatar from '@/components/project/UserAvatar.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';

/** Avatar et prénom de l'utilisateur connecté (en haut à droite) : un bouton avec une flèche qui ouvre le menu du compte. */
const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="group flex shrink-0 cursor-pointer items-center gap-2 rounded-full border border-app-border bg-background py-1 pl-1 pr-2.5 shadow-sm ring-offset-background transition hover:border-app-muted hover:bg-app-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 data-[state=open]:border-brand data-[state=open]:bg-app-hover"
            :title="$t('Mon compte')"
            :aria-label="$t('Ouvrir le menu du compte')"
        >
            <UserAvatar :user="user" size="sm" />
            <span class="hidden max-w-32 truncate text-sm font-semibold text-ink sm:block">{{ user.name.split(' ')[0] }}</span>
            <ChevronDown class="size-4 text-ink-soft transition-transform group-data-[state=open]:rotate-180" />
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-56 rounded-lg" align="end" :side-offset="8">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
