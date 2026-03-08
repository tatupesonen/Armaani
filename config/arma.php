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
    | Games Base Path
    |--------------------------------------------------------------------------
    |
    | The base directory where game server installations are stored.
    | Each game install gets its own subdirectory: {base_path}/{game_install_id}/
    |
    */

    'games_base_path' => env('GAMES_BASE_PATH') ?: storage_path('arma/games'),

    /*
    |--------------------------------------------------------------------------
    | Servers Base Path
    |--------------------------------------------------------------------------
    |
    | The base directory where server instance data is stored.
    | Each server gets its own subdirectory: {base_path}/{server_id}/
    |
    */

    'servers_base_path' => env('SERVERS_BASE_PATH') ?: storage_path('arma/servers'),

    /*
    |--------------------------------------------------------------------------
    | Mods Base Path
    |--------------------------------------------------------------------------
    |
    | The base directory where SteamCMD downloads workshop mods.
    | Mods end up at: {base_path}/steamapps/workshop/content/{game_id}/{mod_id}/
    |
    */

    'mods_base_path' => env('MODS_BASE_PATH') ?: storage_path('arma/mods'),

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

    'missions_base_path' => env('MISSIONS_BASE_PATH') ?: storage_path('arma/missions'),

    /*
    |--------------------------------------------------------------------------
    | Max Backups Per Server
    |--------------------------------------------------------------------------
    |
    | The maximum number of profile backups to retain per server.
    | When the limit is reached, the oldest backup is automatically deleted
    | to make room for new ones. Set to 0 for unlimited.
    |
    */

    'max_backups_per_server' => (int) env('MAX_BACKUPS_PER_SERVER', 20),

    /*
    |--------------------------------------------------------------------------
    | Home Path
    |--------------------------------------------------------------------------
    |
    | The HOME directory used by SteamCMD for storing its configuration.
    |
    */

    'home_path' => env('HOME', '/root'),

];
