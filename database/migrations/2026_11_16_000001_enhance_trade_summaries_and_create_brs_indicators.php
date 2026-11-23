<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enhances monthly_trade_summaries (Tier-1) and creates brs_indicator_summaries (Tier-2).
     */
    public function up(): void
    {
        // 1. Enhance monthly_trade_summaries (Tier-1 Granular Cube Mart)
        Schema::table('monthly_trade_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('monthly_trade_summaries', 'batch_id')) {
                $table->unsignedInteger('batch_id')->nullable()->after('summary_id');
                $table->foreign('batch_id')->references('batch_id')->on('data_batches')->onDelete('cascade');
            }

            if (!Schema::hasColumn('monthly_trade_summaries', 'status_visibilitas')) {
                $table->enum('status_visibilitas', ['PRIVATE', 'RILIS'])->default('PRIVATE')->after('batch_id');
            }

            if (!Schema::hasColumn('monthly_trade_summaries', 'hs2')) {
                $table->string('hs2', 2)->nullable()->default('UN')->after('bulan');
            }

            if (!Schema::hasColumn('monthly_trade_summaries', 'hs_code_raw')) {
                $table->string('hs_code_raw', 20)->nullable()->after('hs2');
            }

            if (!Schema::hasColumn('monthly_trade_summaries', 'mapping_status')) {
                $table->enum('mapping_status', ['mapped', 'unmapped'])->default('mapped')->after('hs_code_raw');
            }

            if (!Schema::hasColumn('monthly_trade_summaries', 'is_migas')) {
                $table->boolean('is_migas')->default(false)->after('mapping_status');
            }

            if (!Schema::hasColumn('monthly_trade_summaries', 'sektor')) {
                $table->string('sektor', 50)->nullable()->after('is_migas');
            }

            if (!Schema::hasColumn('monthly_trade_summaries', 'bec_category')) {
                $table->string('bec_category', 50)->nullable()->after('sektor');
            }

            if (!Schema::hasColumn('monthly_trade_summaries', 'provorig_key')) {
                $table->string('provorig_key', 10)->default('')->after('bec_category');
            }

            // Make hs_id nullable to accommodate unmapped transactions
            $table->unsignedInteger('hs_id')->nullable()->change();

            $table->index(
                ['status_visibilitas', 'tipe_sumber', 'jenis_transaksi', 'tahun', 'bulan'],
                'idx_mts_vis_period'
            );
        });

        // 2. Create brs_indicator_summaries (Tier-2 Publication-Ready KPI Mart)
        if (!Schema::hasTable('brs_indicator_summaries')) {
            Schema::create('brs_indicator_summaries', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('batch_id');
                $table->enum('status_visibilitas', ['PRIVATE', 'RILIS'])->default('PRIVATE');
                $table->enum('tipe_sumber', ['distribusi', 'neraca']);
                $table->enum('jenis_transaksi', ['Ekspor', 'Impor', 'Neraca_Perdagangan']);
                $table->unsignedSmallInteger('tahun');
                $table->unsignedTinyInteger('bulan');
                $table->enum('periode_tipe', ['BULANAN', 'TRIWULAN', 'KUMULATIF', 'TAHUNAN'])->default('BULANAN');
                $table->string('kategori_dimensi', 30)->comment('TOTAL, SEKTOR, HS2_TOP10, NEGARA_TOP10, BEC, PELABUHAN, NERACA_DAGANG');
                $table->string('kode_dimensi', 50);
                $table->string('nama_dimensi', 150);
                $table->decimal('nilai_usd', 20, 4)->default(0);
                $table->decimal('nilai_juta_usd', 15, 2)->default(0);
                $table->decimal('volume_ribu_ton', 15, 2)->default(0);
                $table->decimal('share_percentage', 6, 2)->nullable();
                $table->decimal('growth_mtom', 6, 2)->nullable();
                $table->decimal('growth_yoy', 6, 2)->nullable();
                $table->decimal('growth_ctc', 6, 2)->nullable();
                $table->timestamps();

                $table->foreign('batch_id')->references('batch_id')->on('data_batches')->onDelete('cascade');
                $table->unique(
                    ['tipe_sumber', 'jenis_transaksi', 'tahun', 'bulan', 'periode_tipe', 'kategori_dimensi', 'kode_dimensi'],
                    'uq_brs_indicator_scope'
                );
                $table->index(
                    ['status_visibilitas', 'tipe_sumber', 'jenis_transaksi', 'tahun', 'bulan', 'periode_tipe'],
                    'idx_brs_lookup'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pre-flight guard: cegah korupsi skema setengah jalan (implicit DDL commit MySQL)
        // jika masih ada baris unmapped (hs_id IS NULL) di monthly_trade_summaries.
        if (
            Schema::hasTable('monthly_trade_summaries')
            && DB::table('monthly_trade_summaries')->whereNull('hs_id')->exists()
        ) {
            throw new \RuntimeException(
                'Rollback dibatalkan: monthly_trade_summaries memuat baris unmapped (hs_id IS NULL). ' .
                'Amankan atau arsipkan baris unmapped secara eksplisit sebelum mengembalikan hs_id menjadi NOT NULL.'
            );
        }

        Schema::dropIfExists('brs_indicator_summaries');

        Schema::table('monthly_trade_summaries', function (Blueprint $table) {
            $table->dropIndex('idx_mts_vis_period');
            $table->dropForeign(['batch_id']);
            $table->dropColumn([
                'batch_id',
                'status_visibilitas',
                'hs2',
                'hs_code_raw',
                'mapping_status',
                'is_migas',
                'sektor',
                'bec_category',
                'provorig_key',
            ]);
            $table->unsignedInteger('hs_id')->nullable(false)->change();
        });
    }
};
