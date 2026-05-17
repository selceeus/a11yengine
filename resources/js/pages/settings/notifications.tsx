import { Transition } from '@headlessui/react';
import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { BreadcrumbItem } from '@/types';
import NotificationPreferencesController from '@/actions/App/Http/Controllers/Settings/NotificationPreferencesController';
import { edit } from '@/routes/notification-preferences';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Notification preferences',
        href: edit().url,
    },
];

interface Props {
    preferences: Record<string, boolean>;
    notificationTypes: Record<string, string>;
    channels: Record<string, string>;
}

export default function Notifications({
    preferences,
    notificationTypes,
    channels,
}: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notification preferences" />

            <h1 className="sr-only">Notification Preferences</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Notification preferences"
                        description="Choose which notifications you'd like to receive and how"
                    />

                    <Form
                        {...NotificationPreferencesController.update.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful }) => (
                            <>
                                {Object.entries(notificationTypes).map(
                                    ([typeKey, typeLabel]) => (
                                        <div
                                            key={typeKey}
                                            className="space-y-3"
                                        >
                                            <h3 className="text-sm font-medium">
                                                {typeLabel}
                                            </h3>
                                            <div className="space-y-2">
                                                {Object.entries(channels).map(
                                                    ([
                                                        channelKey,
                                                        channelLabel,
                                                    ]) => {
                                                        const prefKey = `${typeKey}.${channelKey}`;
                                                        const enabled =
                                                            preferences[
                                                                prefKey
                                                            ] ?? true;

                                                        return (
                                                            <div
                                                                key={prefKey}
                                                                className="flex items-center justify-between rounded border p-3"
                                                            >
                                                                <Label
                                                                    htmlFor={prefKey}
                                                                    className="cursor-pointer"
                                                                >
                                                                    {channelLabel}
                                                                </Label>
                                                                <input
                                                                    type="hidden"
                                                                    name={`preferences[${prefKey}]`}
                                                                    value="0"
                                                                />
                                                                <Switch
                                                                    id={prefKey}
                                                                    name={`preferences[${prefKey}]`}
                                                                    defaultChecked={enabled}
                                                                    value="1"
                                                                />
                                                            </div>
                                                        );
                                                    },
                                                )}
                                            </div>
                                        </div>
                                    ),
                                )}

                                <div className="flex items-center gap-4">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Save preferences
                                    </Button>

                                    <Transition
                                        show={recentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-neutral-600">
                                            Saved.
                                        </p>
                                    </Transition>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
