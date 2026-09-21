<?php

use App\Http\Controllers\CommunityController;
use App\Http\Controllers\CreatorContentController;
use App\Http\Controllers\CreatorProfileController;
use App\Http\Controllers\CreatorsDirectoryController;
use App\Http\Controllers\EpisodeController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\ListItemController;
use App\Http\Controllers\ListOrderController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonCreditsController;
use App\Http\Controllers\RelatedTitlesController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TitleController;
use App\Http\Controllers\TitleCreditController;
use App\Http\Controllers\UserProfileController;
use Common\Auth\Controllers\GetAccessTokenController;
use Common\Auth\Controllers\RegisterController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['prefix' => 'v1'], function() {
    Route::group(['middleware' => 'auth:sanctum'], function () {
        // TITLES
        Route::get('titles/{id}', [TitleController::class, 'show']);
        Route::get('movies/{id}', [TitleController::class, 'show']);
        Route::get('series/{id}', [TitleController::class, 'show']);
        Route::get('titles/{id}/related', [RelatedTitlesController::class, 'index']);
        Route::get('titles', [TitleController::class, 'index']);
        Route::post('titles', [TitleController::class, 'store']);
        Route::post('titles/credits', [TitleCreditController::class, 'store']);
        Route::post('titles/credits/reorder', [TitleCreditController::class, 'changeOrder']);
        Route::put('titles/credits/{id}', [TitleCreditController::class, 'update']);
        Route::delete('titles/credits/{id}', [TitleCreditController::class, 'destroy']);
        Route::put('titles/{id}', [TitleController::class, 'update']);
        Route::delete('titles', [TitleController::class, 'destroy']);

        // episodes
        Route::get('episodes/{id}', [EpisodeController::class, 'show']);

        // people
        Route::get('people', [PersonController::class, 'index']);
        Route::get('people/{id}', [PersonController::class, 'show']);

        // search
        Route::get('search/{query}', [SearchController::class, 'index']);

        // lists
        Route::get('lists/{id}', [ListController::class, 'show']);
        Route::post('lists', [ListController::class, 'store']);
        Route::put('lists/{id}', [ListController::class, 'update']);
        Route::post('lists/{id}/reorder', [ListOrderController::class, 'changeOrder']);
        Route::delete('lists/{id}', [ListController::class, 'destroy']);
        Route::post('lists/{id}/add',  [ListItemController::class, 'add']);
        Route::post('lists/{id}/remove', [ListItemController::class, 'remove']);

        // reviews
        Route::post('reviews', [ReviewController::class, 'store'])->middleware('not.blocked');
        Route::put('reviews/{id}', [ReviewController::class, 'update'])->middleware('not.blocked');
        Route::delete('reviews/{id}', [ReviewController::class, 'destroy']);

        // news
        Route::get('news', [NewsController::class, 'index']);
        Route::get('news/{id}', [NewsController::class, 'show']);

        // USER PROFILE
        Route::get('user-profile/{user}', [UserProfileController::class, 'show']);
        Route::get('user-profile/{user}/lists', [UserProfileController::class, 'loadLists']);
        Route::get('user-profile/{user}/ratings', [UserProfileController::class, 'loadRatings']);
        Route::get('user-profile/{user}/reviews', [UserProfileController::class, 'loadReviews']);
        Route::get('user-profile/{user}/comments', [UserProfileController::class, 'loadComments']);

        // CREATORS DIRECTORY — accessible to all authenticated users
        Route::get('creators', [CreatorsDirectoryController::class, 'index']);
        Route::get('creators/{userId}', [CreatorsDirectoryController::class, 'show']);

        // COMMUNITY — accessible to all authenticated users
        Route::get('community/posts', [CommunityController::class, 'index']);
        Route::get('community/posts/{postId}', [CommunityController::class, 'show']);
        Route::post('community/posts', [CommunityController::class, 'store'])->middleware('not.blocked');
        Route::post('community/posts/{postId}/comments', [CommunityController::class, 'addComment'])->middleware('not.blocked');
        Route::post('community/posts/{postId}/like', [CommunityController::class, 'toggleLike'])->middleware('not.blocked');

        // CREATOR — restricted to creator role only
        Route::middleware('role:creator')->group(function () {
            Route::get('creator/profile', [CreatorProfileController::class, 'show']);
            Route::post('creator/profile', [CreatorProfileController::class, 'store']);
            Route::get('creator/content', [CreatorContentController::class, 'index']);
            Route::post('creator/content', [CreatorContentController::class, 'store']);
            // POST (not PUT) so the browser can send multipart/form-data for
            // optional artwork replacement, same as store().
            Route::post('creator/content/{id}', [CreatorContentController::class, 'update']);
            Route::delete('creator/content/{id}', [CreatorContentController::class, 'destroy']);
            // Series episodes (see routes/web.php for why these are
            // creator-scoped rather than reusing Season/EpisodeController).
            Route::get('creator/content/{id}/episodes', [CreatorContentController::class, 'episodes']);
            Route::post('creator/content/{id}/episodes', [CreatorContentController::class, 'storeEpisode']);
            Route::delete('creator/content/{id}/episodes/{episodeId}', [CreatorContentController::class, 'destroyEpisode']);
        });
    });

    // AUTH
    Route::post('auth/register', [RegisterController::class, 'register']);
    Route::post('auth/login', [GetAccessTokenController::class, 'login']);
    Route::get('auth/social/{provider}/callback', '\Common\Auth\Controllers\SocialAuthController@loginCallback');
    Route::post('auth/password/email', '\Common\Auth\Controllers\SendPasswordResetEmailController@sendResetLinkEmail');
});
