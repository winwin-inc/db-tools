<?php
namespace winwin\db\tools\commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use kuiper\helper\DataDumper;
use RuntimeException;
use PDO;

class DumpCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName("dump")
            ->setDescription("Dumps records from database table")
            ->addOption('--format', '-f', InputOption::VALUE_REQUIRED, "Output format, support json|yaml|php")
            ->addOption('--limit', '-l', InputOption::VALUE_REQUIRED, "number of records", 10)
            ->addOption('--sql', '-s', InputOption::VALUE_REQUIRED, "Query SQL")
            ->addArgument('table', InputArgument::OPTIONAL, "Table name");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sql = $input->getOption('sql');
        if (empty($sql)) {
            $table = $input->getArgument('table');
            if (empty($table)) {
                throw new RuntimeException("Either option 'sql' or argument 'table' is required");
            } else {
                $sql = "SELECT * FROM {$table}";
            }
        }
        if (!preg_match("/ limit \d+/i", $sql)) {
            $limit = (int) $input->getOption('limit');
            if ($limit > 0) {
                $sql .= " LIMIT " . $limit;
            }
        }
        $connection = $this->getConnection($input);
        echo $this->dump($connection, $sql, $input->getOption('format'));
    }

    private function dump($connection, $sql, $format)
    {
        if (!preg_match("/select .* from (\w+) ([^;]+)/i", $sql, $matches)) {
            throw new RuntimeException("Invalid SQL: '$sql'");
        }
        $table = $matches[1];
        $stmt = $connection->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $data = [$table => $stmt->fetchAll()];
        $comment = "dump by ". implode(" ", $_SERVER['argv']);
        $content = DataDumper::dump($data, $format ?: 'yaml');
        if ($format == "php") {
            $content = sprintf("<?php\n// %s\nreturn %s;", $comment, $content);
        } elseif ($format == "yaml") {
            $content = sprintf("# %s\n%s", $comment, $content);
        }
        return $content;
    }
}
