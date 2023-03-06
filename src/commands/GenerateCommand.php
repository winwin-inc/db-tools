<?php

declare(strict_types=1);

namespace winwin\db\tools\commands;

use Composer\Autoload\ClassLoader;
use function kuiper\helper\env;
use kuiper\helper\Text;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use winwin\db\tools\generator\EntityGenerator;

class GenerateCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('generate');
        $this->setDescription('Generate entity class code');
        $this->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'entity namespace');
        $this->addOption('repository-namespace', null, InputOption::VALUE_REQUIRED, 'repository namespace');
        $this->addOption('output', '-o', InputOption::VALUE_REQUIRED, 'output file name');
        $this->addOption('dry', '-d', InputOption::VALUE_NONE, 'show generated code');
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

        $ns = $input->getOption('namespace')
            ?? $config->entity_namespace
            ?? $this->getRootNamespace($projectPath).'domain\\entity';
        $generator = new EntityGenerator($connection, $loader, $ns, $input->getArgument('table'));
        $code = $generator->generate();
        if ($input->getOption('dry')) {
            $output->writeln($code);
        } else {
            $outputFile = $input->getOption('output') ?? $this->getFile($loader, $generator->getClassName());
            $file = $generator->getFile();
            $repositoryNs = $input->getOption('repository-namespace')
                          ?? $config->repository_namespace
                          ?? $this->getRootNamespace($projectPath).'domain\\repository';
            $repositoryFile = $this->getFile($loader, $repositoryNs.'\\'.$generator->getClassShortName().'Repository');
            if (false === $file || !file_exists($repositoryFile)) {
                // 生成 repository 代码
                file_put_contents($repositoryFile, $generator->generateRepository($repositoryNs));
                $repositoryFile = $this->getFile($loader, $repositoryNs.'\\'.$generator->getClassShortName().'RepositoryImpl');
                file_put_contents($repositoryFile, $generator->generateRepository($repositoryNs, true));
            }
            file_put_contents($outputFile, $code);
            $output->writeln("<info>Write to $outputFile</>");
        }

        return 0;
    }

    public function getRootNamespace(string $projectPath): string
    {
        $composerJson = json_decode(file_get_contents($projectPath.'/composer.json'), true);

        return array_keys($composerJson['autoload']['psr-4'])[0];
    }

    private function getFile(ClassLoader $loader, string $className): string
    {
        $prefixesPsr4 = $loader->getPrefixesPsr4();
        ksort($prefixesPsr4);
        foreach (array_reverse($prefixesPsr4) as $ns => $paths) {
            if (Text::startsWith($className, $ns)) {
                $file = $paths[0].'/'.str_replace('\\', '/', substr($className, strlen($ns))).'.php';
                $dir = dirname($file);
                if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
                    throw new \RuntimeException("Cannot mkdir $dir");
                }

                return $file;
            }
        }
        throw new \InvalidArgumentException("Cannot find path for class $className");
    }
}
