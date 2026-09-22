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
    /**
     * GET /api/v1/categories: the active category tree (root categories, each with its `children`
     * loaded recursively — see App\Services\CategoryTree).
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => CategoryResource::collection(CategoryTree::get())]);
    }

    /**
     * GET /api/v1/categories/{category}/fields: the dynamic fields a listing in this (postable, leaf)
     * category must/can fill, own + inherited from its ancestors.
     */
    public function fields(Category $category): JsonResponse
    {
        abort_unless($category->isPostable(), 404);

        return response()->json([
            'data' => $category->effectiveFields()->map->toFormArray()->values(),
        ]);
    }
}
