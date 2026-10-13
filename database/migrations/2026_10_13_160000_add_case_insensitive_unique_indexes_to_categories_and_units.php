<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCaseInsensitiveUniqueIndexesToCategoriesAndUnits extends Migration
{
    public function up()
    {
        $this->assertNoDuplicateNames('categories');
        $this->addNormalizedNameColumn('categories');

        if (!$this->hasIndex('categories', 'categories_name_normalized_unique')) {
            DB::statement('CREATE UNIQUE INDEX categories_name_normalized_unique ON categories (name_normalized)');
        }

        $this->createUnitDuplicateTriggers();
    }

    public function down()
    {
        $this->dropUnitDuplicateTriggers();

        if ($this->hasIndex('categories', 'categories_name_normalized_unique')) {
            $driver = Schema::getConnection()->getDriverName();
            DB::statement($driver === 'mysql'
                ? 'DROP INDEX categories_name_normalized_unique ON categories'
                : 'DROP INDEX categories_name_normalized_unique');
        }
        if (Schema::hasColumn('categories', 'name_normalized')) {
            Schema::table('categories', function ($blueprint) {
                $blueprint->dropColumn('name_normalized');
            });
        }
    }

    private function assertNoDuplicateNames($table)
    {
        $duplicate = DB::table($table)
            ->selectRaw('LOWER(TRIM(name)) as normalized_name, COUNT(*) as name_count')
            ->groupByRaw('LOWER(TRIM(name))')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->first();

        if ($duplicate) {
            throw new RuntimeException(
                'Cannot add case-insensitive name uniqueness to ' . $table
                . ': existing duplicate name "' . $duplicate->normalized_name . '" must be resolved first.'
            );
        }
    }

    private function addNormalizedNameColumn($table)
    {
        if (Schema::hasColumn($table, 'name_normalized')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE ' . $table
                . ' ADD name_normalized VARCHAR(255) GENERATED ALWAYS AS (LOWER(TRIM(name))) STORED'
            );

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement(
                'ALTER TABLE ' . $table
                . ' ADD COLUMN name_normalized TEXT GENERATED ALWAYS AS (LOWER(TRIM(name))) STORED'
            );

            return;
        }

        throw new RuntimeException('Case-insensitive name indexes do not support database driver: ' . $driver);
    }

    private function hasIndex($table, $index)
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            return count(DB::select('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$index])) > 0;
        }

        if ($driver === 'sqlite') {
            foreach (DB::select("PRAGMA index_list('" . $table . "')") as $existingIndex) {
                if ($existingIndex->name === $index) {
                    return true;
                }
            }

            return false;
        }

        throw new RuntimeException('Case-insensitive name indexes do not support database driver: ' . $driver);
    }

    private function createUnitDuplicateTriggers()
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::unprepared("CREATE TRIGGER units_prevent_duplicate_name_insert BEFORE INSERT ON units
                FOR EACH ROW BEGIN
                    DECLARE duplicate_count INT DEFAULT 0;
                    SELECT COUNT(*) INTO duplicate_count FROM units
                    WHERE LOWER(TRIM(name)) = LOWER(TRIM(NEW.name));
                    IF duplicate_count > 0 THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unit is already Present';
                    END IF;
                END");
            DB::unprepared("CREATE TRIGGER units_prevent_duplicate_name_update BEFORE UPDATE ON units
                FOR EACH ROW BEGIN
                    DECLARE duplicate_count INT DEFAULT 0;
                    IF LOWER(TRIM(OLD.name)) <> LOWER(TRIM(NEW.name)) THEN
                        SELECT COUNT(*) INTO duplicate_count FROM units
                        WHERE id <> NEW.id AND LOWER(TRIM(name)) = LOWER(TRIM(NEW.name));
                        IF duplicate_count > 0 THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unit is already Present';
                        END IF;
                    END IF;
                END");

            return;
        }

        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER units_prevent_duplicate_name_insert
                BEFORE INSERT ON units
                WHEN EXISTS (SELECT 1 FROM units WHERE LOWER(TRIM(name)) = LOWER(TRIM(NEW.name)))
                BEGIN SELECT RAISE(ABORT, 'Unit is already Present'); END");
            DB::unprepared("CREATE TRIGGER units_prevent_duplicate_name_update
                BEFORE UPDATE ON units
                WHEN LOWER(TRIM(OLD.name)) <> LOWER(TRIM(NEW.name))
                  AND EXISTS (SELECT 1 FROM units WHERE id <> NEW.id AND LOWER(TRIM(name)) = LOWER(TRIM(NEW.name)))
                BEGIN SELECT RAISE(ABORT, 'Unit is already Present'); END");

            return;
        }

        throw new RuntimeException('Case-insensitive unit protection does not support database driver: ' . $driver);
    }

    private function dropUnitDuplicateTriggers()
    {
        $driver = Schema::getConnection()->getDriverName();
        foreach (['insert', 'update'] as $action) {
            $trigger = 'units_prevent_duplicate_name_' . $action;
            if ($driver === 'mysql') {
                DB::statement('DROP TRIGGER IF EXISTS ' . $trigger);
            } elseif ($driver === 'sqlite') {
                DB::statement('DROP TRIGGER IF EXISTS ' . $trigger);
            } else {
                throw new RuntimeException('Case-insensitive unit protection does not support database driver: ' . $driver);
            }
        }
    }
}
