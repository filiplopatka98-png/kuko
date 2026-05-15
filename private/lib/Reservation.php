<?php
declare(strict_types=1);
namespace Kuko;

final class Reservation
{
    public const PACKAGES = ['mini', 'maxi', 'closed'];
    public const STATUSES = ['pending', 'confirmed', 'cancelled'];

    /** @return array<string,string> field => error message */
    public static function validate(array $d): array
    {
        $errors = [];

        $pkg = (string)($d['package'] ?? '');
        if (!in_array($pkg, self::PACKAGES, true)) {
            $errors['package'] = 'Neznámy balíček.';
        }

        $date = (string)($d['wished_date'] ?? '');
        $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            $errors['wished_date'] = 'Neplatný dátum.';
        } elseif ($dateObj < new \DateTimeImmutable('today')) {
            $errors['wished_date'] = 'Dátum nemôže byť v minulosti.';
        }

        $time = (string)($d['wished_time'] ?? '');
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
            $errors['wished_time'] = 'Neplatný čas (formát HH:MM).';
        }

        $kids = filter_var($d['kids_count'] ?? null, FILTER_VALIDATE_INT);
        if ($kids === false || $kids < 1 || $kids > 50) {
            $errors['kids_count'] = 'Počet detí musí byť 1 – 50.';
        }

        $name = trim((string)($d['name'] ?? ''));
        $len = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        if ($len < 2 || $len > 120) {
            $errors['name'] = 'Meno musí mať 2 – 120 znakov.';
        }

        $phone = (string)($d['phone'] ?? '');
        $phoneDigits = str_replace([' ', '(', ')', '/', '-'], '', $phone);
        if (!preg_match('/^(\+421|0)[0-9]{9}$/', $phoneDigits)) {
            $errors['phone'] = 'Zadajte platné slovenské telefónne číslo (napr. +421 915 319 934 alebo 0915 319 934).';
        }

        $email = (string)($d['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Neplatný e-mail.';
        }

        $note = (string)($d['note'] ?? '');
        $noteLen = function_exists('mb_strlen') ? mb_strlen($note) : strlen($note);
        if ($noteLen > 1000) {
            $errors['note'] = 'Poznámka môže mať max 1000 znakov.';
        }

        return $errors;
    }
}
