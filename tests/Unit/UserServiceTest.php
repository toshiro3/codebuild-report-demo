<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qameta\Allure\Attribute\DisplayName;
use Qameta\Allure\Attribute\Description;
use Qameta\Allure\Attribute\Severity;
use Qameta\Allure\Model\Severity as SeverityLevel;

#[DisplayName('ユーザーサービスのテスト')]
#[Description('ユーザー関連の機能をテストします')]
class UserServiceTest extends TestCase
{
    #[DisplayName('ユーザー名のバリデーション - 正常系')]
    #[Severity(SeverityLevel::CRITICAL)]
    public function testValidUsername(): void
    {
        $username = 'testuser';
        $this->assertMatchesRegularExpression('/^[a-z0-9]+$/', $username);
    }

    #[DisplayName('メールアドレスのバリデーション')]
    #[Severity(SeverityLevel::NORMAL)]
    public function testValidEmail(): void
    {
        $email = 'test@example.com';
        $this->assertNotFalse(filter_var($email, FILTER_VALIDATE_EMAIL));
    }

    #[DisplayName('パスワード長のチェック')]
    public function testPasswordLength(): void
    {
        $password = 'securepassword123';
        $this->assertGreaterThanOrEqual(8, strlen($password));
    }
}
