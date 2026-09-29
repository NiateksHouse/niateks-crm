<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionController
{
    public function create()
    {
        return view('login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate(['username' => ['required', 'string', 'max:80'], 'password' => ['required', 'string', 'max:256']]);
        $credentials['username'] = strtolower(trim($credentials['username']));
        $credentials['active'] = true;
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['username' => 'Kullanıcı adı veya parola hatalı.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('workspace.home'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
