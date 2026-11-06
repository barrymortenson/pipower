<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Recipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

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
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:32',
            'enabled' => 'boolean',
        ]);

        $recipient = new Recipient($validated);
        $this->ensureHasContact($recipient);
        $recipient->save();

        return response()->json($recipient, 201);
    }

    public function update(Request $request, Recipient $recipient): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
            'phone' => 'sometimes|nullable|string|max:32',
            'enabled' => 'sometimes|boolean',
        ]);

        $recipient->fill($validated);
        $this->ensureHasContact($recipient);
        $recipient->save();

        return response()->json($recipient);
    }

    public function destroy(Recipient $recipient): Response
    {
        $recipient->delete();

        return response()->noContent();
    }

    /**
     * Email and phone are each optional, but a recipient needs at least one.
     */
    private function ensureHasContact(Recipient $recipient): void
    {
        if (blank($recipient->email) && blank($recipient->phone)) {
            throw ValidationException::withMessages([
                'email' => 'Enter an email address, a phone number, or both.',
            ]);
        }
    }
}
