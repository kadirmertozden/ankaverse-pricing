<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ChangePassword extends Command
{
    protected $signature = 'user:changepassword {email} {password}';
    protected $description = 'Change the password of a user by email';

    public function handle()
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (!$user) {
            $this->error('User not found.');
            return;
        }

        $user->password = Hash::make($this->argument('password'));
        $user->save();

        $this->info('Password updated successfully.');
    }
}
