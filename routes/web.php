<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return redirect('/mahasiswa');
});

// READ, PENCARIAN, DAN FILTER
Route::get('/mahasiswa', function (Request $request) {
    try {
        $pdo = DB::connection()->getPdo();

        $kataKunci = trim((string) $request->query('q', ''));
        $programStudiId = $request->query('program_studi_id', '');

        $sql = "SELECT
                    m.id,
                    m.nim,
                    m.nama,
                    m.email,
                    m.usia,
                    m.program_studi_id,
                    p.nama_prodi
                FROM mahasiswa AS m
                JOIN program_studi AS p
                    ON p.id = m.program_studi_id
                WHERE 1=1";

        $parameter = [];

        if ($kataKunci !== '') {
            $sql .= " AND (
                m.nim LIKE :nim
                OR m.nama LIKE :nama
                OR m.email LIKE :email
            )";

            $parameter['nim'] = "%{$kataKunci}%";
            $parameter['nama'] = "%{$kataKunci}%";
            $parameter['email'] = "%{$kataKunci}%";
        }

        if ($programStudiId !== '') {
            $sql .= " AND m.program_studi_id = :program_studi_id";
            $parameter['program_studi_id'] = (int) $programStudiId;
        }

        $sql .= " ORDER BY m.nim";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($parameter);

        $mahasiswa = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtProdi = $pdo->prepare(
            "SELECT id, nama_prodi FROM program_studi ORDER BY nama_prodi"
        );
        $stmtProdi->execute();

        $daftarProgramStudi = $stmtProdi->fetchAll(PDO::FETCH_ASSOC);

        return view('mahasiswa', [
            'mahasiswa' => $mahasiswa,
            'daftarProgramStudi' => $daftarProgramStudi,
            'kataKunci' => $kataKunci,
            'programStudiId' => $programStudiId,
        ]);

    } catch (\Throwable $e) {
        report($e);

        return response(
            'Koneksi atau query database gagal. Periksa konfigurasi database.',
            500
        );
    }
})->name('mahasiswa.index');


// CREATE
Route::post('/mahasiswa', function (Request $request) {
    $validator = Validator::make($request->all(), [
        'nim' => ['required', 'digits:10'],
        'nama' => ['required', 'string', 'min:3', 'max:100'],
        'email' => ['required', 'email', 'max:100'],
        'usia' => ['required', 'integer', 'min:17', 'max:60'],
        'program_studi_id' => [
            'required',
            'integer',
            'exists:program_studi,id'
        ],
    ], [
        'nim.required' => 'NIM wajib diisi.',
        'nim.digits' => 'NIM harus terdiri dari 10 angka.',
        'nama.required' => 'Nama wajib diisi.',
        'nama.min' => 'Nama minimal 3 karakter.',
        'nama.max' => 'Nama maksimal 100 karakter.',
        'email.required' => 'Email wajib diisi.',
        'email.email' => 'Format email tidak valid.',
        'usia.required' => 'Usia wajib diisi.',
        'usia.integer' => 'Usia harus berupa angka.',
        'usia.min' => 'Usia minimal 17 tahun.',
        'usia.max' => 'Usia maksimal 60 tahun.',
        'program_studi_id.required' => 'Program studi wajib dipilih.',
        'program_studi_id.exists' => 'Program studi tidak ditemukan.',
    ]);

    if ($validator->fails()) {
        return redirect('/mahasiswa')
            ->withErrors($validator)
            ->withInput();
    }

    $data = $validator->validated();

    try {
        $pdo = DB::connection()->getPdo();

        $cek = $pdo->prepare(
            'SELECT COUNT(*) FROM mahasiswa
             WHERE nim = :nim OR email = :email'
        );

        $cek->execute([
            'nim' => $data['nim'],
            'email' => $data['email'],
        ]);

        if ($cek->fetchColumn() > 0) {
            return redirect('/mahasiswa')
                ->withInput()
                ->with('error', 'NIM atau email sudah digunakan.');
        }

        $sql = "INSERT INTO mahasiswa
                    (nim, nama, email, usia, program_studi_id)
                VALUES
                    (:nim, :nama, :email, :usia, :program_studi_id)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        return redirect('/mahasiswa')
            ->with('success', 'Data mahasiswa berhasil ditambahkan.');

    } catch (\Throwable $e) {
        report($e);

        return redirect('/mahasiswa')
            ->withInput()
            ->with('error', 'Data gagal disimpan. Periksa koneksi database.');
    }
})->name('mahasiswa.store');


// FORM EDIT
Route::get('/mahasiswa/{id}/edit', function ($id) {
    try {
        $pdo = DB::connection()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT * FROM mahasiswa WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);

        $mahasiswa = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mahasiswa) {
            return redirect('/mahasiswa')
                ->with('error', 'Data mahasiswa tidak ditemukan.');
        }

        $stmtProdi = $pdo->prepare(
            "SELECT id, nama_prodi FROM program_studi ORDER BY nama_prodi"
        );
        $stmtProdi->execute();

        $daftarProgramStudi = $stmtProdi->fetchAll(PDO::FETCH_ASSOC);

        return view('mahasiswa-edit', [
            'mahasiswa' => $mahasiswa,
            'daftarProgramStudi' => $daftarProgramStudi,
        ]);

    } catch (\Throwable $e) {
        report($e);

        return redirect('/mahasiswa')
            ->with('error', 'Data gagal ditampilkan.');
    }
})->name('mahasiswa.edit');


// UPDATE
Route::put('/mahasiswa/{id}', function (Request $request, $id) {
    $validator = Validator::make($request->all(), [
        'nim' => ['required', 'digits:10'],
        'nama' => ['required', 'string', 'min:3', 'max:100'],
        'email' => ['required', 'email', 'max:100'],
        'usia' => ['required', 'integer', 'min:17', 'max:60'],
        'program_studi_id' => [
            'required',
            'integer',
            'exists:program_studi,id'
        ],
    ]);

    if ($validator->fails()) {
        return redirect("/mahasiswa/{$id}/edit")
            ->withErrors($validator)
            ->withInput();
    }

    $data = $validator->validated();

    try {
        $pdo = DB::connection()->getPdo();

        $cek = $pdo->prepare(
            'SELECT COUNT(*) FROM mahasiswa
             WHERE (nim = :nim OR email = :email)
             AND id <> :id'
        );

        $cek->execute([
            'nim' => $data['nim'],
            'email' => $data['email'],
            'id' => $id,
        ]);

        if ($cek->fetchColumn() > 0) {
            return redirect("/mahasiswa/{$id}/edit")
                ->withInput()
                ->with('error', 'NIM atau email sudah digunakan mahasiswa lain.');
        }

        $sql = "UPDATE mahasiswa SET
                    nim = :nim,
                    nama = :nama,
                    email = :email,
                    usia = :usia,
                    program_studi_id = :program_studi_id
                WHERE id = :id";

        $data['id'] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        return redirect('/mahasiswa')
            ->with('success', 'Data mahasiswa berhasil diperbarui.');

    } catch (\Throwable $e) {
        report($e);

        return redirect("/mahasiswa/{$id}/edit")
            ->withInput()
            ->with('error', 'Data gagal diperbarui.');
    }
})->name('mahasiswa.update');


// DELETE
Route::delete('/mahasiswa/{id}', function ($id) {
    try {
        $pdo = DB::connection()->getPdo();

        $stmt = $pdo->prepare(
            "DELETE FROM mahasiswa WHERE id = :id"
        );

        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() === 0) {
            return redirect('/mahasiswa')
                ->with('error', 'Data mahasiswa tidak ditemukan.');
        }

        return redirect('/mahasiswa')
            ->with('success', 'Data mahasiswa berhasil dihapus.');

    } catch (\Throwable $e) {
        report($e);

        return redirect('/mahasiswa')
            ->with('error', 'Data gagal dihapus.');
    }
})->name('mahasiswa.destroy');
