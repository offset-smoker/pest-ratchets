<?php

namespace App;

use Illuminate\Support\Facades\DB;
use App\Models\User;

final class Queries
{
    private const STATUS = 'active';

    public function unsafe(object $query, string $name, int $id, array $input): void
    {
        DB::raw("lower(name) = '$name'");
        DB::select('select * from users where id = ' . $id);
        $query->whereRaw("name = '{$input['name']}'");
        $query->orderByRaw(sprintf('%s desc', $input['sort']));
        User::whereRaw("id = $id");
        \DB::statement(sql: "delete from users where id = {$id}");
        $query?->havingRaw('total > ' . $input['min']);
        $query->selectRaw($id > 0 ? "count({$name})" : 'count(*)');
    }

    public function safe(object $query, string $sql, int $id): void
    {
        DB::raw('lower(name) = ?');
        DB::select('select * from users where status = ' . self::STATUS . ' and id = ?', [$id]);
        $query->whereRaw('name = ?', [$id]);
        $query->whereRaw("name = 'literal' and id = " . PHP_INT_MAX);
        $query->orderByRaw(sprintf('%s desc', 'created_at'));
        $query->whereRaw($sql, [$id]);
        $query->where("name = $id");
        $query->whereRaw(...);
        // DB::raw("lower(name) = '$id'");
        $comment = "DB::raw(\"x = $id\")";
        DB::table('users')->update(['name' => "x{$id}"]);
    }
}
