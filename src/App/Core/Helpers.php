<?php
namespace App\Core;

class Helpers
{
    public static function convertToInt($data): int
    {
        switch (gettype($data)) {
            case 'string':
                if (empty($data)) {return 0;}
                $data = preg_replace('/[^\d.]+/', '', $data);
                $dots = substr_count($data, '.');
                if ($dots > 1) {return 0;}
                if ($dots == 0) {return (int) $data*100;}
                $before = stristr($data, '.', true);
                $after = stristr($data, '.');
                $after = substr($after, 1, 2);
                if (strlen($after) == 1) {$after = $after.'0';}
                $data = $before.$after;
                return (int) $data;
                break;
            case 'double':
                $data = preg_replace('/[^\d]+/', '', number_format($data, 2));
                return (int) $data;
                break;
            case 'integer':
                return $data*100;
                break;
            default:
                return 0;
                break;
        }
    }

    public static function convertToDecimal(int $data)
    {
        return $data/100;
    }

    public static function generateUuid($length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}