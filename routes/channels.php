<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Add support for user.{id} channel format (Laravel will add private- prefix automatically)
Broadcast::channel('user.{id}', function ($user, $id) {
    $authorized = (int) $user->id === (int) $id;
    return $authorized;
});
