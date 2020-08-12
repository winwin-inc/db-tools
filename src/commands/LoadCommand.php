<?php
namespace winwin\db\tools\commands;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Column;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use winwin\db\tools\DataDumper;

class LoadCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName("load")
            ->setDescription("Load data to database table")
            ->addOption('--table', null, InputOption::VALUE_REQUIRED, "Table to load")
            ->addOption('--format', '-f', InputOption::VALUE_REQUIRED, "Input data format, support json|yaml|php")
            ->addOption('--truncate', '-t', InputOption::VALUE_NONE, "Truncate table before load data")
            ->addArgument('file', InputArgument::OPTIONAL, "Data input file, default read from stdin");
    }
 
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $truncate = $input->getOption('truncate');
        $format = $input->getOption('format');
        $file = $input->getArgument('file');
        $table = $input->getOption('table');
        if (empty($file)) {
            $file = "php://stdin";
            if (empty($format)) {
                $format = 'yaml';
            }
        }
        $dataset = DataDumper::loadFile($file, $format);
        if ($table) {
            $dataset = [$table => $dataset];
        }
        $db = $this->getConnection($input);
        try {
            foreach ($dataset as $tableName => $rows) {
                if (empty($rows)) {
                    continue;
                }
                $columns = $db->getSchemaManager()->listTableColumns($tableName);
                $fields = array_intersect(array_keys($rows[0]), array_map(function(Column $column): string {
                    return $column->getName();
                }, $columns));
                if ($truncate) {
                    $db->executeUpdate("truncate `$tableName`");
                }
                $batchSize = 1000;
                foreach (array_chunk($rows, $batchSize) as $batchRows) {
                    $sql = sprintf(
                        'INSERT INTO `%s` (`%s`) VALUES',
                        $tableName,
                        implode('`,`', $fields)
                    );
                    $rowPlaceholder = sprintf('(%s)', implode(',', array_fill(0, count($fields), '?')));
                    $sql .= implode(',', array_fill(0, count($batchRows), $rowPlaceholder));
                    $bindValues = [];
                    foreach ($batchRows as $row) {
                        foreach ($fields as $name) {
                            $bindValues[] = $row[$name] ?? null;
                        }
                    }
                    $stmt = $db->prepare($sql);
                    $stmt->execute($bindValues);
                }
                $output->writeln(sprintf("<info>Load %d records to table %s</>", count($rows), $tableName));
            }
        } catch (UniqueConstraintViolationException $e) {
            if (isset($_SERVER["argv"])) {
                $argv = $_SERVER["argv"];
                array_splice($argv, 2, 0, "-t");
                $example = "For example: \n" . implode(" ", $argv) . "\n";
            } else {
                $example = "";
            }
            $output->writeln(sprintf(
                "<error>Data integrity violation occur. Use -t to truncate table before load data.\n%s</>",
                $example
            ));
        }
        return 0;
    }
}
