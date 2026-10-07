<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateHallLocationTablesAndNormalizeProducts extends Migration
{
    public function up()
    {
        $legacyProducts = DB::table('products')
            ->select('id', 'hall', 'rack', 'shell')
            ->get();

        foreach ($legacyProducts as $product) {
            $hall = $product->hall;
            $rack = $product->rack;
            $shell = $product->shell;

            if (($rack !== null && $rack !== '') && ($hall === null || $hall === '')) {
                throw new RuntimeException(
                    'Cannot migrate Product ' . $product->id . ': its Rack has no Hall.'
                );
            }

            if (($shell !== null && $shell !== '') && ($rack === null || $rack === '')) {
                throw new RuntimeException(
                    'Cannot migrate Product ' . $product->id . ': its Shell has no Rack.'
                );
            }

            foreach (['Hall' => $hall, 'Rack' => $rack, 'Shell' => $shell] as $label => $value) {
                if ($value !== null && $value !== ''
                    && (strlen($value) > 50 || !preg_match('/\A[a-z0-9]+\z/i', $value))) {
                    throw new RuntimeException(
                        'Cannot migrate Product ' . $product->id . ': its ' . $label
                        . ' value cannot be represented as an alphanumeric location name.'
                    );
                }
            }
        }

        Schema::create('halls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 50)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('racks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('hall_id');
            $table->string('name', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hall_id', 'name']);
            $table->foreign('hall_id')->references('id')->on('halls')->onDelete('restrict');
        });

        Schema::create('shelves', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rack_id');
            $table->string('name', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['rack_id', 'name']);
            $table->foreign('rack_id')->references('id')->on('racks')->onDelete('restrict');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('hall_id')->nullable();
            $table->unsignedBigInteger('rack_id')->nullable();
            $table->unsignedBigInteger('shelf_id')->nullable();
            $table->foreign('hall_id')->references('id')->on('halls')->onDelete('restrict');
            $table->foreign('rack_id')->references('id')->on('racks')->onDelete('restrict');
            $table->foreign('shelf_id')->references('id')->on('shelves')->onDelete('restrict');
        });

        foreach ($legacyProducts as $product) {
            if ($product->hall === null || $product->hall === '') {
                continue;
            }

            $hall = DB::table('halls')->where('name', $product->hall)->first();
            if (!$hall) {
                $hallId = DB::table('halls')->insertGetId([
                    'name' => $product->hall,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $hallId = $hall->id;
            }

            $rackId = null;
            if ($product->rack !== null && $product->rack !== '') {
                $rack = DB::table('racks')
                    ->where('hall_id', $hallId)
                    ->where('name', $product->rack)
                    ->first();

                if (!$rack) {
                    $rackId = DB::table('racks')->insertGetId([
                        'hall_id' => $hallId,
                        'name' => $product->rack,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $rackId = $rack->id;
                }
            }

            $shelfId = null;
            if ($product->shell !== null && $product->shell !== '') {
                $shelf = DB::table('shelves')
                    ->where('rack_id', $rackId)
                    ->where('name', $product->shell)
                    ->first();

                if (!$shelf) {
                    $shelfId = DB::table('shelves')->insertGetId([
                        'rack_id' => $rackId,
                        'name' => $product->shell,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $shelfId = $shelf->id;
                }
            }

            DB::table('products')->where('id', $product->id)->update([
                'hall_id' => $hallId,
                'rack_id' => $rackId,
                'shelf_id' => $shelfId,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['hall', 'rack', 'shell']);
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('hall', 50)->nullable();
            $table->string('rack', 50)->nullable();
            $table->string('shell', 50)->nullable();
        });

        $locations = DB::table('products')
            ->leftJoin('halls', 'products.hall_id', '=', 'halls.id')
            ->leftJoin('racks', 'products.rack_id', '=', 'racks.id')
            ->leftJoin('shelves', 'products.shelf_id', '=', 'shelves.id')
            ->select('products.id', 'halls.name as hall_name', 'racks.name as rack_name', 'shelves.name as shell_name')
            ->get();

        foreach ($locations as $location) {
            DB::table('products')->where('id', $location->id)->update([
                'hall' => $location->hall_name,
                'rack' => $location->rack_name,
                'shell' => $location->shell_name,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['shelf_id']);
            $table->dropForeign(['rack_id']);
            $table->dropForeign(['hall_id']);
            $table->dropColumn(['shelf_id', 'rack_id', 'hall_id']);
        });

        Schema::dropIfExists('shelves');
        Schema::dropIfExists('racks');
        Schema::dropIfExists('halls');
    }
}
