<?php

use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\Web\HvnAdminController;
use App\Http\Controllers\Web\HvnController;

Route::group(['prefix' => 'secure'], function () {
    // titles
    Route::get('movies/{id}', 'TitleController@show');
    Route::get('series/{id}', 'TitleController@show');
    Route::get('titles/{id}', 'TitleController@show');
    Route::get('titles/{id}/related', 'RelatedTitlesController@index');
    Route::get('titles', 'TitleController@index');
    Route::post('titles', 'TitleController@store');
    Route::post('titles/credits', 'TitleCreditController@store');
    Route::post('titles/credits/reorder', 'TitleCreditController@changeOrder');
    Route::put('titles/credits/{id}', 'TitleCreditController@update');
    Route::delete('titles/credits/{id}', 'TitleCreditController@destroy');
    Route::put('titles/{id}', 'TitleController@update');
    Route::delete('titles', 'TitleController@destroy');

    // seasons
    Route::post('titles/{titleId}/seasons', 'SeasonController@store');
    Route::delete('seasons/{seasonId}', 'SeasonController@destroy');

    // episodes
    Route::get('episodes/{id}', 'EpisodeController@show');
    Route::post('seasons/{seasonId}/episodes', 'EpisodeController@store');
    Route::put('episodes/{id}', 'EpisodeController@update');
    Route::delete('episodes/{id}', 'EpisodeController@destroy');

    // people
    Route::get('people', 'PersonController@index');
    Route::get('people/{id}', 'PersonController@show');
    Route::get('people/{personId}/full-credits/{titleId}/{department}', 'PersonCreditsController@fullTitleCredits');
    Route::post('people', 'PersonController@store');
    Route::put('people/{id}', 'PersonController@update');
    Route::delete('people', 'PersonController@destroy');

    // search
    Route::get('search/{query}', 'SearchController@index');

    // lists
    Route::get('lists', 'ListController@index');
    Route::post('lists/auto-update-content', 'ListController@autoUpdateContent');
    Route::get('lists/{id}', 'ListController@show');
    Route::post('lists', 'ListController@store');
    Route::put('lists/{id}', 'ListController@update');
    Route::post('lists/{id}/reorder', 'ListOrderController@changeOrder');
    Route::delete('lists/{id}', 'ListController@destroy');
    Route::post('lists/{id}/add', 'ListItemController@add');
    Route::post('lists/{id}/remove', 'ListItemController@remove');

    // homepage
    Route::get('homepage/lists', 'HomepageContentController@show');

    // related videos
    Route::get('related-videos', 'RelatedVideosController@index');

    // images
    Route::post('images', 'ImagesController@store');
    Route::delete('images', 'ImagesController@destroy');
    Route::post('titles/{id}/images/change-order', 'ImageOrderController@changeOrder');

    // reviews
    Route::get('reviews', 'ReviewController@index');
    Route::post('reviews', 'ReviewController@store');
    Route::put('reviews/{id}', 'ReviewController@update');
    Route::delete('reviews/{id}', 'ReviewController@destroy');

    // news
    Route::get('news', 'NewsController@index');
    Route::post('news/import-from-remote-provider', 'NewsController@importFromRemoteProvider');
    Route::get('news/{id}', 'NewsController@show');
    Route::post('news', 'NewsController@store');
    Route::put('news/{id}', 'NewsController@update');
    Route::delete('news', 'NewsController@destroy');

    // videos
    Route::get('videos', 'VideosController@index');
    Route::post('videos', 'VideosController@store');
    Route::put('videos/{id}', 'VideosController@update');
    Route::delete('videos/{ids}', 'VideosController@destroy');
    Route::post('videos/{id}/rate', 'VideoRatingController@rate');
    Route::post('videos/{video}/approve', 'VideoApproveController@approve');
    Route::post('videos/{video}/disapprove', 'VideoApproveController@disapprove');
    Route::post('videos/{video}/report', 'VideoReportController@report');
    Route::post('videos/{video}/log-play', 'VideosController@logPlay');
    Route::post('titles/{video}/videos/change-order', 'VideoOrderController@changeOrder');

    // title tags
    Route::post('titles/{titleId}/tags', 'TitleTagsController@store');
    Route::delete('titles/{titleId}/tags/{type}/{tagId}', 'TitleTagsController@destroy');

    // import
    Route::post('media/import', 'ImportMediaController@importMediaItem');
    Route::get('tmdb/import', 'ImportMediaController@importViaBrowse');

    // CAPTIONS
    Route::apiResource('caption', 'CaptionController');
    Route::post('caption/{videoId}/order', 'CaptionOrderController@changeOrder');

    // USER PROFILE
    Route::get('user-profile/{user}', [UserProfileController::class, 'show']);
    Route::get('user-profile/{user}/lists', [UserProfileController::class, 'loadLists']);
    Route::get('user-profile/{user}/ratings', [UserProfileController::class, 'loadRatings']);
    Route::get('user-profile/{user}/reviews', [UserProfileController::class, 'loadReviews']);
    Route::get('user-profile/{user}/comments', [UserProfileController::class, 'loadComments']);

    // HVN ADMIN — JSON API for native Angular tabs
    Route::get('admin/creators',                 [HvnAdminController::class, 'creatorsJson']);
    Route::post('admin/creators/{id}',           [HvnAdminController::class, 'updateCreator']);
    Route::post('admin/creators/{id}/toggle',    [HvnAdminController::class, 'toggleCreator']);
    Route::delete('admin/creators',              [HvnAdminController::class, 'deleteCreatorsJson']);
    Route::get('admin/community',                [HvnAdminController::class, 'communityJson']);
    Route::post('admin/community/{id}',          [HvnAdminController::class, 'updatePost']);
    Route::post('admin/community/{id}/hide',     [HvnAdminController::class, 'hidePost']);
    Route::delete('admin/community/{id}',        [HvnAdminController::class, 'deletePost']);
    Route::delete('admin/community',             [HvnAdminController::class, 'deleteCommunityPostsJson']);
});

// FRONT-END ROUTES THAT NEED TO BE PRE-RENDERED
$homeController = '\Common\Core\Controllers\HomeController@show';
Route::get('/', 'HomepageContentController@show')->middleware('prerenderIfCrawler');
Route::get('browse', 'TitleController@index')->middleware('prerenderIfCrawler');

// TITLE SHOW
Route::get('titles/{id}', 'TitleController@showWithoutNameParam')->middleware('prerenderIfCrawler');
Route::get('titles/{id}/{name}', 'TitleController@show')->middleware('prerenderIfCrawler');

// EPISODE SHOW
Route::get('titles/{id}/season/{season}/episode/{episode}', 'TitleController@showWithoutNameParam')->middleware('prerenderIfCrawler');
Route::get('titles/{id}/{name}/season/{season}/episode/{episode}', 'TitleController@show')->middleware('prerenderIfCrawler');

// SEASON SHOW
Route::get('titles/{id}/season/{season}', 'TitleController@showWithoutNameParam')->middleware('prerenderIfCrawler');
Route::get('titles/{id}/{name}/season/{season}', 'TitleController@show')->middleware('prerenderIfCrawler');

Route::get('people', 'PersonController@index')->middleware('prerenderIfCrawler');
Route::get('people/{id}', 'PersonController@show')->middleware('prerenderIfCrawler');
Route::get('people/{id}/{name}', 'PersonController@show')->middleware('prerenderIfCrawler');
Route::get('news', 'NewsController@index')->middleware('prerenderIfCrawler');
Route::get('news/{id}', 'NewsController@show')->middleware('prerenderIfCrawler');
Route::get('lists/{id}', 'ListController@show')->middleware('prerenderIfCrawler');

// HVN STANDALONE PAGES — Blade routes for /creators and /community removed.
// These are now native Angular SPA pages served by the catch-all below; the
// SPA hits the JSON API endpoints in the secure group further down.
Route::get('creator-signup', [HvnController::class, 'creatorSignup']);
Route::post('community/posts', [HvnController::class, 'communityStore']);
Route::post('community/{id}/comments', [HvnController::class, 'commentStore'])->where('id', '[0-9]+');
Route::get('creator/dashboard', [HvnController::class, 'creatorDashboard']);
Route::post('creator/profile', [HvnController::class, 'profileUpdate']);

// HVN PUBLIC JSON API — consumed by the SPA's native creators/community pages.
// AppHttpClient auto-prefixes outgoing requests with 'secure/', so all SPA
// reads AND writes have to live under this prefix.
Route::group(['prefix' => 'secure'], function () {
    Route::get('creators',                [HvnController::class, 'apiCreatorsList']);
    Route::get('creators/{username}',     [HvnController::class, 'apiCreatorProfile'])->where('username', '[^/]+');
    Route::get('community',               [HvnController::class, 'apiCommunityList']);
    Route::get('community/{id}',          [HvnController::class, 'apiCommunityShow'])->where('id', '[0-9]+');
    Route::post('community/posts',        [HvnController::class, 'communityStore']);
    Route::post('community/{id}/comments',[HvnController::class, 'commentStore'])->where('id', '[0-9]+');
    Route::post('community/{id}/like',    [HvnController::class, 'apiToggleLike'])->where('id', '[0-9]+');
    Route::post('community/comments/{id}/like', [HvnController::class, 'apiToggleCommentLike'])->where('id', '[0-9]+');
    Route::get('creator/dashboard',       [HvnController::class, 'apiCreatorDashboard']);
    // Creator content uploads — same controller as the /api/v1 routes,
    // mounted under /secure so session-cookie auth (the SPA) reaches it.
    Route::get('creator/content',         [\App\Http\Controllers\CreatorContentController::class, 'index']);
    Route::post('creator/content',        [\App\Http\Controllers\CreatorContentController::class, 'store']);
    Route::delete('creator/content/{id}', [\App\Http\Controllers\CreatorContentController::class, 'destroy'])->where('id', '[0-9]+');

    // Owner edit/delete (controller checks ownership; admin uses /secure/admin/*).
    Route::put('community/{id}',          [HvnController::class, 'apiUpdateOwnPost'])->where('id', '[0-9]+');
    Route::delete('community/{id}',       [HvnController::class, 'apiDeleteOwnPost'])->where('id', '[0-9]+');
    Route::put('community/comments/{id}', [HvnController::class, 'apiUpdateOwnComment'])->where('id', '[0-9]+');
    Route::delete('community/comments/{id}', [HvnController::class, 'apiDeleteOwnComment'])->where('id', '[0-9]+');
});

// HVN ADMIN — Blade GET routes removed; native Angular admin tabs handle these now.
// Mutating endpoints below are kept in case anything else still calls them.
Route::post('admin/creators/{id}',             [HvnAdminController::class, 'updateCreator']);
Route::post('admin/creators/{id}/toggle',      [HvnAdminController::class, 'toggleCreator']);
Route::post('admin/community/{id}',            [HvnAdminController::class, 'updatePost']);
Route::post('admin/community/{id}/hide',       [HvnAdminController::class, 'hidePost']);
Route::delete('admin/community/{id}',          [HvnAdminController::class, 'deletePost']);
Route::delete('admin/community/comments/{id}', [HvnAdminController::class, 'deleteComment']);

// HVN ADMIN — JSON API for native Angular admin tabs (under SPA's /secure prefix)
Route::group(['prefix' => 'secure/admin'], function () {
    Route::get('creators',          [HvnAdminController::class, 'apiCreators']);
    Route::put('creators/{id}',     [HvnAdminController::class, 'apiUpdateCreator']);
    Route::post('creators/{id}/toggle', [HvnAdminController::class, 'apiToggleCreator']);
    Route::post('creators/{id}/block',  [HvnAdminController::class, 'apiToggleBlock']);
    Route::delete('creators/{id}',  [HvnAdminController::class, 'apiDeleteCreator']);
    Route::get('community',         [HvnAdminController::class, 'apiCommunity']);
    Route::put('community/{id}',    [HvnAdminController::class, 'apiUpdatePost']);
    Route::post('community/{id}/hide', [HvnAdminController::class, 'apiHidePost']);
    Route::post('community/{id}/pin',  [HvnAdminController::class, 'apiPinPost']);
    Route::delete('community/{id}', [HvnAdminController::class, 'apiDeletePost']);
    Route::get('community/{id}/comments', [HvnAdminController::class, 'apiPostComments']);
    Route::delete('community/comments/{id}', [HvnAdminController::class, 'apiDeleteComment']);
});

// SESSION LOGOUT for server-rendered pages
Route::post('logout', [HvnController::class, 'logout']);

// Static-file fallback for /storage/* — when Hostinger's storage:link is
// missing, broken, or shadowed by a real directory the Apache rewrite
// punts these requests to PHP; without this route Laravel returns 422
// for the binary path. Serve them straight from storage/app/public.
Route::get('storage/{path}', function ($path) {
    $abs = storage_path('app/public/' . $path);
    if (!is_file($abs)) abort(404);
    return response()->file($abs);
})->where('path', '.*');

// CATCH ALL ROUTES AND REDIRECT TO HOME
Route::get('{all}', $homeController)->where('all', '.*');
