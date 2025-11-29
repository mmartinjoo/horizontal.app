<?php

namespace App\Enums\Integration;

enum Category: string
{
    case Communication = 'communication';
    case TaskManagement = 'task_management';
    case CodeRepository = 'code_repository';
    case Storage = 'linear';
    case Documentation = 'documentation';
}