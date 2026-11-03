<?php

namespace App\Console\Commands;

use App\EmailTools\EmailToolManager;
use App\EmailTools\Exceptions\EmailToolException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('email-tools:prepare')]
#[Description('Set up what the connected email tool needs before it can take our data, such as audience fields')]
class PrepareEmailTool extends Command
{
    /**
     * Safe to run again: anything already set up is left alone.
     */
    public function handle(EmailToolManager $tools): int
    {
        if (! $tools->isEnabled()) {
            $this->error('No email tool is connected. Set EMAIL_TOOL first.');

            return self::FAILURE;
        }

        try {
            $changes = $tools->driver()->prepare();
        } catch (EmailToolException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($changes as $change) {
            $this->line($change);
        }

        $this->info($changes === [] ? 'Everything was already set up.' : 'Done.');

        return self::SUCCESS;
    }
}
