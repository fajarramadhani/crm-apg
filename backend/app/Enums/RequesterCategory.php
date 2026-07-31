<?php

namespace App\Enums;

enum RequesterCategory: string
{
    case Request = 'request';
    case ErrorBug = 'error_bug';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Request => 'Request',
            self::ErrorBug => 'Error / Bug',
            self::Other => 'Lainnya',
        };
    }
}
