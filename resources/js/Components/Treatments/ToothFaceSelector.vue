<script setup>
import { ref, computed, watch } from 'vue'
import { ALL_FACES, FACE_LABELS, getFaceLayout } from '@/Components/Prontuario/Odontogram/toothFaces.js'
import PermanentTeeth from '@/Components/Prontuario/Odontogram/PermanentTeeth.vue'
import DeciduousTeeth from '@/Components/Prontuario/Odontogram/DeciduousTeeth.vue'

const props = defineProps({
    arch:      { type: String, default: 'permanent' }, // permanent | deciduous — sugestão inicial de aba
    teeth:     { type: Array,  default: () => [] },
    faces:     { type: Array,  default: () => [] },
    // Múltiplos dentes só faz sentido ao CRIAR um tratamento (gera uma linha
    // por dente — ver PatientTreatmentController::store()). Editar continua
    // sendo sempre um dente só, porque um tratamento já salvo é uma linha só;
    // clicar em outro dente SUBSTITUI a seleção em vez de acumular.
    multiple:  { type: Boolean, default: true },
    // Opcional: colore dentes com tratamento já existente no mini-odontograma,
    // pro mesmo contexto visual da aba Tratamentos (ver PatientTreatment::groupedByTooth()).
    treatmentsByTooth: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:teeth', 'update:faces'])

const isDeciduousTooth = (t) => !!t && parseInt(String(t).charAt(0), 10) >= 5

// Aba inicial: se já houver um dente decíduo selecionado (modo edição),
// abre direto em Decíduos — senão respeita a prop `arch` (default permanent).
const pickerArch = ref(isDeciduousTooth(props.teeth[0]) ? 'deciduous' : props.arch)

watch(() => props.teeth, (teeth) => {
    if (teeth.length) pickerArch.value = isDeciduousTooth(teeth[0]) ? 'deciduous' : 'permanent'
})

const hoveredTooth = ref(null)

const onToothClick = (t) => {
    if (props.multiple) {
        const set = new Set(props.teeth)
        set.has(t) ? set.delete(t) : set.add(t)
        emit('update:teeth', Array.from(set))
    } else {
        // Seleção única (edição): clicar de novo no mesmo dente desmarca;
        // clicar em outro SUBSTITUI, nunca acumula.
        emit('update:teeth', props.teeth[0] === t ? [] : [t])
    }
}

// Orientação da cruz de faces é calculada a partir do PRIMEIRO dente
// selecionado — os códigos M/D/V/L/O são abstratos e cada dente já é
// reorientado individualmente na hora de aplicar/pintar (ver toothFaces.js),
// então isso só afeta qual posição visual mostra qual letra aqui no seletor,
// não o dado gravado.
const layout = computed(() => props.teeth.length ? getFaceLayout(props.teeth[0]) : null)

const toggleFace = (face) => {
    const set = new Set(props.faces)
    set.has(face) ? set.delete(face) : set.add(face)
    emit('update:faces', Array.from(set))
}

const selectAllFaces = () => emit('update:faces', [...ALL_FACES])

const isActive = (face) => props.faces.includes(face)

const faceButtonLabel = (face) => face === 'O' ? 'O/I' : (face === 'V' ? 'V' : (face === 'L' ? 'L/P' : face))
</script>

<template>
<div class="space-y-3">
    <div>
        <div class="flex items-center justify-between mb-1.5">
            <label class="text-xs font-semibold text-slate-600">
                Dente <span v-if="multiple" class="font-normal text-slate-400">— pode marcar mais de um</span>
            </label>
            <button v-if="teeth.length" type="button" @click="emit('update:teeth', [])"
                    class="text-[11px] font-medium text-slate-400 hover:text-slate-600 transition-colors">
                Sem dente específico
            </button>
        </div>

        <div class="flex gap-1 mb-2 bg-slate-100 p-1 rounded-xl w-fit">
            <button type="button" @click="pickerArch = 'permanent'"
                    class="px-3 py-1 rounded-lg text-[11px] font-semibold transition-all duration-150"
                    :class="pickerArch === 'permanent' ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                Permanentes
            </button>
            <button type="button" @click="pickerArch = 'deciduous'"
                    class="px-3 py-1 rounded-lg text-[11px] font-semibold transition-all duration-150"
                    :class="pickerArch === 'deciduous' ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                Decíduos
            </button>
        </div>

        <div class="border border-slate-200 rounded-xl p-2 bg-slate-50/50">
            <PermanentTeeth v-if="pickerArch === 'permanent'"
                :teeth-data="{}"
                :treatments-by-tooth="treatmentsByTooth"
                :selected-tooth="teeth"
                :hovered-tooth="hoveredTooth"
                @tooth:click="onToothClick"
                @tooth:hover="(t) => hoveredTooth = t"
                @tooth:leave="() => hoveredTooth = null" />
            <DeciduousTeeth v-else
                :teeth-data="{}"
                :treatments-by-tooth="treatmentsByTooth"
                :selected-tooth="teeth"
                :hovered-tooth="hoveredTooth"
                @tooth:click="onToothClick"
                @tooth:hover="(t) => hoveredTooth = t"
                @tooth:leave="() => hoveredTooth = null" />
        </div>
        <p v-if="teeth.length === 1" class="text-center text-[11px] font-semibold text-teal-700 mt-1.5">Dente {{ teeth[0] }} selecionado</p>
        <p v-else-if="teeth.length > 1" class="text-center text-[11px] font-semibold text-teal-700 mt-1.5">
            {{ teeth.length }} dentes selecionados: {{ teeth.join(', ') }}
        </p>
    </div>

    <div v-if="teeth.length && layout">
        <div class="flex items-center justify-between mb-1.5">
            <label class="text-xs font-semibold text-slate-600">
                Face(s) <span class="font-normal text-slate-400">— opcional, selecione uma ou mais</span>
            </label>
            <button type="button" @click="selectAllFaces"
                    class="text-[11px] font-semibold text-teal-600 hover:text-teal-800 transition-colors">
                Todas as faces
            </button>
        </div>
        <div class="grid grid-cols-3 gap-1 w-36 mx-auto select-none" style="aspect-ratio: 1">
            <div></div>
            <button type="button" @click="toggleFace(layout.top)"
                    class="rounded-lg border text-[11px] font-bold flex items-center justify-center transition-colors"
                    :class="isActive(layout.top) ? 'bg-teal-600 text-white border-teal-600' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'"
                    :title="FACE_LABELS[layout.top]">{{ faceButtonLabel(layout.top) }}</button>
            <div></div>

            <button type="button" @click="toggleFace(layout.left)"
                    class="rounded-lg border text-[11px] font-bold flex items-center justify-center transition-colors"
                    :class="isActive(layout.left) ? 'bg-teal-600 text-white border-teal-600' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'"
                    :title="FACE_LABELS[layout.left]">{{ faceButtonLabel(layout.left) }}</button>
            <button type="button" @click="toggleFace(layout.center)"
                    class="rounded-lg border text-[11px] font-bold flex items-center justify-center transition-colors"
                    :class="isActive(layout.center) ? 'bg-teal-600 text-white border-teal-600' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'"
                    :title="FACE_LABELS[layout.center]">{{ faceButtonLabel(layout.center) }}</button>
            <button type="button" @click="toggleFace(layout.right)"
                    class="rounded-lg border text-[11px] font-bold flex items-center justify-center transition-colors"
                    :class="isActive(layout.right) ? 'bg-teal-600 text-white border-teal-600' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'"
                    :title="FACE_LABELS[layout.right]">{{ faceButtonLabel(layout.right) }}</button>

            <div></div>
            <button type="button" @click="toggleFace(layout.bottom)"
                    class="rounded-lg border text-[11px] font-bold flex items-center justify-center transition-colors"
                    :class="isActive(layout.bottom) ? 'bg-teal-600 text-white border-teal-600' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'"
                    :title="FACE_LABELS[layout.bottom]">{{ faceButtonLabel(layout.bottom) }}</button>
            <div></div>
        </div>
        <p v-if="faces.length" class="text-center text-[11px] text-slate-400 mt-1.5">
            {{ faces.map(f => FACE_LABELS[f]).join(' · ') }}
        </p>
    </div>
</div>
</template>
