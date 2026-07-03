<?php

namespace Maestrodimateo\Workflow\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasUuids;

    protected $table = 'documents';

    protected $fillable = ['test_id', 'type'];
}
