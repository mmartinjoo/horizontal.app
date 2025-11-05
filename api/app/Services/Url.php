<?php

namespace App\Services;

use Exception;

class Url
{
    /**
     * `state` is an OAUth GET param use in integrations and OAuth login
     * It can contain many fields: `state=tenant_id=abc|random_str=asdf|code=1234`
     */
    public static function extractKeyFromState(string $state, string $key): string
    {
        // array be like ['tenant_id=abc', 'random_str=xyz']
        $parts = explode('|', $state);
        foreach ($parts as $part) {
            if (str_starts_with($part, "{$key}=")) {
                return substr($part, strlen("{$key}="));
            }
        }

        throw new Exception("$key not found");
    }
}