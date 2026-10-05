<?php

declare(strict_types=1);

namespace Osmium\Services\Mailgun\Models;

/**
 * Adapts Mailgun to core's mail.providers hook (see ServiceHooks /
 * MailerFactory).
 */
class MailgunMailProvider
{
    public const ID = 'mailgun';

    /**
     * mail.providers - advertise Mailgun and whether it can send now.
     */
    public static function provider(array $payload): array
    {
        return [
            'id' => self::ID,
            'label' => 'Mailgun',
            'ready' => MailgunConfig::isReady(),
            'settingsRoute' => 'settings/mailgun/',
            'mailer' => MailgunMailer::class,
        ];
    }
}
