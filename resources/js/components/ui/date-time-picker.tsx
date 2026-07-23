import { format } from 'date-fns';
import { Calendar as CalendarIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

interface DateTimePickerProps {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
}

export function DateTimePicker({ value, onChange, placeholder = 'Seleccionar fecha' }: DateTimePickerProps) {
    const date = value ? new Date(value) : undefined;
    
    const handleDateSelect = (selectedDate: Date | undefined) => {
        if (!selectedDate) return;
        
        const currentDate = date ? new Date(date) : new Date();
        currentDate.setFullYear(selectedDate.getFullYear());
        currentDate.setMonth(selectedDate.getMonth());
        currentDate.setDate(selectedDate.getDate());
        
        onChange(format(currentDate, "yyyy-MM-dd'T'HH:mm"));
    };

    const handleTimeChange = (time: string) => {
        if (!date) {
            const now = new Date();
            now.setHours(parseInt(time.split(':')[0]) || 0);
            now.setMinutes(parseInt(time.split(':')[1]) || 0);
            onChange(format(now, "yyyy-MM-dd'T'HH:mm"));
            return;
        }
        
        const newDate = new Date(date);
        newDate.setHours(parseInt(time.split(':')[0]) || 0);
        newDate.setMinutes(parseInt(time.split(':')[1]) || 0);
        onChange(format(newDate, "yyyy-MM-dd'T'HH:mm"));
    };

    return (
        <div className="flex gap-2">
            <Popover>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        className={cn(
                            "justify-start text-left font-normal flex-1",
                            !date && "text-muted-foreground"
                        )}
                    >
                        <CalendarIcon className="mr-2 h-4 w-4" />
                        {date ? format(date, 'dd/MM/yyyy') : placeholder}
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                        mode="single"
                        selected={date}
                        onSelect={handleDateSelect}
                        initialFocus
                    />
                </PopoverContent>
            </Popover>
            
            <Input
                type="time"
                value={date ? format(date, 'HH:mm') : ''}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => handleTimeChange(e.target.value)}
                className="w-32"
            />
        </div>
    );
}