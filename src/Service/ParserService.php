<?php

namespace GS\Service\Service;

class ParserService
{
    public function __construct()
    {
    }

    //###> API ###

    /*
        Usage:
            [
                $name,          // "Name"
                $surname,       // "Surname"
                $patronymic,    // "Patronymic"
            ] = $this->parser->getFirstNameLastNamePatronymic('   Name Surname   Patronymic');

            [
                $name,          // "Name"
                $surname,       // "Surname"
                $patronymic,    // null
            ] = $this->parser->getFirstNameLastNamePatronymic('Name                 Surname');

            [
                $name,          // "Name"
                $surname,       // null
                $patronymic,    // null
            ] = $this->parser->getFirstNameLastNamePatronymic('         Name        ');
    */
    public static function getFirstNameLastNamePatronymic(
        string $fullName,
    ): array {
        $matches = [];
        $fullName = \trim($fullName);

        \preg_match('~^([a-zа-я]*)\s*([a-zа-я]*)\s*([a-zа-я]*)\s*$~iu', $fullName, $matches);
        \array_walk($matches, static fn(&$v) => $v = \trim($v));

        $firstName = null;
        $lastName = null;
        $patronymic = null;

        foreach (
            [
            [ &$firstName, 1 ],
            [ &$lastName, 2 ],
            [ &$patronymic, 3 ],
            ] as [ &$propertyRef, $groupNumber ]
        ) {
            if (isset($matches[$groupNumber]) && $matches[$groupNumber] !== '') {
                $propertyRef = $matches[$groupNumber];
            }
        }

        return [
            $firstName,
            $lastName,
            $patronymic,
        ];
    }

    //###< API ###
}
