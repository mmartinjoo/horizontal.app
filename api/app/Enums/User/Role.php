<?php 

namespace App\Enums\User;

enum Role: string
{
    case User = 'user';
    case Admin = 'admin';
}