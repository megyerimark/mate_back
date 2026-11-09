<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\CreateAdmin;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Admin felhasználók kezelése és saját jelszó módosítása az admin felületen. */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => User::orderBy('name')->get()->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'created_at' => $u->created_at?->toIso8601String(),
                'is_me' => $u->id === $request->user()->id,
            ]),
        ]);
    }

    /** Új admin; a jelszót a rendszer generálja, és csak ebben a válaszban látszik. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
        ], [
            'name.required' => 'Add meg a nevet.',
            'email.required' => 'Add meg az e-mail-címet.',
            'email.email' => 'Érvénytelen e-mail-cím.',
            'email.unique' => 'Ezzel az e-mail-címmel már van admin.',
        ]);

        $password = CreateAdmin::randomPassword();
        $user = User::create($data + ['password' => $password]);

        return response()->json([
            'data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'is_me' => false],
            'password' => $password,
        ], 201);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'Saját magadat nem törölheted.');
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Törölve.']);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ], [
            'current_password.required' => 'Add meg a jelenlegi jelszót.',
            'password.required' => 'Add meg az új jelszót.',
            'password.min' => 'Az új jelszó legalább 10 karakter legyen.',
            'password.confirmed' => 'A két új jelszó nem egyezik.',
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'A jelenlegi jelszó nem helyes.']);
        }

        $user->forceFill(['password' => $data['password']])->save();
        // A többi eszközön kilépteti, ezen bejelentkezve marad.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Jelszó módosítva.']);
    }
}
