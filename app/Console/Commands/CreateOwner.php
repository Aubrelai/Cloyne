<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Locked out? Run this on the computer (or server) where Cloyne runs.
 * It makes the email an Owner and gives it a new temporary password.
 */
class CreateOwner extends Command
{
    protected $signature = 'cloyne:owner {email : Email for the Owner account} {--name= : Name, for a new account}';

    protected $description = 'Create an Owner account, or reset an existing account to Owner with a new temporary password';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That isn’t a valid email.');

            return self::FAILURE;
        }

        $password = User::temporaryPassword();
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update(['role' => 'owner', 'password' => $password, 'must_change_password' => true, 'remember_token' => null]);
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            $this->info("{$user->name} is now an Owner with a new temporary password.");
        } else {
            $user = User::create([
                'name' => $this->option('name') ?: ucfirst(strstr($email, '@', true)),
                'email' => $email,
                'password' => $password,
                'role' => 'owner',
                'must_change_password' => true,
            ]);
            $this->info("Owner account created for {$user->email}.");
        }

        $this->line("Temporary password: {$password}");
        $this->line('Sign in with it, then pick your own password.');

        return self::SUCCESS;
    }
}
