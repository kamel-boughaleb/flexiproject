<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateTime } from '@/lib/labels';
import { Head, Link, router } from '@inertiajs/vue3';

type Notification = {
    id: string;
    read_at: string | null;
    created_at: string;
    data: { message: string; project_id: number | null; task_id: number | null; url?: string };
};
defineProps<{ notifications: Notification[] }>();

const href = (n: Notification) =>
    n.data.url ??
    (n.data.project_id
        ? route('projects.view', [n.data.project_id, 'list']) + (n.data.task_id ? `?task=${n.data.task_id}` : '')
        : route('dashboard'));
</script>

<template>
    <Head :title="$t('Notifications')" />
    <AppLayout :breadcrumbs="[{ title: $t('Notifications'), href: route('notifications.index') }]">
        <div class="p-6">
            <div class="flex items-center">
                <h1 class="text-2xl font-semibold">{{ $t('Notifications') }}</h1>
                <Button variant="ghost" class="ml-auto" @click="router.post(route('notifications.read'))">{{ $t('Tout marquer comme lu') }}</Button>
            </div>
            <ul class="mt-6 divide-y rounded-lg border">
                <li
                    v-for="n in notifications"
                    :key="n.id"
                    class="flex items-center gap-3 px-4 py-3 text-sm"
                    :class="n.read_at ? 'text-muted-foreground' : 'font-medium'"
                >
                    <span v-if="!n.read_at" class="size-2 rounded-full bg-brand-active" />
                    <Link :href="href(n)" class="flex-1 hover:underline">{{ n.data.message }}</Link>
                    <span class="text-xs text-muted-foreground">{{ dateTime(n.created_at) }}</span>
                </li>
                <li v-if="!notifications.length" class="px-4 py-8 text-center text-sm text-muted-foreground">{{ $t('Aucune notification') }}</li>
            </ul>
        </div>
    </AppLayout>
</template>
