<script setup lang="ts">
import UserAvatar from '@/components/project/UserAvatar.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Avatar de l'utilisateur connecté (en haut à droite) qui ouvre le menu du compte. */
const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="rounded-full ring-offset-background transition hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            :title="user.name"
        >
            <UserAvatar :user="user" size="md" />
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-56 rounded-lg" align="end" :side-offset="8">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
