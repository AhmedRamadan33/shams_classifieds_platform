<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryFieldsController extends Controller
{
    /**
     * GET /api/categories/{category}/fields: the fields a listing in this category must/can fill
     * (own + inherited), used by the listing form to render inputs dynamically.
     */
    public function show(Category $category): JsonResponse
    {
        abort_unless($category->isPostable(), 404);

        return response()->json([
            'category' => ['id' => $category->id, 'name' => $category->name],
            'fields' => $category->effectiveFields()->map->toFormArray()->values(),
        ]);
    }
}
