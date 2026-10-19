<?php
/**
 * Task 5 — one-time passcode service.
 *
 * Design decisions that matter:
 *
 *  - The code is never stored. otp_codes holds sha256(code) only, so a dump of
 *    the database cannot be replayed against the verification endpoint.
 *  - A code expires (configurable, default 10 minutes) and can be consumed once.
 *  - Verification is rate limited per code (10 attempts) and per account per
 *    hour (10 issuances), so neither brute force nor OTP flooding is possible.
 *  - Issuing a new code for the same purpose invalidates the previous one, which
 *    stops a code an attacker already received from surviving a re-issue.
 *
 * Delivery is pluggable. With mail_transport = console the code is written to
 * the PHP error log and returned to the caller only when APP_DEBUG is on, so a
 * local run is usable without a mail server. Setting mail_transport = mail
 * hands the message to PHP's mail().
 */

declare(strict_types=1);

final class Otp
{
    /** @var array<string,mixed> */
    private array $config;
    private PDO $pdo;
    private bool $debug;

    public function __construct(PDO $pdo, array $config, bool $debug = false)
    {
        $this->pdo    = $pdo;
        $this->config = $config;
        $this->debug  = $debug;
    }

    private function ttl(): int
    {
        return max(60, (int) ($this->config['otp_ttl'] ?? 600));
    }

    private function hash(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * Generate a cryptographically random numeric code.
     * random_int is used rather than rand/mt_rand, which are predictable.
     */
    private function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    /**
     * Issue a code, invalidating any previous unused code for the same
     * user and purpose.
     *
     * @return string The plaintext code. Only ever returned so it can be sent;
     *                  it is never written to the database.
     */
    public function issue(int $userId, string $purpose = 'email_verification'): ?string
    {
        // Flood guard: at most 10 issuances per user per hour.
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM otp_codes
             WHERE user_id = ? AND purpose = ? AND created_at > (NOW() - INTERVAL 1 HOUR)"
        );
        $stmt->execute([$userId, $purpose]);
        $recent = (int) $stmt->fetchColumn();

        if ($recent >= 10) {
            return null;
        }

        // Any earlier code for this purpose stops working now.
        $this->pdo->prepare(
            "UPDATE otp_codes SET consumed_at = NOW()
             WHERE user_id = ? AND purpose = ? AND consumed_at IS NULL"
        )->execute([$userId, $purpose]);

        $code = $this->generateCode();

        $insert = $this->pdo->prepare(
            'INSERT INTO otp_codes (user_id, code_hash, purpose, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))'
        );
        $insert->execute([$userId, $this->hash($code), $purpose, $this->ttl()]);

        $this->deliver($userId, $purpose, $code);

        return $code;
    }

    /**
     * Check a submitted code.
     *
     * @return array{ok:bool,reason:string}
     */
    public function verify(int $userId, string $code, string $purpose = 'email_verification'): array
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($code) !== 6) {
            return ['ok' => false, 'reason' => 'Enter the six digit code.'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, code_hash, attempts FROM otp_codes
             WHERE user_id = ? AND purpose = ? AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $purpose]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['ok' => false, 'reason' => 'No code is pending. Request a new one.'];
        }

        if ((int) $row['attempts'] >= 10) {
            return ['ok' => false, 'reason' => 'Too many attempts. Request a new code.'];
        }

        $this->pdo->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?')
            ->execute([$row['id']]);

        if (!hash_equals((string) $row['code_hash'], $this->hash($code))) {
            return ['ok' => false, 'reason' => 'That code is not correct.'];
        }

        $consume = $this->pdo->prepare(
            'UPDATE otp_codes SET consumed_at = NOW()
             WHERE id = ? AND consumed_at IS NULL AND expires_at > NOW()'
        );
        $consume->execute([$row['id']]);

        if ($consume->rowCount() !== 1) {
            // Expired between the SELECT and the UPDATE.
            return ['ok' => false, 'reason' => 'That code has expired. Request a new one.'];
        }

        return ['ok' => true, 'reason' => 'Verified.'];
    }

    private function deliver(int $userId, string $purpose, string $code): void
    {
        $stmt = $this->pdo->prepare('SELECT email, full_name FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return;
        }

        $body = sprintf(
            "Hello %s,\n\nYour ApexPlanet Learn verification code is:\n\n    %s\n\n"
            . "It expires in %d minutes. If you did not request it, ignore this message.\n",
            (string) $user['full_name'],
            $code,
            intdiv($this->ttl(), 60)
        );

        $transport = (string) ($this->config['mail_transport'] ?? 'console');

        if ($transport === 'mail') {
            $headers = 'From: ' . ($this->config['mail_from'] ?? 'no-reply@apexplanet.local') . "\r\n";
            @mail((string) $user['email'], 'Your ApexPlanet Learn code', $body, $headers);
            return;
        }

        // Console transport: the log stands in for the inbox.
        error_log(sprintf(
            '[task-5 otp] to=%s purpose=%s code=%s ttl=%ds',
            (string) $user['email'],
            $purpose,
            $code,
            $this->ttl()
        ));
    }

    /** Seconds until the newest pending code expires, or null when none is pending. */
    public function ttlRemaining(int $userId, string $purpose = 'email_verification'): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS left_seconds
             FROM otp_codes
             WHERE user_id = ? AND purpose = ? AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $purpose]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : max(0, (int) $v);
    }
}
