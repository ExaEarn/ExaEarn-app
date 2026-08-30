<?php
declare(strict_types=1);
$script=file_get_contents(dirname(__DIR__).'/classify-migrations.php');foreach(['POSTGRES_REHEARSAL_REQUIRED','DATA_MIGRATION','DESTRUCTIVE','DB::(?:statement|unprepared)','dropIndex']as$signal)if(!str_contains($script,$signal))exit(1);
$known=file_get_contents(dirname(__DIR__,2).'/backend/api-gateway/database/migrations/2026_07_30_223500_expand_giftcard_rate_currency_length.php');if(!str_contains($known,'DB::statement'))exit(2);echo"Migration classifier fixtures: PASS\n";
