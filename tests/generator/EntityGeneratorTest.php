<?php

declare(strict_types=1);

namespace winwin\db\tools\generator;

use Composer\Autoload\ClassLoader;
use function kuiper\helper\env;
use winwin\db\tools\Db;
use winwin\db\tools\TestCase;

class EntityGeneratorTest extends TestCase
{
    protected function createGenerator(string $namespace)
    {
        $ns = 'winwin\\db\\tools\\fixtures\\';
        $loader = new ClassLoader();
        $loader->addPsr4($ns, __DIR__.'/../fixtures');
        $conn = Db::getConnection([
            'driver' => env('DB_DRIVER', 'mysql'),
            'username' => env('DB_USER'),
            'password' => env('DB_PASS'),
            'dbname' => env('DB_NAME'),
            'host' => env('DB_HOST'),
            'port' => (int) env('DB_PORT', 3306),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
        ]);
        $conn->exec('
DROP TABLE IF EXISTS foo;
CREATE TABLE foo(
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `create_time` datetime NOT NULL,
  `update_time` datetime NOT NULL,
  `client_id` int(11) NOT NULL,
  `vip_no` varchar(50) NOT NULL,
  PRIMARY KEY(id)
)');

        return new EntityGenerator($conn, $loader, $ns.$namespace, 'foo');
    }

    public function testGenerateClassNotExist()
    {
        $code = $this->createGenerator('dao')->generate();
        $this->assertEquals($code, file_get_contents(__DIR__.'/../fixtures/Foo.php'));
    }

    public function testGenerateClassExist()
    {
        $code = $this->createGenerator('entity')->generate();
        $file = __DIR__.'/../fixtures/Foo.modified.php';
        // file_put_contents($file, $code);
        $this->assertEquals($code, file_get_contents($file));
    }

    public function testGenerateRepository()
    {
        $code = $this->createGenerator('entity')->generateRepository('repository');
        $this->assertEquals($code, file_get_contents(__DIR__.'/../fixtures/FooRepository.php'));
    }
}
