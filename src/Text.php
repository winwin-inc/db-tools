<?php


namespace winwin\db\tools;


class Text
{
    public static function camelCase(string $str, string $delimiter = null): string
    {
        $sep = "\x00";
        $replace = null === $delimiter ? ['_'] : str_split($delimiter);

        return implode('', array_map('ucfirst', explode($sep, str_replace($replace, $sep, $str))));
    }
}