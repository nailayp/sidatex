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
     * Mengambil daftar komentar pada suatu batch data.
     */
    public function getByBatch($batchId)
    {
        $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Batch data tidak ditemukan.'], 404);
        }

        $comments = DB::table('comments')
            ->join('users', 'comments.user_id', '=', 'users.id')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.role_id')
            ->where('comments.batch_id', $batchId)
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
     * Dapat dilakukan oleh Super Admin dan Viewer Berkepentingan.
     */
    public function store(Request $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Silakan login terlebih dahulu.'], 401);
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

        $commentId = DB::table('comments')->insertGetId([
            'batch_id' => $request->batch_id,
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
     * Hanya Super Admin atau pemilik komentar yang berwenang.
     */
    public function destroy($commentId)
    {
        $currentUser = Auth::user();
        $comment = DB::table('comments')->where('comment_id', $commentId)->first();

        if (!$comment) {
            return response()->json(['success' => false, 'message' => 'Komentar tidak ditemukan.'], 404);
        }

        $isSuperAdmin = ($currentUser->role_id === 1);
        $isOwner = ($comment->user_id === $currentUser->id);

        if (!$isSuperAdmin && !$isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda tidak berwenang menghapus komentar ini.',
            ], 403);
        }

        DB::table('comments')->where('comment_id', $commentId)->delete();

        ActivityLogger::log('Hapus Komentar', 'Komentar', "Menghapus komentar #{$commentId}");

        return response()->json([
            'success' => true,
            'message' => 'Komentar berhasil dihapus.',
        ]);
    }
}
