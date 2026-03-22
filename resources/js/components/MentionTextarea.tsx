import { useCallback, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

type TeamMember = { id: number; name: string };

interface MentionTextareaProps {
    value: string;
    onChange: (value: string) => void;
    teamMembers: TeamMember[];
    placeholder?: string;
    className?: string;
    rows?: number;
}

export default function MentionTextarea({ value, onChange, teamMembers, placeholder, className, rows = 3 }: MentionTextareaProps) {
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const [mentionQuery, setMentionQuery] = useState<string | null>(null);
    const [mentionStart, setMentionStart] = useState<number>(-1);

    const filteredMembers =
        mentionQuery !== null
            ? teamMembers.filter((m) => m.name.toLowerCase().startsWith(mentionQuery.toLowerCase())).slice(0, 6)
            : [];

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
            if (mentionQuery !== null && filteredMembers.length > 0 && e.key === 'Enter') {
                e.preventDefault();
                insertMention(filteredMembers[0]);
            }
            if (e.key === 'Escape') {
                setMentionQuery(null);
            }
        },
        [mentionQuery, filteredMembers],
    );

    const handleChange = useCallback(
        (e: React.ChangeEvent<HTMLTextAreaElement>) => {
            const newValue = e.target.value;
            const cursor = e.target.selectionStart ?? newValue.length;
            onChange(newValue);

            // Find the most recent @ before the cursor
            const textBeforeCursor = newValue.slice(0, cursor);
            const atMatch = textBeforeCursor.match(/@(\w*)$/);

            if (atMatch) {
                setMentionQuery(atMatch[1]);
                setMentionStart(cursor - atMatch[0].length);
            } else {
                setMentionQuery(null);
            }
        },
        [onChange],
    );

    function insertMention(member: TeamMember) {
        const firstName = member.name.split(' ')[0];
        const before = value.slice(0, mentionStart);
        const after = value.slice(mentionStart + 1 + (mentionQuery ?? '').length);
        const newValue = `${before}@${firstName} ${after}`;
        onChange(newValue);
        setMentionQuery(null);

        // Restore focus and position cursor after the inserted mention
        requestAnimationFrame(() => {
            const ta = textareaRef.current;
            if (ta) {
                ta.focus();
                const pos = before.length + firstName.length + 2; // @name + space
                ta.setSelectionRange(pos, pos);
            }
        });
    }

    return (
        <div className="relative">
            <textarea
                ref={textareaRef}
                value={value}
                onChange={handleChange}
                onKeyDown={handleKeyDown}
                placeholder={placeholder}
                rows={rows}
                className={cn(
                    'w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 resize-none',
                    className,
                )}
            />

            {mentionQuery !== null && filteredMembers.length > 0 && (
                <ul className="absolute left-0 z-50 mt-1 w-56 rounded-md border bg-popover py-1 shadow-md">
                    {filteredMembers.map((member) => (
                        <li key={member.id}>
                            <button
                                type="button"
                                className="w-full px-3 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                                onMouseDown={(e) => {
                                    e.preventDefault(); // keep textarea focus
                                    insertMention(member);
                                }}
                            >
                                @{member.name}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
