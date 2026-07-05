<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        $user = $request->user();
        $redirect = $user && $user->isAdmin() ? '/admin/dashboard' : '/';

        return $request->wantsJson()
            ? response()->json(['status' => 'registered'])
            : redirect($redirect);
    }
}
