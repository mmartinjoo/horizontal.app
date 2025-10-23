<?php

namespace App\Services\Integration\Communication\GoogleChat;

use Google\Client;
use Google\Service\HangoutsChat;
use Illuminate\Support\Facades\Storage;

class GoogleChat
{
    public function testing()
    {
        $redirecUrl = "https://tenant2-horizontal.loca.lt/api/integrations/google/oauth/callback";
        $client = new Client();
        $client->setAuthConfig(storage_path('/app/private/google_creds.json'));
        $client->addScope('https://googleapis.com/auth/chat.spaces');
        $client->addScope('https://googleapis.com/auth/chat.spaces.readonly');
        $client->addScope('https://googleapis.com/auth/chat.memberships');
        $client->addScope('https://googleapis.com/auth/auth/chat.memberships.readonly');
        $client->addScope('https://googleapis.com/auth/auth/chat.messages');
        $client->addScope('https://googleapis.com/auth/auth/auth/chat.messages.readonly');
        
        // $client->setAuthConfig([
        //     'client_id' => config('services.google_drive.client_id'),
        //     'client_secret' => config('services.google_drive.client_secret'),
        //     'redirect_uris' => ["https://developers.google.com/oauthplayground"],
        // ]);
        // $chat = new HangoutsChat($client);
        // dd($chat->spaces->listSpaces());
    }
}