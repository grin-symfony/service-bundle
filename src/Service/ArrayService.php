<?php

namespace GS\Service\Service;

class ArrayService
{
    public function __construct()
    {
    }

    //###> API ###

    /*###> FILTERING FOR REALIZATION OF \GS\Command\Trait\PatternAbleInstance ### */
    /* FILTERING FOR REALIZATION OF \GS\Command\Trait\PatternAbleInstance

        -> AbstractPatternAbleCommandUseOneThreeReplacementPartsOfRegexTrait

        Gets the first result.

        If pass all the possible keys into $primaryKeysValues
            you'll get [] guaranteed!

        forFilter: false (for constructing aims)
            returns pattern diff array when there were interesections
            RETURNS [] WHEN THERE IS NO DIFFERENCE BETWEEN PATTERN DATA AND PASSED ONES
            RETURNS null when wasn't intersection passed $primaryKeysValues with passed $patterns
            Если $pattern имеет какие-то различия от $primaryKeysValues возвращает не пустой массив
        forFilter: true (for filtering aims)
            returns [] when pattern was matched
            returns null when pattern was NOT matched
    */
    public function getParsedOneThreeReplacementPartsRegex(
        array $primaryKeysValues,
        array $patterns,
        bool $forFilter = false,
    ): ?array {
        foreach ($patterns as $pattern) {
            $diff = \array_diff_assoc($pattern, $primaryKeysValues);
            //###> Goal forFilter: return [] if it's possible (there is no difference with $pattern)
            if ($forFilter) {
                if (empty($diff)) {
                    return [];
                }
                continue;
            }
            // Are there any coincidences?
            $result = \array_intersect_assoc($primaryKeysValues, $pattern);
            //###> Return the difference related $pattern
            if (!empty($result)) {
                return $diff;
            }
        }
        return null;
    }
    /*###< FILTERING FOR REALIZATION OF \GS\Command\Trait\PatternAbleInstance ### */

    public static function getKeyValueString(
        array $keyValue,
        string $separator = ', ',
        bool $considerAlphaKyesOnly = true,
    ): string {
        $params = [];
        \array_walk($keyValue, static function ($v, $k) use (&$params, &$considerAlphaKyesOnly) {
            if ($considerAlphaKyesOnly && \is_int($k)) {
                return $params[] = $v;
            }
            $params[] = $k . ': ' . $v;
        });

        return \implode($separator, $params);
    }

    public function getMaxLen(array $input): int
    {
        return \max(\array_map(static fn($v) => \mb_strlen((string) $v), $input));
    }

    public function throwIfNotItemsType(
        \Traversable $items,
        string $type,
    ): void {
        $this->isItemsMeasureUpToType(
            items:          $items,
            type:           $type,
            throw:          true,
        );
    }

    public function isItemsType(
        \Traversable $items,
        string $type,
    ): bool {
        return $this->isItemsMeasureUpToType(
            items:          $items,
            type:           $type,
            throw:          false,
        );
    }

    //###< API ###


    //###> HELPER ###

    /*
        Are all the items measure up to type
    */
    private function isItemsMeasureUpToType(
        \Traversable $items,
        string $type,
        bool $throw,
    ): bool {
        $measureUpTypes = true;

        foreach ($items as $item) {
            $measureUpTypes = $measureUpTypes && (\is_object($item)
                ? $item instanceof $type
                : gettype($item) === $type
            );
            if ($measureUpTypes === false) {
                break;
            }
        }

        if ($throw && !$measureUpTypes) {
            throw new \Exception('Не все элементы массива типа: "' . $type . '"');
        }

        return $measureUpTypes;
    }

    //###< HELPER ###
}
