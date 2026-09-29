<?php

namespace App\Http\Controllers;

use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ActivationController
{
    public function create()
    {
        return view('activate');
    }

    public function store(Request $request, InvitationService $service)
    {
        $data = $request->validate([
            'invitation_code' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'password' => ['bail', 'required', 'string', 'confirmed', Password::min(14)->mixedCase()->numbers()->symbols(), function ($attribute, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail('Parola çok uzun; daha kısa bir parola seçin.');
                }
            }],
        ]);
        $service->accept($data['invitation_code'], $data['password']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Hesabınız hazır. Size bildirilen kullanıcı adı ve belirlediğiniz parolayla giriş yapın.');
    }
}
