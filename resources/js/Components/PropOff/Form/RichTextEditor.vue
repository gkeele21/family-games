<template>
    <div class="rich-text-editor">
        <!-- Toolbar -->
        <div v-if="editor" class="border border-b-0 border-gray-300 rounded-t-md bg-gray-50 px-2 py-1 flex flex-wrap gap-1">
            <!-- Text formatting -->
            <button
                type="button"
                @click="editor.chain().focus().toggleBold().run()"
                :class="{ 'bg-primary text-white': editor.isActive('bold') }"
                class="p-2 rounded hover:bg-gray-200 transition-colors"
                title="Bold"
            >
                <i class="fa-solid fa-bold"></i>
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleItalic().run()"
                :class="{ 'bg-primary text-white': editor.isActive('italic') }"
                class="p-2 rounded hover:bg-gray-200 transition-colors"
                title="Italic"
            >
                <i class="fa-solid fa-italic"></i>
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleUnderline().run()"
                :class="{ 'bg-primary text-white': editor.isActive('underline') }"
                class="p-2 rounded hover:bg-gray-200 transition-colors"
                title="Underline"
            >
                <i class="fa-solid fa-underline"></i>
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleStrike().run()"
                :class="{ 'bg-primary text-white': editor.isActive('strike') }"
                class="p-2 rounded hover:bg-gray-200 transition-colors"
                title="Strikethrough"
            >
                <i class="fa-solid fa-strikethrough"></i>
            </button>

            <div class="w-px bg-gray-300 mx-1"></div>

            <!-- Text Color -->
            <div class="relative" ref="textColorDropdownRef">
                <button
                    type="button"
                    @click="showTextColorPicker = !showTextColorPicker"
                    class="p-2 rounded hover:bg-gray-200 transition-colors flex items-center gap-1"
                    title="Text Color"
                >
                    <i class="fa-solid fa-font"></i>
                    <span class="w-3 h-1 rounded-sm" :style="{ backgroundColor: currentTextColor || '#000000' }"></span>
                </button>
                <div
                    v-if="showTextColorPicker"
                    class="absolute top-full left-0 mt-1 bg-white border border-gray-300 rounded-md shadow-lg p-2 z-50"
                >
                    <div class="grid grid-cols-6 gap-1 mb-2">
                        <button
                            v-for="color in textColors"
                            :key="color"
                            type="button"
                            @click="setTextColor(color)"
                            class="w-6 h-6 rounded border border-gray-300 hover:scale-110 transition-transform"
                            :style="{ backgroundColor: color }"
                            :title="color"
                        ></button>
                    </div>
                    <div class="flex items-center gap-2 border-t border-gray-200 pt-2 mt-1">
                        <input
                            type="color"
                            :value="currentTextColor || '#000000'"
                            @input="setTextColor($event.target.value)"
                            class="w-8 h-6 cursor-pointer border border-gray-300 rounded"
                            title="Pick custom color"
                        />
                        <span class="text-xs text-gray-500">Custom</span>
                    </div>
                    <button
                        type="button"
                        @click="clearTextColor"
                        class="w-full text-xs text-gray-600 hover:text-gray-800 py-1 mt-1"
                    >
                        Remove color
                    </button>
                </div>
            </div>

            <!-- Highlight Color -->
            <div class="relative" ref="highlightDropdownRef">
                <button
                    type="button"
                    @click="showHighlightPicker = !showHighlightPicker"
                    class="p-2 rounded hover:bg-gray-200 transition-colors flex items-center gap-1"
                    title="Highlight Color"
                >
                    <i class="fa-solid fa-highlighter"></i>
                    <span class="w-3 h-1 rounded-sm" :style="{ backgroundColor: currentHighlightColor || '#ffff00' }"></span>
                </button>
                <div
                    v-if="showHighlightPicker"
                    class="absolute top-full left-0 mt-1 bg-white border border-gray-300 rounded-md shadow-lg p-2 z-50"
                >
                    <div class="grid grid-cols-6 gap-1 mb-2">
                        <button
                            v-for="color in highlightColors"
                            :key="color"
                            type="button"
                            @click="setHighlightColor(color)"
                            class="w-6 h-6 rounded border border-gray-300 hover:scale-110 transition-transform"
                            :style="{ backgroundColor: color }"
                            :title="color"
                        ></button>
                    </div>
                    <div class="flex items-center gap-2 border-t border-gray-200 pt-2 mt-1">
                        <input
                            type="color"
                            :value="currentHighlightColor || '#ffff00'"
                            @input="setHighlightColor($event.target.value)"
                            class="w-8 h-6 cursor-pointer border border-gray-300 rounded"
                            title="Pick custom color"
                        />
                        <span class="text-xs text-gray-500">Custom</span>
                    </div>
                    <button
                        type="button"
                        @click="clearHighlight"
                        class="w-full text-xs text-gray-600 hover:text-gray-800 py-1 mt-1"
                    >
                        Remove highlight
                    </button>
                </div>
            </div>

            <div class="w-px bg-gray-300 mx-1"></div>

            <!-- Headings -->
            <button
                type="button"
                @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
                :class="{ 'bg-primary text-white': editor.isActive('heading', { level: 1 }) }"
                class="p-2 rounded hover:bg-gray-200 transition-colors text-sm font-bold"
                title="Heading 1"
            >
                H1
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                :class="{ 'bg-primary text-white': editor.isActive('heading', { level: 2 }) }"
                class="p-2 rounded hover:bg-gray-200 transition-colors text-sm font-bold"
                title="Heading 2"
            >
                H2
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                :class="{ 'bg-primary text-white': editor.isActive('heading', { level: 3 }) }"
                class="p-2 rounded hover:bg-gray-200 transition-colors text-sm font-bold"
                title="Heading 3"
            >
                H3
            </button>

            <div class="w-px bg-gray-300 mx-1"></div>

            <!-- Lists -->
            <button
                type="button"
                @click="editor.chain().focus().toggleBulletList().run()"
                :class="{ 'bg-primary text-white': editor.isActive('bulletList') }"
                class="p-2 rounded hover:bg-gray-200 transition-colors"
                title="Bullet List"
            >
                <i class="fa-solid fa-list-ul"></i>
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleOrderedList().run()"
                :class="{ 'bg-primary text-white': editor.isActive('orderedList') }"
                class="p-2 rounded hover:bg-gray-200 transition-colors"
                title="Numbered List"
            >
                <i class="fa-solid fa-list-ol"></i>
            </button>

            <div class="w-px bg-gray-300 mx-1"></div>

            <!-- Text alignment -->
            <button
                type="button"
                @click="editor.chain().focus().setTextAlign('left').run()"
                :class="{ 'bg-primary text-white': editor.isActive({ textAlign: 'left' }) }"
                class="p-2 rounded hover:bg-gray-200 transition-colors"
                title="Align Left"
            >
                <i class="fa-solid fa-align-left"></i>
            </button>
            <button
                type="button"
                @click="editor.chain().focus().setTextAlign('center').run()"
                :class="{ 'bg-primary text-white': editor.isActive({ textAlign: 'center' }) }"
                class="p-2 rounded hover:bg-gray-200 transition-colors"
                title="Align Center"
            >
                <i class="fa-solid fa-align-center"></i>
            </button>
            <button
                type="button"
                @click="editor.chain().focus().setTextAlign('right').run()"
                :class="{ 'bg-primary text-white': editor.isActive({ textAlign: 'right' }) }"
                class="p-2 rounded hover:bg-gray-200 transition-colors"
                title="Align Right"
            >
                <i class="fa-solid fa-align-right"></i>
            </button>

            <div class="w-px bg-gray-300 mx-1"></div>

            <!-- Link -->
            <button
                type="button"
                @click="setLink"
                :class="{ 'bg-primary text-white': editor.isActive('link') }"
                class="p-2 rounded hover:bg-gray-200 transition-colors"
                title="Add Link"
            >
                <i class="fa-solid fa-link"></i>
            </button>
            <button
                type="button"
                v-if="editor.isActive('link')"
                @click="editor.chain().focus().unsetLink().run()"
                class="p-2 rounded hover:bg-gray-200 transition-colors"
                title="Remove Link"
            >
                <i class="fa-solid fa-link-slash"></i>
            </button>

            <!-- Image -->
            <button
                type="button"
                @click="addImage"
                class="p-2 rounded hover:bg-gray-200 transition-colors"
                title="Insert Image"
            >
                <i class="fa-solid fa-image"></i>
            </button>

            <div class="w-px bg-gray-300 mx-1"></div>

            <!-- Table -->
            <div class="relative" ref="tableDropdownRef">
                <button
                    type="button"
                    @click="showTableMenu = !showTableMenu"
                    :class="{ 'bg-primary text-white': editor.isActive('table') }"
                    class="p-2 rounded hover:bg-gray-200 transition-colors"
                    title="Table"
                >
                    <i class="fa-solid fa-table"></i>
                </button>
                <div
                    v-if="showTableMenu"
                    class="absolute top-full left-0 mt-1 bg-white border border-gray-300 rounded-md shadow-lg p-2 z-50 min-w-[160px]"
                >
                    <button
                        type="button"
                        @click="insertTable"
                        class="w-full text-left px-2 py-1 text-sm hover:bg-gray-100 rounded flex items-center gap-2"
                    >
                        <i class="fa-solid fa-plus w-4"></i> Insert Table
                    </button>
                    <template v-if="editor.isActive('table')">
                        <div class="border-t border-gray-200 my-1"></div>
                        <button
                            type="button"
                            @click="editor.chain().focus().addColumnBefore().run(); showTableMenu = false"
                            class="w-full text-left px-2 py-1 text-sm hover:bg-gray-100 rounded flex items-center gap-2"
                        >
                            <i class="fa-solid fa-arrow-left w-4"></i> Add Column Before
                        </button>
                        <button
                            type="button"
                            @click="editor.chain().focus().addColumnAfter().run(); showTableMenu = false"
                            class="w-full text-left px-2 py-1 text-sm hover:bg-gray-100 rounded flex items-center gap-2"
                        >
                            <i class="fa-solid fa-arrow-right w-4"></i> Add Column After
                        </button>
                        <button
                            type="button"
                            @click="editor.chain().focus().deleteColumn().run(); showTableMenu = false"
                            class="w-full text-left px-2 py-1 text-sm hover:bg-gray-100 rounded flex items-center gap-2 text-danger"
                        >
                            <i class="fa-solid fa-trash w-4"></i> Delete Column
                        </button>
                        <div class="border-t border-gray-200 my-1"></div>
                        <button
                            type="button"
                            @click="editor.chain().focus().addRowBefore().run(); showTableMenu = false"
                            class="w-full text-left px-2 py-1 text-sm hover:bg-gray-100 rounded flex items-center gap-2"
                        >
                            <i class="fa-solid fa-arrow-up w-4"></i> Add Row Before
                        </button>
                        <button
                            type="button"
                            @click="editor.chain().focus().addRowAfter().run(); showTableMenu = false"
                            class="w-full text-left px-2 py-1 text-sm hover:bg-gray-100 rounded flex items-center gap-2"
                        >
                            <i class="fa-solid fa-arrow-down w-4"></i> Add Row After
                        </button>
                        <button
                            type="button"
                            @click="editor.chain().focus().deleteRow().run(); showTableMenu = false"
                            class="w-full text-left px-2 py-1 text-sm hover:bg-gray-100 rounded flex items-center gap-2 text-danger"
                        >
                            <i class="fa-solid fa-trash w-4"></i> Delete Row
                        </button>
                        <div class="border-t border-gray-200 my-1"></div>
                        <button
                            type="button"
                            @click="editor.chain().focus().toggleHeaderRow().run(); showTableMenu = false"
                            class="w-full text-left px-2 py-1 text-sm hover:bg-gray-100 rounded flex items-center gap-2"
                        >
                            <i class="fa-solid fa-heading w-4"></i> Toggle Header Row
                        </button>
                        <button
                            type="button"
                            @click="editor.chain().focus().mergeCells().run(); showTableMenu = false"
                            class="w-full text-left px-2 py-1 text-sm hover:bg-gray-100 rounded flex items-center gap-2"
                        >
                            <i class="fa-solid fa-compress w-4"></i> Merge Cells
                        </button>
                        <button
                            type="button"
                            @click="editor.chain().focus().splitCell().run(); showTableMenu = false"
                            class="w-full text-left px-2 py-1 text-sm hover:bg-gray-100 rounded flex items-center gap-2"
                        >
                            <i class="fa-solid fa-expand w-4"></i> Split Cell
                        </button>
                        <div class="border-t border-gray-200 my-1"></div>
                        <button
                            type="button"
                            @click="editor.chain().focus().deleteTable().run(); showTableMenu = false"
                            class="w-full text-left px-2 py-1 text-sm hover:bg-gray-100 rounded flex items-center gap-2 text-danger"
                        >
                            <i class="fa-solid fa-trash w-4"></i> Delete Table
                        </button>
                    </template>
                </div>
            </div>

            <div class="w-px bg-gray-300 mx-1"></div>

            <!-- Other -->
            <button
                type="button"
                @click="editor.chain().focus().toggleBlockquote().run()"
                :class="{ 'bg-primary text-white': editor.isActive('blockquote') }"
                class="p-2 rounded hover:bg-gray-200 transition-colors"
                title="Blockquote"
            >
                <i class="fa-solid fa-quote-left"></i>
            </button>
            <button
                type="button"
                @click="editor.chain().focus().setHorizontalRule().run()"
                class="p-2 rounded hover:bg-gray-200 transition-colors"
                title="Horizontal Rule"
            >
                <i class="fa-solid fa-minus"></i>
            </button>

            <div class="w-px bg-gray-300 mx-1"></div>

            <!-- Undo/Redo -->
            <button
                type="button"
                @click="editor.chain().focus().undo().run()"
                :disabled="!editor.can().undo()"
                class="p-2 rounded hover:bg-gray-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                title="Undo"
            >
                <i class="fa-solid fa-rotate-left"></i>
            </button>
            <button
                type="button"
                @click="editor.chain().focus().redo().run()"
                :disabled="!editor.can().redo()"
                class="p-2 rounded hover:bg-gray-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                title="Redo"
            >
                <i class="fa-solid fa-rotate-right"></i>
            </button>

            <div class="flex-1"></div>

            <!-- Toggle HTML view -->
            <button
                type="button"
                @click="showHtml = !showHtml"
                :class="{ 'bg-primary text-white': showHtml }"
                class="p-2 rounded hover:bg-gray-200 transition-colors"
                title="View HTML"
            >
                <i class="fa-solid fa-code"></i>
            </button>
        </div>

        <!-- Editor content -->
        <div v-show="!showHtml">
            <editor-content
                :editor="editor"
                class="border border-gray-300 rounded-b-md min-h-[200px] prose max-w-none focus-within:ring-2 focus-within:ring-primary focus-within:border-primary"
            />
        </div>

        <!-- HTML source view -->
        <div v-show="showHtml">
            <textarea
                :value="modelValue"
                @input="$emit('update:modelValue', $event.target.value)"
                class="w-full border border-gray-300 rounded-b-md min-h-[200px] p-3 font-mono text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                placeholder="Enter HTML..."
            ></textarea>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onBeforeUnmount, onMounted, computed } from 'vue';
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import { Table, TableRow, TableCell, TableHeader } from '@tiptap/extension-table';
import Image from '@tiptap/extension-image';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Enter content...',
    },
});

const emit = defineEmits(['update:modelValue']);

const showHtml = ref(false);
const editor = ref(null);

// Color picker state
const showTextColorPicker = ref(false);
const showHighlightPicker = ref(false);
const textColorDropdownRef = ref(null);
const highlightDropdownRef = ref(null);

// Table menu state
const showTableMenu = ref(false);
const tableDropdownRef = ref(null);

// Preset colors
const textColors = [
    '#ffffff', '#f3f4f6', '#e5e7eb', '#d1d5db', '#9ca3af', '#6b7280',
    '#4b5563', '#374151', '#1f2937', '#111827', '#030712', '#000000',
    '#fef08a', '#fde047', '#facc15', '#eab308', '#ca8a04', '#a16207',
    '#bbf7d0', '#86efac', '#4ade80', '#22c55e', '#16a34a', '#15803d',
    '#bfdbfe', '#93c5fd', '#60a5fa', '#3b82f6', '#2563eb', '#1d4ed8',
    '#fecaca', '#fca5a5', '#f87171', '#ef4444', '#dc2626', '#b91c1c',
    '#e9d5ff', '#d8b4fe', '#c084fc', '#a855f7', '#9333ea', '#7e22ce',
];

const highlightColors = [
    '#ffffff', '#f3f4f6', '#e5e7eb', '#d1d5db', '#9ca3af', '#6b7280',
    '#fef08a', '#fde047', '#facc15', '#eab308', '#ca8a04', '#a16207',
    '#bbf7d0', '#86efac', '#4ade80', '#22c55e', '#16a34a', '#15803d',
    '#bfdbfe', '#93c5fd', '#60a5fa', '#3b82f6', '#2563eb', '#1d4ed8',
    '#fecaca', '#fca5a5', '#f87171', '#ef4444', '#dc2626', '#b91c1c',
    '#e9d5ff', '#d8b4fe', '#c084fc', '#a855f7', '#9333ea', '#7e22ce',
];

// Get current text color from editor
const currentTextColor = computed(() => {
    if (!editor.value) return null;
    return editor.value.getAttributes('textStyle').color || null;
});

// Get current highlight color from editor
const currentHighlightColor = computed(() => {
    if (!editor.value) return null;
    return editor.value.getAttributes('highlight').color || null;
});

// Create editor instance
function createEditor() {
    if (editor.value) {
        editor.value.destroy();
        editor.value = null;
    }

    editor.value = new Editor({
        content: props.modelValue,
        extensions: [
            StarterKit.configure({
                // Disable extensions we're adding separately with custom config
                link: false,
                underline: false,
            }),
            Underline,
            Link.configure({
                openOnClick: false,
                HTMLAttributes: {
                    class: 'text-primary underline',
                },
            }),
            TextAlign.configure({
                types: ['heading', 'paragraph'],
            }),
            TextStyle,
            Color,
            Highlight.configure({
                multicolor: true,
            }),
            Table.configure({
                resizable: true,
            }),
            TableRow,
            TableHeader,
            TableCell,
            Image.configure({
                inline: false,
                allowBase64: true,
            }),
        ],
        editorProps: {
            attributes: {
                class: 'p-3 focus:outline-none min-h-[200px]',
            },
        },
        onUpdate: ({ editor: ed }) => {
            emit('update:modelValue', ed.getHTML());
        },
    });
}

// Initialize editor in onMounted for proper Vue lifecycle
onMounted(() => {
    createEditor();
});

// Watch for external changes to modelValue
watch(() => props.modelValue, (newValue) => {
    if (editor.value && newValue !== editor.value.getHTML()) {
        editor.value.commands.setContent(newValue, false);
    }
});

// Watch for HTML view toggle to sync content
watch(showHtml, (isHtml) => {
    if (!isHtml && editor.value) {
        // Switching back to visual editor, update content
        editor.value.commands.setContent(props.modelValue, false);
    }
});

function setLink() {
    const previousUrl = editor.value.getAttributes('link').href;
    const url = window.prompt('Enter URL:', previousUrl);

    if (url === null) {
        return;
    }

    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }

    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}

function setTextColor(color) {
    editor.value.chain().focus().setColor(color).run();
    showTextColorPicker.value = false;
}

function clearTextColor() {
    editor.value.chain().focus().unsetColor().run();
    showTextColorPicker.value = false;
}

function setHighlightColor(color) {
    editor.value.chain().focus().toggleHighlight({ color }).run();
    showHighlightPicker.value = false;
}

function clearHighlight() {
    editor.value.chain().focus().unsetHighlight().run();
    showHighlightPicker.value = false;
}

function insertTable() {
    editor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
    showTableMenu.value = false;
}

function addImage() {
    const url = window.prompt('Enter image URL:');
    if (url) {
        editor.value.chain().focus().setImage({ src: url }).run();
    }
}

// Close dropdowns when clicking outside
function handleClickOutside(event) {
    if (textColorDropdownRef.value && !textColorDropdownRef.value.contains(event.target)) {
        showTextColorPicker.value = false;
    }
    if (highlightDropdownRef.value && !highlightDropdownRef.value.contains(event.target)) {
        showHighlightPicker.value = false;
    }
    if (tableDropdownRef.value && !tableDropdownRef.value.contains(event.target)) {
        showTableMenu.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
    if (editor.value) {
        editor.value.destroy();
    }
});
</script>

<style>
.rich-text-editor .ProseMirror {
    min-height: 200px;
}

.rich-text-editor .ProseMirror p {
    margin: 0.5em 0;
}

.rich-text-editor .ProseMirror h1 {
    font-size: 1.5em;
    font-weight: bold;
    margin: 0.5em 0;
}

.rich-text-editor .ProseMirror h2 {
    font-size: 1.25em;
    font-weight: bold;
    margin: 0.5em 0;
}

.rich-text-editor .ProseMirror h3 {
    font-size: 1.1em;
    font-weight: bold;
    margin: 0.5em 0;
}

.rich-text-editor .ProseMirror ul {
    list-style-type: disc;
    padding-left: 1.5em;
    margin: 0.5em 0;
}

.rich-text-editor .ProseMirror ol {
    list-style-type: decimal;
    padding-left: 1.5em;
    margin: 0.5em 0;
}

.rich-text-editor .ProseMirror blockquote {
    border-left: 3px solid #ccc;
    padding-left: 1em;
    margin: 0.5em 0;
    color: #666;
}

.rich-text-editor .ProseMirror hr {
    border: none;
    border-top: 1px solid #ccc;
    margin: 1em 0;
}

.rich-text-editor .ProseMirror a {
    color: rgb(var(--color-primary));
    text-decoration: underline;
}

.rich-text-editor .ProseMirror mark {
    border-radius: 0.25em;
    padding: 0.125em 0.25em;
}

/* Table styles */
.rich-text-editor .ProseMirror table {
    border-collapse: collapse;
    table-layout: fixed;
    width: 100%;
    margin: 1em 0;
    overflow: hidden;
}

.rich-text-editor .ProseMirror td,
.rich-text-editor .ProseMirror th {
    min-width: 1em;
    border: 1px solid #d1d5db;
    padding: 0.5em;
    vertical-align: top;
    box-sizing: border-box;
    position: relative;
}

.rich-text-editor .ProseMirror th {
    font-weight: bold;
    text-align: left;
    background-color: #f3f4f6;
}

.rich-text-editor .ProseMirror .selectedCell:after {
    z-index: 2;
    position: absolute;
    content: "";
    left: 0;
    right: 0;
    top: 0;
    bottom: 0;
    background: rgb(var(--color-primary) / 0.15);
    pointer-events: none;
}

.rich-text-editor .ProseMirror .column-resize-handle {
    position: absolute;
    right: -2px;
    top: 0;
    bottom: -2px;
    width: 4px;
    background-color: rgb(var(--color-primary));
    pointer-events: none;
}

.rich-text-editor .ProseMirror.resize-cursor {
    cursor: ew-resize;
    cursor: col-resize;
}

/* Image styles */
.rich-text-editor .ProseMirror img {
    max-width: 100%;
    height: auto;
    margin: 1em 0;
    border-radius: 0.25em;
}

.rich-text-editor .ProseMirror img.ProseMirror-selectednode {
    outline: 2px solid rgb(var(--color-primary));
}
</style>
