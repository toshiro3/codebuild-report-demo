<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qameta\Allure\Attribute\DisplayName;
use Qameta\Allure\Attribute\Description;
use Qameta\Allure\Attribute\Severity;
use Qameta\Allure\Model\Severity as SeverityLevel;

#[DisplayName('計算サービスのテスト')]
#[Description('計算処理の正確性をテストします')]
class CalculatorTest extends TestCase
{
    #[DisplayName('足し算のテスト')]
    #[Severity(SeverityLevel::BLOCKER)]
    public function testAddition(): void
    {
        $result = 2 + 3;
        $this->assertEquals(5, $result);
    }

    #[DisplayName('引き算のテスト')]
    public function testSubtraction(): void
    {
        $result = 10 - 4;
        $this->assertEquals(6, $result);
    }

    #[DisplayName('掛け算のテスト')]
    public function testMultiplication(): void
    {
        $result = 3 * 4;
        $this->assertEquals(12, $result);
    }

    #[DisplayName('割り算のテスト')]
    public function testDivision(): void
    {
        $result = 20 / 4;
        $this->assertEquals(5, $result);
    }

    #[DisplayName('ゼロ除算の例外テスト')]
    #[Severity(SeverityLevel::CRITICAL)]
    public function testDivisionByZeroThrowsException(): void
    {
        $this->expectException(\DivisionByZeroError::class);
        $result = 1 / 0;
    }
}
