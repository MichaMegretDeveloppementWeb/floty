<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Dev;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class UiKitShowcaseController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dev/UiKitShowcase');
    }
}
