<?php

declare(strict_types=1);

namespace winwin\db\tools;

use Dotenv\Dotenv;

class TestCase extends \PHPUnit\Framework\TestCase
{
    public static function setUpBeforeClass(): void
    {
        chdir(dirname(__DIR__));
        date_default_timezone_set('Asia/Shanghai');
        if (file_exists(__DIR__.'/.env')) {
            (Dotenv::createUnsafeImmutable(__DIR__))->load();
        }
    }
}
