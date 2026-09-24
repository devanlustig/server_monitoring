<?php

namespace App\Http\Controllers;

use App\Models\AuthorizedEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Gagal terhubung dengan Google. Silakan coba lagi.');
        }

        $email = strtolower(trim($googleUser->getEmail()));

        $authorizedEmail = AuthorizedEmail::where('email', $email)->first();

        if (!$authorizedEmail || !$authorizedEmail->is_active) {
            return redirect()->route('login')->with('error', 'Akun Google ini tidak memiliki akses ke Server Monitoring.');
        }

        Auth::login($authorizedEmail);
        session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
