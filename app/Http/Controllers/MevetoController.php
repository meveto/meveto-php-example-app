<?php

namespace App\Http\Controllers;

use App\Events\LoggedOut;
use App\MevetoState;
use App\MevetoUser;
use App\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Meveto\Client\MevetoService;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Log;

class MevetoController extends Controller
{
    use AuthenticatesUsers;

    /**
     * The Meveto Object
     * @var
     */
    protected $meveto;

    /**
     * 
     */
    public function __construct()
    {
        $this->Meveto();
    }

    /**
     * Login to Meveto
     */
    public function login(Request $request)
    {
        /**
         * Generate an application state and store it. Session or cookie will not work here because you will be redirected away from your
         * application to Meveto. You can save the state in your database or more efficiently in something like Redis if your application
         * is using it.
         * This state must be passed to the authorization request and when the response is received back from Meveto, you must compare
         * this state with the one that's received and make sure both the states are exactly the same before further processing
         * user login
         * 
         * We have created a Model 'MevetoState' for simplicity and we will use it to store the application state. we are intentionally
         * creating a 512 characters long random string to represent the state to make sure that practically ending up with the same
         * state value for more than 1 users at the same time never happens. Additionally, this state record must be immediately removed
         * when it is verified at the redirect
         */
        $state = Str::random(256);
        MevetoState::create([
            'state' => $state
        ]);
        $this->meveto->setState($state);

        /**
         * Check if there is a `client_token` in the login URL of your application. If so, you need to pass the value of this token to
         * the Meveto login method. Meveto application will attach a one time `client_token` to the login URL of your application
         * to bypass the need for logging in to Meveto first before Meveto can log the user in to your application. 
         */

        /**
         * If your application allows Meveto account sharing, then you must also check for a `sharing_token` in the login URL. If a
         * sharing_token is found, then you need to pass it as a second parameter to the Meveto login method.
         */
        $url = $this->meveto->login($request->get('client_token'), $request->get('sharing_token'));
        return Redirect::away($url);
    }

    /**
     * Handle Redirect when Meveto returns the authorization code.
     */
    public function handleRedirect(Request $request)
    {
        // Get the authorization code and state from the redirect URL
        $code = $request->get('code');
        $state = $request->get('state');

        /**
         * You must confirm that the application state that was generated before the authorization request and was stored in the 
         * database, is exactly the same as the one received from Meveto.
         * In this case, we can simply check if the state returnd by Meveto, exist in our the database.
         */
        $appMevetoState = MevetoState::where('state', $state)->first();
        if($appMevetoState !== null)
        {
            // You must delete the record for this state before continuing
            $appMevetoState->delete();

            // Exchange auth code with access token
            $response = $this->meveto->getAccessToken($code);

            /**
             * The getAccessToken method returns an array that contains token_type, expires_in, access_token and refresh_token.
             * You can do whatever you want with the rest of the data, but for sake of simplicity, let's use the access_token to get
             * user information i.e. email address or username
             */
            $data = $this->meveto->getResourceOwnerData($response['access_token']);

            /**
             * The getResourceOwnerData method also returns an array which at the moment contains `user` that's by default the user's
             * email address or an alias of the user if the user has an alias identifier for your application.
             * 
             */
            $userIdentifer = $data['user'];

            /**
             * Next, Try to find the user by Meveto ID.
             */
            $user = User::where('meveto_id', '=', $userIdentifer)->first();
            if($user !== null)
            {
                try {
                    /**
                     * Create or Update the MevetoUser record for this login
                     */
                    MevetoUser::updateOrCreate(
                        ['id' => $user->id],
                        ['last_logged_in' => Carbon::now()->timestamp]
                    );
    
                    // Log the user in and redirect the user to any destination you want.
                    Auth::login($user);
                    return redirect()->route('home');

                } catch(Exception $e)
                {
                    // Catch any exceptions
                }
            }

            /**
             * If userIdentifier returned by Meveto could not be found, then this user might be registered with your application by a
             * different identifier such as different email address. In this case, you might want to allow the user to synchronize their
             * account with their Meveto's account. We also call this as setting an alias name for the the user at Meveto. Next time,
             * Meveto will send you the user's alias identifier for your application instead of returning their registered identifier
             * with Meveto.
             * For this process to work, your application should first ask the user to enter their account credentials. You must verify
             * the credentials for their account and only then ask Meveto to set an alias identifier for the account. Your application
             * will be required to send an Authorization token and a key value pair {"alias_name: "user_alias_you_want_to_set"} in
             * the setting alias request.
             * First redirect user to a login page. But to not lose the Auth token returned by Meveto, you can either add it to the URL
             * of the login page, save it in the database, an http cookie or anyway you want. It is necessary you ensure the security and
             * integrity of the Auth token. We highly recommend adding the token to the URL of the login page. This is the most striaght-
             * forward and reasonably secure way to do it. Because the user that can see the token in the URL, is the one that just
             * authorized your application's access and is owner of the token.
             */

            return redirect()->route('meveto-connect', ['meveto_id' => $userIdentifer]);

        } else {
            return 'States did not match. Invalid OAuth Response';
        }
    }

    /**
     * Validate a User's credentials before attaching a Meveto ID to the user
     */
    public function connectToMeveto(Request $request)
    {
        $this->validateLogin($request);

        /**
         * If Login attempt was successful, then send the synchronization request to Meveto.
         */
        if ($this->attemptLogin($request)) {

            // Attach Meveto ID to the user now
            try {
                $user = User::where('email', $request->post('email'))->first();
                $user->meveto_id = $request->post('meveto_id');
                $user->save();

                /**
                 * Create or Update the MevetoUser record for this login
                 */
                MevetoUser::updateOrCreate(
                    ['id' => $user->id],
                    ['last_logged_in' => Carbon::now()->timestamp]
                );
                
                return $this->sendLoginResponse($request);
            } catch(\Exception $e)
            {
                var_dump($e->getTrace());
                return 'There was a problem::'.$e->getMessage();
                // Catch any exceptions
            }
        }

        return $this->sendFailedLoginResponse($request);
    }

    /**
     * This method will handle a webhook call to your application by Meveto
     * 
     * @param Request
     * @return Response
     */
    public function handleWebhookCall(Request $request)
    {
        Log::info("Received webhook call from Meveto.");
        /**
         * Meveto will send a `type` attribute in the webhook call's payload to your application. Your application should switch over
         * the `type` of the call and then process the request accordingly.
         * 
         * If user identification is required for your application to process a webhook call, then Meveto will send your application
         * a user token. You will need to grab this token from the request and exchange it with Meveto for information on the user that
         * performed the action. The token is sent to your application as "user_token"
         */

        //First grab payload from the request
        $payload = $request->all();

        switch($payload['type'])
        {
            // This `type` means that the User has requested a logout from your application using their Meveto dashboard.
            case 'User_Logged_Out':
                Log::info("Processing `User_Logged_Out` event...");
                
                // Identify the user that logged out. Exchange the 'user_token' with Meveto for user ID
                $userToken = $payload['user_token'];
                try {
                    // Retrieve the user from Meveto
                    $user = $this->meveto->getTokenUser($userToken);

                    /**
                     * Next, Find the user by Meveto ID
                     */
                    $user = User::where('meveto_id', $user)->first();
                    if($user !== null)
                    {
                        try {
                            /**
                             * Remember that this method was invoked by a webhook call to your application from Meveto. Therefore, current
                             * execution of this method does not have any link with the users's browser. Your application might be using
                             * browser cookies or an Authorization token for user login. There are two recommended ways of loggin the user
                             * out.
                             * 1. If your application is using browser cookies/session to keep a user logged in, then we can use MevetoUser
                             * instance and set the `is_logged_in` falg to FALSE. Then, there should be a global middleware or a method
                             * that must run before each protected page of your application is accessed. In this middleware or method, you
                             * will need to check if the `is_logged_in` property for the currently authenticated user has been set to false.
                             * If so, your clear authentication cookies/sessions of your browser and redirect the user to another location.
                             * You can choose to have other similar approaches if you want. So as soon as the user's browser makes a request
                             * to your application, they will be automatically logged out. You can further inhance this experience for the user
                             * by using a service like Pusher.
                             * 
                             * 2. If your application is using access tokens such as JWT tokens for authentication, then the process will be
                             * relatively simple. All you need to do here is to revoke or delete the access token for the current user. But
                             * for this to work, your application must be keeping track/storing access tokens. If access tokens are not stored
                             * and managed, then you can still implement a similar approach as described in approach 1 above. Only this time, in
                             * your middleware or global method, you would invalidate the access token if your application is not using cookies.
                             */
                            MevetoUser::where('id', $user->id)->update(['last_logged_out' => Carbon::now()->timestamp]);
    
                            /**
                             * Finally, invoke an event that would let your application's user interface to refresh automatically. This will make
                             * the logout process seamless for your users. Usually, for this purpose, your application would use something like
                             * Pusher or Socket.io
                             */
                            event(new LoggedOut($user->id, $user->name));

                            // You need to return a 200 OK response to Meveto
                            return response()->json('');
                        } catch(\Exception $e)
                        {
                            // Catch any exceptions
                            Log::info("An error occured while trying to logout user `{$user->id}`", [
                                'message' => $e->getMessage()
                            ]);
                        }
                    } else {
                        // You can ignore the webhook call for logout if the Meveto ID could not be matched to a user at your application.
                    }
        
                } catch(\Exception $e)
                {
                    // Catch any exceptions thrown by Meveto SDK
                    Log::info("Could not retrieve logout user identifier from Meveto.", [
                        'message' => $e->getMessage()
                    ]);
                }
            break;
            case 'Meveto_Protection_Removed':
                Log::info("Processing `Meveto_Protection_Removed` event...");

                // Identify the user that removed Meveto protection. Exchange the 'user_token' with Meveto for user ID
                $userToken = $payload['user_token'];
                try {
                    // Retrieve the user from Meveto
                    $user = $this->meveto->getTokenUser($userToken);

                    /**
                     * Next, Find the user by Meveto ID
                     */
                    $user = User::where('meveto_id', $user)->first();
                    if($user !== null)
                    {
                        try {
                            // Set Meveto ID on the user to NULL
                            $user->meveto_id = null;
                            $user->save();

                            // Next, also remove the corresponding MevetoUser Record
                            MevetoUser::where('id', $user->id)->delete();

                            // You need to return a 200 OK response to Meveto
                            return response()->json('');
                        } catch(\Exception $e)
                        {
                            // Catch any exceptions
                            Log::info("An error occured while trying to set Meveto ID to null for user `{$user->id}`", [
                                'message' => $e->getMessage()
                            ]);
                        }
                    } else {
                        // You can ignore the webhook call for logout if the Meveto ID could not be matched to a user at your application.
                    }
        
                } catch(\Exception $e)
                {
                    // Catch any exceptions thrown by Meveto SDK
                    Log::info("Could not retrieve logout user identifier from Meveto.", [
                        'message' => $e->getMessage()
                    ]);
                }
            break;
        }

    }

    /**
     * Show the connect to Meveto page
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function loginPage(Request $request)
    {
        return view('auth.connect')->with('meveto_id', $request->get('meveto_id'));
    }

    /**
     * Display the warning to use Meveto for logging in to Meveto protected accounts
     */
    public function useMevetoPage(Request $request)
    {
        return view('usemeveto');
    }

    /**
     * Instantiate Meveto Object
     * 
     */
    protected function Meveto()
    {
        $this->meveto = new MevetoService([
            'id' => config('meveto.id'),
            'secret' => config('meveto.secret'),
            'scope' => config('meveto.scope'),
            'redirect_url' => config('meveto.redirect_url'),
        ]);
    }
}
