# AGENTS.md

Laravel 9 (PHP ^8.2) monolith — a "Master Items" CRUD for pharmacy inventory. UI text and some comments are Indonesian; `lang/` only has `en`. No monorepo, no CI, no pre-commit, no typecheck.

## Project shape

- Real entrypoint: `routes/web.php` → `app/Http/Controllers/MasterItemsController.php` (the only feature controller). Everything else under `app/` is stock Laravel/laravel/ui scaffolding.
- Views: `resources/views/master_items/{index,form,single}/`. `index/js.blade.php` holds the page's jQuery logic (loaded via `@section('js')` in `layouts/app.blade.php`).
- Model: `app/Models/MasterItem.php` (SoftDeletes, `$fillable` termasuk `foto`). Schema in `database/migrations/2022_11_05_005605_create_master_items_table.php`.
- Dead code, don't chase: `app/Models/Pasien.php` (no table), `config/datatables.php` (yajra package not installed).
- Front end is Bootstrap 5 + Sass bundled by Vite, but jQuery/DataTables are loaded from CDN inside `index/js.blade.php`, not bundled.

## Commands

- Dev server: `php artisan serve`; assets: `npm run dev` (Vite, creates `public/hot`) or `npm run build`.
- Tests: `php artisan test`. Single file: `vendor/bin/phpunit tests/Unit/ExampleTest.php`; single test: `php artisan test --filter=<name>`.
- Format: `vendor/bin/pint <path>`. There is no `pint.json` and repo-wide `pint --test` already fails on ~18 untouched files — only format files you changed, never run Pint across the whole repo.
- No lint/typecheck/CI to run. Suggested check: Pint on changed files → `php artisan test`.

## Verified baseline & gotchas

- `.env` exists (APP_KEY set); MySQL runs locally, DB `laravel`, root/no password. Migrations are already applied — do **not** assume a fresh DB.
- `phpunit.xml` does **not** switch to sqlite (that override is commented out): tests hit the real MySQL DB. Avoid destructive commands while testing.
- `tests/Feature/ExampleTest` fails at baseline (expects 200 on `/`, but `/` → `HomeController` which requires auth → 302). Unit test passes. PHP 8.4 also prints vendor deprecation warnings from `nunomaduro/collision` — noise, not failures.
- Pages return 500 "Vite manifest not found" unless `public/hot` exists (dev server running) or `npm run build` was run.
- Auth: only `/` and `/home` are behind `auth` middleware. All `/master-items*` routes — including create/edit/delete — are public. `Auth::routes()` (laravel/ui) includes open registration.
- `GET /master-items/update-random-data` overwrites every row's kode/harga/supplier/jenis with random values — destructive, don't hit it during verification.
- `php artisan recreate-db` DROPs/CREATEs the hardcoded `medify_rekrutmen` DB via the `hospital` connection (database `medify_hospital`, see `config/database.php`). It fails locally (that DB doesn't exist here) and exists for the server deploy script `rekrutmen.sh` (`/var/www/rekrutmen`, invoked with `php8.2`).
- Delete route is `POST /master-items/delete/{id}` (CSRF), not GET.

## Feature conventions

- `kode` (item code) is generated in `MasterItemsController::generateKode()`: max kode **including soft-deleted** + 1, zero-padded to 5 digits.
- Sell price (harga jual) is never stored — computed as `harga_beli + harga_beli * laba / 100` in three places: `index/js.blade.php` (JS, rounded), `single/index.blade.php`, and `app/Exports/MasterItemsExport.php` (Excel, di-cast `int`). Keep them in sync.
- Supplier/jenis option lists are hardcoded in **two** places: the `<select>`s in `master_items/form/form.blade.php` and `getRandomSupplier()`/`getRandomJenis()` in the controller. Update both when adding an option.
- Search endpoint `GET /master-items/search` returns `{status, data}` JSON, consumed by client-side DataTables. Row array order in `index/js.blade.php` must match the `<thead>` order in `index/table.blade.php` (there's a comment marking this too).

## Konvensi view & query (catatan review user)

Dua aturan yang diminta user: (1) halaman baru / perbaikan harus punya view yang **sesuai pola Master Items**, (2) query data harus **Eloquent + eager loading**.

### View mengikuti pola Master Items

- Struktur: `@extends('layouts.app')` → `container > row justify-content-center > col-md-8` → tombol aksi di atas `.card` → `card-header` + `card-body` yang meng-`@include` partial → `@section('js')` meng-`@include` `index/js.blade.php`.
- Halaman index: partial `filter.blade.php` wajib punya `#filter-container`, area pesan `#filter-error` (`class="text-danger mt-1" … role="alert"`), `#loading-filter`, tombol `.btn-get-data`; `js.blade.php` wajib punya `showFilterError()` / `hideFilterError()` (error 4xx ditampilkan **inline**, bukan `alert`), dan urutan array baris == urutan `<thead>` di `table.blade.php`.
- Halaman detail: tabel identitas `<table>` dengan pola `<th>`/`<td>:</td>`/`<td>…</td>`, lalu baris tombol — Edit `btn-info`, Delete berupa `<form method=POST>` `d-inline` (bukan link GET), dan link "Kembali ke Daftar …" `btn-secondary` di atas kartu.
- Filter input memakai `id` + `<label for>` + `autocomplete="off"` di kedua menu.

### Eloquent + eager loading

Tidak ada `DB::`/query builder mentah; semua lewat Eloquent. Relasi yang diakses dari view/loop **wajib** di-eager-load di controller:

- `MasterItemsController::search()` → `with(['kategori' => fn ($q) => $q->orderBy('nama')])` (butuh `id` di `select`).
- `MasterItemsController::singleView()` → `with('kategori')` — view mengakses `$data->kategori`.
- `KategoriItemsController::singleView()` & `cetakPdf()` → `kategoriDenganItem($kode)`, helper privat `with(['masterItems' => …])` (DRY untuk dua method ini).
- `KategoriItemsController::search()` → `withCount('masterItems')` (aggregate, bukan hitung per baris).
- `MasterItemsExport::collection()` → `with('kategori')`.
- Urutan daftar kategori selalu `orderBy('nama')` (sama seperti `<select>` di form).
- `delete()` kedua controller pakai `findOrFail($id)` — id asing → 404, bukan fatal `delete() on null` (500).

### Gotcha saat menguji eager loading

- `Model::preventLazyLoading()` di Laravel 9 **hanya** menandai model hasil `hydrate` **lebih dari 1 baris** (`Builder::hydrate`); query `first()` (halaman detail) tidak pernah kena. Jadi test ini tidak bisa dipakai untuk halaman detail.
- Serialisasi JSON **tidak** me-lazy-load relasi yang belum `with()` — kalau `with('kategori')` dilepas, kolom Kategori di index jadi **kosong tanpa error**. Guard-nya: `KategoriItemsCrudTest::test_search_master_item_membawa_data_kategori`.
- Test anti N+1 yang berguna: hitung query yang menyentuh `kategori_master_item` saat GET (eager = 1 query untuk semua baris, lazy = 1 per baris) — lihat `tests/Feature/EagerLoadingTest.php`.
- Test tampilan halaman detail: `tests/Feature/DetailViewTest.php` (3 tes).

## Foto (upload)

- Kolom `foto` (nullable) di `master_items`; menyimpan **path relatif** terhadap disk `public` (`master-items/<hashed>.jpg`), bukan URL. Ditampilkan via `asset('storage/'.$foto)` — symlink `public/storage` wajib ada (`php artisan storage:link`).
- Aturan validasi: `nullable|image|mimes:jpeg,jpg,png,webp|max:2048` — `max:2048` disamakan dengan `upload_max_filesize` (2M) di php.ini, karena file lebih besar ditolak PHP sebelum sampai ke Laravel.
- Form hanya bisa mengganti/menghapus foto lewat `formSubmit` (method `new`/`edit`); checkbox `hapus_foto=1` menghapus file + mengosongkan kolom. Saat edit dengan foto baru, file lama dihapus **setelah** file baru tersimpan.
- Test: `tests/Feature/MasterItemFotoTest.php` (pakai `Storage::fake('public')` + `DatabaseTransactions`, aman untuk DB dev).

## Kategori Items (many-to-many dengan Master Items)

- Route prefix `/kategori-items` — pola identik dengan `/master-items`: `GET /` (index), `GET /search` (JSON filter kode+nama untuk DataTables), `GET|POST /form/{method}/{id?}`, `GET /view/{kode}`, `POST /delete/{id}`. Controller `KategoriItemsController`, views `resources/views/kategori_items/{index,form,single}/`.
- Tabel: `kategori_items` (`kode` **unique**, `nama`, SoftDeletes) + pivot `kategori_master_item` (`kategori_item_id`, `master_item_id`, unique pasangan, `cascadeOnDelete`).
- **Gotcha pivot**: nama kolom foreign key diturunkan Laravel dari **nama class** (`KategoriItem` → `kategori_item_id`), bukan dari nama tabel (`kategori_items` → `kategori_id`). Salah tebak → `QueryException: Unknown column 'kategori_id'`.
- Relasi: `MasterItem::kategori()` ↔ `KategoriItem::masterItems()`, keduanya `->withTimestamps()`. Kategori yang di-soft-delete otomatis hilang dari relasi (global scope SoftDeletes berlaku di query relasi) dan muncul lagi bila di-restore — baris pivot sengaja tidak dihapus saat delete.
- Master items: `search()` wajib `select` menyertakan `id` (kunci join untuk `with('kategori')`, kalau tidak ada kolom kategori kosong). Form memakai `<select name="kategori_ids[]" multiple>` dan di-`formSubmit` di-`sync()`; validasi `kategori_ids.*` = `exists:kategori_items,id` + `whereNull('deleted_at')`.
- Kode kategori dibuat `KategoriItemsController::generateKode()` (max kode termasuk trashed + 1, zero-pad 5) — terpisah dari sekuens kode master items.
- Navbar (`layouts/app.blade.php`, `navbar-nav me-auto`) punya link **Master Items** dan **Kategori Items**.
- Test: `tests/Feature/KategoriItemsCrudTest.php` (13 tes, `DatabaseTransactions`).

## Cetak PDF Kategori (barryvdh/laravel-dompdf)

- `GET /kategori-items/pdf/{kode}` → `KategoriItemsController::cetakPdf()` → download `kategori-{kode}.pdf` (bukan stream). Package `barryvdh/laravel-dompdf` **v3** sudah ada di composer.json (dulu disebut dead code, sekarang dipakai) — fasad: `Barryvdh\DomPDF\Facade\Pdf` (ada juga alias `PDF` dari auto-discovery).
- View `resources/views/kategori_items/single/pdf.blade.php` = HTML murni (tidak extends `layouts.app`) dan harus **dompdf-safe**: layout pakai `<table>` (hindari flex/grid), font bawaan, **tanpa resource remote** — karena itu kolom foto sengaja tidak ikut dicetak.
- Footer dicetak di setiap halaman memakai `position: fixed; bottom: 0` (dompdf mengulang elemen fixed per halaman) + `.konten { padding-bottom: 44pt }` supaya isi tidak menimpa footer.
- Stempel waktu dari `KategoriItemsController::waktuCetak()`: `now()->setTimezone('Asia/Jakarta')->locale('id')->isoFormat('dddd, D MMMM YYYY HH:mm:ss').' WIB'` → "Rabu, 30 September 2026 10:20:41 WIB". **`config('app.timezone')` tetap UTC** (jangan diubah) supaya timestamp di database tidak bercampur; kesenjangan7 jam itu memang disengaja.
- Test: `tests/Feature/KategoriItemsPdfTest.php` (7 tes: header response, isi view, footer waktu) — verifikasi visual bisa dengan `pdftoppm -png <file.pdf> out` lalu lihat PNG-nya, atau `pdftotext -layout`.

## Export Excel Master Items (maatwebsite/excel)

- Package **baru** di sesi ini: `maatwebsite/excel ^3.1` (+ `phpoffice/phpspreadsheet 1.30.7`). Sebelumnya tidak ada package Excel sama sekali. Fasad: `Maatwebsite\Excel\Facades\Excel`. (`composer audit` melaporkan 5 advisory — semuanya `laravel/framework` yang sudah ada dari awal, bukan dari package ini.)
- `GET /master-items/excel` → `MasterItemsController::downloadExcel()` → `Excel::download(new MasterItemsExport, 'master-items-'.now()->setTimezone('Asia/Jakarta')->format('Y-m-d').'.xlsx')`. Tombol **"Download Excel"** ada di `master_items/index/index.blade.php` sebelah tombol "+ Master Items Baru".
- Kolom persis ketentuan user: `No`, `Nama Kategori` (bisa >1 kategori digabung `", "`; kalau kosong diisi `"-"` sama seperti tabel index), `Nama Items`, `Nama Supplier`, `Harga` (= `harga_beli`), `Laba` (persen, angka polos), `Harga Jual` (rumus di atas, `int`).
- **Isi = seluruh data master item** urut `kode` (soft delete dikecualikan otomatis) — **bukan** hasil filter halaman index; sengaja supaya tombol download tidak tergantung nilai filter yang mungkin sedang tidak valid (filter harga invalid = 422, yang pada link download akan jadi redirect tanpa file).
- **Gotcha maatwebsite/excel**: `WithColumnWidths::columnWidths()` kuncinya **huruf kolom** (`'A'`..`'G'`), sedangkan `WithStyles::styles()` kuncinya **nomor baris** (`1`). Salah pakai angka untuk columnWidths → `Undefined array key 1` di `PhpSpreadsheet\Cell\Coordinate::columnIndexFromString` (500).
- `map($item)` dipanggil per baris berurutan → counter `private int $no` aman karena instance export baru dibuat tiap request.
- Test: `tests/Feature/MasterItemsExcelTest.php` (7 tes). Membaca isi file lewat `$response->baseResponse->getFile()->getPathname()` + `IOFactory::load()` — **bukan** `getContent()`, karena `Excel::download()` menghasilkan `BinaryFileResponse` yang `getContent()`-nya `false`. Verifikasi visual: `soffice --headless --convert-to csv --outdir . file.xlsx`.
