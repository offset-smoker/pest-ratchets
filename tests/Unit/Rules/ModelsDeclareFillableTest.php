<?php

declare(strict_types=1);

use OffsetSmoker\PestRatchets\Rules\ModelsDeclareFillable;

it('requires $fillable on concrete models and flags $guarded = [] on any model', function (): void {
    expect(offendersIn(ModelsDeclareFillable::class, fixturePath('Rules/Models')))->toBe([
        'Models.php:14 App\Models\Comment does not declare $fillable',
        'Models.php:19 App\Models\Tag does not declare $fillable',
        'Models.php:21 App\Models\Tag sets $guarded = [], which leaves every attribute mass assignable; declare $fillable instead',
        'Models.php:28 App\Models\Reaction sets $guarded = [], which leaves every attribute mass assignable; declare $fillable instead',
        'Models.php:31 App\Models\Membership does not declare $fillable',
        'Models.php:35 App\Models\User does not declare $fillable',
        'Models.php:46 App\Models\Unguarded sets $guarded = [], which leaves every attribute mass assignable; declare $fillable instead',
        'Models.php:58 App\Models\Category does not declare $fillable',
    ]);
});

it('follows a base model only when it is configured', function (): void {
    $rule = new ModelsDeclareFillable(['App\Models\Base']);

    expect(offendersIn($rule, fixturePath('Rules/Models')))->toBe([
        'Models.php:49 App\Models\Report does not declare $fillable',
    ]);
});

it('names anonymous models', function (): void {
    $project = temporaryProject('Rules/Models');
    writeFile("{$project}/Anonymous.php", "<?php\n\nreturn new class extends \\Illuminate\\Database\\Eloquent\\Model {\n    protected \$guarded = [];\n};\n");

    expect(offendersIn(ModelsDeclareFillable::class, $project, 'Anonymous.php'))->toBe([
        'Anonymous.php:3 An anonymous model class does not declare $fillable',
        'Anonymous.php:4 An anonymous model class sets $guarded = [], which leaves every attribute mass assignable; declare $fillable instead',
    ]);
});

it('accepts the stock Laravel 13 User model, which declares #[Fillable]', function (): void {
    expect(offendersIn(ModelsDeclareFillable::class, fixturePath('Rules/ModelAttributes'), 'User.php'))->toBe([]);
});

it('reads Eloquent\'s #[Fillable], #[Guarded] and #[Unguarded] attributes', function (): void {
    expect(offendersIn(ModelsDeclareFillable::class, fixturePath('Rules/ModelAttributes'), 'Attributes.php'))->toBe([
        'Attributes.php:16 App\Models\Draft does not declare $fillable',
        'Attributes.php:16 App\Models\Draft is marked #[Unguarded], which leaves every attribute mass assignable; declare $fillable instead',
        'Attributes.php:21 App\Models\Setting is marked #[Unguarded], which leaves every attribute mass assignable; declare $fillable instead',
        'Attributes.php:26 App\Models\Upload does not declare $fillable',
        'Attributes.php:26 App\Models\Upload sets #[Guarded([])], which leaves every attribute mass assignable; declare $fillable instead',
        'Attributes.php:31 App\Models\OpenBase sets #[Guarded([])], which leaves every attribute mass assignable; declare $fillable instead',
        'Attributes.php:42 App\Models\Invoice does not declare $fillable',
        'Attributes.php:47 App\Models\Lookalike does not declare $fillable',
    ]);
});
