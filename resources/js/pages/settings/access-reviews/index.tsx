import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { accessReviews } from '@/actions/Settings/AccessReviewController';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'Access Reviews', href: '/settings/access-reviews' },
];

type Review = {
    id: number;
    period: string;
    status: 'pending' | 'completed';
    due_at: string;
    completed_at: string | null;
    completed_by: { id: number; name: string } | null;
};

export default function AccessReviewsIndex({ reviews }: { reviews: Review[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Access Reviews" />

            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Access Reviews</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Quarterly SOC2 access reviews. Confirm or revoke team member access each quarter.
                    </p>
                </div>

                {reviews.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No access reviews yet. They are created automatically each quarter.</p>
                ) : (
                    <div className="space-y-3">
                        {reviews.map((review) => (
                            <Card key={review.id}>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <div>
                                        <CardTitle className="text-base">{review.period}</CardTitle>
                                        <CardDescription>
                                            {review.status === 'pending'
                                                ? `Due ${new Date(review.due_at).toLocaleDateString()}`
                                                : `Completed ${new Date(review.completed_at!).toLocaleDateString()} by ${review.completed_by?.name ?? '—'}`}
                                        </CardDescription>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <Badge variant={review.status === 'pending' ? 'destructive' : 'default'}>
                                            {review.status}
                                        </Badge>
                                        {review.status === 'pending' && (
                                            <Button className="cursor-pointer" asChild size="sm">
                                                <Link href={`/settings/access-reviews/${review.id}`}>Start Review</Link>
                                            </Button>
                                        )}
                                        {review.status === 'completed' && (
                                            <Button className="cursor-pointer" asChild size="sm" variant="outline">
                                                <Link href={`/settings/access-reviews/${review.id}`}>View</Link>
                                            </Button>
                                        )}
                                    </div>
                                </CardHeader>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
