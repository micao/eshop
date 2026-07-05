<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        $user = $request->user();
        $redirect = $user && $user->isAdmin() ? '/admin/dashboard' : '/';

        return $request->wantsJson()
            ? response()->json(['status' => 'verified'])
            : redirect($redirect.'?verified=1');
    }
}
