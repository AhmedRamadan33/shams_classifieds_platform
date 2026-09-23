<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryFieldsController extends Controller
{
    public function show(Category $category): JsonResponse
    {
        abort_unless($category->isPostable(), 404);

        return response()->json([
            'category' => ['id' => $category->id, 'name' => $category->name],
            'fields' => $category->effectiveFields()->map->toFormArray()->values(),
        ]);
    }
}
