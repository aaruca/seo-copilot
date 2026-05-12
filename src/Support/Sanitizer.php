<?php

namespace SeoCopilot\Support;

class Sanitizer
{
    public static function plain(string $value, int $max = 0): string
    {
        $value = wp_strip_all_tags($value);
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($max > 0 && function_exists('mb_substr')) {
            $value = mb_substr($value, 0, $max);
        } elseif ($max > 0) {
            $value = substr($value, 0, $max);
        }
        return $value;
    }

    public static function multiline(string $value, int $max = 0): string
    {
        $value = wp_kses_post($value);
        if ($max > 0 && function_exists('mb_substr')) {
            $value = mb_substr($value, 0, $max);
        } elseif ($max > 0) {
            $value = substr($value, 0, $max);
        }
        return $value;
    }
}
