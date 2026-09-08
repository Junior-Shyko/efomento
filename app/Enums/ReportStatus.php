<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ReportStatus: string implements HasLabel
{
    use HasOptions;

    case NAO_APLICA = 'NAO_APLICA';
    case SEM_CADASTRO = 'SEM_CADASTRO';
    case REGULAR_E_ADIMPLENTE = 'REGULAR E_ADIMPLENTE';
    case REGULAR_E_INADIMPLENTE = 'REGULAR E_INADIMPLENTE';
    case IRREGULAR_E_ADIMPLENTE = 'IRREGULAR E_ADIMPLENTE';
    case IRREGULAR_E_INADIMPLENTE = 'IRREGULAR E_INADIMPLENTE';

    public function label(): string
    {
        return match ($this) {
            self::NAO_APLICA => 'Não se aplica',
            self::SEM_CADASTRO => 'Sem Cadastro',
            self::REGULAR_E_ADIMPLENTE => 'Regular e Adimplente',
            self::REGULAR_E_INADIMPLENTE => 'Regular e Inadimplente',
            self::IRREGULAR_E_ADIMPLENTE => 'Irregular e Adimplente',
            self::IRREGULAR_E_INADIMPLENTE => 'Irregular e Inadimplente',
        };
    }

    public static function tryFromLoose(mixed $value): ?self
    {
        if (blank($value)) {
            return null;
        }

        $clean = mb_strtoupper(trim((string) $value));
        $clean = preg_replace('/\s+/', ' ', $clean);

        return match (true) {
            str_contains($clean, 'SEM CADASTRO') => self::SEM_CADASTRO,
            str_contains($clean, 'NÃO SE APLICA') || str_contains($clean, 'NAO SE APLICA') => self::NAO_APLICA,
            str_contains($clean, 'IRREGULAR') && (str_contains($clean, 'INADIMPL') || str_contains($clean, 'INADIMPLE')) => self::IRREGULAR_E_INADIMPLENTE,
            str_contains($clean, 'IRREGULAR') && (str_contains($clean, 'ADIMPL') || str_contains($clean, 'ADIMPLE')) => self::IRREGULAR_E_ADIMPLENTE,
            str_contains($clean, 'REGULAR') && (str_contains($clean, 'INADIMPL') || str_contains($clean, 'INADIMPLE')) => self::REGULAR_E_INADIMPLENTE,
            str_contains($clean, 'REGULAR') && (str_contains($clean, 'ADIMPL') || str_contains($clean, 'ADIMPLE')) => self::REGULAR_E_ADIMPLENTE,
            default => self::tryFrom($clean),
        };
    }
}
