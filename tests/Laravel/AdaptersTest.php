<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OrmUniversal\Contracts\Enums\TypeKind;
use OrmUniversal\Contracts\Exceptions\MigrationException;
use OrmUniversal\Contracts\ValueObjects\ColumnDefinition;
use OrmUniversal\Contracts\ValueObjects\TableDefinition;
use OrmUniversal\Laravel\Adapters\LaravelBuilderAdapter;
use OrmUniversal\Laravel\Adapters\LaravelMigrationAdapter;
use OrmUniversal\Laravel\Adapters\LaravelModelAdapter;
use OrmUniversal\Laravel\Adapters\LaravelVersionResolver;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use OrmUniversal\Relational\Migration\SchemaOperation;
use OrmUniversal\Relational\Relation\RelationDefinition;
use PDO;
use PHPUnit\Framework\TestCase;

final class AdaptersTest extends TestCase
{
    protected function setUp(): void
    {
        $container = new Container;
        $container['config'] = new Repository([
            'database' => [
                'default' => 'testing',
                'connections' => [
                    'testing' => ['driver' => 'sqlite', 'database' => ':memory:'],
                ],
            ],
        ]);

        Model::setConnectionResolver(new DatabaseManager($container, new ConnectionFactory($container)));
    }

    protected function tearDown(): void
    {
        Model::unsetConnectionResolver();
    }

    private function executor(): QueryExecutor
    {
        return new QueryExecutor(new PdoConnection(new PDO('sqlite::memory:'), 'sqlite-adapters'), SqliteDialect::make('3.49.2'));
    }

    public function test_version_resolver_reports_runtime_information(): void
    {
        $resolver = new LaravelVersionResolver;

        self::assertNotSame('', $resolver->version());
        self::assertTrue($resolver->isAtLeast(12));
        self::assertArrayHasKey('features', $resolver->report());
    }

    public function test_model_adapter_reads_metadata_and_relations(): void
    {
        $adapter = new LaravelModelAdapter;

        $metadata = $adapter->metadata(AdapterUser::class);
        self::assertSame('adapter_users', $metadata['table']);
        self::assertSame('id', $metadata['primary_key']);
        self::assertSame('boolean', $metadata['casts']['active']);

        $definitions = $adapter->relationDefinitions(AdapterUser::class);
        $names = array_map(static fn (RelationDefinition $definition): string => $definition->name(), $definitions);

        self::assertContains('team', $names);
        self::assertContains('posts', $names);

        foreach ($definitions as $definition) {
            if ($definition->name() === 'team') {
                self::assertSame(RelationDefinition::BELONGS_TO, $definition->type());
                self::assertSame('adapter_teams', $definition->relatedTable()->name);
                self::assertSame('team_id', $definition->foreignKey());
            }

            if ($definition->name() === 'posts') {
                self::assertSame(RelationDefinition::HAS_MANY, $definition->type());
                self::assertTrue($definition->isToMany());
                self::assertSame('adapter_posts', $definition->relatedTable()->name);
            }
        }
    }

    public function test_builder_adapter_copies_known_clauses_only(): void
    {
        $eloquent = AdapterUser::query()->where('active', true)->orderBy('name')->limit(5);

        $ude = (new LaravelBuilderAdapter)->fromEloquent($eloquent, $this->executor());

        self::assertSame('adapter_users', $ude->query()->table()?->name);
        self::assertNotNull($ude->query()->whereExpression());
        self::assertSame(5, $ude->query()->limitValue());

        $plan = $ude->toPlan();
        self::assertSame(5, $plan['limit']);
    }

    public function test_migration_adapter_previews_and_guards_ddl(): void
    {
        $adapter = new LaravelMigrationAdapter(SqliteDialect::make('3.49.2'));

        $preview = $adapter->preview(new TableDefinition(
            name: 'adapter_logs',
            columns: [new ColumnDefinition(name: 'id', databaseType: 'integer', kind: TypeKind::Integer, nullable: false)],
        ));

        self::assertStringContainsString('create table "adapter_logs"', $preview[0]->sql);

        $report = $adapter->safetyReport('drop table users');
        self::assertTrue($report['destructive']);
        self::assertNotEmpty($report['warnings']);

        $this->expectException(MigrationException::class);
        $adapter->assertSafe($adapter->previewOperation(SchemaOperation::dropTable('users', false)));
    }
}

class AdapterTeam extends Model
{
    protected $table = 'adapter_teams';

    public $timestamps = false;
}

class AdapterUser extends Model
{
    protected $table = 'adapter_users';

    public $timestamps = false;

    protected $fillable = ['name', 'team_id', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(AdapterTeam::class, 'team_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(AdapterPost::class, 'user_id');
    }
}

class AdapterPost extends Model
{
    protected $table = 'adapter_posts';

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(AdapterUser::class, 'user_id');
    }
}
