<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Contact::where('user_id', $request->user()->id);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($tag = $request->query('tag')) {
            $query->whereJsonContains('tags', $tag);
        }

        $contacts = $query->orderByDesc('last_message_at')->paginate(15);

        return response()->json($contacts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'tags' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['phone'] = preg_replace('/\D/', '', $validated['phone']);

        $contact = Contact::create($validated);

        return response()->json(['contact' => $contact], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $contact = Contact::where('user_id', $request->user()->id)->findOrFail($id);
        $messages = Message::where('contact_id', $contact->id)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json(['contact' => $contact, 'recent_messages' => $messages]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $contact = Contact::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'tags' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ]);

        if (isset($validated['phone'])) {
            $validated['phone'] = preg_replace('/\D/', '', $validated['phone']);
        }

        $contact->update($validated);

        return response()->json(['contact' => $contact]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $contact = Contact::where('user_id', $request->user()->id)->findOrFail($id);
        $contact->delete();

        return response()->json(['message' => 'Contact deleted']);
    }
}
