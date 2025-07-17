<?php

// --- Polyfill: array_find_key since v8.4 ---
if (!function_exists('array_find_key')) {
    /**
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @param callable(TValue, TKey): bool $callback
     * @return TKey|false
     */
    function array_find_key(array $array, callable $callback) {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $key;
            }
        }
        return false;
    }
}

// --- Polyfill: array_find since v8.4 ---
if (!function_exists('array_find')) {
    /**
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @param callable(TValue, TKey): bool $callback
     * @return TValue|null
     */
    function array_find(array $array, callable $callback) {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }
        return null;
    }
}
