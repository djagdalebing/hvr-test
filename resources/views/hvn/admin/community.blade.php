@extends('hvn.admin.layout')
@section('title', 'Community')

@section('content')
<div class="page-heading">
    <h1>Community Posts</h1>
    <p>Review, hide, or delete community discussions.</p>
</div>

<form class="admin-search-bar" method="GET" action="/admin/community">
    <input type="text" name="q" value="{{ $search }}" placeholder="Search by post title…">
    <button type="submit">Search</button>
    @if($search)
        <a href="/admin/community" style="align-self:center;font-size:13px;color:#555;text-decoration:none;white-space:nowrap;">Clear</a>
    @endif
</form>

<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Post</th>
                <th>Author</th>
                <th>Stats</th>
                <th>Status</th>
                <th>Posted</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($posts as $post)
            <tr>
                <td style="max-width:280px;">
                    <a href="/community/{{ $post->id }}/{{ \Illuminate\Support\Str::slug($post->title) }}"
                       style="color:#e0e0e0;text-decoration:none;font-weight:500;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                       target="_blank">{{ $post->title }}</a>
                    <div style="font-size:12px;color:#555;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:260px;">
                        {{ \Illuminate\Support\Str::limit($post->body, 80) }}
                    </div>
                </td>
                <td style="white-space:nowrap;color:#888;font-size:13px;">{{ $post->user->username ?? 'Unknown' }}</td>
                <td style="white-space:nowrap;font-size:13px;color:#555;">
                    {{ $post->comments_count }} comments<br>
                    {{ $post->likes_count }} likes
                </td>
                <td>
                    @if($post->status === 'published')
                        <span class="badge badge-green">Published</span>
                    @elseif($post->status === 'removed')
                        <span class="badge badge-red">Hidden</span>
                    @else
                        <span class="badge badge-gray">{{ ucfirst($post->status) }}</span>
                    @endif
                </td>
                <td style="color:#555;font-size:12px;white-space:nowrap;">{{ $post->created_at->diffForHumans() }}</td>
                <td>
                    <div class="action-btns">
                        <a href="/admin/community/{{ $post->id }}/edit" class="btn-action">Edit</a>
                        <form method="POST" action="/admin/community/{{ $post->id }}/hide" style="margin:0;">
                            @csrf
                            <button type="submit" class="btn-action">
                                {{ $post->status === 'published' ? 'Hide' : 'Restore' }}
                            </button>
                        </form>
                        <form method="POST" action="/admin/community/{{ $post->id }}" style="margin:0;"
                              onsubmit="return confirm('Permanently delete this post and all its comments?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-action danger">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align:center;color:#444;padding:40px;">
                    {{ $search ? 'No posts found matching "' . e($search) . '".' : 'No community posts yet.' }}
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($posts->hasPages())
<div class="pagination">
    @if($posts->onFirstPage())
        <span style="opacity:.35;">← Prev</span>
    @else
        <a href="{{ $posts->previousPageUrl() }}">← Prev</a>
    @endif
    <span class="pg-info">Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
    @if($posts->hasMorePages())
        <a href="{{ $posts->nextPageUrl() }}">Next →</a>
    @else
        <span style="opacity:.35;">Next →</span>
    @endif
</div>
@endif
@endsection
