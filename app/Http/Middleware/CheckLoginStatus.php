<?php

namespace App\Http\Middleware;

use App\MevetoUser;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckLoginStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // First, check if an authenticated user exist
        if(Auth::check())
        {
            $user = Auth::user();

            // Next let's check if the authenticated user has started using Meveto with the application.
            $mevetoUser = MevetoUser::where('id', '=', $user->id)->first();

            if($mevetoUser !== null)
            {
                /**
                 * Since the user has started using Meveto, make sure the user was logged in to the application using Meveto.
                 * Compare last_logged_in with last_logged_out such that if last_logged_out is later than last_logged_in, that means
                 * the user must have logged out of your application using their Meveto dashboard.
                 * Also don't forget to check if last_logged_out is NULL since it can have NULL value if the user has never logged out
                 * using their Meveto dashboard so far.
                 */
                if($mevetoUser->last_logged_out != null && ($mevetoUser->last_logged_out > $mevetoUser->last_logged_in))
                {
                    // Log the user out. This means that the user must have logged out using their Meveto dashboard.
                    return redirect()->route('logout');
                }
            } else {
                Log::info("User has not started using Meveto.");
            }
        } else {
            Log::info("A logged in user does not exist");
        }

        // Continue to the rest of the application
        return $next($request);
    }
}
