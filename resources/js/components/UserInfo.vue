<script setup lang="ts">
import UserAvatar from '@/components/project/UserAvatar.vue';
import type { User } from '@/types';

withDefaults(defineProps<{ user: User; showEmail?: boolean }>(), { showEmail: false });
</script>

<template>
    <UserAvatar :user="user" size="md" />

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{ user.email }}</span>
    </div>
</template>
