@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="form-group mb-2">
                <a href="{{url('kategori-items/form/new')}}" class="btn btn-secondary">+ Kategori Baru</a>
            </div>
            <div class="card">
                <div class="card-header">Daftar Kategori Items</div>

                <div class="card-body">
                    @include('kategori_items.index.filter')
                    @include('kategori_items.index.table')
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('js')
@include('kategori_items.index.js')
@endsection
