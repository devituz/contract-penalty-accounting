<?php

namespace App\Domain;

use Illuminate\Support\Facades\DB;

/**
 * Tranzaksiya ichida qatorni qulflaydi.
 *
 * SQLite'da lockForUpdate() hech narsa qilmaydi, shuning uchun qulfni yozish orqali olamiz:
 * tranzaksiyaning birinchi amali yozish bo'lsa, SQLite ikkinchi yozuvchini tranzaksiya tugaguncha
 * kutdiradi (busy_timeout) va u o'qishni allaqachon yangilangan ma'lumotdan boshlaydi.
 * PostgreSQL'da shu UPDATE oddiy qator qulfi bo'ladi, kod o'zgarmaydi.
 *
 * Qulf tranzaksiyaning birinchi amali bo'lishi shart: oldin o'qilsa, SQLite yozishga o'tishda kutmasdan
 * "database is locked" qaytaradi. Har doim bir xil tartibda oling: avval contracts, keyin budget_lines.
 */
final class RowLock
{
    public static function acquire(string $table, int $id): void
    {
        DB::table($table)->where('id', $id)->update(['updated_at' => DB::raw('updated_at')]);
    }
}
