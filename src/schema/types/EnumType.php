<?php


namespace winwin\db\tools\schema\types;


use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class EnumType extends Type
{
    const ENUM_TYPE = 'enum';

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return self::ENUM_TYPE;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return (string) $value;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return $value;
    }

    public function getName()
    {
        return self::ENUM_TYPE;
    }
}