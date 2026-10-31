<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import type { SharedData, User } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, LogOut, Settings, Users, Zap } from 'lucide-vue-next';

interface Props {
    user: User;
}

defineProps<Props>();
const page = usePage<SharedData>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="flex w-full items-center" :href="route('notifications.index')" as="button">
                <Bell class="mr-2 h-4 w-4" />
                Notifications
                <span
                    v-if="page.props.unreadNotifications"
                    class="ml-auto rounded-full bg-brand-active px-1.5 text-[11px] font-bold leading-[18px] text-white"
                    >{{ page.props.unreadNotifications }}</span
                >
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="route('team.index')" as="button">
                <Users class="mr-2 h-4 w-4" />
                Équipe
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="route('rules.index')" as="button">
                <Zap class="mr-2 h-4 w-4" />
                Règles
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="route('profile.edit')" as="button">
                <Settings class="mr-2 h-4 w-4" />
                Réglages
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link class="block w-full" method="post" :href="route('logout')" as="button">
            <LogOut class="mr-2 h-4 w-4" />
            Se déconnecter
        </Link>
    </DropdownMenuItem>
</template>
