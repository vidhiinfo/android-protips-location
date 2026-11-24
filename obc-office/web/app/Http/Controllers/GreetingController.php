<?php

namespace App\Http\Controllers;

use App\Services\GreetingService;
use Illuminate\Http\Request;

class GreetingController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()->canView('greetings'), 403);
        $days = min(31, max(0, (int) $request->query('days', setting('greeting_days_ahead', 7))));

        return view('greetings', ['items' => (new GreetingService($request->user()))->upcoming($days), 'days' => $days]);
    }
}
