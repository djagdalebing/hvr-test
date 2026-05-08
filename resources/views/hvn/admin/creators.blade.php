@extends('hvn.admin.layout')
@section('title', 'Creators')

@section('content')
<div class="page-heading">
    <h1>Creators</h1>
    <p>Manage creator accounts — toggle access or review profiles.</p>
</div>

<form class="admin-search-bar" method="GET" action="/admin/creators">
    <input type="text" name="q" value="{{ $search }}" placeholder="Search by username or email…">
    <button type="submit">Search</button>
    @if($search)
        <a href="/admin/creators" style="align-self:center;font-size:13px;color:#555;text-decoration:none;white-space:nowrap;">Clear</a>
    @endif
</form>

<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Creator</th>
                <th>Bio</th>
                <th>Joined</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($creators as $creator)
            <tr>
                <td>
                    <div style="font-weight:500;color:#e0e0e0;">{{ $creator->username }}</div>
                    <div style="font-size:12px;color:#555;">{{ $creator->email }}</div>
                </td>
                <td style="max-width:260px;">
                    @if($creator->creatorProfile && $creator->creatorProfile->bio)
                        <span style="font-size:13px;color:#777;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ $creator->creatorProfile->bio }}</span>
                    @else
                        <span style="font-size:12px;color:#444;">—</span>
                    @endif
                </td>
                <td style="color:#555;font-size:12px;white-space:nowrap;">{{ $creator->created_at->diffForHumans() }}</td>
                <td>
                    @if($creator->role === 'creator')
                        <span class="badge badge-green">Active</span>
                    @else
                        <span class="badge badge-red">Revoked</span>
                    @endif
                </td>
                <td>
                    <div class="action-btns">
                        <a href="/admin/creators/{{ $creator->id }}/edit" class="btn-action">Edit</a>
                        <a href="/creators/{{ $creator->username }}" class="btn-action" target="_blank">View</a>
                        <form method="POST" action="/admin/creators/{{ $creator->id }}/toggle" style="margin:0;"
                              data-username="{{ $creator->username }}"
                              onsubmit="return confirm('Toggle creator access for ' + this.dataset.username + '?')">
                            @csrf
                            <button type="submit" class="btn-action {{ $creator->role === 'creator' ? 'danger' : 'success' }}">
                                {{ $creator->role === 'creator' ? 'Revoke' : 'Restore' }}
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align:center;color:#444;padding:40px;">
                    {{ $search ? 'No creators found matching "' . e($search) . '".' : 'No creators yet.' }}
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($creators->hasPages())
<div class="pagination">
    @if($creators->onFirstPage())
        <span style="opacity:.35;">← Prev</span>
    @else
        <a href="{{ $creators->previousPageUrl() }}">← Prev</a>
    @endif
    <span class="pg-info">Page {{ $creators->currentPage() }} of {{ $creators->lastPage() }}</span>
    @if($creators->hasMorePages())
        <a href="{{ $creators->nextPageUrl() }}">Next →</a>
    @else
        <span style="opacity:.35;">Next →</span>
    @endif
</div>
@endif
@endsection
