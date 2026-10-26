<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Auth\User as Authenticatable;

final class Post extends Model
{
    protected $fillable = ['title'];
}

final class Comment extends Model
{
    protected $hidden = ['secret'];
}

final class Tag extends Model
{
    protected $guarded = [];
}

final class Reaction extends Model
{
    protected $fillable = ['kind'];

    protected $guarded = [];
}

final class Membership extends Pivot
{
}

final class User extends Authenticatable
{
    protected $guarded = ['*'];
}

abstract class Base extends Model
{
}

abstract class Unguarded extends Model
{
    protected $guarded = [];
}

final class Report extends Base
{
}

final class NotAModel
{
    protected $guarded = [];
}

final class Category extends \Illuminate\Database\Eloquent\Model
{
    // protected $fillable = ['name'];
    public static $fillable = ['name'];
}

$anonymous = new class extends Model {
    protected $fillable = [];
};
