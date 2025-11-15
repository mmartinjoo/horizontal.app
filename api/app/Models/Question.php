<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $guarded = [];

    protected $casts = [
        'answer' => 'array',
        'potentially_relevant_documents' => 'array',
        'relevant_documents' => 'array',
        'relevant_graph_paths' => 'array',
    ];
}
