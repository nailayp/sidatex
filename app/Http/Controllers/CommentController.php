<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    /**
     * Memeriksa otorisasi pengguna terhadap batch berdasarkan role, status batch, dan status Trash.
     *
     * @return array{0: ?object, 1: ?\Illuminate\Http\JsonResponse}
     */
    private function authorizeBatchAccess(int $batchId, object $user, bool $requireWrite = false): array
    {
        $roleId = (int) $user->role_id;
        $isSuperAdmin = ($roleId === 1);
        $isViewerEksekutif = ($roleId === 3);

        if ($requireWrite && !$isSuperAdmin && !$isViewerEksekutif) {
            return [null, response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Super Admin dan Viewer Eksekutif yang dapat menambahkan komentar.',
            ], 403)];
        }

        $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
        if (!$batch) {
            return [null, response()->json([
                'success' => false,
                'message' => 'Batch data tidak ditemukan.',
            ], 404)];
        }

        $isTrashed = DB::table('trash')
            ->where('batch_id', $batchId)
            ->whereNull('restored_at')
            ->exists();

        if ($isTrashed && ($requireWrite || !$isSuperAdmin)) {
            return [null, response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Batch data berada di Trash.',
            ], 403)];
        }

        $status = strtoupper(trim((string) ($batch->status ?? '')));

        if ($isSuperAdmin) {
            return [$batch, null];
        }

        if ($isViewerEksekutif) {
            if (!in_array($status, ['RILIS', 'PRIVATE'], true)) {
                return [null, response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Viewer Eksekutif tidak dapat mengakses batch berstatus DRAFT.',
                ], 403)];
            }

            return [$batch, null];
        }

        // Pegawai BPS (Role 2): hanya baca pada batch berstatus RILIS
        if ($status !== 'RILIS') {
            return [null, response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda hanya berwenang melihat komentar pada batch berstatus RILIS.',
            ], 403)];
        }

        return [$batch, null];
    }

    /**
     * Mengambil daftar komentar pada suatu batch data.
     */
    public function getByBatch($batchId)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Silakan login terlebih dahulu.'], 401);
        }

        [, $errorResponse] = $this->authorizeBatchAccess((int) $batchId, $currentUser, false);
        if ($errorResponse) {
            return $errorResponse;
        }

        $comments = DB::table('comments')
            ->join('users', 'comments.user_id', '=', 'users.id')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.role_id')
            ->where('comments.batch_id', (int) $batchId)
            ->select(
                'comments.comment_id',
                'comments.batch_id',
                'comments.komentar',
                'comments.created_at',
                'users.id as user_id',
                'users.name as user_name',
                'roles.nama_role'
            )
            ->orderBy('comments.created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $comments,
        ]);
    }

    /**
     * Menambahkan komentar / catatan verifikasi baru pada batch data.
     * Hanya dapat dilakukan oleh Super Admin (Role 1) dan Viewer Eksekutif (Role 3).
     */
    public function store(Request $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Silakan login terlebih dahulu.'], 401);
        }

        // Periksa kewenangan role terlebih dahulu sebelum validasi exists:data_batches (mencegah enumerasi ID)
        if (!in_array((int) $currentUser->role_id, [1, 3], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Super Admin dan Viewer Eksekutif yang dapat menambahkan komentar.',
            ], 403);
        }

        $request->validate([
            'batch_id' => 'required|integer|exists:data_batches,batch_id',
            'komentar' => 'required|string|max:2000',
        ], [
            'batch_id.required' => 'Batch ID wajib disertakan.',
            'batch_id.exists' => 'Batch data tidak ditemukan di sistem.',
            'komentar.required' => 'Isi komentar tidak boleh kosong.',
            'komentar.max' => 'Komentar maksimal 2.000 karakter.',
        ]);

        [, $errorResponse] = $this->authorizeBatchAccess((int) $request->batch_id, $currentUser, true);
        if ($errorResponse) {
            return $errorResponse;
        }

        $commentId = DB::table('comments')->insertGetId([
            'batch_id' => (int) $request->batch_id,
            'user_id' => $currentUser->id,
            'komentar' => trim($request->komentar),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ActivityLogger::log(
            'Tambah Komentar',
            'Komentar',
            "Pengguna {$currentUser->name} menambahkan catatan pada batch #{$request->batch_id}"
        );

        $createdComment = DB::table('comments')
            ->join('users', 'comments.user_id', '=', 'users.id')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.role_id')
            ->where('comments.comment_id', $commentId)
            ->select(
                'comments.comment_id',
                'comments.batch_id',
                'comments.komentar',
                'comments.created_at',
                'users.id as user_id',
                'users.name as user_name',
                'roles.nama_role'
            )
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Catatan verifikasi berhasil disimpan.',
            'data' => $createdComment,
        ], 201);
    }

    /**
     * Menghapus komentar.
     * Hanya Super Admin atau pemilik komentar (pada batch non-Trash yang masih boleh diakses) yang berwenang.
     */
    public function destroy($commentId)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Silakan login terlebih dahulu.'], 401);
        }

        $comment = DB::table('comments')->where('comment_id', (int) $commentId)->first();

        if (!$comment) {
            return response()->json(['success' => false, 'message' => 'Komentar tidak ditemukan.'], 404);
        }

        $isSuperAdmin = ((int) $currentUser->role_id === 1);
        $isOwner = ((int) $comment->user_id === (int) $currentUser->id);

        if (!$isSuperAdmin && !$isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda tidak berwenang menghapus komentar ini.',
            ], 403);
        }

        if (!$isSuperAdmin) {
            [, $errorResponse] = $this->authorizeBatchAccess((int) $comment->batch_id, $currentUser, true);
            if ($errorResponse) {
                return $errorResponse;
            }
        }

        DB::table('comments')->where('comment_id', (int) $commentId)->delete();

        ActivityLogger::log('Hapus Komentar', 'Komentar', "Menghapus komentar #{$commentId}");

        return response()->json([
            'success' => true,
            'message' => 'Komentar berhasil dihapus.',
        ]);
    }
}
