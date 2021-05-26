<?php


namespace winwin\db\tools;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

class DataDumper
{
    public const FORMATS = [
        'json' => 'json',
        'yaml' => 'yaml',
        'yml' => 'yaml',
        'php' => 'php',
    ];

    /**
     * @param mixed $data   data to serialize
     * @param bool  $pretty
     *
     * @return string
     */
    public static function json($data, bool $pretty = true): string
    {
        if ($pretty) {
            $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

            return json_encode($data, $flags)."\n";
        }

        return json_encode($data);
    }

    /**
     * @param mixed $data   data to serialize
     * @param bool  $pretty
     *
     * @return string
     */
    public static function yaml($data, bool $pretty = true): string
    {
        return Yaml::dump($data, $pretty ? 4 : 2, 2);
    }

    /**
     * @param mixed $data data to serialize
     *
     * @return string
     */
    public static function php($data): string
    {
        return var_export($data, true);
    }

    /**
     * serialize to specific format.
     *
     * @param mixed  $data
     * @param string $format
     * @param bool   $pretty
     *
     * @return string
     */
    public static function dump($data, string $format = 'yaml', bool $pretty = true): string
    {
        return self::$format($data, $pretty);
    }

    /**
     * @param string $content
     * @param string $format
     *
     * @SuppressWarnings("eval")
     *
     * @return mixed
     */
    public static function load(string $content, string $format)
    {
        switch ($format) {
            case 'json':
                return json_decode($content, true);
            case 'yaml':
                return Yaml::parse($content);
            case 'php':
                return eval('return '.$content.';');
            default:
                throw new InvalidArgumentException("Invalid format '{$format}'");
        }
    }

    /**
     * Gets data format from file name.
     *
     * @param string $file
     *
     * @return string
     */
    public static function guessFormat(string $file): string
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (!isset(self::FORMATS[$ext])) {
            throw new InvalidArgumentException("Cannot guess format from file '{$file}'");
        }

        return self::FORMATS[$ext];
    }

    /**
     * load serialized data.
     *
     * @param string $file
     * @param string $format file format. If null, determine from file extension
     *
     * @return mixed
     */
    public static function loadFile(string $file, ?string $format = null)
    {
        if (!isset($format)) {
            $format = self::guessFormat($file);
        }
        switch ($format) {
            case 'json':
                return json_decode(file_get_contents($file), true);
            case 'yaml':
            case 'yml':
                return Yaml::parse(file_get_contents($file));
            case 'php':
                /* @noinspection PhpIncludeInspection */
                return require $file;
            default:
                throw new InvalidArgumentException("Invalid format '{$format}'");
        }
    }

    /**
     * dump serialized data to file.
     *
     * @param string $file
     * @param mixed  $data
     * @param string $format file format. If null, determine from file extension
     * @param bool   $pretty
     *
     * @return bool|int
     */
    public static function dumpFile(string $file, $data, ?string $format = null, bool $pretty = true)
    {
        if (!isset($format)) {
            $format = self::guessFormat($file);
        }

        return file_put_contents($file, self::dump($data, $format, $pretty));
    }
}