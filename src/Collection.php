<?php

namespace PicoMapper;

class Collection
{
    /**
     * Returns the first element of collection for which callback returns
     * true.
     *
     * @param array    $collection
     * @param callable $callback
     * @return mixed
     */
    public static function first(array $collection, callable $callback)
    {
        foreach ($collection as $item) {
            if ($callback($item)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Splits collection into groups indexed by the key returned by
     * callback.
     *
     * @param array    $collection
     * @param callable $callback
     * @return array
     */
    public static function group(array $collection, callable $callback)
    {
        $groups = [];

        foreach ($collection as $item) {
            $groups[$callback($item)][] = $item;
        }

        return $groups;
    }

    /**
     * Returns all elements in array a for which no elements in
     * array b exist with the same value for keys.
     *
     * @param array    $a
     * @param array    $b
     * @param string[] $keys
     * @return array
     */
    public static function diffByKeys(array $a, array $b, array $keys)
    {
        $keys = array_flip($keys);

        return array_udiff($a, $b, function($x, $y) use ($keys) {
            return implode(':', array_intersect_key($x, $keys)) <=> implode(':', array_intersect_key($y, $keys));
        });
    }

    /**
     * Returns all elements in array a for which an element in array
     * b exists with the same value for keys.
     *
     * @param array    $a
     * @param array    $b
     * @param string[] $keys
     * @return array
     */
    public static function intersectByKeys(array $a, array $b, array $keys)
    {
        $keys = array_flip($keys);

        return array_uintersect($a, $b, function($x, $y) use ($keys) {
            return implode(':', array_intersect_key($x, $keys)) <=> implode(':', array_intersect_key($y, $keys));
        });
    }
}
