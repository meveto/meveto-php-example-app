<?php

namespace App\Listeners;

use App\Events\LoggedOut;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class sendBrowserRefreshSignal
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  LoggedOut  $event
     * @return void
     */
    public function handle(LoggedOut $event)
    {
        //
    }
}
