<?php

declare(strict_types=1);

arch()->preset()->php();

arch()->preset()->strict();

arch()->preset()->security();

arch('the source never echoes, exits or dumps')
    ->expect('OffsetSmoker\PestRatchets')
    ->not->toUse(['echo', 'print', 'die', 'exit', 'var_dump', 'print_r', 'error_log']);

arch('rules implement the Rule interface')
    ->expect('OffsetSmoker\PestRatchets\Rules')
    ->toImplement('OffsetSmoker\PestRatchets\Rule')
    ->toBeFinal()
    ->toBeReadonly();
