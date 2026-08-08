<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Mail\MailerInterface;

/** Mailer di test: registra le chiamate senza inviare nulla. */
final class FakeMailer implements MailerInterface
{
    /** @var array<int, array{to:string, template:string, vars:array}> */
    public array $sent = [];

    public function send(
        string $toEmail,
        string $templateKey,
        array $vars = [],
        ?string $relatedType = null,
        ?int $relatedId = null,
        array $attachments = [],
    ): bool {
        $this->sent[] = ['to' => $toEmail, 'template' => $templateKey, 'vars' => $vars, 'attachments' => array_map(fn ($a) => $a['name'], $attachments)];
        return true;
    }
}
