<script setup lang="ts">
import { isOverdue, shortDate } from '@/lib/labels';
import type { Section, Task } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { ListTree } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PriorityBadge from './PriorityBadge.vue';
import TaskCheck from './TaskCheck.vue';
import UserAvatar from './UserAvatar.vue';

const props = defineProps<{ projectId: number; sections: Section[]; tasks: Task[]; canEdit: boolean }>();
const emit = defineEmits<{ open: [id: number] }>();

const hideDone = ref(false);
const visible = computed(() => (hideDone.value ? props.tasks.filter((t) => !t.completed) : props.tasks));
const forSection = (id: number) => visible.value.filter((t) => t.section_id === id);

const form = useForm({ title: '', section_id: 0 });
function add(sectionId: number, event: Event) {
    const input = event.target as HTMLInputElement;
    form.title = input.value;
    form.section_id = sectionId;
    if (!form.title.trim()) return;
    form.post(route('tasks.store', props.projectId), { preserveScroll: true, preserveState: true, onSuccess: () => (input.value = '') });
}
</script>

<template>
    <div class="p-6">
        <div class="mb-3 flex justify-end">
            <label class="flex items-center gap-2 text-sm text-muted-foreground">
                <input v-model="hideDone" type="checkbox" class="rounded" /> Masquer les tâches terminées
            </label>
        </div>
        <div class="overflow-hidden rounded-lg border">
            <div class="grid grid-cols-[1fr_160px_90px_90px_90px] gap-2 border-b bg-muted/50 px-4 py-2 text-xs font-medium text-muted-foreground">
                <span>Tâche</span><span>Responsable</span><span>Début</span><span>Échéance</span><span>Priorité</span>
            </div>
            <section v-for="section in sections" :key="section.id">
                <h3 class="border-b px-4 pb-1 pt-4 text-sm font-semibold">
                    {{ section.name }} <span class="font-normal text-muted-foreground">{{ forSection(section.id).length }}</span>
                </h3>
                <div
                    v-for="task in forSection(section.id)"
                    :key="task.id"
                    class="grid cursor-pointer grid-cols-[1fr_160px_90px_90px_90px] items-center gap-2 border-b px-4 py-2 text-sm hover:bg-muted/40"
                    @click="emit('open', task.id)"
                >
                    <span class="flex items-center gap-2">
                        <TaskCheck :task-id="task.id" :completed="task.completed" :disabled="!canEdit" />
                        <span :class="{ 'text-muted-foreground line-through': task.completed }">{{ task.title }}</span>
                        <span v-if="task.subtasks_count" class="flex items-center gap-0.5 text-xs text-muted-foreground"
                            ><ListTree class="size-3" />{{ task.subtasks_count }}</span
                        >
                    </span>
                    <span class="flex items-center gap-1.5 truncate text-xs"><UserAvatar :user="task.assignee" /> {{ task.assignee?.name }}</span>
                    <span class="text-xs">{{ shortDate(task.start_date) }}</span>
                    <span class="text-xs" :class="{ 'font-medium text-rose-600': isOverdue(task.due_date, task.completed) }">{{
                        shortDate(task.due_date)
                    }}</span>
                    <span><PriorityBadge :priority="task.priority" /></span>
                </div>
                <input
                    v-if="canEdit"
                    placeholder="+ Ajouter une tâche"
                    class="w-full border-b bg-transparent px-4 py-1.5 pl-10 text-sm outline-none placeholder:text-muted-foreground"
                    @keydown.enter.prevent="add(section.id, $event)"
                />
            </section>
        </div>
    </div>
</template>
