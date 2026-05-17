import { router } from '@inertiajs/react';
import { CircleAlert, Globe, Building2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';

type SearchProperty = { id: number; name: string; base_url: string };
type SearchOrganization = { id: number; name: string; domain: string | null };
type SearchIssue = {
    id: number;
    rule_key: string;
    description: string | null;
    severity: string;
    property: { id: number; name: string } | null;
};
type SearchResults = {
    properties: SearchProperty[];
    organizations: SearchOrganization[];
    issues: SearchIssue[];
};

const emptyResults: SearchResults = { properties: [], organizations: [], issues: [] };

function hasResults(results: SearchResults): boolean {
    return results.properties.length > 0 || results.organizations.length > 0 || results.issues.length > 0;
}

export function GlobalSearch() {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResults>(emptyResults);
    const [loading, setLoading] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const handleKey = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                setOpen((prev) => !prev);
            }
        };
        window.addEventListener('keydown', handleKey);
        return () => window.removeEventListener('keydown', handleKey);
    }, []);

    const search = useCallback(async (term: string) => {
        if (term.length < 2) {
            setResults(emptyResults);
            return;
        }
        setLoading(true);
        try {
            const res = await fetch(`/api/search?q=${encodeURIComponent(term)}`, {
                credentials: 'include',
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                setResults(await res.json());
            }
        } finally {
            setLoading(false);
        }
    }, []);

    const handleValueChange = (value: string) => {
        setQuery(value);
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }
        debounceRef.current = setTimeout(() => search(value), 300);
    };

    const navigate = (href: string) => {
        setOpen(false);
        setQuery('');
        setResults(emptyResults);
        router.visit(href);
    };

    const handleOpenChange = (isOpen: boolean) => {
        setOpen(isOpen);
        if (!isOpen) {
            setQuery('');
            setResults(emptyResults);
        }
    };

    return (
        <>
            <button
                onClick={() => setOpen(true)}
                className="text-muted-foreground hover:text-foreground flex items-center gap-1.5 rounded border border-input bg-muted/40 px-3 py-1.5 text-sm transition-colors hover:bg-muted"
                aria-label="Open search"
            >
                <span className="hidden sm:inline">Search…</span>
                <kbd className="pointer-events-none hidden rounded border border-border bg-background px-1.5 py-0.5 font-mono text-[10px] font-medium text-muted-foreground sm:inline-flex">
                    ⌘K
                </kbd>
            </button>

            <Dialog open={open} onOpenChange={handleOpenChange}>
                <DialogContent className="overflow-hidden p-0 shadow-lg sm:max-w-lg">
                    <DialogTitle className="sr-only">Search</DialogTitle>
                    <Command shouldFilter={false}>
                        <CommandInput
                            placeholder="Search properties, organizations, issues…"
                            value={query}
                            onValueChange={handleValueChange}
                        />
                        <CommandList>
                            {loading && (
                                <div className="py-6 text-center text-sm text-muted-foreground">Searching…</div>
                            )}

                            {!loading && query.length >= 2 && !hasResults(results) && (
                                <CommandEmpty>No results for &ldquo;{query}&rdquo;</CommandEmpty>
                            )}

                            {!loading && results.properties.length > 0 && (
                                <CommandGroup heading="Properties">
                                    {results.properties.map((p) => (
                                        <CommandItem
                                            key={`property-${p.id}`}
                                            onSelect={() => navigate(`/properties/${p.id}`)}
                                        >
                                            <Globe className="size-4 text-muted-foreground" />
                                            <span>{p.name}</span>
                                            <span className="ml-auto truncate text-xs text-muted-foreground">{p.base_url}</span>
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            )}

                            {!loading && results.organizations.length > 0 && (
                                <CommandGroup heading="Organizations">
                                    {results.organizations.map((o) => (
                                        <CommandItem
                                            key={`org-${o.id}`}
                                            onSelect={() => navigate(`/organizations/${o.id}`)}
                                        >
                                            <Building2 className="size-4 text-muted-foreground" />
                                            <span>{o.name}</span>
                                            {o.domain && (
                                                <span className="ml-auto truncate text-xs text-muted-foreground">{o.domain}</span>
                                            )}
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            )}

                            {!loading && results.issues.length > 0 && (
                                <CommandGroup heading="Issues">
                                    {results.issues.map((issue) => (
                                        <CommandItem
                                            key={`issue-${issue.id}`}
                                            onSelect={() => navigate(`/issues/${issue.id}`)}
                                        >
                                            <CircleAlert className="size-4 text-muted-foreground" />
                                            <span className="font-mono text-xs">{issue.rule_key}</span>
                                            {issue.property && (
                                                <span className="ml-auto truncate text-xs text-muted-foreground">{issue.property.name}</span>
                                            )}
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            )}
                        </CommandList>
                    </Command>
                </DialogContent>
            </Dialog>
        </>
    );
}
