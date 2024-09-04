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
            'client_id' => '9be08ef1-7142-495a-bdca-249e407dd40e',
            'redirect_uri' => 'http://127.0.0.1:3000/sso/auth/callback',
            'response_type' => 'code',
            'scope' => 'view-user',
            'state' => $state
        ]);

        return redirect('http://127.0.0.1:8000/oauth/authorize?' . $query);
    }

    public function getCallback(Request $request)
    {

        $state = $request->session()->pull('state');

        throw_unless(
            strlen($state) > 0 && $state === $request->state,
            InvalidArgumentException::class,
            'Invalid state value.'
        );

        $response = Http::asForm()->post('http://127.0.0.1:8000/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => '9be08ef1-7142-495a-bdca-249e407dd40e',
            'client_secret' => 'UmhAT8Fspvs39BYS3hi2sELvUteDcIe8SIa8iSb8',
            'redirect_uri' => 'http://127.0.0.1:3000/sso/auth/callback',
            'code' => $request->code,
        ]);

        dd($response->json());

        $request->session()->put($response->json());

        return redirect(route('sso.connect'));
    }

    public function connectUser(Request $request)
    {
        $access_token = $request->session()->get('access_token');

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,

        ])->get('http://127.0.0.1:8000/api/user-sso');

        $userArray = $response->json();
        // dd($userArray);

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
