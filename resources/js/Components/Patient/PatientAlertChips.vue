<script setup>
import { onMounted, ref } from 'vue'
import { notePriorityStyle, worstNotePriority } from '@/lib/patientNotePriority.js'

const props = defineProps({
    anamnesisAlerts: { type: Array, default: () => [] },
    noteAlerts: { type: Array, default: () => [] },
})

const hovered = ref(null)

// Chama atenção por ~2,5s quando a página abre, depois fica estático — não
// pisca indefinidamente (fadiga visual, distração, ignorado depois de dias).
const justOpened = ref(true)
onMounted(() => {
    setTimeout(() => { justOpened.value = false }, 2500)
})

const noteCount = () => props.noteAlerts.length
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 mt-3">
    <template v-for="alert in anamnesisAlerts" :key="'a-' + alert.id">
            <div class="relative"
                 @mouseenter="hovered = 'a-' + alert.id"
                 @mouseleave="hovered = null">
                <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800">
                    {{ alert.label }}
                </span>
                <div v-if="hovered === 'a-' + alert.id"
                     class="absolute left-0 top-full z-30 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-3 text-xs shadow-lg">
                    <p class="font-semibold text-slate-800 mb-2">{{ alert.label }}</p>
                    <dl class="space-y-1 text-slate-600">
                        <div><dt class="text-slate-400 inline">Origem: </dt>{{ alert.origin }}</div>
                        <div><dt class="text-slate-400 inline">Pergunta: </dt>{{ alert.question }}</div>
                        <div><dt class="text-slate-400 inline">Resposta: </dt>{{ alert.answer }}</div>
                        <div><dt class="text-slate-400 inline">Profissional: </dt>{{ alert.professional || '—' }}</div>
                        <div><dt class="text-slate-400 inline">Data: </dt>{{ alert.date }} {{ alert.time }}</div>
                    </dl>
                </div>
            </div>
        </template>

        <template v-if="noteCount() === 1">
            <div class="relative"
                 @mouseenter="hovered = 'n-single'"
                 @mouseleave="hovered = null">
                <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold"
                      :class="[notePriorityStyle(noteAlerts[0].priority).badgeClass, justOpened && 'animate-pulse']">
                    {{ notePriorityStyle(noteAlerts[0].priority).emoji }} ALERTA IMPORTANTE
                </span>
                <Transition
                    enter-active-class="transition-opacity duration-150"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition-opacity duration-100"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div v-if="hovered === 'n-single'"
                         class="absolute left-0 top-full z-50 mt-2 w-80 rounded-xl border border-slate-200 bg-white p-4 text-xs shadow-xl">
                        <p class="font-semibold text-red-700 mb-2 flex items-center gap-1.5">
                            {{ notePriorityStyle(noteAlerts[0].priority).emoji }} {{ noteAlerts[0].title }}
                        </p>
                        <p class="text-slate-600 whitespace-pre-wrap leading-relaxed">{{ noteAlerts[0].description }}</p>
                        <div v-if="noteAlerts[0].tags?.length" class="flex flex-wrap gap-1 mt-2">
                            <span v-for="tag in noteAlerts[0].tags" :key="tag.id"
                                  class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                                  :style="{ backgroundColor: tag.color + '20', color: tag.color }">
                                {{ tag.name }}
                            </span>
                        </div>
                        <p v-if="noteAlerts[0].is_pinned" class="text-slate-500 mt-2 flex gap-3">
                            <span>📌 Fixada</span>
                        </p>
                        <p class="text-slate-400 mt-3 pt-2 border-t border-slate-100">
                            {{ noteAlerts[0].author }} · {{ noteAlerts[0].date }} {{ noteAlerts[0].time }}
                            <template v-if="noteAlerts[0].edited"> · editado em {{ noteAlerts[0].updated_date }} {{ noteAlerts[0].updated_time }}</template>
                        </p>
                    </div>
                </Transition>
            </div>
        </template>

        <template v-else-if="noteCount() > 1">
            <div class="relative"
                 @mouseenter="hovered = 'n-multi'"
                 @mouseleave="hovered = null">
                <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold"
                      :class="[notePriorityStyle(worstNotePriority(noteAlerts)).badgeClass, justOpened && 'animate-pulse']">
                    {{ notePriorityStyle(worstNotePriority(noteAlerts)).emoji }} {{ noteCount() }} ALERTAS IMPORTANTES
                </span>
                <Transition
                    enter-active-class="transition-opacity duration-150"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition-opacity duration-100"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div v-if="hovered === 'n-multi'"
                         class="absolute left-0 top-full z-50 mt-2 w-80 rounded-xl border border-slate-200 bg-white p-4 text-xs shadow-xl">
                        <p class="font-semibold text-slate-700 mb-3">Alertas importantes</p>
                        <ul class="space-y-2.5">
                            <li v-for="note in noteAlerts" :key="note.id"
                                class="flex items-start gap-2 border-b border-slate-100 pb-2.5 last:border-0 last:pb-0">
                                <span class="shrink-0 mt-0.5">{{ notePriorityStyle(note.priority).emoji }}</span>
                                <div>
                                    <p class="font-semibold text-slate-800 flex items-center gap-1">
                                        {{ note.title }}
                                        <span v-if="note.is_pinned" title="Fixada">📌</span>
                                    </p>
                                    <p class="text-slate-500 mt-0.5 line-clamp-2 leading-relaxed">{{ note.description }}</p>
                                    <div v-if="note.tags?.length" class="flex flex-wrap gap-1 mt-1">
                                        <span v-for="tag in note.tags" :key="tag.id"
                                              class="rounded-full px-1.5 py-0.5 text-[9px] font-medium"
                                              :style="{ backgroundColor: tag.color + '20', color: tag.color }">
                                            {{ tag.name }}
                                        </span>
                                    </div>
                                    <p class="text-slate-400 mt-1">
                                        {{ note.author }} · {{ note.date }}
                                        <template v-if="note.edited"> · editado em {{ note.updated_date }}</template>
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </Transition>
            </div>
        </template>
    </div>
</template>
