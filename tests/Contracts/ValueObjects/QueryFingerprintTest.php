<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Tests\ValueObjects;

use OrmUniversal\Contracts\ValueObjects\QueryFingerprint;
use PHPUnit\Framework\TestCase;

final class QueryFingerprintTest extends TestCase
{
    public function test_equivalent_queries_share_a_fingerprint(): void
    {
        $a = QueryFingerprint::fromSql("select * from users where name = 'ana' and age > 30", 0);
        $b = QueryFingerprint::fromSql('SELECT *  FROM users WHERE name = ? AND age > ?', 2);

        self::assertSame($a->normalizedSql, $b->normalizedSql);
        self::assertFalse($a->equals($b), 'Binding count is part of the statement identity.');

        $same = QueryFingerprint::fromSql('SELECT * FROM users WHERE name = ? AND age > ?', 2);
        self::assertTrue($b->equals($same));
    }

    public function test_different_shapes_have_different_fingerprints(): void
    {
        $a = QueryFingerprint::fromSql('select * from users where active = 1', 0);
        $b = QueryFingerprint::fromSql('select * from users order by id', 0);

        self::assertFalse($a->equals($b));
    }

    public function test_string_literals_with_escaped_quotes_are_normalized(): void
    {
        $sql = "select * from t where a = 'o''brien' and b = \"x\\\"y\" and c = 42";
        $normalized = QueryFingerprint::normalize($sql);

        self::assertStringNotContainsString('obrien', $normalized);
        self::assertStringNotContainsString('42', $normalized);
        self::assertSame('select * from t where a = ? and b = ? and c = ?', $normalized);
    }
}
