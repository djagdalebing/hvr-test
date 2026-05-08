@extends('hvn.admin.layout')
@section('title', 'Edit Post')

@section('head')
<style>
    .form-card { background: #2a2a2a; border: 1px solid rgba(255,255,255,0.06); border-radius: 8px; padding: 24px; margin-bottom: 24px; }
    .form-row { margin-bottom: 16px; }
    .form-row label { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; color: #888; margin-bottom: 6px; }
    .form-row input[type=text], .form-row select, .form-row textarea {
        width: 100%; background: #1a1a1a; border: 1px solid #333; border-radius: 5px;
        color: #e0e0e0; padding: 9px 12px; font-size: 14px; font-family: inherit; outline: none;
    }
    .form-row input:focus, .form-row select:focus, .form-row textarea:focus { border-color: #F65F54; }
    .form-row textarea { min-height: 140px; resize: vertical; }
    .form-actions { display: flex; gap: 10px; align-items: center; margin-top: 8px; }
    .btn-primary { background: #F65F54; color: #fff; border: none; border-radius: 5px; padding: 9px 18px; font-size: 14px; font-family: inherit; cursor: pointer; }
    .btn-primary:hover { background: #d94f45; }
    .field-err { color: #f87171; font-size: 12px; margin-top: 4px; }
    .meta-line { color: #555; font-size: 12px; }
    .comment-row { padding: 12px 14px; border-bottom: 1px solid rgba(255,255,255,0.04); display: flex; gap: 12px; align-items: flex-start; }
    .comment-row:last-child { border-bottom: none; }
    .comment-body { flex: 1; min-width: 0; }
    .comment-body .who { font-size: 13px; color: #ccc; font-weight: 500; }
    .comment-body .when { font-size: 11px; color: #555; margin-left: 6px; }
    .comment-body .txt { font-size: 13px; color: #aaa; margin-top: 4px; white-space: pre-wrap; word-break: break-word; }
</style>
@endsection

@section('content')
<div class="page-heading">
    <h1>Edit Post</h1>
    <p class="meta-line">By {{ $post->user->username ?? 'Unknown' }} · {{ $post->created_at->diffForHumans() }} · <a href="/admin/community" style="color:#888;">← Back to list</a></p>
</div>

<form class="form-card" method="POST" action="/admin/community/{{ $post->id }}">
    @csrf

    <div class="form-row">
        <label for="title">Title</label>
        <input id="title" type="text" name="title" value="{{ old('title', $post->title) }}" maxlength="255" required>
        @error('title')<div class="field-err">{{ $message }}</div>@enderror
    </div>

    <div class="form-row">
        <label for="body">Body</label>
        <textarea id="body" name="body" required>{{ old('body', $post->body) }}</textarea>
        @error('body')<div class="field-err">{{ $message }}</div>@enderror
    </div>

    <div class="form-row" style="max-width:240px;">
        <label for="status">Status</label>
        <select id="status" name="status">
            @foreach(['published' => 'Published', 'draft' => 'Draft', 'removed' => 'Hidden'] as $val => $label)
                <option value="{{ $val }}" @selected(old('status', $post->status) === $val)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')<div class="field-err">{{ $message }}</div>@enderror
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">Save changes</button>
        <a href="/community/{{ $post->id }}/{{ \Illuminate\Support\Str::slug($post->title) }}" target="_blank" class="btn-action">View on site</a>
    </div>
</form>

<div class="section-header"><h2>Comments ({{ $comments->count() }})</h2></div>
<div class="form-card" style="padding: 0;">
    @forelse($comments as $comment)
        <div class="comment-row">
            <div class="comment-body">
                <span class="who">{{ $comment->user->username ?? 'Unknown' }}</span>
                <span class="when">{{ $comment->created_at->diffForHumans() }}</span>
                <div class="txt">{{ $comment->body }}</div>
            </div>
            <form method="POST" action="/admin/community/comments/{{ $comment->id }}" style="margin:0;"
                  onsubmit="return confirm('Delete this comment?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-action danger">Delete</button>
            </form>
        </div>
    @empty
        <div style="padding:30px;text-align:center;color:#444;">No comments on this post.</div>
    @endforelse
</div>
@endsection
