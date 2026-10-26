<?php

namespace App;

final class NotTheFacade
{
    public function run(int $id): void
    {
        DB::select("select $id");
        Reports::raw("value $id");
    }
}
