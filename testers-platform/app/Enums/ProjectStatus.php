<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Disabled = 'disabled';
    case Live = 'live';
    case Done = 'done';

    
    //Admin can change a project from disabled to live, live to done, done to live.
    
    public function canMoveTo(self $next): bool
    {
        return match ($this) {
            self::Disabled => $next === self::Live,
            self::Live => in_array($next, [self::Disabled, self::Done], true),
            self::Done => $next === self::Live,
        };
    }
}