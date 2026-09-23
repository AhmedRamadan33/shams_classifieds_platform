<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CategoryResource;
use App\Models\Category;
use App\Services\CategoryTree;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => CategoryResource::collection(CategoryTree::get())]);
    }

    public function fields(Category $category): JsonResponse
    {
        abort_unless($category->isPostable(), 404);

        return response()->json([
            'data' => $category->effectiveFields()->map->toFormArray()->values(),
        ]);
    }
}
