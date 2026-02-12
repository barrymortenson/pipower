<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 50);

        $events = Event::latest()
            ->limit($limit)
            ->get();

        return response()->json($events);
    }
}
