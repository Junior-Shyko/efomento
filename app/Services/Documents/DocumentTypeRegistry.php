<?php

namespace App\Services\Documents;

use App\Enums\DocumentPhase;
use App\Enums\DocumentType;
use App\Exceptions\Domain\BusinessRuleException;

class DocumentTypeRegistry
{
    private array $map = [
        'ci+opening' => [
            'label' => 'Comunicação Interna',
            'requires_sign' => true,
            'requires_legal' => false,
        ],
        'tc+formalization' => [
            'label' => 'Termo de Execução Cultural',
            'requires_sign' => true,
            'requires_legal' => false,
        ],
        'et+formalization' => [
            'label' => 'Extrato do Termo',
            'requires_sign' => false,
            'requires_legal' => false,
        ],
        'pj+formalization' => [
            'label' => 'Parecer Jurídico',
            'requires_sign' => true,
            'requires_legal' => true,
        ],
        'jr+juridical' => [
            'label' => 'Parecer Jurídico Referencial',
            'requires_sign' => true,
            'requires_legal' => true,
        ],
        'pi+budget' => [
            'label' => 'Parecer Orçamentário Inicial',
            'requires_sign' => true,
            'requires_legal' => false,
        ],
        'pf+budget' => [
            'label' => 'Parecer Orçamentário Final',
            'requires_sign' => true,
            'requires_legal' => false,
        ],
        'do+budget' => [
            'label' => 'Despacho Orçamentário',
            'requires_sign' => true,
            'requires_legal' => true,
        ],
        'dp+payment' => [
            'label' => 'Despacho de Pagamento',
            'requires_sign' => false,
            'requires_legal' => false,
        ],
    ];

    public function resolve(DocumentType $type, DocumentPhase $phase): array
    {
        $key = "{$type->value}+{$phase->value}";

        if (! isset($this->map[$key])) {
            throw new BusinessRuleException(
                "Combinação de tipo e fase inválida: tipo={$type->value}, fase={$phase->value}."
            );
        }

        return $this->map[$key];
    }
}
