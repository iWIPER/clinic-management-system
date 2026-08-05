<script setup>
import { onBeforeUnmount, watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import TextAlign from '@tiptap/extension-text-align'
import Underline from '@tiptap/extension-underline'

const props = defineProps({
    modelValue: { type: String, default: '' },
    minHeight: { type: String, default: '440px' },
})
const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Underline,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none focus:outline-none px-5 py-4',
            style: `min-height: ${props.minHeight}`,
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML())
    },
})

watch(() => props.modelValue, (value) => {
    if (editor.value && value !== editor.value.getHTML()) {
        editor.value.commands.setContent(value || '', false)
    }
})

const insertPlaceholder = (key) => {
    editor.value?.chain().focus().insertContent(`%${key}%`).run()
}

const isActive = (name, attrs) => editor.value?.isActive(name, attrs) ?? false

defineExpose({ insertPlaceholder })

onBeforeUnmount(() => {
    editor.value?.destroy()
})
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
        <div v-if="editor" class="flex flex-wrap items-center gap-1 border-b border-slate-100 px-3 py-2 bg-slate-50/60">
            <button type="button" @click="editor.chain().focus().toggleBold().run()" class="toolbar-btn font-bold" :class="{ 'is-active': isActive('bold') }">B</button>
            <button type="button" @click="editor.chain().focus().toggleItalic().run()" class="toolbar-btn italic" :class="{ 'is-active': isActive('italic') }">I</button>
            <button type="button" @click="editor.chain().focus().toggleUnderline().run()" class="toolbar-btn underline" :class="{ 'is-active': isActive('underline') }">U</button>
            <span class="w-px h-4 bg-slate-200 mx-1" />
            <button type="button" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()" class="toolbar-btn" :class="{ 'is-active': isActive('heading', { level: 1 }) }">H1</button>
            <button type="button" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" class="toolbar-btn" :class="{ 'is-active': isActive('heading', { level: 2 }) }">H2</button>
            <button type="button" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()" class="toolbar-btn" :class="{ 'is-active': isActive('heading', { level: 3 }) }">H3</button>
            <span class="w-px h-4 bg-slate-200 mx-1" />
            <button type="button" @click="editor.chain().focus().toggleBulletList().run()" class="toolbar-btn" :class="{ 'is-active': isActive('bulletList') }">•</button>
            <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" class="toolbar-btn" :class="{ 'is-active': isActive('orderedList') }">1.</button>
            <span class="w-px h-4 bg-slate-200 mx-1" />
            <button type="button" @click="editor.chain().focus().setTextAlign('left').run()" class="toolbar-btn" :class="{ 'is-active': isActive({ textAlign: 'left' }) }">⯇</button>
            <button type="button" @click="editor.chain().focus().setTextAlign('center').run()" class="toolbar-btn" :class="{ 'is-active': isActive({ textAlign: 'center' }) }">≡</button>
            <button type="button" @click="editor.chain().focus().setTextAlign('right').run()" class="toolbar-btn" :class="{ 'is-active': isActive({ textAlign: 'right' }) }">⯈</button>
            <span class="w-px h-4 bg-slate-200 mx-1" />
            <button type="button" @click="editor.chain().focus().undo().run()" class="toolbar-btn">↺</button>
            <button type="button" @click="editor.chain().focus().redo().run()" class="toolbar-btn">↻</button>
        </div>

        <EditorContent :editor="editor" />
    </div>
</template>

<style scoped>
.toolbar-btn {
    width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
    border-radius: 6px; font-size: 12px; color: #64748b; transition: background-color .15s, color .15s;
}
.toolbar-btn:hover { background-color: #e2e8f0; }
.toolbar-btn.is-active { background-color: #ccfbf1; color: #0f766e; }
</style>
