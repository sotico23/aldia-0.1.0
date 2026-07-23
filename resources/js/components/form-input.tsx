import { Eye, EyeOff } from 'lucide-react';
import * as React from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

interface FormInputProps extends React.ComponentProps<'input'> {
    label?: string;
    error?: string;
    id: string;
    description?: string;
}

const FormInput = React.forwardRef<HTMLInputElement, FormInputProps>(
    ({ label, error, className, id, description, type, ...props }, ref) => {
        const isNumber = type === 'number';
        const isPassword = type === 'password';
        const [showPassword, setShowPassword] = React.useState(false);

        return (
            <div className="grid gap-1.5 transition-all duration-200">
                {label && (
                    <Label
                        htmlFor={id}
                        className={cn(
                            'text-sm font-semibold transition-colors',
                            error
                                ? 'text-red-500'
                                : 'text-slate-700 dark:text-slate-300',
                        )}
                    >
                        {label}
                    </Label>
                )}

                <div className="group relative">
                    <Input
                        id={id}
                        ref={ref}
                        type={isPassword && showPassword ? 'text' : type}
                        className={cn(
                            isPassword && 'pr-10',
                            'transition-all duration-200 focus-visible:ring-offset-0',
                            error
                                ? 'border-red-500 bg-red-50/10 ring-1 ring-red-500 focus-visible:ring-red-200'
                                : 'border-slate-200 hover:border-slate-300 focus-visible:ring-blue-100 dark:border-slate-800',
                            className,
                        )}
                        aria-invalid={error ? 'true' : 'false'}
                        onKeyDown={(e) => {
                            if (isNumber) {
                                const isModifierKey =
                                    e.ctrlKey || e.metaKey || e.altKey;
                                const isAllowedKey = [
                                    'Backspace',
                                    'Delete',
                                    'Tab',
                                    'Escape',
                                    'Enter',
                                    'ArrowLeft',
                                    'ArrowRight',
                                    'ArrowUp',
                                    'ArrowDown',
                                    'Home',
                                    'End',
                                ].includes(e.key);

                                if (
                                    !isModifierKey &&
                                    !isAllowedKey &&
                                    !/[0-9]/.test(e.key)
                                ) {
                                    e.preventDefault();
                                }
                            }
                            if (props.onKeyDown) props.onKeyDown(e);
                        }}
                        {...props}
                    />
                    {isPassword && (
                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            className="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground focus:outline-none"
                            tabIndex={-1}
                        >
                            {showPassword ? (
                                <EyeOff className="h-4 w-4" />
                            ) : (
                                <Eye className="h-4 w-4" />
                            )}
                        </button>
                    )}
                </div>

                {description && !error && (
                    <p className="text-[11px] text-slate-400 dark:text-slate-500">
                        {description}
                    </p>
                )}

                <InputError message={error} className="mt-0" />
            </div>
        );
    },
);

FormInput.displayName = 'FormInput';

export { FormInput };
