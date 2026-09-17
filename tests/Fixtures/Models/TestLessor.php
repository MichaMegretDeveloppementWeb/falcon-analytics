<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TestLessor extends Authenticatable
{
    protected $table = 'test_lessors';

    protected $guarded = [];
}
