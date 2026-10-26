<?php

namespace App;

use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

final class Connections
{
    public function unsafe(object $query, string $name, int $id): void
    {
        DB::connection('pgsql')->select("select * from users where id = $id");
        DB::connection()->statement('drop table ' . $name);
        \DB::connection('reports')?->unprepared("vacuum {$name}");
        DB::connection()->raw("lower({$name})");
        $query->select(new Expression("lower($name)"));
        new \Illuminate\Database\Query\Expression(value: 'count(' . $name . ')');
    }

    public function safe(object $query, object $connection, string $name, int $id): void
    {
        DB::connection('pgsql')->select('select * from users where id = ?', [$id]);
        DB::connection()->table('users')->insert(['name' => "x{$name}"]);
        $query->select(new Expression('lower(name)'));
        new \App\Query\Expression("lower($name)");
        $connection->select("select * from users where id = $id");
        App::connection()->select("select $id");
        // updateFrom() takes an array of values, never SQL.
        $query->updateFrom("{$name}");
    }
}
