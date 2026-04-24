<?php

namespace App\Http\Controllers\Web\Dev;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class UiKitUserLayoutController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dev/UiKitUserLayoutDemo');
    }
}
