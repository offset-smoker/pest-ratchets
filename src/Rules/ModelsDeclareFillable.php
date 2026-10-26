<?php

declare(strict_types=1);

namespace OffsetSmoker\PestRatchets\Rules;

use OffsetSmoker\PestRatchets\Offense;
use OffsetSmoker\PestRatchets\Rule;
use OffsetSmoker\PestRatchets\SourceFile;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\Class_;

/**
 * Requires every concrete Eloquent model to declare a $fillable property,
 * and flags any model, abstract ones included, that sets $guarded = [],
 * which turns mass-assignment protection off for every attribute.
 *
 * A class counts as a model when it directly extends one of the configured
 * base classes. Add your own base model (App\Models\Model, say) to the list:
 * the rule reads one file at a time, so it cannot follow a chain of parents
 * through other files.
 */
final readonly class ModelsDeclareFillable implements Rule
{
    /**
     * @param  list<string>  $modelClasses  fully qualified base classes whose direct subclasses are models
     */
    public function __construct(
        private array $modelClasses = [
            'Illuminate\Database\Eloquent\Model',
            'Illuminate\Database\Eloquent\Relations\Pivot',
            'Illuminate\Database\Eloquent\Relations\MorphPivot',
            'Illuminate\Foundation\Auth\User',
        ],
    ) {}

    public function check(SourceFile $file): array
    {
        $offenses = [];

        foreach ($file->findInstanceOf(Class_::class) as $class) {
            if (! $this->isModel($class)) {
                continue;
            }

            $name = $class->namespacedName?->toString() ?? 'An anonymous model class';
            $declaresFillable = false;

            foreach ($class->getProperties() as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                foreach ($property->props as $item) {
                    $propertyName = $item->name->toString();

                    if ($propertyName === 'fillable') {
                        $declaresFillable = true;
                    }

                    if ($propertyName === 'guarded' && $item->default instanceof Array_ && $item->default->items === []) {
                        $offenses[] = new Offense($item->getStartLine(), "{$name} sets \$guarded = [], which leaves every attribute mass assignable; declare \$fillable instead");
                    }
                }
            }

            if (! $declaresFillable && ! $class->isAbstract()) {
                $offenses[] = new Offense($class->getStartLine(), "{$name} does not declare \$fillable");
            }
        }

        return $offenses;
    }

    private function isModel(Class_ $class): bool
    {
        if ($class->extends === null) {
            return false;
        }

        $parent = $class->extends->toString();

        foreach ($this->modelClasses as $modelClass) {
            if (strcasecmp(ltrim($modelClass, '\\'), $parent) === 0) {
                return true;
            }
        }

        return false;
    }
}
