<?php

namespace Tests\Unit;

use App\Enums\DocumentPhase;
use App\Enums\DocumentType;
use PHPUnit\Framework\TestCase;

class DocumentTypeTest extends TestCase
{
    public function test_label_returns_short_label_for_every_case(): void
    {
        $expected = [
            DocumentType::TC->value => 'TC',
            DocumentType::ET->value => 'ET',
            DocumentType::PJ->value => 'PJ',
            DocumentType::PI->value => 'PI',
            DocumentType::PF->value => 'PF',
            DocumentType::DP->value => 'DP',
            DocumentType::DO->value => 'DO',
            DocumentType::CI->value => 'CI',
            DocumentType::JR->value => 'JR',
        ];

        foreach (DocumentType::cases() as $type) {
            $this->assertSame($expected[$type->value], $type->label());
        }
    }

    public function test_full_label_returns_descriptive_label_for_every_case(): void
    {
        $expected = [
            DocumentType::TC->value => 'Termo de Execução Cultural',
            DocumentType::ET->value => 'Extrato',
            DocumentType::PJ->value => 'Parecer Jurídico',
            DocumentType::PI->value => 'Parecer Orçamentário Inicial',
            DocumentType::PF->value => 'Parecer Orçamentário Final',
            DocumentType::DP->value => 'Despacho de Pagamento',
            DocumentType::DO->value => 'Despacho Orçamentário',
            DocumentType::CI->value => 'Comunicação Interna',
            DocumentType::JR->value => 'Parecer Jurídico Referencial',
        ];

        foreach (DocumentType::cases() as $type) {
            $this->assertSame($expected[$type->value], $type->fullLabel());
        }
    }

    public function test_phase_maps_each_type_to_expected_phase(): void
    {
        $this->assertSame(DocumentPhase::OPENING, DocumentType::CI->phase());
        $this->assertSame(DocumentPhase::FORMALIZATION, DocumentType::TC->phase());
        $this->assertSame(DocumentPhase::FORMALIZATION, DocumentType::ET->phase());
        $this->assertSame(DocumentPhase::FORMALIZATION, DocumentType::PJ->phase());
        $this->assertSame(DocumentPhase::BUDGET, DocumentType::PI->phase());
        $this->assertSame(DocumentPhase::BUDGET, DocumentType::PF->phase());
        $this->assertSame(DocumentPhase::BUDGET, DocumentType::DO->phase());
        $this->assertSame(DocumentPhase::PAYMENT, DocumentType::DP->phase());
        $this->assertSame(DocumentPhase::JURIDICAL, DocumentType::JR->phase());
    }

    public function test_is_notice_level_identifies_pi_and_jr(): void
    {
        $this->assertTrue(DocumentType::PI->isNoticeLevel());
        $this->assertTrue(DocumentType::JR->isNoticeLevel());
        $this->assertFalse(DocumentType::PF->isNoticeLevel());
        $this->assertFalse(DocumentType::PJ->isNoticeLevel());
    }

    public function test_is_juridical_reference_identifies_jr(): void
    {
        $this->assertTrue(DocumentType::JR->isJuridicalReference());
        $this->assertFalse(DocumentType::PJ->isJuridicalReference());
        $this->assertFalse(DocumentType::PI->isJuridicalReference());
    }

    public function test_required_for_formalization_advance_returns_tc_et_pj(): void
    {
        $this->assertSame(
            [DocumentType::TC, DocumentType::ET, DocumentType::PJ],
            DocumentType::requiredForFormalizationAdvance()
        );
    }
}
