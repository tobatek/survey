<?php

namespace Tests\Unit\Analytics;

use App\Domains\Analytics\Support\CsvFormulaInjectionGuard;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §21.2 "CSV formula injection dicegah dengan escaping nilai berbahaya".
class CsvFormulaInjectionGuardTest extends TestCase
{
    #[Test]
    public function prefixes_values_starting_with_dangerous_characters(): void
    {
        $this->assertSame("'=1+1", CsvFormulaInjectionGuard::escape('=1+1'));
        $this->assertSame("'+CMD", CsvFormulaInjectionGuard::escape('+CMD'));
        $this->assertSame("'-1", CsvFormulaInjectionGuard::escape('-1'));
        $this->assertSame("'@SUM(A1:A9)", CsvFormulaInjectionGuard::escape('@SUM(A1:A9)'));
    }

    #[Test]
    public function leaves_normal_text_untouched(): void
    {
        $this->assertSame('Fasilitas kurang lengkap', CsvFormulaInjectionGuard::escape('Fasilitas kurang lengkap'));
        $this->assertSame('', CsvFormulaInjectionGuard::escape(''));
        $this->assertSame('50% puas', CsvFormulaInjectionGuard::escape('50% puas'));
    }
}
