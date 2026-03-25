<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Model;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

final class MailService extends Model
{
    public function sendVerificationEmail(int $userId, string $email, string $token): bool
    {
        return $this->sendGenericTemplate($userId, $email, 'Verifique seu email', 'verify-account', ['token' => $token]);
    }

    public function sendWelcomeEmail(int $userId, string $email): bool { return $this->sendGenericTemplate($userId, $email, 'Bem-vindo ao Rota do Amor', 'welcome'); }
    public function sendPasswordResetEmail(int $userId, string $email, string $token): bool { return $this->sendGenericTemplate($userId, $email, 'Recuperacao de senha', 'reset-password', ['token' => $token]); }
    public function sendActivationPaymentConfirmedEmail(int $userId, string $email): bool { return $this->sendGenericTemplate($userId, $email, 'Ativacao confirmada', 'activation-confirmed'); }
    public function sendSubscriptionRenewedEmail(int $userId, string $email): bool { return $this->sendGenericTemplate($userId, $email, 'Subscricao renovada', 'subscription-renewed'); }
    public function sendSubscriptionExpiringSoonEmail(int $userId, string $email, int $daysRemaining): bool { return $this->sendGenericTemplate($userId, $email, 'Subscricao a expirar', 'subscription-expiring', ['daysRemaining' => $daysRemaining]); }
    public function sendSubscriptionExpiredEmail(int $userId, string $email): bool { return $this->sendGenericTemplate($userId, $email, 'Subscricao expirada', 'subscription-expired'); }
    public function sendIdentityVerificationApprovedEmail(int $userId, string $email): bool { return $this->sendGenericTemplate($userId, $email, 'Identidade aprovada', 'verification-approved'); }
    public function sendIdentityVerificationRejectedEmail(int $userId, string $email, string $reason): bool { return $this->sendGenericTemplate($userId, $email, 'Identidade rejeitada', 'verification-rejected', ['reason' => $reason]); }
    public function sendAccountStatusChangedEmail(int $userId, string $email, string $status): bool { return $this->sendGenericTemplate($userId, $email, 'Estado da conta alterado', 'verification-rejected', ['reason' => 'Novo estado: ' . $status]); }

    public function sendGenericTemplate(int $userId, string $to, string $subject, string $template, array $vars = []): bool
    {
        $htmlPath = dirname(__DIR__) . '/Mail/Templates/' . $template . '.php';
        if (!is_file($htmlPath)) {
            return false;
        }

        extract($vars, EXTR_SKIP);
        $appUrl = (string) Config::env('APP_URL', 'http://localhost');

        ob_start();
        require $htmlPath;
        $html = (string) ob_get_clean();

        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));

        try {
            $mail = $this->buildMailer();
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $text;
            return $mail->send();
        } catch (Exception $exception) {
            file_put_contents(dirname(__DIR__, 2) . '/storage/logs/mail.log', date('c') . ' ' . $exception->getMessage() . PHP_EOL, FILE_APPEND);
            return false;
        }
    }

    private function buildMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = (string) Config::env('MAIL_HOST');
        $mail->Port = (int) Config::env('MAIL_PORT', 587);
        $mail->SMTPAuth = true;
        $mail->Username = (string) Config::env('MAIL_USERNAME');
        $mail->Password = (string) Config::env('MAIL_PASSWORD');
        $mail->SMTPSecure = (string) Config::env('MAIL_ENCRYPTION', 'tls');
        $mail->setFrom((string) Config::env('MAIL_FROM_ADDRESS'), (string) Config::env('MAIL_FROM_NAME', 'Rota do Amor'));

        $support = (string) Config::env('MAIL_SUPPORT_ADDRESS', '');
        if ($support !== '') {
            $mail->addReplyTo($support, (string) Config::env('MAIL_SUPPORT_NAME', 'Suporte Rota do Amor'));
        }

        return $mail;
    }
}
