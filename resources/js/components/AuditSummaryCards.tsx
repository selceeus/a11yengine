import { Link } from '@inertiajs/react';
import { Minus, TrendingDown, TrendingUp } from 'lucide-react';

export type AuditSummary = {
    id: number;
    title: string;
    overall_score: number | null;
    score_delta: number | null;
    trend_direction: 'improving' | 'declining' | 'stable';
    generated_at: string | null;
    property: { id: number; name: string } | null;
};

function scoreColor(score: number | null) {
    if (score === null) return 'bg-muted text-muted-foreground';
    if (score >= 80) return 'bg-green-100 text-green-800';
    if (score >= 50) return 'bg-amber-100 text-amber-800';
    return 'bg-red-100 text-red-800';
}

export function AuditSummaryCards({ audits }: { audits: AuditSummary[] }) {
    if (audits.length === 0) {
        return (
            <p className="py-4 text-center text-sm text-muted-foreground">
                No completed audits yet.{' '}
                <Link href="/audits" className="text-primary underline">
                    Run an audit
                </Link>
            </p>
        );
    }

    return (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            {audits.map((audit) => (
                <Link
                    key={audit.id}
                    href={`/audits/${audit.id}`}
                    className="block rounded-xl border bg-card p-5 transition-colors hover:bg-muted/30"
                >
                    <div className="mb-3 flex items-start justify-between gap-2">
                        <div className="min-w-0">
                            <p className="line-clamp-2 font-medium leading-tight">{audit.title}</p>
                            {audit.property && (
                                <p className="mt-0.5 text-xs text-muted-foreground">{audit.property.name}</p>
                            )}
                        </div>
                        <span className={`shrink-0 rounded-full px-3 py-1 text-sm font-bold ${scoreColor(audit.overall_score)}`}>
                            {audit.overall_score ?? '—'}
                        </span>
                    </div>

                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <div className="flex items-center gap-1">
                            {audit.trend_direction === 'improving' && <TrendingUp className="h-3.5 w-3.5 text-green-600" />}
                            {audit.trend_direction === 'declining' && <TrendingDown className="h-3.5 w-3.5 text-red-600" />}
                            {audit.trend_direction === 'stable' && <Minus className="h-3.5 w-3.5 text-muted-foreground" />}
                            {audit.score_delta !== null && (
                                <span
                                    className={
                                        audit.trend_direction === 'improving'
                                            ? 'text-green-600'
                                            : audit.trend_direction === 'declining'
                                              ? 'text-red-600'
                                              : 'text-muted-foreground'
                                    }
                                >
                                    {audit.score_delta > 0 ? '+' : ''}
                                    {audit.score_delta} vs prev
                                </span>
                            )}
                        </div>

                        {audit.generated_at && <span>{new Date(audit.generated_at).toLocaleDateString()}</span>}
                    </div>
                </Link>
            ))}
        </div>
    );
}
