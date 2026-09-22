<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    /**
     * /p/{slug}: a published static page.
     */
    public function show(Page $page): View
    {
        abort_unless($page->is_published, 404);

        return view('pages.show', ['page' => $page]);
    }
}
