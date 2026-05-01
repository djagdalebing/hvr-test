<?php

return [
    'local_search_mode' => env('LOCAL_SEARCH_MODE', 'fulltext'),
    'scout_mysql_mode' => env('SCOUT_MYSQL_MODE', 'extended'),
    'rating_column' => env('RATING_COLUMN', 'tmdb_vote_average'),
];
