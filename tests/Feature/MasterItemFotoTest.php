<?php

namespace Tests\Feature;

use App\Models\MasterItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MasterItemFotoTest extends TestCase
{
    use DatabaseTransactions;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'nama' => 'Item Uji Foto',
            'harga_beli' => 1000,
            'laba' => 10,
            'supplier' => 'Tokopaedi',
            'jenis' => 'Obat',
        ], $overrides);
    }

    public function test_foto_can_be_uploaded_when_creating()
    {
        Storage::fake('public');

        $response = $this->post('/master-items/form/new', $this->validPayload([
            'foto' => UploadedFile::fake()->image('obat.jpg', 120, 120),
        ]));

        $response->assertRedirect('master-items');
        $response->assertSessionHasNoErrors();

        $item = MasterItem::where('nama', 'Item Uji Foto')->first();
        $this->assertNotNull($item, 'Item harus tersimpan ke database');
        $this->assertNotEmpty($item->foto, 'Path foto harus tersimpan ke database');
        $this->assertStringStartsWith('master-items/', $item->foto);
        Storage::disk('public')->assertExists($item->foto);
    }

    public function test_creating_without_foto_is_allowed()
    {
        Storage::fake('public');

        $response = $this->post('/master-items/form/new', $this->validPayload());

        $response->assertRedirect('master-items');
        $item = MasterItem::where('nama', 'Item Uji Foto')->first();
        $this->assertNotNull($item);
        $this->assertNull($item->foto);
    }

    public function test_non_image_foto_is_rejected()
    {
        Storage::fake('public');

        $response = $this->post('/master-items/form/new', $this->validPayload([
            'foto' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
        ]));

        $response->assertSessionHasErrors('foto');
        $this->assertNull(MasterItem::where('nama', 'Item Uji Foto')->first());

        Storage::disk('public')->assertDirectoryEmpty('master-items');
    }

    public function test_oversized_foto_is_rejected()
    {
        Storage::fake('public');

        $response = $this->post('/master-items/form/new', $this->validPayload([
            'foto' => UploadedFile::fake()->image('besar.jpg', 100, 100)->size(3000),
        ]));

        $response->assertSessionHasErrors('foto');
        $this->assertNull(MasterItem::where('nama', 'Item Uji Foto')->first());
    }

    public function test_updating_foto_replaces_the_old_file()
    {
        Storage::fake('public');

        $item = MasterItem::create($this->validPayload([
            'kode' => '90001',
            'foto' => 'master-items/old.jpg',
        ]));
        Storage::disk('public')->put('master-items/old.jpg', 'isi-lama');

        $response = $this->post('/master-items/form/edit/'.$item->id, $this->validPayload([
            'foto' => UploadedFile::fake()->image('baru.jpg', 80, 80),
        ]));

        $response->assertRedirect('master-items');
        $response->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertNotEquals('master-items/old.jpg', $item->foto);
        Storage::disk('public')->assertExists($item->foto);
        Storage::disk('public')->assertMissing('master-items/old.jpg');
    }

    public function test_updating_without_new_foto_keeps_the_existing_one()
    {
        Storage::fake('public');

        $item = MasterItem::create($this->validPayload([
            'kode' => '90002',
            'foto' => 'master-items/lama.jpg',
        ]));
        Storage::disk('public')->put('master-items/lama.jpg', 'isi');

        $response = $this->post('/master-items/form/edit/'.$item->id, $this->validPayload([
            'nama' => 'Nama Baru',
        ]));

        $response->assertRedirect('master-items');
        $item->refresh();
        $this->assertEquals('master-items/lama.jpg', $item->foto);
        Storage::disk('public')->assertExists('master-items/lama.jpg');
    }

    public function test_hapus_foto_flag_clears_photo_and_deletes_file()
    {
        Storage::fake('public');

        $item = MasterItem::create($this->validPayload([
            'kode' => '90003',
            'foto' => 'master-items/hapus.jpg',
        ]));
        Storage::disk('public')->put('master-items/hapus.jpg', 'isi');

        $response = $this->post('/master-items/form/edit/'.$item->id, $this->validPayload([
            'hapus_foto' => 1,
        ]));

        $response->assertRedirect('master-items');
        $item->refresh();
        $this->assertNull($item->foto);
        Storage::disk('public')->assertMissing('master-items/hapus.jpg');
    }

    public function test_new_form_has_foto_input_and_multipart_encoding()
    {
        $this->get('/master-items/form/new')
            ->assertStatus(200)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="foto"', false);
    }

    public function test_edit_form_shows_current_foto_and_delete_option()
    {
        Storage::fake('public');

        $item = MasterItem::create($this->validPayload([
            'kode' => '90005',
            'foto' => 'master-items/preview.jpg',
        ]));
        Storage::disk('public')->put('master-items/preview.jpg', 'isi');

        $this->get('/master-items/form/edit/'.$item->id)
            ->assertStatus(200)
            ->assertSee('storage/master-items/preview.jpg', false)
            ->assertSee('name="hapus_foto"', false);
    }

    public function test_failed_foto_upload_keeps_the_other_input()
    {
        Storage::fake('public');

        $response = $this->post('/master-items/form/new', $this->validPayload([
            'nama' => 'Nama Yang Diketik',
            'foto' => UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf'),
        ]));

        $response->assertStatus(302);
        $response->assertSessionHasErrors('foto');
        $this->assertEquals('Nama Yang Diketik', data_get(session('_old_input'), 'nama'));
        $this->assertNull(MasterItem::where('nama', 'Nama Yang Diketik')->first());
    }

    public function test_form_renders_old_input_after_validation_error()
    {
        $this->withSession(['_old_input' => ['nama' => 'Nama Hasil Pantulan']])
            ->get('/master-items/form/new')
            ->assertStatus(200)
            ->assertSee('value="Nama Hasil Pantulan"', false);
    }

    public function test_search_returns_foto_and_detail_page_shows_it()
    {
        Storage::fake('public');

        $item = MasterItem::create($this->validPayload([
            'kode' => '90004',
            'foto' => 'master-items/tampil.jpg',
        ]));
        Storage::disk('public')->put('master-items/tampil.jpg', 'isi');

        $this->getJson('/master-items/search?nama=Item Uji Foto')
            ->assertStatus(200)
            ->assertJsonPath('data.0.foto', 'master-items/tampil.jpg');

        $this->get('/master-items/view/90004')
            ->assertStatus(200)
            ->assertSee('storage/master-items/tampil.jpg', false);
    }
}
