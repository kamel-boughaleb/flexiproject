<script setup lang="ts">
import FeedFormDialog from '@/components/feed/FeedFormDialog.vue';
import ProjectAvatar from '@/components/project/ProjectAvatar.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dateTime } from '@/lib/labels';
import type { Feed, MiniUser } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Bell, BellOff, Plus } from 'lucide-vue-next';
import { ref } from 'vue';

type FeedRow = Feed & { subscribers_count: number; posts_count: number; subscribed: boolean; owner: MiniUser | null; last_post_at: string | null };
defineProps<{ feeds: FeedRow[]; colors: string[] }>();

const creating = ref(new URLSearchParams(window.location.search).has('new'));
const toggle = (f: FeedRow) =>
    f.subscribed
        ? router.delete(route('feeds.unsubscribe', f.id), { preserveScroll: true })
        : router.post(route('feeds.subscribe', f.id), {}, { preserveScroll: true });
</script>

<template>
    <Head :title="$t('Fils d\'actualité')" />
    <AppLayout :breadcrumbs="[{ title: $t('Fils d\'actualité'), href: route('feeds.index') }]">
        <div class="mx-auto w-full max-w-4xl p-6">
            <div class="flex items-start gap-4">
                <div class="flex-1">
                    <h1 class="text-2xl font-semibold">{{ $t("Fils d'actualité") }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ $t('Abonnez-vous aux fils qui vous intéressent : chaque nouvelle actualité vous est envoyée par e-mail.') }}
                    </p>
                </div>
                <Button @click="creating = true"><Plus class="size-4" /> {{ $t('Nouveau fil') }}</Button>
            </div>

            <ul class="mt-6 grid gap-4 sm:grid-cols-2">
                <li v-for="f in feeds" :key="f.id" class="flex flex-col rounded-2xl border border-app-border p-4 transition hover:shadow-hover">
                    <Link :href="route('feeds.show', f.id)" class="flex-1">
                        <div class="flex items-center gap-2">
                            <ProjectAvatar :project="f" size="md" />
                            <h2 class="font-bold">{{ f.name }}</h2>
                        </div>
                        <p v-if="f.description" class="mt-2 line-clamp-2 text-sm text-muted-foreground">{{ f.description }}</p>
                        <p class="mt-3 text-xs text-ink-soft">
                            {{ $tc(':count actualité|:count actualités', f.posts_count) }} ·
                            {{ $tc(':count abonné|:count abonnés', f.subscribers_count) }}
                            <template v-if="f.last_post_at"> · {{ $t('dernière le :date', { date: dateTime(f.last_post_at) }) }}</template>
                        </p>
                    </Link>
                    <div class="mt-3">
                        <Button :variant="f.subscribed ? 'outline' : 'default'" size="sm" @click="toggle(f)">
                            <component :is="f.subscribed ? BellOff : Bell" class="size-4" />
                            {{ f.subscribed ? $t('Se désabonner') : $t("S'abonner") }}
                        </Button>
                    </div>
                </li>
                <li v-if="!feeds.length" class="sm:col-span-2">
                    <button
                        class="w-full rounded-2xl border border-dashed p-8 text-sm text-muted-foreground hover:bg-muted/40"
                        @click="creating = true"
                    >
                        {{ $t("Créez le premier fil d'actualité") }}
                    </button>
                </li>
            </ul>
        </div>

        <FeedFormDialog v-model:open="creating" :colors="colors" />
    </AppLayout>
</template>
