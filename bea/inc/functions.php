<?php
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function valid_date($date)
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function input_date($key, $default)
{
    if (isset($_GET[$key]) && valid_date($_GET[$key])) {
        return $_GET[$key];
    }
    return $default;
}

function selected($a, $b)
{
    return ((string) $a === (string) $b) ? ' selected="selected"' : '';
}

function active_menu($page, $current)
{
    return $page === $current ? ' active' : '';
}

function format_number_id($number)
{
    return number_format((float) $number, 0, ',', '.');
}
