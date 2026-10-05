<?php

declare(strict_types=1);

namespace Osmium\Services\Mailgun\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\Mailgun\Models\MailgunConfig;

/**
 * Mailgun settings controller - full-page form POST/redirect, same shape as
 * TurnstileController.
 *
 * Routes:
 *   - index() → /admin/settings/mailgun/  (GET shows the form, POST saves it)
 */
class MailgunController extends AdminController
{
    private const CONFIG_FILE_PATH = 'app/config/services/mailgun.json.php';

    public function index(): void
    {
        $isPost = $this->isPost();
        if ($isPost) $this->handleSubmit();

        $this->data['admin']['config']['mailgun'] = (array) MailgunConfig::get();
        $this->data['admin']['mailgunRegions'] = MailgunConfig::REGIONS;
        $this->data['admin']['settingsSaved'] = $_SESSION['mailgun_settings_saved'] ?? false;
        $this->data['admin']['settingsError'] = $_SESSION['mailgun_settings_error'] ?? false;
        unset($_SESSION['mailgun_settings_saved'], $_SESSION['mailgun_settings_error']);

        $this->setView('mailgun/index.phtml');
    }

    private function handleSubmit(): void
    {
        $csrfValid = $this->admin->auth->validateCsrf();
        if (!$csrfValid) {
            $_SESSION['mailgun_settings_error'] = 'Invalid form submission. Please try again.';
            $this->redirect('settings/mailgun/');
        }

        $domain = \trim($_POST['domain'] ?? '');

        $region = $_POST['region'] ?? 'eu';
        $regionValid = \array_key_exists($region, MailgunConfig::REGIONS);
        if (!$regionValid) $region = 'eu';

        $postedApiKey = \trim($_POST['api_key'] ?? '');
        $apiKey = $postedApiKey === '' ? (string) (MailgunConfig::get()->apiKey ?? '') : $postedApiKey; // Blank keeps the stored secret

        $this->saveConfig($domain, $region, $apiKey);

        $this->admin->model->changelog->log(
            description: 'Updated Mailgun settings',
            recordType: 'settings',
        );

        MailgunConfig::clearCache();

        $_SESSION['mailgun_settings_saved'] = true;
        $this->redirect('settings/mailgun/');
    }

    private function saveConfig(string $domain, string $region, string $apiKey): void
    {
        $configExists = \file_exists(self::CONFIG_FILE_PATH);
        if (!$configExists) $this->ensureConfigDirectoryExists();

        $newJson = \json_encode(
            value: ['mailgun' => [
                'domain' => $domain,
                'region' => $region,
                'apiKey' => $apiKey,
            ]],
            flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        \file_put_contents(self::CONFIG_FILE_PATH, "<?php exit(); ?>\n" . $newJson . "\n");
    }

    private function ensureConfigDirectoryExists(): void
    {
        $dir = \dirname(self::CONFIG_FILE_PATH);
        $alreadyExists = \is_dir($dir);
        if (!$alreadyExists) \mkdir(directory: $dir, permissions: 0755, recursive: true);
    }

    private function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}
