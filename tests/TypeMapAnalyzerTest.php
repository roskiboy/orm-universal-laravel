<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Tests;

use OrmUniversal\Contracts\ValueObjects\ColumnDefinition;
use OrmUniversal\Laravel\Analysis\ModelCastCodemod;
use OrmUniversal\Laravel\Analysis\TypeMapAnalyzer;
use PHPUnit\Framework\TestCase;

final class TypeMapAnalyzerTest extends TestCase
{
    /**
     * @return list<ColumnDefinition>
     */
    private function columns(): array
    {
        return [
            new ColumnDefinition('price', 'decimal(32,6)', scale: 6),
            new ColumnDefinition('meta', 'json'),
            new ColumnDefinition('active', 'tinyint', length: 1),
            new ColumnDefinition('created_at', 'datetime'),
            new ColumnDefinition('embedding', 'vector'),
            new ColumnDefinition('name', 'varchar', length: 255),
        ];
    }

    public function test_missing_casts_are_suggested_from_column_types(): void
    {
        $suggestions = (new TypeMapAnalyzer)->analyze($this->columns(), []);

        self::assertSame(
            ['price', 'meta', 'active', 'created_at', 'embedding'],
            array_column($suggestions, 'column'),
        );
        self::assertSame(
            ['decimal:6', 'array', 'bool', 'datetime', 'vector'],
            array_column($suggestions, 'suggested'),
        );
        self::assertSame(['missing', 'missing', 'missing', 'missing', 'missing'], array_column($suggestions, 'severity'));
    }

    public function test_matching_casts_produce_no_suggestions(): void
    {
        $suggestions = (new TypeMapAnalyzer)->analyze($this->columns(), [
            'price' => 'decimal:6',
            'meta' => 'json',
            'active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'embedding' => 'vector',
        ]);

        self::assertSame([], $suggestions);
    }

    public function test_decimal_as_float_is_an_error_never_a_float_cast(): void
    {
        $suggestions = (new TypeMapAnalyzer)->analyze($this->columns(), ['price' => 'float']);

        self::assertSame('price', $suggestions[0]['column']);
        self::assertSame('error', $suggestions[0]['severity']);
        self::assertSame('decimal:6', $suggestions[0]['suggested']);
        self::assertStringContainsString('must stay strings', $suggestions[0]['reason']);
    }

    public function test_wrong_scalar_casts_are_warnings(): void
    {
        $suggestions = (new TypeMapAnalyzer)->analyze($this->columns(), [
            'meta' => 'string',
            'active' => 'int',
        ]);

        $declared = array_values(array_filter(
            $suggestions,
            static fn (array $suggestion): bool => $suggestion['current'] !== null,
        ));

        self::assertSame(['meta', 'active'], array_column($declared, 'column'));
        self::assertSame(['warning', 'warning'], array_column($declared, 'severity'));
    }
}

final class ModelCastCodemodTest extends TestCase
{
    private const MODEL = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Fixture extends Model
{
    protected $casts = [
        'price' => 'float',
        'active' => 'bool',
    ];

    protected $fillable = ['price'];
}
PHP;

    public function test_inspect_reads_existing_casts(): void
    {
        $inspection = (new ModelCastCodemod)->inspect(self::MODEL);

        self::assertTrue($inspection['casts_property']);
        self::assertSame(['price' => 'float', 'active' => 'bool'], $inspection['current']);
    }

    public function test_apply_replaces_and_appends_surgically(): void
    {
        $codemod = new ModelCastCodemod;
        $proposed = $codemod->apply(self::MODEL, [
            ['column' => 'price', 'suggested' => 'decimal:2'],
            ['column' => 'meta', 'suggested' => 'array'],
        ]);

        self::assertNotNull($proposed);
        self::assertStringContainsString("'price' => 'decimal:2'", $proposed);
        self::assertStringContainsString("'meta' => 'array',", $proposed);
        self::assertStringContainsString("'active' => 'bool'", $proposed);
        self::assertStringContainsString("protected \$fillable = ['price'];", $proposed);
        self::assertStringStartsWith('<?php', $proposed);

        $inspection = $codemod->inspect($proposed);
        self::assertSame(['price' => 'decimal:2', 'active' => 'bool', 'meta' => 'array'], $inspection['current']);
    }

    public function test_models_without_casts_are_not_guessed(): void
    {
        $codemod = new ModelCastCodemod;

        self::assertNull($codemod->apply('<?php class Plain {}', [['column' => 'x', 'suggested' => 'array']]));
        self::assertFalse($codemod->inspect('<?php class Plain {}')['casts_property']);
    }
}
