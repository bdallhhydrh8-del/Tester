<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TesterLogin;
use Illuminate\Http\Request;

class TesterAuthController extends Controller
{
    public function login(Request $request, TesterLogin $login)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:30',
        ]);

        $tester = $login->find($request, $data['phone']);
        $login->startSession($request, $tester);

        return response()->json(['data' => $tester, 'message' => 'Logged in.']);
    }
}