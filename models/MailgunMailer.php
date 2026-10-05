<?php

declare(strict_types=1);

namespace Osmium\Services\Mailgun\Models;

use Osmium\Core\Library\MailerInterface;

/**
 * Sends mail via the Mailgun HTTP API, for sites on hosting that blocks
 * outbound SMTP or clients who already have a Mailgun account. A plain curl
 * POST with HTTP basic auth, so there is no SDK to install.
 *
 * Built by MailerFactory with the site config, which supplies the shared
 * From address and name; the domain, region and API key come from this
 * service's own config.
 */
class MailgunMailer implements MailerInterface
{
    private const US_BASE_URL = 'https://api.mailgun.net/v3/%s/messages';
    private const EU_BASE_URL = 'https://api.mailgun.eu/v3/%s/messages';

    public function __construct(private object $config) {}

    /**
     * @param array<int, array{email: string, name?: string}> $recipients
     * @return array{success: bool, error?: string}
     */
    public function send(array $recipients, string $subject, string $htmlBody): array
    {
        try {
            $this->postMessage($recipients, $subject, $htmlBody);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param array<int, array{email: string, name?: string}> $recipients
     */
    private function postMessage(array $recipients, string $subject, string $htmlBody): void
    {
        $mailgun = MailgunConfig::get();

        $notReady = !MailgunConfig::isReady();
        if ($notReady) throw new \RuntimeException('Mailgun is not configured: set the sending domain and API key on the Mailgun settings page.');

        $urlTemplate = $mailgun->region === 'us' ? self::US_BASE_URL : self::EU_BASE_URL;
        $url = \sprintf($urlTemplate, $mailgun->domain);

        $email = $this->config->email;
        $fromName = $email->fromName ?? '';
        $from = $fromName !== '' ? "{$fromName} <{$email->fromAddress}>" : $email->fromAddress;

        $to = \implode(', ', \array_map(
            fn (array $recipient) => isset($recipient['name'])
                ? "{$recipient['name']} <{$recipient['email']}>"
                : $recipient['email'],
            $recipients,
        ));

        $ch = \curl_init($url);
        \curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
                'html' => $htmlBody,
            ],
            CURLOPT_USERPWD => 'api:' . $mailgun->apiKey,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = \curl_exec($ch);
        $curlError = \curl_error($ch);
        $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($curlError) throw new \RuntimeException("Mailgun send curl error: {$curlError}");

        $failed = $httpCode < 200 || $httpCode >= 300;
        if ($failed) {
            $decoded = \json_decode(json: (string) $response, associative: true);
            $error = $decoded['message'] ?? $response ?: "HTTP {$httpCode}";
            throw new \RuntimeException("Mailgun send failed ({$httpCode}): {$error}");
        }
    }
}
