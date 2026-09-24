<?php

namespace miniOrm\Tests;

use miniOrm\Db;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class DbTest extends TestCase
{
    public function testQuoteIdentifierQuotesQualifiedNames(): void
    {
        self::assertSame('`characters`', Db::quoteIdentifier('characters'));
        self::assertSame('`game`.`characters`', Db::quoteIdentifier('game.characters'));
    }

    public function testQuoteIdentifierRejectsSqlFragments(): void
    {
        $this->expectException(\Exception::class);
        Db::quoteIdentifier('characters; DROP TABLE users');
    }

    public function testWhereCriteriaBindValuesAndHandleNull(): void
    {
        $params = [];
        $arguments = [
            [
                'name' => "Conan' OR 1=1 --",
                'damage >' => 10,
                'deleted_at' => null,
            ],
            &$params,
        ];

        $where = $this->invokePrivate('buildWhereClause', $arguments);

        self::assertSame(' WHERE `name` = ? AND `damage` > ? AND `deleted_at` IS NULL', $where);
        self::assertSame(["Conan' OR 1=1 --", 10], $params);
    }

    public function testWhereCriteriaSupportInListsAndEmptyLists(): void
    {
        $params = [];
        $arguments = [
            [
                'id IN' => [2, 4, 8],
                'status NOT IN' => [],
            ],
            &$params,
        ];

        $where = $this->invokePrivate('buildWhereClause', $arguments);

        self::assertSame(' WHERE `id` IN (?, ?, ?) AND 1=1', $where);
        self::assertSame([2, 4, 8], $params);
    }

    public function testWhereCriteriaRejectUnsafeFieldNames(): void
    {
        $params = [];
        $arguments = [["name) = 'x' OR 1=1 --" => 'value'], &$params];

        $this->expectException(\Exception::class);
        $this->invokePrivate('buildWhereClause', $arguments);
    }

    public function testUpdateAndDeleteRequireAWhereClause(): void
    {
        $this->expectException(\Exception::class);
        $this->invokePrivate('ensureWhereNotEmpty', [[], 'Delete']);
    }

    public function testSelectBuilderQuotesIdentifiersAndValidatesLimit(): void
    {
        $arguments = [
            ['name', 'damage'],
            'characters',
            ' WHERE `damage` > ?',
            ['race_id'],
            ['damage DESC'],
            '0,10',
        ];

        $sql = $this->invokePrivate('getQuerySelect', $arguments);

        self::assertSame(
            'SELECT `name`, `damage` FROM `characters` WHERE `damage` > ? GROUP BY `race_id` ORDER BY `damage` DESC LIMIT 0,10',
            $sql
        );
    }

    public function testSelectBuilderRejectsUnsafeLimit(): void
    {
        $arguments = ['*', 'characters', null, null, null, '0; DROP TABLE users'];

        $this->expectException(\Exception::class);
        $this->invokePrivate('getQuerySelect', $arguments);
    }

    public function testInsertBuilderBindsValuesAndRestrictsInsertType(): void
    {
        $query = $this->invokePrivate('getQueryInsert', ['characters', ['name' => 'Conan']]);

        self::assertSame('INSERT INTO `characters` (`name`) VALUES (?)', $query[0]);
        self::assertSame(['Conan'], $query[1]);
    }

    public function testInsertBuilderRejectsAnUntrustedVerb(): void
    {
        $this->expectException(\Exception::class);
        $this->invokePrivate('getQueryInsert', ['characters', ['name' => 'Conan'], 'INSERT; DROP TABLE users']);
    }

    public function testInsertBuilderCanUseDatabaseDefaults(): void
    {
        $query = $this->invokePrivate('getQueryInsert', ['characters', []]);

        self::assertSame('INSERT INTO `characters` () VALUES ()', $query[0]);
        self::assertSame([], $query[1]);
    }

    private function invokePrivate(string $methodName, array $arguments)
    {
        $database = (new ReflectionClass(Db::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(Db::class, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($database, $arguments);
    }
}
