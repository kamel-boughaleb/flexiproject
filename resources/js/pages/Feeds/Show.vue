<script setup lang="ts">
import FeedFormDialog from '@/components/feed/FeedFormDialog.vue';
import FeedPostCard from '@/components/feed/FeedPostCard.vue';
import PostFormDialog from '@/components/feed/PostFormDialog.vue';
import ProjectAvatar from '@/components/project/ProjectAvatar.vue';
import UserAvatar from '@/components/project/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/AppLayout.vue';
import { t } from '@/lib/i18n';
import type { Feed, FeedPost, MiniUser } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Bell, BellOff, Ellipsis, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Paginated<T> = { data: T[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null };
const props = defineProps<{
    feed: Feed & { owner: MiniUser | null; subscribers: MiniUser[]; subscribed: boolean; can_manage: boolean };
    posts: Paginated<FeedPost>;
    colors: string[];
}>();

const toggle = () =>
    props.feed.subscribed
        ? router.delete(route('feeds.unsubscribe', props.feed.id), { preserveScroll: true })
        : router.post(route('feeds.subscribe', props.feed.id), {}, { preserveScroll: true });

const editingFeed = ref(false);
const destroyFeed = () =>
    confirm(t('Supprimer le fil « :name » et toutes ses actualités ?', { name: props.feed.name })) &&
    router.delete(route('feeds.destroy', props.feed.id));

const composing = ref(false);
const editingId = ref<number | null>(null);
const editingPost = computed(() => props.posts.data.find((p) => p.id === editingId.value) ?? null);
const editing = computed({ get: () => editingId.value !== null, set: (v) => !v && (editingId.value = null) });
</script>

<template>
    <Head :title="feed.name" />
    <AppLayout
        :breadcrumbs="[
            { title: $t('Fils d\'actualité'), href: route('feeds.index') },
            { title: feed.name, href: route('feeds.show', feed.id) },
        ]"
    >
        <div class="w-full max-w-3xl p-6">
            <div class="flex flex-wrap items-start gap-4">
                <ProjectAvatar :project="feed" size="lg" />
                <div class="min-w-0 flex-1">
                    <h1 class="text-2xl font-semibold">{{ feed.name }}</h1>
                    <p v-if="feed.description" class="mt-1 whitespace-pre-line text-sm text-muted-foreground">{{ feed.description }}</p>
                    <div class="mt-2 flex items-center gap-2 text-xs text-ink-soft">
                        <span class="flex -space-x-1.5">
                            <span v-for="s in feed.subscribers.slice(0, 6)" :key="s.id" class="rounded-full ring-2 ring-background" :title="s.name">
                                <UserAvatar :user="s" size="xs" />
                            </span>
                        </span>
                        {{ $tc(':count abonné|:count abonnés', feed.subscribers.length) }}
                        <template v-if="feed.owner"> · {{ $t('créé par :name', { name: feed.owner.name }) }}</template>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button :variant="feed.subscribed ? 'outline' : 'default'" @click="toggle">
                        <component :is="feed.subscribed ? BellOff : Bell" class="size-4" />
                        {{ feed.subscribed ? $t('Se désabonner') : $t("S'abonner") }}
                    </Button>
                    <DropdownMenu v-if="feed.can_manage">
                        <DropdownMenuTrigger
                            class="flex size-9 items-center justify-center rounded-icon text-ink-soft hover:bg-app-hover hover:text-ink"
                            :aria-label="$t('Actions')"
                        >
                            <Ellipsis class="size-4" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-48 rounded-2xl p-1.5">
                            <DropdownMenuItem class="rounded-icon font-semibold" @select="editingFeed = true">
                                <Pencil class="size-4" /> {{ $t('Modifier le fil') }}
                            </DropdownMenuItem>
                            <DropdownMenuItem class="rounded-icon font-semibold text-brand-active" @select="destroyFeed">
                                <Trash2 class="size-4" /> {{ $t('Supprimer le fil') }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <button
                class="mt-6 flex w-full items-center gap-3 rounded-2xl border border-dashed border-app-border-strong px-5 py-4 text-left text-sm font-bold text-ink-soft transition hover:border-app-muted hover:text-ink"
                @click="composing = true"
            >
                <Plus class="size-5" /> {{ $t('Publier une actualité') }}
            </button>

            <div class="mt-6 space-y-6">
                <FeedPostCard v-for="p in posts.data" :key="p.id" :post="p" link @edit="editingId = $event.id" />
                <p v-if="!posts.data.length" class="py-10 text-center text-sm text-muted-foreground">{{ $t('Aucune actualité pour le moment.') }}</p>
            </div>

            <nav v-if="posts.last_page > 1" class="mt-6 flex items-center justify-between text-sm font-semibold">
                <Link v-if="posts.prev_page_url" :href="posts.prev_page_url" class="hover:underline">← {{ $t('Plus récentes') }}</Link>
                <span v-else />
                <span class="text-ink-soft">{{ $t('Page :page sur :count', { page: posts.current_page, count: posts.last_page }) }}</span>
                <Link v-if="posts.next_page_url" :href="posts.next_page_url" class="hover:underline">{{ $t('Plus anciennes') }} →</Link>
                <span v-else />
            </nav>
        </div>

        <PostFormDialog v-model:open="composing" :feed-id="feed.id" />
        <PostFormDialog v-model:open="editing" :feed-id="feed.id" :post="editingPost" />
        <FeedFormDialog v-if="feed.can_manage" v-model:open="editingFeed" :feed="feed" :colors="colors" />
    </AppLayout>
</template>
