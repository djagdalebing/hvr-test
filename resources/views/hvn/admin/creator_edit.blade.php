@extends('hvn.admin.layout')
@section('title', 'Edit Creator')

@section('head')
<style>
    .form-card { background: #2a2a2a; border: 1px solid rgba(255,255,255,0.06); border-radius: 8px; padding: 24px; margin-bottom: 24px; }
    .form-row { margin-bottom: 16px; }
    .form-row label { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; color: #888; margin-bottom: 6px; }
    .form-row input[type=text], .form-row input[type=email], .form-row textarea {
        width: 100%; background: #1a1a1a; border: 1px solid #333; border-radius: 5px;
        color: #e0e0e0; padding: 9px 12px; font-size: 14px; font-family: inherit; outline: none;
    }
    .form-row input:focus, .form-row textarea:focus { border-color: #F65F54; }
    .form-row textarea { min-height: 100px; resize: vertical; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-actions { display: flex; gap: 10px; align-items: center; margin-top: 8px; }
    .btn-primary { background: #F65F54; color: #fff; border: none; border-radius: 5px; padding: 9px 18px; font-size: 14px; font-family: inherit; cursor: pointer; }
    .btn-primary:hover { background: #d94f45; }
    .field-err { color: #f87171; font-size: 12px; margin-top: 4px; }
    .meta-line { color: #555; font-size: 12px; }
</style>
@endsection

@section('content')
<div class="page-heading">
    <h1>Edit Creator</h1>
    <p class="meta-line">{{ $creator->username }} · {{ $creator->email }} · <a href="/admin/creators" style="color:#888;">← Back to list</a></p>
</div>

<form class="form-card" method="POST" action="/admin/creators/{{ $creator->id }}">
    @csrf

    <div class="form-grid">
        <div class="form-row">
            <label for="display_name">Display name</label>
            <input id="display_name" type="text" name="display_name" value="{{ old('display_name', $profile->display_name) }}" maxlength="100">
            @error('display_name')<div class="field-err">{{ $message }}</div>@enderror
        </div>
        <div class="form-row">
            <label for="contact_email">Contact email</label>
            <input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email', $profile->contact_email) }}">
            @error('contact_email')<div class="field-err">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="form-row">
        <label for="bio">Bio</label>
        <textarea id="bio" name="bio">{{ old('bio', $profile->bio) }}</textarea>
    </div>

    <div class="form-row">
        <label for="profile_photo">Profile photo URL</label>
        <input id="profile_photo" type="text" name="profile_photo" value="{{ old('profile_photo', $profile->profile_photo) }}">
    </div>

    <div class="form-row">
        <label for="website_url">Website</label>
        <input id="website_url" type="text" name="website_url" value="{{ old('website_url', $profile->website_url ?: $profile->website) }}">
    </div>

    <div class="form-grid">
        <div class="form-row">
            <label for="youtube_url">YouTube</label>
            <input id="youtube_url" type="text" name="youtube_url" value="{{ old('youtube_url', $profile->youtube_url) }}">
        </div>
        <div class="form-row">
            <label for="twitter_url">Twitter / X</label>
            <input id="twitter_url" type="text" name="twitter_url" value="{{ old('twitter_url', $profile->twitter_url) }}">
        </div>
        <div class="form-row">
            <label for="instagram_url">Instagram</label>
            <input id="instagram_url" type="text" name="instagram_url" value="{{ old('instagram_url', $profile->instagram_url) }}">
        </div>
        <div class="form-row">
            <label for="facebook_url">Facebook</label>
            <input id="facebook_url" type="text" name="facebook_url" value="{{ old('facebook_url', $profile->facebook_url) }}">
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">Save changes</button>
        <a href="/creators/{{ $creator->username }}" target="_blank" class="btn-action">View public profile</a>
    </div>
</form>
@endsection
