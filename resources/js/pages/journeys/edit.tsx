import { Head, Link, useForm } from '@inertiajs/react';
import { Trash2, ArrowUp, ArrowDown, Plus } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Property = {
    id: number;
    name: string;
};

type Step = {
    label: string;
    url: string;
};

type Journey = {
    id: number;
    name: string;
    description: string | null;
    property_id: number | null;
    steps: (Step & { id: number; position: number })[];
};

export default function Edit({ journey, properties }: { journey: Journey; properties: Property[] }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Journeys', href: '/journeys' },
        { title: journey.name, href: `/journeys/${journey.id}/edit` },
    ];

    const { data, setData, patch, processing, errors } = useForm<{
        name: string;
        property_id: string;
        description: string;
        steps: Step[];
    }>({
        name: journey.name,
        property_id: journey.property_id !== null ? String(journey.property_id) : '',
        description: journey.description ?? '',
        steps: journey.steps
            .slice()
            .sort((a, b) => a.position - b.position)
            .map(({ label, url }) => ({ label, url })),
    });

    function addStep() {
        setData('steps', [...data.steps, { label: '', url: '' }]);
    }

    function removeStep(index: number) {
        setData('steps', data.steps.filter((_, i) => i !== index));
    }

    function moveStep(index: number, direction: 'up' | 'down') {
        const next = [...data.steps];
        const swap = direction === 'up' ? index - 1 : index + 1;
        [next[index], next[swap]] = [next[swap], next[index]];
        setData('steps', next);
    }

    function updateStep(index: number, field: keyof Step, value: string) {
        const next = [...data.steps];
        next[index] = { ...next[index], [field]: value };
        setData('steps', next);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        patch(`/journeys/${journey.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit: ${journey.name}`} />

            <div className="flex flex-col gap-6 p-6">
                <h1 className="text-xl font-semibold">Edit Journey</h1>

                <form onSubmit={submit} className="max-w-2xl space-y-5">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Journey name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="e.g. Marketing funnel"
                            autoFocus
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="property_id">Property</Label>
                        <Select
                            value={data.property_id}
                            onValueChange={(v) => setData('property_id', v)}
                        >
                            <SelectTrigger id="property_id">
                                <SelectValue placeholder="Select a property…" />
                            </SelectTrigger>
                            <SelectContent>
                                {properties.map((p) => (
                                    <SelectItem key={p.id} value={String(p.id)}>
                                        {p.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.property_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Description <span className="text-muted-foreground">(optional)</span></Label>
                        <Textarea
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Brief description of this journey"
                            rows={2}
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-3">
                        <Label>Steps</Label>
                        <InputError message={errors.steps} />

                        {data.steps.map((step, i) => (
                            <div key={i} className="flex items-start gap-2 rounded-lg border bg-card p-3">
                                <span className="mt-2.5 min-w-[1.5rem] text-center text-sm font-medium text-muted-foreground">
                                    {i + 1}
                                </span>

                                <div className="flex flex-1 flex-col gap-2">
                                    <Input
                                        value={step.label}
                                        onChange={(e) => updateStep(i, 'label', e.target.value)}
                                        placeholder="Step label (e.g. Home page)"
                                    />
                                    <InputError message={(errors as Record<string, string>)[`steps.${i}.label`]} />
                                    <Input
                                        value={step.url}
                                        onChange={(e) => updateStep(i, 'url', e.target.value)}
                                        type="url"
                                        placeholder="https://example.com/page"
                                    />
                                    <InputError message={(errors as Record<string, string>)[`steps.${i}.url`]} />
                                </div>

                                <div className="flex flex-col gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={i === 0}
                                        onClick={() => moveStep(i, 'up')}
                                        aria-label="Move step up"
                                    >
                                        <ArrowUp className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={i === data.steps.length - 1}
                                        onClick={() => moveStep(i, 'down')}
                                        aria-label="Move step down"
                                    >
                                        <ArrowDown className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={data.steps.length === 1}
                                        onClick={() => removeStep(i)}
                                        aria-label="Remove step"
                                        className="text-destructive hover:text-destructive"
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        ))}

                        <Button type="button" variant="outline" size="sm" onClick={addStep} className="w-fit">
                            <Plus className="mr-1 size-4" />
                            Add step
                        </Button>
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <Button type="submit" disabled={processing}>
                            Save changes
                        </Button>
                        <Button variant="ghost" asChild>
                            <Link href="/journeys">Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
