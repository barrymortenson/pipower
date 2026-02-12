<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Recipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RecipientsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Recipient::all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'type' => 'required|in:email,phone',
            'value' => 'required|string',
            'enabled' => 'boolean',
        ]);

        $recipient = Recipient::create($validated);

        return response()->json($recipient, 201);
    }

    public function update(Request $request, Recipient $recipient): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'type' => 'sometimes|in:email,phone',
            'value' => 'sometimes|string',
            'enabled' => 'sometimes|boolean',
        ]);

        $recipient->update($validated);

        return response()->json($recipient);
    }

    public function destroy(Recipient $recipient): Response
    {
        $recipient->delete();

        return response()->noContent();
    }
}
