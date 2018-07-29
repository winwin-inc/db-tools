<?php


namespace winwin\db\tools\schema;


class PatternMatcher
{
    /**
     * @var array
     */
    private $targets;

    public function add($pattern, $target)
    {
        $this->targets[] = [$pattern, $target];
    }

    public function match($name)
    {
        $target = $this->get($name);
        return isset($target);
    }

    public function get($name)
    {
        foreach ($this->targets as $one) {
            if (preg_match($one[0], $name)) {
                return $one[1];
            }
        }
        return null;
    }
}