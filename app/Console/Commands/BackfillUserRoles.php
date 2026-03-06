<?php

namespace App\Console\Commands;

use App\Enums\UserRole as UserRoleEnum;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BackfillUserRoles extends Command
{
    protected $signature = 'app:backfill-user-roles
                            {--dry-run : Preview changes without writing to the database}';

    protected $description = 'Backfill user_roles for existing users based on their current agency, organization, and property associations';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $now = Carbon::now();
        $created = 0;
        $skipped = 0;

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }

        // Agency admins: all users that are scoped to an agency.
        // In the current flat model every agency-scoped user is an agency admin.
        User::query()
            ->whereNotNull('agency_id')
            ->orderBy('id')
            ->chunk(200, function ($users) use ($dryRun, $now, &$created, &$skipped): void {
                foreach ($users as $user) {
                    $exists = UserRole::query()
                        ->where('user_id', $user->id)
                        ->where('role', UserRoleEnum::AgencyAdmin->value)
                        ->where('agency_id', $user->agency_id)
                        ->exists();

                    if ($exists) {
                        $this->line("  skip  agency_admin  user={$user->id}");
                        $skipped++;

                        continue;
                    }

                    $this->line("  create  agency_admin  user={$user->id}  agency={$user->agency_id}");

                    if (! $dryRun) {
                        UserRole::query()->create([
                            'user_id' => $user->id,
                            'role' => UserRoleEnum::AgencyAdmin->value,
                            'agency_id' => $user->agency_id,
                            'organization_id' => null,
                            'property_id' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    $created++;
                }
            });

        // Org admins: extend this block once a user–organization admin relationship
        // exists in the schema (e.g. an `organization_users` pivot or an `admin_user_id`
        // column on `organizations`).
        //
        // Example:
        // Organization::query()->whereNotNull('admin_user_id')->chunk(200, function ($orgs) { ... });

        // Property admins: extend this block once a user–property admin relationship
        // exists in the schema (e.g. a `property_users` pivot or an `admin_user_id`
        // column on `properties`).
        //
        // Example:
        // Property::query()->whereNotNull('admin_user_id')->chunk(200, function ($props) { ... });

        $this->newLine();
        $this->info("Done. Created: {$created}  Skipped (already existed): {$skipped}");

        return self::SUCCESS;
    }
}
