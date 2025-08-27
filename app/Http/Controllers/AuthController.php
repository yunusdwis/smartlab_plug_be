<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{

  public function register(Request $request){
    $this->validate($request, [
      'email' => ['required', 'email', 'unique:users'],
      'password' => ['required']
    ]);

    $password = Hash::make($request->password);
    $token = md5($request->email);

    $user = User::create([
      'email' => $request->email,
      'password' => $password,
      'token' => $token
    ]);
    
    return response()->json([
      'message' => 'user registered success',
      'token' => $token
    ]);
  }

  public function login(Request $request){
    $this->validate($request, [
      'email' => ['required', 'email'],
      'password' => ['required']
    ]);

    $user = User::where('email', $request->email)->first();

    if(!$user){
      return response()->json([
        'message' => 'email or password incorrect'
      ], 404); 
    }
    
    if(!Hash::check($request->password, $user->password)){
      return response()->json([
        'message' => 'email or password incorrect'
      ], 404); 
    }

    $token = md5($request->email);
    $user->token = $token;
    $user->save();

    return response()->json([
      'message' => 'user logged in successfully',
      'token' => $token
    ]);
  }

  public function logout(Request $request){
    $user = $request->user;
    $user->token = null;
    $user->save();

    return response()->json([
      'message' => 'user logout success'
    ]);
  }

}