<?php

namespace App\Services\Voters;

/** A voter search stopped by the database time limit (see VoterService::timeLimited). */
class SearchTooSlow extends \RuntimeException {}
