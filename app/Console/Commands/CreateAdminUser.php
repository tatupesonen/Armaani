<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdminUser extends Command
{
    /** @var string */
    protected $signature = 'user:create-admin
                            {--name=Admin : The name of the admin user}
                            {--email= : The email address}
                            {--password= : The password}';

    /** @var string */
    protected $description = 'Create an initial admin user if no users exist';

    public function handle(): int
    {
        if (User::count() > 0) {
            $this->info('Users already exist — skipping admin creation.');

            return self::SUCCESS;
        }

        $email = $this->option('email');
        $password = $this->option('password');

        if (! $email || ! $password) {
            $this->error('Both --email and --password are required.');

            return self::FAILURE;
        }

        User::create([
            'name' => $this->option('name'),
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $this->info("Admin user created: {$email}");

        return self::SUCCESS;
    }
}
