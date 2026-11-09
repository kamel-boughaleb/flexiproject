<script setup lang="ts">
import FeedPostCard from '@/components/feed/FeedPostCard.vue';
import PostFormDialog from '@/components/feed/PostFormDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { FeedBadge, FeedPost } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{ feed: FeedBadge & { subscribed: boolean }; post: FeedPost }>();
const editing = ref(false);
</script>

<template>
    <Head :title="post.title" />
    <AppLayout
        :breadcrumbs="[
            { title: $t('Fils d\'actualité'), href: route('feeds.index') },
            { title: feed.name, href: route('feeds.show', feed.id) },
            { title: post.title, href: route('posts.show', [feed.id, post.id]) },
        ]"
    >
        <div class="w-full max-w-3xl p-6">
            <Link :href="route('feeds.show', feed.id)" class="text-sm font-semibold text-ink-soft hover:text-ink">
                ← {{ $t('Toutes les actualités de :feed', { feed: feed.name }) }}
            </Link>
            <FeedPostCard class="mt-4" :post="post" @edit="editing = true" />
        </div>
        <PostFormDialog v-model:open="editing" :feed-id="feed.id" :post="post" />
    </AppLayout>
</template>
