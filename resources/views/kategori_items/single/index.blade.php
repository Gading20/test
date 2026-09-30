@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="form-group mb-2">
                <a href="{{url('kategori-items')}}" class="btn btn-secondary">Kembali ke Daftar Kategori</a>
            </div>
            <div class="card">
                <div class="card-header">Kategori Item</div>

                <div class="card-body">
                    {{-- 1. Identitas kategori --}}
                    <table>
                        <tr>
                            <th>Kode</th>
                            <td>:</td>
                            <td>{{$data->kode}}</td>
                        </tr>
                        <tr>
                            <th>Nama</th>
                            <td>:</td>
                            <td>{{$data->nama}}</td>
                        </tr>
                        <tr>
                            <th>Jumlah Item</th>
                            <td>:</td>
                            <td>{{$items->count()}}</td>
                        </tr>
                    </table>

                    <div class="mt-3">
                        <a class="btn btn-success" href="{{url('kategori-items/pdf/'.$data->kode)}}">Download PDF</a>
                        <a class="btn btn-info" href="{{url('kategori-items/form/edit')}}/{{$data->id}}">Edit</a>
                        <form action="{{url('kategori-items/delete')}}/{{$data->id}}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus kategori ini?');">
                            @csrf
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </div>

                    {{-- 2. Daftar item yang memakai kategori ini --}}
                    <h5 class="mt-4">Daftar Item</h5>

                    @if($items->isEmpty())
                    <p class="text-muted mb-0">Belum ada item pada kategori ini.</p>
                    @else
                    <table class="table table-striped" style="width:100%">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama</th>
                                <th>Jenis</th>
                                <th>Harga Beli</th>
                                <th>Harga Jual</th>
                                <th>Supplier</th>
                                <th>View</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                            <tr>
                                <td>{{$item->kode}}</td>
                                <td>{{$item->nama}}</td>
                                <td>{{$item->jenis}}</td>
                                <td>{{$item->harga_beli}}</td>
                                {{-- Rumus sama dengan index/js.blade.php (dibulatkan). --}}
                                <td>{{round($item->harga_beli + $item->harga_beli * $item->laba / 100)}}</td>
                                <td>{{$item->supplier}}</td>
                                <td><a href="{{url('master-items/view/'.$item->kode)}}" class="btn btn-primary btn-sm">View</a></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('js')
@endsection
