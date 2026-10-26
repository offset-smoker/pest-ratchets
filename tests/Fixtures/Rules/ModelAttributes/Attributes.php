<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes as Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

#[Fillable('title', 'body')]
final class Article extends Model
{
}

#[Unguarded]
final class Draft extends Model
{
}

#[Fillable(['name']), Unguarded]
final class Setting extends Model
{
}

#[Guarded([])]
final class Upload extends Model
{
}

#[Eloquent\Guarded(columns: [])]
abstract class OpenBase extends Model
{
}

#[Guarded(['*'])]
final class Locked extends Model
{
    protected $fillable = ['name'];
}

#[\Illuminate\Database\Eloquent\Attributes\Guarded(['id'])]
final class Invoice extends Model
{
}

#[\App\Attributes\Fillable(['name'])]
final class Lookalike extends Model
{
}

#[Unguarded]
final class NotAModel
{
}
