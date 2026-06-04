# GMIM API

Backend Laravel untuk client `gmim_manage`.

## Setup

1. Buat database MySQL:

```sql
CREATE DATABASE gmim_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Sesuaikan `.env` bila username/password MySQL berbeda.

3. Jalankan migration dan seeder:

```bash
php artisan migrate:fresh --seed
```

4. Jalankan API:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Base URL: `http://127.0.0.1:8000/api`

## Akun Demo

Bendahara:

```json
{
  "gerejaId": "gmim-bethesda",
  "username": "bendahara-bethesda",
  "password": "bendahara123"
}
```

Pelayan Khusus:

```json
{
  "gerejaId": "gmim-bethesda",
  "username": "pelayan-bethesda",
  "password": "pelayan123"
}
```

Bendahara Tumpaan 1:

```json
{
  "gerejaId": "gmim-eben-haezer-tumpaan-1",
  "username": "bendahara-tumpaan-1",
  "password": "bendahara123"
}
```

Gereja demo:

- `gmim-bethesda`
- `gmim-eben-haezer`

## Endpoint Utama

- `GET /health`
- `POST /login`
- `GET /gereja`
- `GET /gereja/{gerejaId}`
- `GET|POST /gereja/{gerejaId}/pengguna`
- `PUT|DELETE /gereja/{gerejaId}/pengguna/{id}`
- `GET /gereja/{gerejaId}/dashboard`
- `GET|POST /gereja/{gerejaId}/kategori-persembahan`
- `PUT|DELETE /gereja/{gerejaId}/kategori-persembahan/{id}`
- `GET|POST /gereja/{gerejaId}/nama-persembahan`
- `GET /gereja/{gerejaId}/kategori-persembahan/{id}/nama-persembahan`
- `PUT|DELETE /gereja/{gerejaId}/nama-persembahan/{id}`
- `GET|POST /gereja/{gerejaId}/pemasukan`
- `GET|PUT|DELETE /gereja/{gerejaId}/pemasukan/{id}`
- `GET|POST /gereja/{gerejaId}/kategori-pengeluaran`
- `PUT|DELETE /gereja/{gerejaId}/kategori-pengeluaran/{id}`
- `GET|POST /gereja/{gerejaId}/pengeluaran`
- `GET|PUT|DELETE /gereja/{gerejaId}/pengeluaran/{id}`
- `GET /gereja/{gerejaId}/laporan/mingguan?startDate=2025-07-07&endDate=2025-07-13`
- `GET /gereja/{gerejaId}/laporan/bulanan?month=7&year=2025`
