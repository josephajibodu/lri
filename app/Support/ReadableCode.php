<?php

namespace App\Support;

/**
 * Random codes people can read out or type, such as order references and ticket codes.
 * The alphabet leaves out 0, O, 1 and I, which are easy to mix up.
 */
final class ReadableCode
{
    private const Alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * A random code of the given length, split into dash-separated groups when a group size is given.
     */
    public static function generate(int $length, ?int $groupSize = null): string
    {
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= self::Alphabet[random_int(0, strlen(self::Alphabet) - 1)];
        }

        return $groupSize ? implode('-', str_split($code, $groupSize)) : $code;
    }
}
