<?php

namespace App\EmailTools;

use App\EmailTools\Contracts\EmailTool;
use App\EmailTools\Providers\MailchimpProvider;
use Illuminate\Support\Manager;

/**
 * Hands out email tools by name. Add a tool by adding a create{Name}Driver method and its config.
 *
 * @method EmailTool driver(?string $driver = null)
 */
class EmailToolManager extends Manager
{
    /**
     * Whether an email tool is connected at all.
     */
    public function isEnabled(): bool
    {
        return filled($this->config->get('email_tools.default'));
    }

    /**
     * Get the default tool name.
     */
    public function getDefaultDriver(): ?string
    {
        return $this->config->get('email_tools.default');
    }

    protected function createMailchimpDriver(): MailchimpProvider
    {
        return new MailchimpProvider($this->config->get('email_tools.tools.mailchimp'));
    }
}
