<?php

use Dashworthy\DatagridViews\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

pest()->extend(TestCase::class)->use(DatabaseTransactions::class)->in('Feature', 'Unit');
