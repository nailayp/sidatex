<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Enhance master_hs with official BPS classification columns
        Schema::table('master_hs', function (Blueprint $table) {
            if (!Schema::hasColumn('master_hs', 'oilgrp_code')) {
                $table->tinyInteger('oilgrp_code')->nullable()->after('bec_desc')->comment('0=Non Migas, 1=Migas');
            }
            if (!Schema::hasColumn('master_hs', 'oilgrp_desc')) {
                $table->string('oilgrp_desc', 20)->nullable()->after('oilgrp_code')->comment('NON MIGAS / MIGAS');
            }
            if (!Schema::hasColumn('master_hs', 'sektor_code')) {
                $table->tinyInteger('sektor_code')->nullable()->after('oilgrp_desc');
            }
            if (!Schema::hasColumn('master_hs', 'sitc_code')) {
                $table->string('sitc_code', 10)->nullable()->after('desk_kbli20');
            }
        });

        // 2. Enhance master_pelabuhan with 5-digit BPS code & UN/LOCODE
        Schema::table('master_pelabuhan', function (Blueprint $table) {
            if (!Schema::hasColumn('master_pelabuhan', 'un_locode')) {
                $table->string('un_locode', 10)->nullable()->after('kode_pelabuhan');
            }
            if (!Schema::hasColumn('master_pelabuhan', 'kode_kab')) {
                $table->string('kode_kab', 10)->nullable()->after('un_locode');
            }
        });

        // 3. Create master_wilayah_bps (to cover national origin PROVORIG like 1371, 1207, 3319, 3514)
        if (!Schema::hasTable('master_wilayah_bps')) {
            Schema::create('master_wilayah_bps', function (Blueprint $table) {
                $table->string('kode_wilayah', 10)->primary(); // e.g. 14, 1473, 1371
                $table->string('nama_wilayah', 100);
                $table->tinyInteger('level')->comment('1=Provinsi, 2=Kab/Kota');
                $table->string('kode_provinsi', 10)->nullable();
                $table->boolean('is_riau')->default(false);
                $table->timestamps();
                $table->index(['kode_provinsi', 'is_riau']);
            });
        }

        // 4. Create monthly_origin_trade_summaries (specifically for PROVORIG / District of Origin Distribution)
        if (!Schema::hasTable('monthly_origin_trade_summaries')) {
            Schema::create('monthly_origin_trade_summaries', function (Blueprint $table) {
                $table->id('summary_id');
                $table->year('tahun');
                $table->tinyInteger('bulan')->unsigned();
                $table->enum('jenis_transaksi', ['ekspor', 'impor'])->default('ekspor');
                $table->string('provorig', 10)->index()->comment('Kode BPS asal komoditas (4 digit)');
                $table->boolean('is_riau')->default(true);
                $table->string('hs2', 2)->nullable()->index();
                $table->string('sektor', 50)->nullable()->index();
                $table->boolean('is_migas')->default(false)->index();
                $table->decimal('total_berat', 20, 2)->default(0);
                $table->decimal('total_nilai', 20, 2)->default(0);
                $table->unsignedInteger('jumlah_transaksi')->default(0);
                $table->timestamps();

                $table->unique(
                    ['tahun', 'bulan', 'jenis_transaksi', 'provorig', 'hs2', 'is_migas'],
                    'unique_origin_summary'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_origin_trade_summaries');
        Schema::dropIfExists('master_wilayah_bps');

        Schema::table('master_pelabuhan', function (Blueprint $table) {
            $table->dropColumn(['un_locode', 'kode_kab']);
        });

        Schema::table('master_hs', function (Blueprint $table) {
            $table->dropColumn(['oilgrp_code', 'oilgrp_desc', 'sektor_code', 'sitc_code']);
        });
    }
};
