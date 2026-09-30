<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Kategori {{ $data->kode }} - {{ $data->nama }}</title>
    <style>
        /* dompdf: pakai table untuk layout, hindari flex/grid, font bawaan. */
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11pt;
            color: #222;
        }

        h1 {
            font-size: 15pt;
            margin: 0 0 2pt;
        }

        .subtitle {
            font-size: 10pt;
            color: #555;
            margin: 0 0 14pt;
        }

        table.meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16pt;
        }

        table.meta td {
            padding: 2pt 0;
            vertical-align: top;
        }

        table.meta td.label {
            width: 110pt;
            color: #555;
        }

        h2 {
            font-size: 12pt;
            margin: 0 0 6pt;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
        }

        table.data th,
        table.data td {
            border: 0.5pt solid #999;
            padding: 4pt 5pt;
            text-align: left;
            vertical-align: top;
        }

        table.data th {
            background-color: #eeeeee;
        }

        table.data td.no {
            width: 22pt;
            text-align: center;
        }

        .kosong {
            font-style: italic;
            color: #777;
        }

        /* Footer ditampilkan berulang di setiap halaman (dukungan position: fixed dompdf). */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 0.5pt solid #999;
            padding-top: 4pt;
            font-size: 9pt;
            color: #555;
        }

        /* Ruang cadangan supaya isi tidak menimpa footer di halaman terakhir. */
        .konten {
            padding-bottom: 44pt;
        }
    </style>
</head>

<body>
    <div class="konten">
        {{-- 1 & 2. Nama serta kode kategori --}}
        <h1>Data Kategori Items</h1>
        <p class="subtitle">Dicetak dari halaman detail kategori</p>

        <table class="meta">
            <tr>
                <td class="label">Kode Kategori</td>
                <td>: <strong>{{ $data->kode }}</strong></td>
            </tr>
            <tr>
                <td class="label">Nama Kategori</td>
                <td>: <strong>{{ $data->nama }}</strong></td>
            </tr>
            <tr>
                <td class="label">Jumlah Item</td>
                <td>: {{ $items->count() }}</td>
            </tr>
        </table>

        {{-- 3. Informasi item yang memiliki kategori tersebut, dalam bentuk tabel --}}
        <h2>Daftar Item pada Kategori Ini</h2>

        @if ($items->isEmpty())
        <p class="kosong">Belum ada item pada kategori ini.</p>
        @else
        <table class="data">
            <thead>
                <tr>
                    <th class="no">No</th>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Jenis</th>
                    <th>Harga Beli</th>
                    <th>Harga Jual</th>
                    <th>Supplier</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $index => $item)
                <tr>
                    <td class="no">{{ $index + 1 }}</td>
                    <td>{{ $item->kode }}</td>
                    <td>{{ $item->nama }}</td>
                    <td>{{ $item->jenis }}</td>
                    <td>{{ $item->harga_beli }}</td>
                    {{-- Rumus sama dengan index/js.blade.php dan halaman view (dibulatkan). --}}
                    <td>{{ round($item->harga_beli + $item->harga_beli * $item->laba / 100) }}</td>
                    <td>{{ $item->supplier }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- 4. Tanggal & waktu cetak di footer (tampil di setiap halaman) --}}
    <div class="footer">
        Dicetak pada: {{ $waktu_cetak }} &nbsp;|&nbsp; Kategori {{ $data->kode }} &mdash; {{ $data->nama }}
    </div>
</body>

</html>
