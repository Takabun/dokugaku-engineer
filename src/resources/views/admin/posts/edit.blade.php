@extends('layouts.admin')

@section('title', "ブログ記事編集")

@section('content')
<div class="row page-heading">
    <div class="col-lg-10">
        <h1 class="">ブログの編集</h1>
    </div>
</div>
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-title">
                <h4>編集</h5>
            </div>
            <div class="box-content">
                @include('admin.posts.partials.form', [
                'method' => 'PUT',
                'action' => route('admin.posts.update', $post)
                ])
            </div>
        </div>
    </div>
</div>
@endsection
