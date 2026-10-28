<script setup lang="ts">
import { toDate } from '@/lib/labels';
import type { Project, Task } from '@/types';
import {
    addMonths,
    eachDayOfInterval,
    endOfMonth,
    endOfWeek,
    format,
    isSameDay,
    isSameMonth,
    isToday,
    isWithinInterval,
    startOfMonth,
    startOfWeek,
} from 'date-fns';
import { fr } from 'date-fns/locale';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ project: Project; tasks: Task[] }>();
const emit = defineEmits<{ open: [id: number] }>();

const WEEKDAYS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
const current = ref(startOfMonth(new Date()));
const days = computed(() =>
    eachDayOfInterval({
        start: startOfWeek(startOfMonth(current.value), { weekStartsOn: 1 }),
        end: endOfWeek(endOfMonth(current.value), { weekStartsOn: 1 }),
    }),
);

/** Une tâche apparaît sur chaque jour entre sa date de début et son échéance. */
function tasksOn(day: Date) {
    return props.tasks.filter((t) => {
        const start = toDate(t.start_date) ?? toDate(t.due_date);
        const end = toDate(t.due_date) ?? start;
        if (!start || !end) return false;
        return start <= end ? isWithinInterval(day, { start, end }) : isSameDay(day, end);
    });
}
</script>

<template>
    <div class="flex h-full flex-col p-6">
        <div class="mb-3 flex items-center gap-2">
            <button class="rounded p-1.5 hover:bg-muted" aria-label="Mois précédent" @click="current = addMonths(current, -1)">
                <ChevronLeft class="size-4" />
            </button>
            <h2 class="w-44 text-center text-lg font-semibold capitalize">{{ format(current, 'MMMM yyyy', { locale: fr }) }}</h2>
            <button class="rounded p-1.5 hover:bg-muted" aria-label="Mois suivant" @click="current = addMonths(current, 1)">
                <ChevronRight class="size-4" />
            </button>
            <button class="rounded px-2 py-1 text-xs hover:bg-muted" @click="current = startOfMonth(new Date())">Aujourd'hui</button>
        </div>
        <div
            class="grid flex-1 grid-cols-7 overflow-hidden rounded-lg border"
            :style="{ gridTemplateRows: `auto repeat(${days.length / 7}, minmax(110px, 1fr))` }"
        >
            <div v-for="d in WEEKDAYS" :key="d" class="border-b bg-muted/50 px-2 py-1.5 text-xs font-medium text-muted-foreground">{{ d }}</div>
            <div
                v-for="day in days"
                :key="day.toISOString()"
                class="border-b border-r p-1.5"
                :class="{ 'bg-muted/30 text-muted-foreground': !isSameMonth(day, current) }"
            >
                <div
                    class="mb-1 inline-flex size-6 items-center justify-center rounded-full text-xs"
                    :class="{ 'bg-orange-500 font-semibold text-white': isToday(day) }"
                >
                    {{ format(day, 'd') }}
                </div>
                <div class="space-y-1">
                    <button
                        v-for="t in tasksOn(day).slice(0, 3)"
                        :key="t.id"
                        :title="t.title"
                        class="block w-full truncate rounded px-1.5 py-0.5 text-left text-xs text-white"
                        :class="{ 'line-through opacity-50': t.completed }"
                        :style="{ backgroundColor: t.assignee?.color ?? project.color }"
                        @click="emit('open', t.id)"
                    >
                        {{ t.title }}
                    </button>
                    <div v-if="tasksOn(day).length > 3" class="px-1.5 text-xs text-muted-foreground">+ {{ tasksOn(day).length - 3 }} autre(s)</div>
                </div>
            </div>
        </div>
    </div>
</template>
