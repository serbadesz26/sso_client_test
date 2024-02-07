<?php

namespace App\Http\Controllers\SSO;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class SSOController extends Controller
{
    public function getLogin(Request $request)
    {

        $request->session()->put('state', $state = Str::random(40));

        $query = http_build_query([
            'client_id' => '9b36d9cd-8241-489f-938f-47d3c61392d5',
            'redirect_uri' => 'http://127.0.0.1:5000/callback',
            'response_type' => 'code',
            'scope' => 'view-user',
            'state' => $state
        ]);

        return redirect('https://bds.babelprov.go.id/oauth/authorize?' . $query);
    }

    public function getCallback(Request $request)
    {

        $state = $request->session()->pull('state');

        throw_unless(
            strlen($state) > 0 && $state === $request->state,
            InvalidArgumentException::class,
            'Invalid state value.'
        );

        $response = Http::asForm()->post('https://bds.babelprov.go.id/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => '9b36d9cd-8241-489f-938f-47d3c61392d5',
            'client_secret' => 'vE4NjkFpWAMYRTFZOAfqcGm4SFzIFUKP1J96nV8X',
            'redirect_uri' => 'http://127.0.0.1:5000/callback',
            'code' => $request->code,
        ]);

        $request->session()->put($response->json());

        return redirect(route('sso.connect'));
    }

    public function connectUser(Request $request)
    {
        $access_token = $request->session()->get('access_token');

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,

        ])->get('https://bds.babelprov.go.id/api/user-sso');

        $userArray = $response->json();

        try {
            $email = $userArray['email'];
        } catch (\Throwable $th) {
            return redirect('login')->withErrors('failed to login');
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = new User;
            $user->name = $userArray['name'];
            $user->email = $userArray['email'];
            $user->password = '123456789';
            $user->save();
        }
        Auth::login($user);
        return redirect()->route('home');
    }
}
