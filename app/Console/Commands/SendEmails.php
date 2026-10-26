<?php

namespace App\Console\Commands;

use App\Services\Instantly;
use App\Services\Sender;
use Illuminate\Console\Command;
use Throwable;

/** Version 2.0: hand approved emails to Instantly and bring back replies, bounces and opt-outs. Does nothing while sending is off. */
class SendEmails extends Command
{
    protected $signature = 'cloyne:send {--sync-only : Only bring back replies, bounces and opt-outs}';

    protected $description = 'Send approved emails through Instantly and sync what happened';

    public function handle(Sender $sender): int
    {
        if (! Instantly::isConfigured()) {
            $this->info('Instantly is not connected.');

            return self::SUCCESS;
        }
        try {
            $synced = $sender->sync();
            $sent = $this->option('sync-only') ? 0 : $sender->send();
            $this->info("Handed {$sent} ".str('email')->plural($sent)." to Instantly; {$synced['updated']} updated, {$synced['replies']} new ".str('reply')->plural($synced['replies']).'.');
        } catch (Throwable $e) {
            report($e);
            $this->error($e->getMessage());
        }

        return self::SUCCESS;
    }
}
