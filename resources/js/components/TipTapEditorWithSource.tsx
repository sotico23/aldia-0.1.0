import { Code, Eye } from 'lucide-react';
import { useState } from 'react';
import RichEditor from '@/components/rich-editor';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

interface TipTapEditorWithSourceProps {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
    minHeight?: string;
    availableVariables?: string[];
}

const VARIABLES = [
    'codigo',
    'valor',
    'tipo',
    'vencimiento',
    'descripcion',
    'tienda',
    'logo_url',
    'compra_minima',
];

export default function TipTapEditorWithSource({
    value,
    onChange,
    placeholder = 'Diseña la plantilla del cupón...',
    minHeight = '200px',
    availableVariables = VARIABLES,
}: TipTapEditorWithSourceProps) {
    const [mode, setMode] = useState<'visual' | 'source'>('visual');

    const insertVariable = (variable: string) => {
        const placeholder = `{{${variable}}}`;

        if (mode === 'source') {
            onChange((value || '') + placeholder);
        }
    };

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant={mode === 'visual' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setMode('visual')}
                    >
                        <Eye className="mr-1 h-4 w-4" /> Visual
                    </Button>
                    <Button
                        type="button"
                        variant={mode === 'source' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setMode('source')}
                    >
                        <Code className="mr-1 h-4 w-4" /> HTML
                    </Button>
                </div>
                <div className="flex flex-wrap gap-1">
                    {availableVariables.map((variable) => (
                        <button
                            key={variable}
                            type="button"
                            onClick={() => insertVariable(variable)}
                            className="rounded-md bg-muted px-2 py-1 text-xs font-mono text-muted-foreground hover:bg-primary/10 hover:text-primary transition-colors"
                        >
                            {'{{'}{variable}{'}}'}
                        </button>
                    ))}
                </div>
            </div>

            {mode === 'visual' ? (
                <RichEditor
                    value={value}
                    onChange={onChange}
                    placeholder={placeholder}
                    minHeight={minHeight}
                />
            ) : (
                <Textarea
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    className="font-mono text-sm"
                    style={{ minHeight }}
                />
            )}
        </div>
    );
}
