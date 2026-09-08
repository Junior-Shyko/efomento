<?php

namespace Tests\Unit;

use App\Enums\ReportStatus;
use PHPUnit\Framework\TestCase;

class ReportStatusTest extends TestCase
{
    public function test_report_status_labels(): void
    {
        $this->assertSame('Não se aplica', ReportStatus::NAO_APLICA->label());
        $this->assertSame('Sem Cadastro', ReportStatus::SEM_CADASTRO->label());
        $this->assertSame('Regular e Adimplente', ReportStatus::REGULAR_E_ADIMPLENTE->label());
        $this->assertSame('Regular e Inadimplente', ReportStatus::REGULAR_E_INADIMPLENTE->label());
        $this->assertSame('Irregular e Adimplente', ReportStatus::IRREGULAR_E_ADIMPLENTE->label());
        $this->assertSame('Irregular e Inadimplente', ReportStatus::IRREGULAR_E_INADIMPLENTE->label());
    }

    public function test_try_from_loose_returns_null_on_blank(): void
    {
        $this->assertNull(ReportStatus::tryFromLoose(null));
        $this->assertNull(ReportStatus::tryFromLoose(''));
        $this->assertNull(ReportStatus::tryFromLoose('   '));
    }

    public function test_try_from_loose_maps_sem_cadastro(): void
    {
        $this->assertSame(ReportStatus::SEM_CADASTRO, ReportStatus::tryFromLoose('SEM CADASTRO'));
        $this->assertSame(ReportStatus::SEM_CADASTRO, ReportStatus::tryFromLoose('sem cadastro na receita'));
    }

    public function test_try_from_loose_maps_nao_se_aplica(): void
    {
        $this->assertSame(ReportStatus::NAO_APLICA, ReportStatus::tryFromLoose('NÃO SE APLICA'));
        $this->assertSame(ReportStatus::NAO_APLICA, ReportStatus::tryFromLoose('nao se aplica'));
    }

    public function test_try_from_loose_maps_irregular_variations(): void
    {
        $this->assertSame(ReportStatus::IRREGULAR_E_INADIMPLENTE, ReportStatus::tryFromLoose('IRREGULAR E INADIMPLENTE'));
        $this->assertSame(ReportStatus::IRREGULAR_E_INADIMPLENTE, ReportStatus::tryFromLoose('irregular / inadimple'));
        $this->assertSame(ReportStatus::IRREGULAR_E_ADIMPLENTE, ReportStatus::tryFromLoose('IRREGULAR E ADIMPLENTE'));
        $this->assertSame(ReportStatus::IRREGULAR_E_ADIMPLENTE, ReportStatus::tryFromLoose('irregular e adimple'));
    }

    public function test_try_from_loose_maps_regular_variations(): void
    {
        $this->assertSame(ReportStatus::REGULAR_E_INADIMPLENTE, ReportStatus::tryFromLoose('REGULAR E INADIMPLENTE'));
        $this->assertSame(ReportStatus::REGULAR_E_INADIMPLENTE, ReportStatus::tryFromLoose('regular inadimple'));
        $this->assertSame(ReportStatus::REGULAR_E_ADIMPLENTE, ReportStatus::tryFromLoose('REGULAR E ADIMPLENTE'));
        $this->assertSame(ReportStatus::REGULAR_E_ADIMPLENTE, ReportStatus::tryFromLoose('regular e adimple'));
    }

    public function test_try_from_loose_fallbacks_to_try_from_or_null(): void
    {
        $this->assertSame(ReportStatus::REGULAR_E_ADIMPLENTE, ReportStatus::tryFromLoose('REGULAR E_ADIMPLENTE'));
        $this->assertNull(ReportStatus::tryFromLoose('VALOR_TOTALMENTE_INVALIDO'));
    }
}
