<?php

namespace winwin\db\tools\commands;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use RuntimeException;
use PDO;
use winwin\db\tools\DataDumper;

class DumpCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName("dump")
            ->setDescription("Dumps records from database table")
            ->addOption('--format', '-f', InputOption::VALUE_REQUIRED, "Output format, support json|yaml|php|csv|xml")
            ->addOption('--delimiter', '-d', InputOption::VALUE_REQUIRED, "Csv delimiter, default tab", "\t")
            ->addOption('--limit', '-l', InputOption::VALUE_REQUIRED, "number of records", 10)
            ->addOption('--sql', '-s', InputOption::VALUE_REQUIRED, "Query SQL")
            ->addArgument('table', InputArgument::OPTIONAL, "Table name");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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
            $limit = (int)$input->getOption('limit');
            if ($limit > 0) {
                $sql .= " LIMIT " . $limit;
            }
        }
        $connection = $this->getConnection($input);
        $format = $input->getOption('format');
        if ($format === 'csv') {
            $delimiter = $input->getOption("delimiter");
            $data = array_values($this->getData($connection, $sql));
            $fp = fopen("php://stdout", 'wb');
            foreach ($data[0] as $row) {
                fputcsv($fp, $row, $delimiter);
            }
        } elseif ($format === 'xml') {
            $this->formatXmlDataset($connection, $sql, $format);
        } else {
            echo $this->dump($connection, $sql, $format);
        }

        return 0;
    }

    private function formatXmlDataset(Connection $connection, string $sql, string $format): void
    {
        $data = $this->getData($connection, $sql);

        echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
        echo "<dataset>\n";
        foreach ($data as $table => $rows) {
            foreach ($rows as $row) {
                echo "    <$table " . implode("\n       ", array_map(function($key, $val): string {
                    return $key . "=\"" . addslashes($val) . "\"";
                }, array_keys($row), array_values($row))). " />\n";
            }
        }
        echo "</dataset>\n";
    }

    private function dump(Connection $connection, string $sql, ?string $format): string
    {
        $data = $this->getData($connection, $sql);
        $comment = "dump by " . implode(" ", $_SERVER['argv']);
        $content = DataDumper::dump($data, $format ?? 'yaml');
        if ($format === "php") {
            $content = sprintf("<?php\n// %s\nreturn %s;", $comment, $content);
        } elseif ($format === "yaml") {
            $content = sprintf("# %s\n%s", $comment, $content);
        }
        return $content;
    }

    /**
     * @param Connection $connection
     * @param string $sql
     * @return array
     * @throws DBALException
     */
    private function getData(Connection $connection, string $sql): array
    {
        if (!preg_match("/select .* from (\w+) ([^;]+)/i", $sql, $matches)) {
            throw new RuntimeException("Invalid SQL: '$sql'");
        }
        $table = $matches[1];
        $stmt = $connection->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        return [$table => $stmt->fetchAll()];
    }
}
