<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\User;
use App\Models\UserProfile;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerContact;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a single ready-to-use User + Customer + Project chain for local development.
 *
 * Run manually:  php artisan db:seed --class=DemoSeeder
 *
 * The login credentials are fixed so the account is easy to reuse; everything
 * else is generated with Faker via the model factories.
 */
final class DemoSeeder extends Seeder
{
    private const string USERNAME = 'demo@rylees.test';

    private const string PASSWORD = 'password';

    public function run(): void
    {
        $organisation = Organisation::factory()->create();

        $user = User::factory()->create([
            'username' => self::USERNAME,
        ]);

        UserProfile::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $organisation->id,
        ]);

        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $organisation->id,
            'industry_id' => DB::table('industry_types')->value('id'),
        ]);

        $contact = CustomerContact::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $customer->update(['main_contact_id' => $contact->id]);

        $project = Project::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->command->info('Demo data seeded:');
        $this->command->table(
            ['Resource', 'Value'],
            [
                ['User login', self::USERNAME],
                ['User password', self::PASSWORD],
                ['User API key', $user->api_key],
                ['Organisation', $organisation->name],
                ['Customer ID', $customer->id],
                ['Project name', $project->name],
                ['Project key', $project->key],
                ['Project token', $project->token],
            ],
        );
    }
}
