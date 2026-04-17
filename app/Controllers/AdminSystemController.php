<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\SettingsService;
use Throwable;

final class AdminSystemController extends Controller
{
    public function __construct(
        private readonly AuditService $audit = new AuditService(),
        private readonly SettingsService $settings = new SettingsService()
    )
    {
    }

    public function index(): void
    {
        $warnings = [];
        $rows = $this->settings->listAllSafe();
        if ($rows === []) {
            $boot = $this->settings->bootstrapMinimumSettings();
            if (($boot['failed'] ?? []) !== []) {
                $warnings[] = 'Configurações mínimas não puderam ser totalmente carregadas neste host.';
            }
            $rows = $this->settings->listAllSafe();
            if ($rows === []) {
                $warnings[] = 'A tabela de configurações ainda não está pronta. O sistema seguirá em modo seguro.';
            }
        }

        if (Request::input('format') === 'json') {
            Response::json(['settings' => $rows, 'warnings' => $warnings]);
        }

        $this->view('admin/settings', ['title' => 'Admin · Configurações', 'settings' => $rows, 'warnings' => $warnings]);
    }

    public function update(): void
    {
        $key = trim((string) Request::input('setting_key', ''));
        $value = (string) Request::input('setting_value', '');
        $type = (string) Request::input('value_type', 'string');
        if ($key === '') {
            Response::json(['ok' => false, 'message' => 'setting_key é obrigatório'], 422);
        }

        $this->settings->ensureSiteSettingsTable();

        try {
            $stmt = \App\Core\Database::connection()->prepare('INSERT INTO site_settings (setting_key,setting_value,value_type,updated_at) VALUES (:k,:v,:t,NOW()) ON DUPLICATE KEY UPDATE setting_value=:v,value_type=:t,updated_at=NOW()');
            $stmt->execute([':k' => $key, ':v' => $value, ':t' => $type]);
        } catch (Throwable $exception) {
            error_log('[admin.settings_update_failed] key=' . $key . ' error=' . $exception->getMessage());
            Response::json(['ok' => false, 'message' => 'Não foi possível atualizar a configuração neste momento.'], 500);
        }

        try {
            $this->audit->logAdminEvent((int) Session::get('admin_id', 0), 'site_setting_updated', 'site_setting', null, ['key' => $key, 'type' => $type]);
        } catch (Throwable $exception) {
            error_log('[admin.settings_audit_failed] key=' . $key . ' error=' . $exception->getMessage());
        }
        Response::json(['ok' => true]);
    }
}
