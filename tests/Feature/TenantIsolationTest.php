<?php

namespace Tests\Feature;

use App\Models\ChurchUser;
use App\Models\Gereja;
use App\Models\KategoriPengeluaran;
use App\Models\KategoriPersembahan;
use App\Models\NamaPersembahan;
use App\Models\Pemasukan;
use App\Models\Pengeluaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Test isolasi tenant: user gereja A tidak boleh membaca/menulis data gereja B.
 * Semua endpoint finance di bawah /gereja/{gereja}/... harus mengembalikan 403
 * bila token milik gereja lain dipakai.
 *
 * WAJIB hijau sebelum go-live (Fase 0 DoD).
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Gereja $gerejaA;
    private Gereja $gerejaB;
    private ChurchUser $userA;
    private ChurchUser $userB;
    private string $tokenA;
    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gerejaA = Gereja::create(['id' => 'g-aaa', 'nama' => 'Gereja A', 'slug' => 'gereja-a']);
        $this->gerejaB = Gereja::create(['id' => 'g-bbb', 'nama' => 'Gereja B', 'slug' => 'gereja-b']);

        $this->userA = ChurchUser::create([
            'id'        => 'u-aaa',
            'gereja_id' => 'g-aaa',
            'nama'      => 'Bendahara A',
            'email'     => 'a@gereja-a.id',
            'username'  => 'bendahara_a',
            'password'  => Hash::make('password'),
            'role'      => 'bendahara',
            'status'    => 'active',
        ]);

        $this->userB = ChurchUser::create([
            'id'        => 'u-bbb',
            'gereja_id' => 'g-bbb',
            'nama'      => 'Bendahara B',
            'email'     => 'b@gereja-b.id',
            'username'  => 'bendahara_b',
            'password'  => Hash::make('password'),
            'role'      => 'bendahara',
            'status'    => 'active',
        ]);

        $this->tokenA = $this->userA->createToken('test')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test')->plainTextToken;
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    private function asA(): array
    {
        return ['Authorization' => "Bearer {$this->tokenA}"];
    }

    private function asB(): array
    {
        return ['Authorization' => "Bearer {$this->tokenB}"];
    }

    // -----------------------------------------------------------------------
    // 1. User A tidak boleh akses endpoint gereja B
    // -----------------------------------------------------------------------

    public function test_user_a_cannot_access_gereja_b_dashboard(): void
    {
        $response = $this->getJson('/api/gereja/g-bbb/dashboard', $this->asA());

        $response->assertStatus(403);
    }

    public function test_user_b_cannot_access_gereja_a_pemasukan(): void
    {
        $response = $this->getJson('/api/gereja/g-aaa/pemasukan', $this->asB());

        $response->assertStatus(403);
    }

    public function test_user_b_cannot_access_gereja_a_pengeluaran(): void
    {
        $response = $this->getJson('/api/gereja/g-aaa/pengeluaran', $this->asB());

        $response->assertStatus(403);
    }

    public function test_user_a_cannot_post_pemasukan_to_gereja_b(): void
    {
        $response = $this->postJson('/api/gereja/g-bbb/pemasukan', [
            'tanggal' => '2026-06-04',
            'jumlah'  => 100000,
        ], $this->asA());

        $response->assertStatus(403);
    }

    // -----------------------------------------------------------------------
    // 2. User A hanya bisa melihat data gerejanya sendiri (Global Scope)
    // -----------------------------------------------------------------------

    public function test_global_scope_filters_pemasukan_by_gereja(): void
    {
        // Buat kategori & pemasukan untuk masing-masing gereja (bypass scope)
        $katA = KategoriPersembahan::withoutGlobalScopes()->create([
            'id' => 'kat-a', 'gereja_id' => 'g-aaa', 'nama' => 'Persembahan A',
        ]);
        $namaA = NamaPersembahan::withoutGlobalScopes()->create([
            'id' => 'nmp-a', 'gereja_id' => 'g-aaa',
            'kategori_persembahan_id' => 'kat-a', 'nama' => 'Mingguan A',
        ]);

        $katB = KategoriPersembahan::withoutGlobalScopes()->create([
            'id' => 'kat-b', 'gereja_id' => 'g-bbb', 'nama' => 'Persembahan B',
        ]);
        $namaB = NamaPersembahan::withoutGlobalScopes()->create([
            'id' => 'nmp-b', 'gereja_id' => 'g-bbb',
            'kategori_persembahan_id' => 'kat-b', 'nama' => 'Mingguan B',
        ]);

        Pemasukan::withoutGlobalScopes()->create([
            'id' => 'pms-a', 'gereja_id' => 'g-aaa',
            'tanggal' => '2026-06-04',
            'kategori_persembahan_id' => 'kat-a',
            'nama_persembahan_id' => 'nmp-a',
            'jumlah' => 500000, 'status' => 'approved', 'sumber' => 'manual',
        ]);

        Pemasukan::withoutGlobalScopes()->create([
            'id' => 'pms-b', 'gereja_id' => 'g-bbb',
            'tanggal' => '2026-06-04',
            'kategori_persembahan_id' => 'kat-b',
            'nama_persembahan_id' => 'nmp-b',
            'jumlah' => 999999, 'status' => 'approved', 'sumber' => 'manual',
        ]);

        // User A minta pemasukan gereja A → hanya dapat data A
        $response = $this->getJson('/api/gereja/g-aaa/pemasukan', $this->asA());

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains('pms-a', $ids);
        $this->assertNotContains('pms-b', $ids, 'Data gereja B tidak boleh muncul di response gereja A');
    }

    public function test_global_scope_filters_pengeluaran_by_gereja(): void
    {
        $katA = KategoriPengeluaran::withoutGlobalScopes()->create([
            'id' => 'kpe-a', 'gereja_id' => 'g-aaa', 'nama' => 'Operasional A',
        ]);
        $katB = KategoriPengeluaran::withoutGlobalScopes()->create([
            'id' => 'kpe-b', 'gereja_id' => 'g-bbb', 'nama' => 'Operasional B',
        ]);

        Pengeluaran::withoutGlobalScopes()->create([
            'id' => 'pen-a', 'gereja_id' => 'g-aaa',
            'tanggal' => '2026-06-04',
            'kategori_pengeluaran_id' => 'kpe-a',
            'jumlah' => 100000, 'keterangan' => 'Operasional bulan ini',
        ]);

        Pengeluaran::withoutGlobalScopes()->create([
            'id' => 'pen-b', 'gereja_id' => 'g-bbb',
            'tanggal' => '2026-06-04',
            'kategori_pengeluaran_id' => 'kpe-b',
            'jumlah' => 777777, 'keterangan' => 'Operasional gereja B',
        ]);

        $response = $this->getJson('/api/gereja/g-aaa/pengeluaran', $this->asA());

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains('pen-a', $ids);
        $this->assertNotContains('pen-b', $ids, 'Data gereja B tidak boleh muncul di response gereja A');
    }

    // -----------------------------------------------------------------------
    // 3. Login by email — resolusi gereja otomatis
    // -----------------------------------------------------------------------

    public function test_login_by_email_resolves_correct_gereja(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'a@gereja-a.id',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.gerejaId', 'g-aaa');
    }

    public function test_login_wrong_password_returns_401(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'a@gereja-a.id',
            'password' => 'salah',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_unknown_email_returns_401(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'unknown@gereja.id',
            'password' => 'password',
        ]);

        $response->assertStatus(401);
    }

    // -----------------------------------------------------------------------
    // 4. Unauthenticated request ditolak
    // -----------------------------------------------------------------------

    public function test_unauthenticated_cannot_access_finance_endpoints(): void
    {
        $this->getJson('/api/gereja/g-aaa/pemasukan')
            ->assertStatus(401);

        $this->getJson('/api/gereja/g-aaa/pengeluaran')
            ->assertStatus(401);

        $this->getJson('/api/gereja/g-aaa/dashboard')
            ->assertStatus(401);
    }
}
