<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\GovernorateResource;
use App\Services\Geography;
use Illuminate\Http\JsonResponse;

class GeographyController extends Controller
{
    /**
     * GET /api/v1/governorates: every governorate with its cities.
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => GovernorateResource::collection(Geography::all())]);
    }
}
