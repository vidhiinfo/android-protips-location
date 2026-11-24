<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\GreetingService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $svc = new DashboardService($user);

        return view('dashboard', [
            'totals' => $svc->totals(),
            'breakdown' => $svc->breakdown(),
            'office' => $svc->officeToday(),
            'myTasks' => $svc->myTasks(),
            'birthdays' => $user->canView('greetings') ? (new GreetingService($user))->upcoming(0) : collect(),
        ]);
    }
}
