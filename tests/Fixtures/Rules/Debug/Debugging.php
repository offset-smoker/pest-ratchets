<?php

namespace App;

final class Debugging
{
    public function run(array $rows, object $query): void
    {
        dd($rows);
        dump($rows);
        \ray($rows);
        collect($rows)->dd();
        $query->dumpRawSql();
        $query?->ddRawSql();
        $query->DUMP();
    }

    public function quiet(array $rows, object $exporter): void
    {
        // dd($rows);
        /* dump($rows); ray($rows); */
        /** @see dd() */
        echo 'dd($rows)';
        $exporter->export($rows);
        var_dump(...);
    }
}
