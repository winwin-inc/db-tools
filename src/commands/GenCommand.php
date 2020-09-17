<?php

declare(strict_types=1);

namespace winwin\db\tools\commands;

use Composer\Autoload\ClassLoader;
use function kuiper\helper\env;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use winwin\db\tools\generator\EntityGenerator;

class GenCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'entity namespace');
        $this->addArgument('table', InputArgument::REQUIRED, 'table name');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectPath = getcwd();
        $autoloadFile = $projectPath.'/vendor/autoload.php';
        if (!file_exists($autoloadFile)) {
            $output->writeln('<error>vendor/autoload.php not found</error>');

            return -1;
        }
        /** @var ClassLoader $loader */
        $loader = require $autoloadFile;
        $loader->unregister();
        $loader->register(false);

        $connection = $this->getConnection($input);

        $config = json_decode(env('DB_GENERATOR_CONFIG', '{}'));

        $ns = $input->getOption('namespace') ?? $config->entity_namespace
            ?? $this->getRootNamespace($projectPath).'\\domain\\entity';
        $generator = new EntityGenerator($connection, $loader, $ns, $input->getArgument('table'));
        $code = $generator->generate();

        if ($outputFile) {
            if (is_dir($outputFile) || '/' === $outputFile[strlen($outputFile) - 1]) {
                $outputFile = rtrim($outputFile, '/')."/${className}.php";
            }
            $dir = dirname($outputFile);
            if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException("cannot create directory $dir");
            }
            ob_start();
            $this->render($input, $context);
            file_put_contents($outputFile, ob_get_clean());
            $output->writeln("<info>Write to $outputFile</>");
        } else {
            $this->render($input, $context);
        }

        return 0;
    }

    public function getRootNamespace(string $projectPath): string
    {
        $composerJson = json_decode(file_get_contents($projectPath.'/composer.json'));

        return array_keys($composerJson['autoload']['psr-4'])[0];
    }
}
