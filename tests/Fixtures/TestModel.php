<?php

namespace Maestrodimateo\Workflow\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Maestrodimateo\Workflow\Models\Message;
use Maestrodimateo\Workflow\Traits\Workflowable;

class TestModel extends Model
{
    use HasUuids, Workflowable;

    protected $table = 'tests';

    protected $fillable = ['name'];

    /** Documents attached to the model (used by RequireDocumentAction). */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'test_id');
    }

    /** Resolve the recipient address for a transition message (used by SendEmailAction). */
    public function recipient(Message $message): string
    {
        return 'ops@example.test';
    }
}
