<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import Color from '@tiptap/extension-color';
import { TextStyle } from '@tiptap/extension-text-style';
import Placeholder from '@tiptap/extension-placeholder';
import Image from '@tiptap/extension-image';
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Escreva sua mensagem...',
    },
    minHeight: {
        type: String,
        default: '200px',
    },
    primaryColor: {
        type: String,
        default: '#3B82F6',
    },
});

const emit = defineEmits(['update:modelValue']);

const showLinkInput = ref(false);
const linkUrl = ref('');
const showColorPicker = ref(false);
const toolbarRef = ref(null);

// Remove botões da toolbar da ordem de TAB para que TAB vá direto ao conteúdo
onMounted(() => {
    if (toolbarRef.value) {
        toolbarRef.value.querySelectorAll('button, input').forEach(el => {
            el.setAttribute('tabindex', '-1');
        });
    }
});

defineExpose({
    focus: () => editor.value?.commands.focus(),
});

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: { levels: [1, 2, 3] },
        }),
        Link.configure({
            openOnClick: false,
            HTMLAttributes: { target: '_blank', rel: 'noopener noreferrer' },
        }),
        Underline,
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        Color,
        TextStyle,
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
        Image.configure({
            inline: true,
        }),
    ],
    onUpdate({ editor }) {
        emit('update:modelValue', editor.getHTML());
    },
});

watch(() => props.modelValue, (newValue) => {
    if (editor.value && editor.value.getHTML() !== newValue) {
        editor.value.commands.setContent(newValue, false);
    }
});

onBeforeUnmount(() => {
    editor.value?.destroy();
});

const toggleLink = () => {
    if (editor.value?.isActive('link')) {
        editor.value.chain().focus().unsetLink().run();
        return;
    }
    linkUrl.value = '';
    showLinkInput.value = true;
};

const applyLink = () => {
    if (linkUrl.value) {
        let url = linkUrl.value;
        if (!/^https?:\/\//i.test(url)) {
            url = 'https://' + url;
        }
        editor.value?.chain().focus().setLink({ href: url }).run();
    }
    showLinkInput.value = false;
    linkUrl.value = '';
};

const cancelLink = () => {
    showLinkInput.value = false;
    linkUrl.value = '';
};

const setColor = (color) => {
    editor.value?.chain().focus().setColor(color).run();
    showColorPicker.value = false;
};

const colors = [
    '#000000', '#434343', '#666666', '#999999',
    '#DC2626', '#EA580C', '#D97706', '#65A30D',
    '#059669', '#0891B2', '#2563EB', '#7C3AED',
    '#DB2777',
];
</script>

<template>
    <div class="tiptap-editor border border-gray-300 rounded-xl overflow-hidden focus-within:ring-2 focus-within:border-transparent transition-shadow" :style="{ '--tw-ring-color': primaryColor + '40' }">
        <!-- Toolbar -->
        <div ref="toolbarRef" class="flex items-center flex-wrap gap-0.5 px-3 py-2 border-b bg-gray-50">
            <!-- Undo/Redo -->
            <button
                type="button"
                @click="editor?.chain().focus().undo().run()"
                :disabled="!editor?.can().undo()"
                class="p-1.5 rounded hover:bg-gray-200 disabled:opacity-30 transition-colors"
                title="Desfazer"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a5 5 0 015 5v0a5 5 0 01-5 5H7" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 6L3 10l4 4" />
                </svg>
            </button>
            <button
                type="button"
                @click="editor?.chain().focus().redo().run()"
                :disabled="!editor?.can().redo()"
                class="p-1.5 rounded hover:bg-gray-200 disabled:opacity-30 transition-colors"
                title="Refazer"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 10H11a5 5 0 00-5 5v0a5 5 0 005 5h6" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 6l4 4-4 4" />
                </svg>
            </button>

            <div class="w-px h-5 bg-gray-300 mx-1"></div>

            <!-- Formatação básica -->
            <button
                type="button"
                @click="editor?.chain().focus().toggleBold().run()"
                :class="{ 'bg-gray-200': editor?.isActive('bold') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors font-bold text-sm"
                title="Negrito"
            >B</button>
            <button
                type="button"
                @click="editor?.chain().focus().toggleItalic().run()"
                :class="{ 'bg-gray-200': editor?.isActive('italic') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors italic text-sm"
                title="Itálico"
            >I</button>
            <button
                type="button"
                @click="editor?.chain().focus().toggleUnderline().run()"
                :class="{ 'bg-gray-200': editor?.isActive('underline') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors underline text-sm"
                title="Sublinhado"
            >U</button>
            <button
                type="button"
                @click="editor?.chain().focus().toggleStrike().run()"
                :class="{ 'bg-gray-200': editor?.isActive('strike') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors line-through text-sm"
                title="Tachado"
            >S</button>

            <div class="w-px h-5 bg-gray-300 mx-1"></div>

            <!-- Cor de texto -->
            <div class="relative">
                <button
                    type="button"
                    @click="showColorPicker = !showColorPicker"
                    class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                    title="Cor do texto"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10M12 3l-4 14h8L12 3z" />
                    </svg>
                </button>
                <div
                    v-if="showColorPicker"
                    class="absolute top-full left-0 mt-1 p-2 bg-white rounded-lg shadow-lg border z-20 grid grid-cols-4 gap-1"
                >
                    <button
                        v-for="color in colors"
                        :key="color"
                        type="button"
                        @click="setColor(color)"
                        class="w-6 h-6 rounded border border-gray-200 hover:scale-110 transition-transform"
                        :style="{ backgroundColor: color }"
                    ></button>
                </div>
            </div>

            <div class="w-px h-5 bg-gray-300 mx-1"></div>

            <!-- Headings -->
            <button
                type="button"
                @click="editor?.chain().focus().toggleHeading({ level: 1 }).run()"
                :class="{ 'bg-gray-200': editor?.isActive('heading', { level: 1 }) }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors text-xs font-bold"
                title="Título 1"
            >H1</button>
            <button
                type="button"
                @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
                :class="{ 'bg-gray-200': editor?.isActive('heading', { level: 2 }) }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors text-xs font-bold"
                title="Título 2"
            >H2</button>

            <div class="w-px h-5 bg-gray-300 mx-1"></div>

            <!-- Listas -->
            <button
                type="button"
                @click="editor?.chain().focus().toggleBulletList().run()"
                :class="{ 'bg-gray-200': editor?.isActive('bulletList') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Lista com marcadores"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                </svg>
            </button>
            <button
                type="button"
                @click="editor?.chain().focus().toggleOrderedList().run()"
                :class="{ 'bg-gray-200': editor?.isActive('orderedList') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Lista numerada"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h10M7 16h10M3 8V6l2-1M3 12v0M3 18v-2l2 1" />
                </svg>
            </button>

            <div class="w-px h-5 bg-gray-300 mx-1"></div>

            <!-- Alinhamento -->
            <button
                type="button"
                @click="editor?.chain().focus().setTextAlign('left').run()"
                :class="{ 'bg-gray-200': editor?.isActive({ textAlign: 'left' }) }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Alinhar à esquerda"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M3 12h12M3 18h18" />
                </svg>
            </button>
            <button
                type="button"
                @click="editor?.chain().focus().setTextAlign('center').run()"
                :class="{ 'bg-gray-200': editor?.isActive({ textAlign: 'center' }) }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Centralizar"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M6 12h12M3 18h18" />
                </svg>
            </button>
            <button
                type="button"
                @click="editor?.chain().focus().setTextAlign('right').run()"
                :class="{ 'bg-gray-200': editor?.isActive({ textAlign: 'right' }) }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Alinhar à direita"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M9 12h12M3 18h18" />
                </svg>
            </button>

            <div class="w-px h-5 bg-gray-300 mx-1"></div>

            <!-- Citação -->
            <button
                type="button"
                @click="editor?.chain().focus().toggleBlockquote().run()"
                :class="{ 'bg-gray-200': editor?.isActive('blockquote') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Citação"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10.5h3a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H8A1.5 1.5 0 006.5 6v3A1.5 1.5 0 008 10.5zm0 0V14m5-3.5h3a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5h-3A1.5 1.5 0 0011.5 6v3a1.5 1.5 0 001.5 1.5zm0 0V14" />
                </svg>
            </button>

            <!-- Link -->
            <button
                type="button"
                @click="toggleLink"
                :class="{ 'bg-gray-200': editor?.isActive('link') }"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Link"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
            </button>

            <!-- Linha horizontal -->
            <button
                type="button"
                @click="editor?.chain().focus().setHorizontalRule().run()"
                class="p-1.5 rounded hover:bg-gray-200 transition-colors"
                title="Linha horizontal"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18" />
                </svg>
            </button>
        </div>

        <!-- Link input -->
        <div v-if="showLinkInput" class="flex items-center gap-2 px-3 py-2 border-b bg-blue-50">
            <input
                v-model="linkUrl"
                type="url"
                placeholder="https://exemplo.com"
                class="flex-1 text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1"
                :style="{ '--tw-ring-color': primaryColor }"
                @keyup.enter="applyLink"
                @keyup.escape="cancelLink"
            />
            <button
                type="button"
                @click="applyLink"
                class="px-3 py-1.5 text-white text-sm rounded-lg"
                :style="{ backgroundColor: primaryColor }"
            >OK</button>
            <button
                type="button"
                @click="cancelLink"
                class="px-3 py-1.5 text-gray-600 text-sm rounded-lg hover:bg-gray-200"
            >Cancelar</button>
        </div>

        <!-- Editor -->
        <EditorContent
            :editor="editor"
            class="prose prose-sm max-w-none px-4 py-3 focus:outline-none"
            :style="{ minHeight: minHeight }"
        />
    </div>
</template>

<style>
.tiptap-editor .tiptap {
    outline: none;
    min-height: inherit;
}
.tiptap-editor .tiptap p.is-editor-empty:first-child::before {
    color: #adb5bd;
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}
.tiptap-editor .tiptap blockquote {
    border-left: 3px solid #d1d5db;
    padding-left: 1rem;
    color: #6b7280;
}
.tiptap-editor .tiptap a {
    color: #2563eb;
    text-decoration: underline;
}
.tiptap-editor .tiptap img {
    max-width: 100%;
    height: auto;
}
</style>
