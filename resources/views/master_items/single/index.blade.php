@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="form-group mb-2">
                <a href="{{url('master-items')}}" class="btn btn-secondary">Kembali ke Daftar Item</a>
            </div>
            <div class="card">
                <div class="card-header">Master Item</div>

                <div class="card-body">
                    <table>
                        <tr>
                            <th>Nama</th>
                            <td>:</td>
                            <td>{{$data->nama}}</td>
                        </tr>
                        <tr>
                            <th>Harga Beli</th>
                            <td>:</td>
                            <td>{{$data->harga_beli}}</td>
                        </tr>
                        <tr>
                            <th>Laba</th>
                            <td>:</td>
                            <td>{{$data->laba}}</td>
                        </tr>
                        <tr>
                            <th>Harga Jual</th>
                            <td>:</td>
                            <td>{{$data->harga_beli + $data->harga_beli * $data->laba / 100 }}</td>
                        </tr>
                        <tr>
                            <th>Supplier</th>
                            <td>:</td>
                            <td>{{$data->supplier}}</td>
                        </tr>
                        <tr>
                            <th>Jenis</th>
                            <td>:</td>
                            <td>{{$data->jenis}}</td>
                        </tr>
                        <tr>
                            <th>Kategori</th>
                            <td>:</td>
                            <td>
                                @if($data->kategori->isEmpty())
                                -Tidak ada kategori-
                                @else
                                @foreach($data->kategori as $kat)
                                <a href="{{url('kategori-items/view/'.$kat->kode)}}">{{ $kat->kode }} - {{ $kat->nama }}</a>@if(!$loop->last), @endif
                                @endforeach
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Foto</th>
                            <td>:</td>
                            <td>
                                @if(!empty($data->foto))
                                <img src="{{ asset('storage/'.$data->foto) }}" alt="{{ $data->nama }}" class="img-thumbnail" style="max-height:200px;">
                                @else
                                -Tidak ada foto-
                                @endif
                            </td>
                        </tr>
                    </table>
                    <a class="btn btn-info" href="{{url('master-items/form/edit')}}/{{$data->id}}">Edit</a>
                    <form action="{{url('master-items/delete')}}/{{$data->id}}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this item?');">
                        @csrf
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('js')
@endsection