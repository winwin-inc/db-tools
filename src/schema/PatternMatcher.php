<?php


namespace winwin\db\tools\schema;


class PatternMatcher
{
    /**
     * @var array
     */
    private $targets;

    /**
     * @param string $pattern
     * @param mixed $target
     */
    public function add(string $pattern, $target): void
    {
        $this->targets[] = [$pattern, $target];
    }

    public function match(string $name): bool
    {
        $target = $this->get($name);
        return isset($target);
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function get(string $name)
    {
        foreach ($this->targets as $one) {
            if (preg_match($one[0], $name)) {
                return $one[1];
            }
        }
        return null;
    }
}