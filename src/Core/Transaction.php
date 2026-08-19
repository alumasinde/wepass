<?php

namespace App\Core;

class Transaction
{
    public static function run(callable $callback)
    {
        DB::beginTransaction();

        try {
            $result = $callback();
            DB::commit();
            return $result;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}