<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\User;

/**
 * Two demo accounts: admin@example.com for the Control Panel, and
 * ada@example.com, a shopper. Both use the password "password", so this
 * only runs on local sites.
 */
class DemoUsers extends Command
{
    protected $signature = 'demo:users';

    protected $description = 'Make the demo shop\'s admin and shopper accounts (local only)';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->components->error('Demo accounts have a known password, so they\'re only made on local sites.');

            return self::FAILURE;
        }

        foreach ([['admin@example.com', 'Demo Admin', true], ['ada@example.com', 'Ada Lovelace', false]] as [$email, $name, $super]) {
            $user = User::findByEmail($email) ?? User::make()->email($email);
            $user->set('name', $name)->password('password');
            $super ? $user->makeSuper() : $user->set('super', false);
            $user->save();

            $this->components->twoColumnDetail($email, $super ? 'Control Panel' : 'Shopper');
        }

        return self::SUCCESS;
    }
}
