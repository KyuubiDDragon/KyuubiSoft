<?php

declare(strict_types=1);

namespace App\Core\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Psr\Log\LoggerInterface;

class MailService
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Send an email via a user-configured SMTP account.
     *
     * @param array $smtpConfig Keys: host, port, encryption (tls/ssl/none), username, password
     * @param string $fromEmail
     * @param string $fromName
     * @param array $to Array of ['email' => '...', 'name' => '...']
     * @param array $cc Array of ['email' => '...', 'name' => '...']
     * @param string $subject
     * @param string $htmlBody
     * @param string $textBody
     * @throws \RuntimeException on failure
     */
    public function send(
        array $smtpConfig,
        string $fromEmail,
        string $fromName,
        array $to,
        array $cc,
        string $subject,
        string $htmlBody,
        string $textBody
    ): void {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $smtpConfig['host'];
            $mail->Port = (int) $smtpConfig['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtpConfig['username'];
            $mail->Password = $smtpConfig['password'];
            $mail->CharSet = 'UTF-8';

            $encryption = strtolower($smtpConfig['encryption'] ?? 'tls');
            if ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom($fromEmail, $fromName);

            foreach ($to as $recipient) {
                $mail->addAddress(
                    $recipient['email'],
                    $recipient['name'] ?? ''
                );
            }

            foreach ($cc as $recipient) {
                $mail->addCC(
                    $recipient['email'],
                    $recipient['name'] ?? ''
                );
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;

            $mail->send();
        } catch (PHPMailerException $e) {
            $this->logger?->error('Mail sending failed', [
                'error' => $e->getMessage(),
                'to' => array_column($to, 'email'),
                'subject' => $subject,
            ]);
            throw new \RuntimeException('E-Mail konnte nicht gesendet werden: ' . $e->getMessage());
        }
    }

    /**
     * Send a system email (password reset, notifications) using system SMTP config from env.
     *
     * @throws \RuntimeException on failure or if SMTP not configured
     */
    public function sendSystemMail(string $toEmail, string $subject, string $htmlBody): void
    {
        $host = $_ENV['SMTP_HOST'] ?? '';
        $username = $_ENV['SMTP_USERNAME'] ?? '';

        if (empty($host) || empty($username)) {
            $this->logger?->warning('System email not sent - SMTP not configured', [
                'to' => $toEmail,
                'subject' => $subject,
            ]);
            return;
        }

        $this->send(
            [
                'host' => $host,
                'port' => (int) ($_ENV['SMTP_PORT'] ?? 587),
                'encryption' => $_ENV['SMTP_ENCRYPTION'] ?? 'tls',
                'username' => $username,
                'password' => $_ENV['SMTP_PASSWORD'] ?? '',
            ],
            $_ENV['SMTP_FROM_ADDRESS'] ?? $username,
            $_ENV['SMTP_FROM_NAME'] ?? 'KyuubiSoft',
            [['email' => $toEmail]],
            [],
            $subject,
            $htmlBody,
            strip_tags($htmlBody)
        );
    }

    /**
     * Test SMTP connection without sending.
     *
     * @return array{status: string, message: string}
     */
    public function testSmtpConnection(string $host, int $port, string $encryption, string $username, string $password): array
    {
        $smtp = new SMTP();
        $smtp->setTimeout(10);

        try {
            $connectHost = $host;
            if (strtolower($encryption) === 'ssl') {
                $connectHost = 'ssl://' . $host;
            }

            if (!$smtp->connect($connectHost, $port)) {
                return ['status' => 'error', 'message' => 'SMTP-Verbindung fehlgeschlagen: Server nicht erreichbar'];
            }

            if (!$smtp->hello(gethostname() ?: 'localhost')) {
                $smtp->quit();
                return ['status' => 'error', 'message' => 'SMTP EHLO fehlgeschlagen'];
            }

            if (strtolower($encryption) === 'tls') {
                if (!$smtp->startTLS()) {
                    $smtp->quit();
                    return ['status' => 'error', 'message' => 'STARTTLS fehlgeschlagen'];
                }
                $smtp->hello(gethostname() ?: 'localhost');
            }

            if (!empty($username) && !empty($password)) {
                if (!$smtp->authenticate($username, $password)) {
                    $smtp->quit();
                    return ['status' => 'error', 'message' => 'SMTP-Authentifizierung fehlgeschlagen: Benutzername oder Passwort falsch'];
                }
            }

            $smtp->quit();
            return ['status' => 'success', 'message' => 'SMTP-Verbindung erfolgreich'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'SMTP-Fehler: ' . $e->getMessage()];
        }
    }

    /**
     * Test IMAP connection.
     *
     * @return array{status: string, message: string}
     */
    public function testImapConnection(string $host, int $port, string $encryption, string $username, string $password): array
    {
        if (function_exists('imap_open')) {
            return $this->testImapViaExtension($host, $port, $encryption, $username, $password);
        }

        return $this->testImapViaSocket($host, $port, $encryption);
    }

    private function testImapViaExtension(string $host, int $port, string $encryption, string $username, string $password): array
    {
        $flags = '/imap';
        $enc = strtolower($encryption);

        if ($enc === 'ssl') {
            $flags .= '/ssl';
        } elseif ($enc === 'tls') {
            $flags .= '/tls';
        } else {
            $flags .= '/notls';
        }
        $flags .= '/novalidate-cert';

        $mailbox = '{' . $host . ':' . $port . $flags . '}';

        $previousTimeout = @imap_timeout(IMAP_OPENTIMEOUT);
        @imap_timeout(IMAP_OPENTIMEOUT, 10);

        $connection = @imap_open($mailbox, $username, $password, 0, 1);

        if ($previousTimeout !== false) {
            @imap_timeout(IMAP_OPENTIMEOUT, $previousTimeout);
        }

        if ($connection) {
            @imap_close($connection);
            return ['status' => 'success', 'message' => 'IMAP-Verbindung erfolgreich'];
        }

        $errors = imap_errors() ?: [];
        $lastError = !empty($errors) ? end($errors) : 'Unbekannter Fehler';

        return ['status' => 'error', 'message' => 'IMAP-Verbindung fehlgeschlagen: ' . $lastError];
    }

    private function testImapViaSocket(string $host, int $port, string $encryption): array
    {
        $connectHost = strtolower($encryption) === 'ssl' ? 'ssl://' . $host : $host;
        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            $connectHost . ':' . $port,
            $errno,
            $errstr,
            10
        );

        if (!$socket) {
            return ['status' => 'error', 'message' => "IMAP-Verbindung fehlgeschlagen: {$errstr}"];
        }

        $greeting = @fgets($socket, 1024);
        @fclose($socket);

        if ($greeting && str_starts_with(trim($greeting), '* OK')) {
            return ['status' => 'success', 'message' => 'IMAP-Verbindung erfolgreich (Socket-Test, Credentials nicht geprueft)'];
        }

        return ['status' => 'error', 'message' => 'IMAP-Server antwortet nicht korrekt'];
    }
}
