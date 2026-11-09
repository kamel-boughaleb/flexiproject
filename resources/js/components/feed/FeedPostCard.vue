<script setup lang="ts">
import RichContent from '@/components/editor/RichContent.vue';
import TaskAttachments from '@/components/project/TaskAttachments.vue';
import UserAvatar from '@/components/project/UserAvatar.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { t } from '@/lib/i18n';
import { longDate } from '@/lib/labels';
import type { FeedPost } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { Ellipsis, Pencil, Trash2 } from 'lucide-vue-next';

/** Une actualité : auteur, date, titre, texte riche et pièces jointes. */
const props = defineProps<{ post: FeedPost; link?: boolean }>();
const emit = defineEmits<{ edit: [post: FeedPost] }>();

function destroy() {
    if (confirm(t('Supprimer l’actualité « :title » ?', { title: props.post.title }))) {
        router.delete(route('posts.destroy', props.post.id), { preserveScroll: true });
    }
}
</script>

<template>
    <article :id="`post-${post.id}`" class="rounded-2xl border border-app-border bg-background p-5 shadow-soft sm:p-6">
        <header class="flex items-center gap-3">
            <UserAvatar :user="post.user" size="md" />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold">{{ post.user?.name ?? $t('Ancien collaborateur') }}</p>
                <p class="text-xs text-ink-soft first-letter:uppercase">{{ longDate(post.published_on) }}</p>
            </div>
            <DropdownMenu v-if="post.can_manage">
                <DropdownMenuTrigger
                    class="flex size-8 items-center justify-center rounded-icon text-ink-soft hover:bg-app-hover hover:text-ink"
                    :aria-label="$t('Actions')"
                >
                    <Ellipsis class="size-4" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-44 rounded-2xl p-1.5">
                    <DropdownMenuItem class="rounded-icon font-semibold" @select="emit('edit', post)">
                        <Pencil class="size-4" /> {{ $t('Modifier') }}
                    </DropdownMenuItem>
                    <DropdownMenuItem class="rounded-icon font-semibold text-brand-active" @select="destroy">
                        <Trash2 class="size-4" /> {{ $t('Supprimer') }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </header>

        <h2 class="mt-4 text-xl font-bold">
            <Link v-if="link" :href="route('posts.show', [post.feed_id, post.id])" class="hover:underline">{{ post.title }}</Link>
            <template v-else>{{ post.title }}</template>
        </h2>
        <RichContent v-if="post.body" :html="post.body" class="mt-3" />

        <TaskAttachments v-if="post.attachments.length" class="mt-5" :attachments="post.attachments" :can-edit="false" :can-manage="false" />
    </article>
</template>
