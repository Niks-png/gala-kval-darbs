<?php

if (! function_exists('lv_number')) {
    /**
     * Format a number the Latvian way: comma decimals and a non-breaking space
     * between thousands (1 234,50).
     */
    function lv_number(float|int|string|null $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, ',', "\u{00A0}");
    }
}
