<?php

namespace App\Http\Controllers;

use Common\Core\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Site-wide beta feedback ("Send Feedback" / "Report a Problem").
 *
 * Open to guests as well as signed-in users — a tester who hits a confusing
 * screen shouldn't have to log in to tell us about it.
 */
class FeedbackController extends BaseController
{
    /** POST /secure/feedback */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'type'          => 'required|string|in:bug,confusing,suggestion,other',
            'message'       => 'required|string|min:5|max:5000',
            'page_route'    => 'nullable|string|max:500',
            'page_title'    => 'nullable|string|max:250',
            'page_url'      => 'nullable|string|max:1000',
            'contact_email' => 'nullable|email|max:190',
        ]);

        $id = DB::table('hvn_feedback')->insertGetId([
            'user_id'       => optional($request->user())->id,
            'type'          => $request->input('type'),
            'message'       => $request->input('message'),
            'page_route'    => Str::limit((string) $request->input('page_route'), 495, ''),
            'page_title'    => Str::limit((string) $request->input('page_title'), 245, ''),
            'page_url'      => Str::limit((string) $request->input('page_url'), 995, ''),
            'contact_email' => $request->input('contact_email'),
            'user_agent'    => Str::limit((string) $request->header('User-Agent'), 495, ''),
            'status'        => 'new',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json(['status' => 'ok', 'id' => $id]);
    }

    /** GET /secure/admin/feedback — admin-only list of submitted feedback. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermission('admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $rows = DB::table('hvn_feedback')
            ->leftJoin('users', 'users.id', '=', 'hvn_feedback.user_id')
            ->orderByDesc('hvn_feedback.id')
            ->limit(500)
            ->get([
                'hvn_feedback.*',
                'users.username',
                'users.email as user_email',
            ]);

        return response()->json(['feedback' => $rows]);
    }

    /** POST /secure/admin/feedback/{id} — mark handled / reopen. */
    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $this->validate($request, [
            'status' => 'required|string|in:new,done',
        ]);

        DB::table('hvn_feedback')->where('id', $id)->update([
            'status' => $request->input('status'),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    /** DELETE /secure/admin/feedback/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        DB::table('hvn_feedback')->where('id', $id)->delete();

        return response()->json(['status' => 'ok']);
    }

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();
        return $user && $user->hasPermission('admin');
    }
}
