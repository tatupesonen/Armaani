<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SteamCMD Path
    |--------------------------------------------------------------------------
    |
    | The full path to the SteamCMD executable. On Linux with the package
    | manager install, this is typically /usr/games/steamcmd.
    |
    */

    'steamcmd_path' => env('STEAMCMD_PATH', '/usr/games/steamcmd'),

    /*
    |--------------------------------------------------------------------------
    | Steam Web API Key
    |--------------------------------------------------------------------------
    |
    | Used to fetch workshop mod metadata (name, file size) from the
    | Steam Web API. Generate one at https://steamcommunity.com/dev/apikey
    |
    */

    'steam_api_key' => env('STEAM_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Servers Base Path
    |--------------------------------------------------------------------------
    |
    | The base directory where Arma 3 server installations are stored.
    | Each server instance gets its own subdirectory: {base_path}/{server_id}/
    |
    */

    'servers_base_path' => env('SERVERS_BASE_PATH', storage_path('arma/servers')),

    /*
    |--------------------------------------------------------------------------
    | Mods Base Path
    |--------------------------------------------------------------------------
    |
    | The base directory where SteamCMD downloads workshop mods.
    | Mods end up at: {base_path}/steamapps/workshop/content/107410/{mod_id}/
    |
    */

    'mods_base_path' => env('MODS_BASE_PATH', storage_path('arma/mods')),

    /*
    |--------------------------------------------------------------------------
    | Missions Base Path
    |--------------------------------------------------------------------------
    |
    | The base directory where uploaded PBO mission files are stored.
    | These files are symlinked into each game install's mpmissions/
    | directory when a server is started.
    |
    */

    'missions_base_path' => env('MISSIONS_BASE_PATH', storage_path('arma/missions')),

    /*
    |--------------------------------------------------------------------------
    | Arma 3 Steam IDs
    |--------------------------------------------------------------------------
    |
    | Steam App ID for the Arma 3 dedicated server, and the game ID
    | used when downloading workshop items.
    |
    */

    'server_app_id' => 233780,

    'game_id' => 107410,

];
