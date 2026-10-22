<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\TwoFactor;

/**
 * Draws backup codes, and says how a typed code is compared.
 *
 * Ten codes of ten characters (`K7QM2-XW9RT`), from an alphabet without the characters a person
 * misreads — no `0`/`O`, no `1`/`I`. Thirty-two symbols, ten draws: fifty bits a code, and the
 * sign-in limiter allows five wrong codes per quarter of an hour.
 *
 * No dependency: an application with its own two-factor implementation uses it as is.
 */
final class BackupCodeGenerator
{
    public const int COUNT = 10;

    private const string ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * @return list<string>
     */
    public function generate(int $count = self::COUNT): array
    {
        $codes = [];
        while (\count($codes) < $count) {
            $code = '';
            for ($i = 0; $i < 10; ++$i) {
                $code .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
            }
            $codes[$code] = substr($code, 0, 5).'-'.substr($code, 5);
        }

        return array_values($codes);
    }

    /**
     * What is compared: capitals, letters and digits only — `k7qm2 xw9rt` is `K7QM2-XW9RT`.
     */
    public static function normalize(string $code): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($code));
    }

    /**
     * What is stored. SHA-256 rather than a password hash: the codes carry fifty random bits, the
     * check runs once per stored code at every sign-in attempt, and the TOTP secret beside them is
     * in clear anyway — what the hash buys is that a code is never readable once shown.
     */
    public static function hash(string $code): string
    {
        return hash('sha256', self::normalize($code));
    }
}
