<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $credentials = $r->validate(['email' => 'required|email', 'password' => 'required']);
        if (! Auth::attempt($credentials, $r->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid credentials.']);
        }$r->session()->regenerate();

        return redirect('/app');
    }

    public function register(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users', 'password' => 'required|string|min:12|confirmed']);
        $user = User::create($data);
        Auth::login($user);
        $r->session()->regenerate();

        return redirect('/app');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }
}
