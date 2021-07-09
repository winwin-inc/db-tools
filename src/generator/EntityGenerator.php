<?php

declare(strict_types=1);

namespace winwin\db\tools\generator;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use kuiper\annotations\AnnotationReader;
use kuiper\db\annotation\Transient;
use function kuiper\helper\env;
use kuiper\web\view\PhpView;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter\Standard;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionProperty;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\SourceLocator\Type\StringSourceLocator;
use winwin\db\tools\schema\Schema;
use winwin\db\tools\Text;

class EntityGenerator
{
    private const SORT_PROPERTY = 100;
    private const SORT_METHOD = 1000;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ClassLoader
     */
    private $loader;

    /**
     * @var string
     */
    private $namespace;

    /**
     * @var string
     */
    private $table;

    /**
     * @var string|null
     */
    private $tablePrefix;

    /**
     * @var string|null
     */
    private $className;

    /**
     * @var array<string,string>
     */
    private $importNames = [];

    /**
     * @var PhpView
     */
    private $view;

    /**
     * EntityGenerator constructor.
     *
     * @param Connection  $connection
     * @param ClassLoader $loader
     * @param string      $table
     */
    public function __construct(Connection $connection, ClassLoader $loader, string $namespace, string $table)
    {
        $this->connection = $connection;
        $this->loader = $loader;
        $this->namespace = trim($namespace, '\\');
        $this->table = $table;
        $this->view = new PhpView(__DIR__.'/../views');
    }

    /**
     * @return string|null
     */
    public function getTablePrefix(): ?string
    {
        if (null === $this->tablePrefix) {
            $this->tablePrefix = env('DB_TABLE_PREFIX', '');
        }

        return $this->tablePrefix;
    }

    /**
     * @param string|null $tablePrefix
     */
    public function setTablePrefix(?string $tablePrefix): void
    {
        $this->tablePrefix = $tablePrefix;
    }

    public function getClassShortName(): string
    {
        if (null === $this->className) {
            $table = $this->table;
            if (\kuiper\helper\Text::isNotEmpty($this->getTablePrefix())
                && 0 === strpos($this->table, $this->getTablePrefix())) {
                $table = substr($table, strlen($this->getTablePrefix()));
            }
            $this->className = Text::camelCase($table);
        }

        return $this->className;
    }

    public function getClassName(): string
    {
        return $this->namespace.'\\'.$this->getClassShortName();
    }

    /**
     * @return false|string
     */
    public function getFile()
    {
        return $this->loader->findFile($this->getClassName());
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function generate(): string
    {
        $file = $this->getFile();
        if (false === $file) {
            return $this->generateCode();
        }
        $stmts = (new BetterReflection())->phpParser()->parse(file_get_contents($file));
        $visitor = new class() extends NodeVisitorAbstract {
            /** @var EntityGenerator */
            public $generator;

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Namespace_) {
                    foreach ($node->stmts as $stmt) {
                        if ($stmt instanceof Node\Stmt\Use_
                            && Node\Stmt\Use_::TYPE_NORMAL === $stmt->type) {
                            foreach ($stmt->uses as $use) {
                                $alias = null === $use->alias ? $use->name->getLast() : $use->alias->toString();
                                $this->generator->addImport($alias, $use->name->toCodeString());
                            }
                        }
                    }
                }
                if ($node instanceof Node\Stmt\Class_
                    && $node->name->toString() === $this->generator->getClassShortName()) {
                    return $this->generator->replaceWithImport($this->generator->getClassAst());
                }

                return null;
            }
        };
        $visitor->generator = $this;
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $printer = new Standard();

        return $printer->prettyPrintFile($traverser->traverse($stmts));
    }

    public function generateRepository(string $repositoryNamespace, bool $impl = false): string
    {
        return $this->view->render($impl ? 'repository-impl' : 'repository', [
            'namespace' => $repositoryNamespace,
            'entityNamespace' => $this->namespace,
            'entityClass' => $this->getClassShortName(),
            'varName' => lcfirst($this->getClassShortName()),
        ]);
    }

    public function getClassAst(): Node
    {
        $propertyAlias = $this->getPropertyAlias();
        $columnAlias = array_flip($propertyAlias);
        $annotationReader = AnnotationReader::getInstance();

        $class = ReflectionClass::createFromName($this->getClassName());
        $reflectionClass = new \ReflectionClass($this->getClassName());
        $astLocator = (new BetterReflection())->astLocator();
        $code = $this->generateCode();
        $reflector = new ClassReflector(new StringSourceLocator($code, $astLocator));
        $generatedClass = $reflector->reflect($this->getClassName());
        foreach ($class->getProperties() as $property) {
            $name = $propertyAlias[$property->getName()];
            if (!$generatedClass->hasProperty($name)
                && null === $annotationReader->getPropertyAnnotation($reflectionClass->getProperty($property->getName()), Transient::class)) {
                $class->removeProperty($property->getName());
                $class->removeMethod('get'.lcfirst($property->getName()));
                $class->removeMethod('set'.lcfirst($property->getName()));
            }
        }
        foreach ($generatedClass->getProperties() as $property) {
            /** @var ReflectionProperty $property */
            $name = $columnAlias[$property->getName()] ?? $property->getName();
            if (!$class->hasProperty($name)) {
                $class->getAst()->stmts[] = $property->getAst();
                $class->getAst()->stmts[] = $generatedClass->getMethod('get'.ucfirst($name))->getAst();
                $class->getAst()->stmts[] = $generatedClass->getMethod('set'.ucfirst($name))->getAst();
            }
        }
        $node = $class->getAst();
        $columnIndex = array_flip(array_map(static function (Column $column) use ($columnAlias) {
            return $columnAlias[$column->getVarName()] ?? $column->getVarName();
        }, $this->getColumns()));
        usort($node->stmts, function ($a, $b) use ($columnIndex) {
            return $this->getStmtSort($a, $columnIndex) <=> $this->getStmtSort($b, $columnIndex);
        });

        return $node;
    }

    public function toRelativeName(Node\Name\FullyQualified $node): Node\Name
    {
        $key = array_search(ltrim($node->toCodeString(), '\\'), $this->importNames, true);
        if (false !== $key) {
            return new Node\Name($key, $node->getAttributes());
        }
        $namespace = $node->slice(0, -1);
        if (null !== $namespace && ltrim($namespace->toCodeString(), '\\') === $this->namespace) {
            return new Node\Name($node->getLast(), $node->getAttributes());
        }

        return $node;
    }

    public function replaceWithImport(Node $node): Node
    {
        $nodeTraverser = new NodeTraverser();
        $visitor = new class() extends NodeVisitorAbstract {
            /**
             * @var EntityGenerator
             */
            public $generator;

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Name\FullyQualified) {
                    return $this->generator->toRelativeName($node);
                }

                return null;
            }
        };
        $visitor->generator = $this;
        $nodeTraverser->addVisitor($visitor);

        return $nodeTraverser->traverse([$node])[0];
    }

    private function getStmtSort(Node $node, array $index): int
    {
        $sort = 0;
        if ($node instanceof Node\Stmt\Property) {
            $sort = self::SORT_PROPERTY;
            $name = $node->props[0]->name->toString();

            return $sort + ($index[$name] ?? 99);
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            $sort = self::SORT_METHOD;
            if (preg_match('/^(get|set)(.*)/', $node->name->toString(), $matches)) {
                $name = lcfirst($matches[2]);
                if (isset($index[$name])) {
                    return $sort + 10 * $index[$name] + ('get' === $matches[1] ? 0 : 1);
                } else {
                    return $sort * 2;
                }
            } else {
                return $sort * 2;
            }
        }

        return $sort;
    }

    /**
     * @return array key 属性名， value 数据库字段名
     *
     * @throws \ReflectionException
     */
    public function getPropertyAlias(): array
    {
        $class = new \ReflectionClass($this->getClassName());
        $annotationReader = \kuiper\annotations\AnnotationReader::getInstance();
        $alias = [];
        foreach ($class->getProperties() as $property) {
            $name = $property->getName();
            /** @var \kuiper\db\annotation\Column $columnAnnotation */
            $columnAnnotation = $annotationReader->getPropertyAnnotation($property, \kuiper\db\annotation\Column::class);
            if (null === $columnAnnotation) {
                $alias[$name] = $name;
            } else {
                $alias[$name] = lcfirst(Text::camelCase($columnAnnotation->name));
            }
        }

        return $alias;
    }

    public function generateCode(): string
    {
        return $this->view->render('entity', [
            'className' => $this->getClassShortName(),
            'namespace' => $this->namespace,
            'table' => $this->table,
            'columns' => $this->getColumns(),
        ]);
    }

    /**
     * @return Column[]
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    private function getColumns(): array
    {
        $columns = [];
        $schema = Schema::createSchema($this->connection, [$this->table]);
        $table = $schema->getTable($this->table);
        foreach ($table->getColumns() as $name => $column) {
            $columns[] = new Column($name, $column);
        }

        return $columns;
    }

    public function addImport(string $alias, string $className): void
    {
        $this->importNames[$alias] = $className;
    }
}
