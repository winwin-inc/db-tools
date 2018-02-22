<?php
namespace winwin\db\tools\commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use kuiper\helper\DataDumper;
use RuntimeException;
use InvalidArgumentException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class LoadCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName("load")
            ->setDescription("Load data to database table")
            ->addOption('--format', '-f', InputOption::VALUE_REQUIRED, "Input data format, support json|yaml|php")
            ->addOption('--truncate', '-t', InputOption::VALUE_NONE, "Truncate table before load data")
            ->addArgument('file', InputArgument::OPTIONAL, "Data input file, default read from stdin");
    }
 
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $truncate = $input->getOption('truncate');
        $format = $input->getOption('format');
        $file = $input->getArgument('file');
        if (empty($file)) {
            $file = "php://stdin";
            if (empty($format)) {
                $format = 'yaml';
            }
        }
        $dataset = DataDumper::loadFile($file, $format);
        $db = $this->getConnection($input);
        try {
            foreach ($dataset as $table => $rows) {
                if ($truncate) {
                    $db->executeUpdate("truncate `$table`");
                }
                $batchSize = 1000;
                foreach (array_chunk($rows, $batchSize) as $batchRows) {
                    $row = $batchRows[0];
                    $sql = sprintf(
                        "INSERT INTO `%s` (`%s`) VALUES",
                        $table,
                        implode("`,`", array_keys($row))
                    );
                    $sql .= implode(",", array_fill(0, count($batchRows), sprintf('(%s)', implode(",", array_fill(0, count($row), "?")))));
                    $keys = array_keys($row);
                    $bindValues = [];
                    foreach ($batchRows as $row) {
                        foreach ($keys as $name) {
                            $bindValues[] = isset($row[$name]) ? $row[$name] : null;
                        }
                    }
                    $stmt = $db->prepare($sql);
                    $stmt->execute($bindValues);
                }
                $output->writeln(sprintf("<info>Load %d records to table %s</>", count($rows), $table));
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
    }
}
