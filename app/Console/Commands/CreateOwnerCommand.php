<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class CreateOwnerCommand extends Command
{
    protected $signature = 'pos:create-owner
        {--name= : Owner name}
        {--username= : Login username}
        {--password= : Login password}
        {--locale=en : Preferred locale (en, fa, ps)}';

    protected $description = 'Create the initial POS owner account';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Owner name');
        $username = $this->option('username') ?: $this->ask('Username');
        $password = $this->option('password') ?: $this->secret('Password');
        $locale = (string) $this->option('locale');

        if (! array_key_exists($locale, config('pos.locales', []))) {
            $this->error('Unsupported locale. Use en, fa, or ps.');

            return self::FAILURE;
        }

        if (! $name || ! $username || ! $password) {
            $this->error('Name, username, and password are required.');

            return self::FAILURE;
        }

        if (User::query()->where('username', $username)->exists()) {
            $this->error('That username already exists.');

            return self::FAILURE;
        }

        $role = Role::query()->where('name', 'owner')->first();

        if (! $role) {
            $this->error('Owner role is missing. Run php artisan db:seed first.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'username' => $username,
            'password' => $password,
            'preferred_locale' => $locale,
            'is_active' => true,
        ]);

        $user->roles()->attach($role);

        $this->info('Owner account created successfully.');

        return self::SUCCESS;
    }
}
