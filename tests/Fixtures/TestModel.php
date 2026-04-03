<?php

namespace Maestrodimateo\Workflow\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Maestrodimateo\Workflow\Traits\Workflowable;

class TestModel extends Model
{
    use HasUuids, Workflowable;

    protected $table = 'tests';

    protected $fillable = ['name'];
}
