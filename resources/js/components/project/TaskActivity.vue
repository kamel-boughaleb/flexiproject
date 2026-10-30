<script setup lang="ts">
import RichContent from '@/components/editor/RichContent.vue';
import SegmentedControl from '@/components/design/SegmentedControl.vue';
import { dateTime } from '@/lib/labels';
import type { TaskDetail } from '@/types';
import { computed, ref } from 'vue';
import UserAvatar from './UserAvatar.vue';

/** Fil d'activité : commentaires et historique fusionnés dans l'ordre chronologique, filtrables. */
const props = defineProps<{ task: TaskDetail }>();

type Filter = 'comments' | 'all' | 'history';
// Par défaut, seuls les commentaires : l'historique reste à un clic.
const filter = ref<Filter>('comments');

const items = computed(() => {
    const comments = props.task.comments.map((c) => ({ kind: 'comment' as const, key: `c${c.id}`, at: c.created_at, comment: c }));
    const events = props.task.activities.map((a) => ({ kind: 'event' as const, key: `a${a.id}`, at: a.created_at, event: a }));
    const list = filter.value === 'comments' ? comments : filter.value === 'history' ? events : [...comments, ...events];
    return list.sort((a, b) => a.at.localeCompare(b.at));
});
</script>

<template>
    <section aria-labelledby="activity-title">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 id="activity-title" class="text-base font-bold">Activité</h3>
            <SegmentedControl
                v-model="filter"
                label="Filtrer l'activité"
                size="sm"
                :options="[
                    { value: 'comments', label: 'Commentaires', count: task.comments.length },
                    { value: 'all', label: 'Tout' },
                    { value: 'history', label: 'Historique' },
                ]"
            />
        </div>
        <ol class="space-y-3">
            <li v-for="item in items" :key="item.key">
                <div v-if="item.kind === 'comment'" class="flex gap-2.5">
                    <UserAvatar :user="item.comment.user" size="md" />
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 text-xs text-ink-soft">
                            <strong class="text-[13px] text-ink">{{ item.comment.user.name }}</strong> · {{ dateTime(item.comment.created_at) }}
                        </div>
                        <div class="rounded-2xl rounded-tl-md bg-app-hover px-3.5 py-2.5">
                            <RichContent :html="item.comment.body" />
                        </div>
                    </div>
                </div>
                <div v-else class="flex items-baseline gap-2.5 pl-3 text-xs text-ink-soft">
                    <span class="size-2 shrink-0 translate-y-[-1px] rounded-full bg-ink-medium" aria-hidden="true" />
                    <span>
                        <strong v-if="item.event.user" class="text-app-strong">{{ item.event.user.name }}</strong
                        ><strong v-else class="text-app-strong">Règle automatique</strong> {{ item.event.message }} ·
                        {{ dateTime(item.event.created_at) }}
                    </span>
                </div>
            </li>
            <li v-if="!items.length" class="text-sm text-ink-soft">
                {{ filter === 'history' ? 'Aucun historique pour le moment.' : 'Aucun commentaire pour le moment.' }}
            </li>
        </ol>
    </section>
</template>
