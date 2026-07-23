import ImageExtension from '@tiptap/extension-image';
import LinkExtension from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { Bold, Italic, Heading1, Heading2, List, ListOrdered, Link, Image, Undo, Redo, Quote } from 'lucide-react';
import { useCallback } from 'react';

interface RichEditorProps {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
    minHeight?: string;
}

export default function RichEditor({ value, onChange, placeholder = 'Escribe aquí...', minHeight = '200px' }: RichEditorProps) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
            }),
            LinkExtension.configure({
                openOnClick: false,
                HTMLAttributes: { class: 'text-primary underline' },
            }),
            ImageExtension.configure({
                inline: true,
                allowBase64: true,
            }),
            Placeholder.configure({ placeholder }),
        ],
        content: value || '',
        onUpdate: ({ editor }) => {
            onChange(editor.getHTML());
        },
        editorProps: {
            attributes: {
                class: 'prose prose-sm max-w-none focus:outline-none px-4 py-3 min-h-[200px]',
                style: `min-height: ${minHeight}`,
            },
        },
    });

    const toggleBold = useCallback(() => editor?.chain().focus().toggleBold().run(), [editor]);
    const toggleItalic = useCallback(() => editor?.chain().focus().toggleItalic().run(), [editor]);
    const toggleHeading2 = useCallback(() => editor?.chain().focus().toggleHeading({ level: 2 }).run(), [editor]);
    const toggleHeading3 = useCallback(() => editor?.chain().focus().toggleHeading({ level: 3 }).run(), [editor]);
    const toggleBulletList = useCallback(() => editor?.chain().focus().toggleBulletList().run(), [editor]);
    const toggleOrderedList = useCallback(() => editor?.chain().focus().toggleOrderedList().run(), [editor]);
    const toggleBlockquote = useCallback(() => editor?.chain().focus().toggleBlockquote().run(), [editor]);
    const undo = useCallback(() => editor?.chain().focus().undo().run(), [editor]);
    const redo = useCallback(() => editor?.chain().focus().redo().run(), [editor]);

    const setLink = useCallback(() => {
        if (!editor) return;
        const previousUrl = editor.getAttributes('link').href;
        const url = window.prompt('URL del enlace:', previousUrl || 'https://');
        if (url === null) return;
        if (url === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();
            return;
        }
        editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }, [editor]);

    const addImage = useCallback(() => {
        const url = window.prompt('URL de la imagen:', 'https://');
        if (url) {
            editor?.chain().focus().setImage({ src: url }).run();
        }
    }, [editor]);

    if (!editor) return null;

    const isActive = (type: string, attrs?: any) => editor.isActive(type, attrs);

    const btnClass = (active: boolean) =>
        `flex h-8 w-8 items-center justify-center rounded-lg text-sm transition-colors ${
            active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'
        }`;

    return (
        <div className="overflow-hidden rounded-xl border border-input bg-background">
            <div className="flex flex-wrap items-center gap-0.5 border-b bg-muted/30 px-2 py-1.5">
                <button type="button" onClick={undo} className={btnClass(false)} title="Deshacer"><Undo className="h-3.5 w-3.5" /></button>
                <button type="button" onClick={redo} className={btnClass(false)} title="Rehacer"><Redo className="h-3.5 w-3.5" /></button>
                <span className="mx-1 h-5 w-px bg-border" />
                <button type="button" onClick={toggleBold} className={btnClass(isActive('bold'))} title="Negrita"><Bold className="h-3.5 w-3.5" /></button>
                <button type="button" onClick={toggleItalic} className={btnClass(isActive('italic'))} title="Cursiva"><Italic className="h-3.5 w-3.5" /></button>
                <span className="mx-1 h-5 w-px bg-border" />
                <button type="button" onClick={toggleHeading2} className={btnClass(isActive('heading', { level: 2 }))} title="Título"><Heading1 className="h-3.5 w-3.5" /></button>
                <button type="button" onClick={toggleHeading3} className={btnClass(isActive('heading', { level: 3 }))} title="Subtítulo"><Heading2 className="h-3.5 w-3.5" /></button>
                <span className="mx-1 h-5 w-px bg-border" />
                <button type="button" onClick={toggleBulletList} className={btnClass(isActive('bulletList'))} title="Lista"><List className="h-3.5 w-3.5" /></button>
                <button type="button" onClick={toggleOrderedList} className={btnClass(isActive('orderedList'))} title="Lista numerada"><ListOrdered className="h-3.5 w-3.5" /></button>
                <button type="button" onClick={toggleBlockquote} className={btnClass(isActive('blockquote'))} title="Cita"><Quote className="h-3.5 w-3.5" /></button>
                <span className="mx-1 h-5 w-px bg-border" />
                <button type="button" onClick={setLink} className={btnClass(isActive('link'))} title="Enlace"><Link className="h-3.5 w-3.5" /></button>
                <button type="button" onClick={addImage} className={btnClass(isActive('image'))} title="Imagen"><Image className="h-3.5 w-3.5" /></button>
            </div>
            <EditorContent editor={editor} />
        </div>
    );
}
