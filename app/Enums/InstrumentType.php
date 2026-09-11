<?php

namespace App\Enums;

enum InstrumentType: string
{
    case EXECUCAO_CULTURAL = 'TERMO DE EXECUÇÃO CULTURAL';
    case FOMENTO = 'TERMO DE FOMENTO';
    case FOMENTO_SIMPLIFICADO = 'TERMO DE FOMENTO SIMPLIFICADO';
    case COLABORACAO = 'TERMO DE COLABORAÇÃO';
    case CONVENIO = 'CONVÊNIO';
    case PREMIACAO = 'PREMIAÇÃO';
    case AQUISICAO_CONTRATO = 'AQUISIÇÃO/CONTRATO';
    case PATROCINIO_CONTRATO = 'PATROCÍNIO/CONTRATO';
    case BOLSA_CULTURAL = 'TERMO DE BOLSA CULTURAL';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
