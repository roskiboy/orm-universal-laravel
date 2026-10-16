<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Integration;

use OrmUniversal\Contracts\Exceptions\RelationException;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use OrmUniversal\Relational\Relation\RelationDefinition;
use OrmUniversal\Relational\Relation\RelationQuery;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Through, morphToMany and morphTo relation families against real SQLite.
 *
 * @group integration
 */
final class RelationFamilyTest extends TestCase
{
    private QueryExecutor $executor;

    private RelationQuery $relations;

    protected function setUp(): void
    {
        $this->executor = new QueryExecutor(
            new PdoConnection(new PDO('sqlite::memory:'), 'family-test'),
            SqliteDialect::make('3.49.2'),
        );

        $this->executor->execute(new RawQuery('create table users (id integer primary key, name text not null)'));
        $this->executor->execute(new RawQuery('create table posts (id integer primary key, user_id integer not null, title text not null)'));
        $this->executor->execute(new RawQuery('create table comments (id integer primary key, post_id integer not null, body text not null)'));
        $this->executor->execute(new RawQuery('create table tags (id integer primary key, name text not null)'));
        $this->executor->execute(new RawQuery('create table taggables (tag_id integer not null, taggable_id integer not null, taggable_type text not null)'));
        $this->executor->execute(new RawQuery('create table feed (id integer primary key, commentable_type text not null, commentable_id integer not null)'));

        $this->executor->execute(new RawQuery('insert into users (id, name) values (1, ?), (2, ?)', ['ana', 'bob']));
        $this->executor->execute(new RawQuery("insert into posts (id, user_id, title) values (1, 1, 'p1'), (2, 1, 'p2'), (3, 2, 'p3')"));
        $this->executor->execute(new RawQuery("insert into comments (id, post_id, body) values (1, 1, 'c1'), (2, 1, 'c2'), (3, 3, 'c3')"));
        $this->executor->execute(new RawQuery("insert into tags (id, name) values (1, 'php'), (2, 'laravel')"));
        $this->executor->execute(new RawQuery("insert into taggables (tag_id, taggable_id, taggable_type) values (1, 1, 'post'), (2, 1, 'post'), (1, 2, 'user')"));
        $this->executor->execute(new RawQuery("insert into feed (id, commentable_type, commentable_id) values (1, 'post', 1), (2, 'post', 3)"));

        $this->relations = new RelationQuery($this->executor);
    }

    public function test_has_many_through_batches_with_join(): void
    {
        $definition = RelationDefinition::hasManyThrough(
            'comments',
            TableIdentifier::fromString('comments'),
            TableIdentifier::fromString('posts'),
            throughForeignKey: 'user_id',
            throughLocalKey: 'id',
            relatedKey: 'post_id',
        );

        $builder = $this->relations->batch($definition, [1, 2]);
        $comments = $builder->orderBy('comments.id')->pluck('body');

        self::assertSame(['c1', 'c2', 'c3'], array_values($comments));

        $single = $this->relations->for($definition, 1)->pluck('body');
        self::assertSame(['c1', 'c2'], array_values($single));

        $sql = $this->relations->batch($definition, [1, 2])->sql();
        self::assertStringContainsString('join "posts" on "posts"."id" = "comments"."post_id"', $sql);
        self::assertStringContainsString('"posts"."user_id" in (?, ?)', $sql);
    }

    public function test_morph_to_many_filters_by_morph_class(): void
    {
        $definition = RelationDefinition::morphToMany(
            'tags',
            TableIdentifier::fromString('tags'),
            TableIdentifier::fromString('taggables'),
            foreignPivotKey: 'taggable_id',
            relatedPivotKey: 'tag_id',
            morphType: 'taggable_type',
            morphClass: 'post',
        );

        $postTags = $this->relations->for($definition, 1)->orderBy('tags.id')->pluck('name');
        self::assertSame(['php', 'laravel'], array_values($postTags));

        $batch = $this->relations->batch($definition, [1, 2])->pluck('name');
        self::assertSame(['php', 'laravel'], array_values($batch));

        $sql = $this->relations->for($definition, 1)->sql();
        self::assertStringContainsString('"taggables"."taggable_type" = ?', $sql);
    }

    public function test_morph_to_batch_uses_explicit_map(): void
    {
        $definition = RelationDefinition::morphTo(
            'commentable',
            foreignKey: 'commentable_id',
            morphType: 'commentable_type',
            morphMap: ['post' => 'posts'],
        );

        $feed = $this->executor->rows(new RawQuery('select * from feed order by id'));
        $posts = $this->relations->morphToBatch($definition, $feed);

        self::assertSame('p1', $posts['1']['title']);
        self::assertSame('p3', $posts['3']['title']);
    }

    public function test_morph_to_without_map_is_not_executable(): void
    {
        $definition = RelationDefinition::morphTo('commentable', 'commentable_id', 'commentable_type', []);

        $this->expectException(RelationException::class);
        $this->relations->morphToBatch($definition, [['commentable_type' => 'post', 'commentable_id' => 1]]);
    }

    public function test_simple_paginate_has_more_pages(): void
    {
        $page = QueryBuilder::table($this->executor, 'comments')
            ->orderBy('id')
            ->simplePaginate(2, 1);

        self::assertCount(2, (array) $page->items());
        self::assertTrue($page->hasMorePages());
        self::assertNull($page->total());
    }
}
