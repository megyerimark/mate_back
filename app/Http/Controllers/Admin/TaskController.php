<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Feladatok és határidők az adminban. */
class TaskController extends Controller
{
    /**
     * A böngésző UTC-ben küldi a határidőt (pl. 2026-11-08T09:00:00Z). Az adatbázis az alkalmazás időzónájában
     * tárol, ezért átváltjuk, különben a visszaolvasott idő egy-két órával elcsúszna.
     */
    private static function localDue(array $data): array
    {
        if (array_key_exists('due_at', $data)) {
            $data['due_at'] = $data['due_at'] ? Carbon::parse($data['due_at'])->setTimezone(config('app.timezone')) : null;
        }

        return $data;
    }

    private const MESSAGES = ['title.required' => 'Írd be a feladatot.', 'due_at.date' => 'Érvénytelen határidő.'];

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'done', 'all'])],
            'quote_request_id' => ['nullable', 'integer'],
        ]);

        $tasks = Task::with(['quoteRequest:id,name', 'user:id,name'])
            ->when(($f['status'] ?? 'open') === 'open', fn ($q) => $q->whereNull('done_at'))
            ->when(($f['status'] ?? null) === 'done', fn ($q) => $q->whereNotNull('done_at'))
            ->when($f['quote_request_id'] ?? null, fn ($q, $id) => $q->where('quote_request_id', $id))
            // Határidő szerint, a határidő nélküliek a végén.
            ->orderByRaw('done_at is not null')->orderByRaw('due_at is null')->orderBy('due_at')->orderBy('id')
            ->limit(200)->get();

        return response()->json(['data' => $tasks->map->toApi()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'due_at' => ['nullable', 'date'],
            'quote_request_id' => ['nullable', 'integer', 'exists:quote_requests,id'],
        ], self::MESSAGES);
        $task = new Task(self::localDue($data));
        $task->user_id = $request->user()->id;
        $task->save();

        return response()->json(['data' => $task->load(['quoteRequest:id,name', 'user:id,name'])->toApi()], 201);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'done' => ['sometimes', 'boolean'],
        ], self::MESSAGES);
        if (array_key_exists('done', $data)) {
            $task->done_at = $data['done'] ? now() : null;
            unset($data['done']);
        }
        $task->fill(self::localDue($data))->save();

        return response()->json(['data' => $task->load(['quoteRequest:id,name', 'user:id,name'])->toApi()]);
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json(['message' => 'Törölve.']);
    }
}
