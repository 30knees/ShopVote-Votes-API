<?php

declare(strict_types=1);

namespace {
    if (!defined('_DB_PREFIX_')) {
        define('_DB_PREFIX_', 'ps_');
    }

    if (!class_exists('DbQuery')) {
        class DbQuery
        {
            private array $select = [];
            private string $from = '';
            private array $where = [];
            private string $orderBy = '';
            private ?int $limit = null;

            public function select(string $fields): self
            {
                $this->select[] = $fields;

                return $this;
            }

            public function from(string $table): self
            {
                $this->from = '`' . _DB_PREFIX_ . $table . '`';

                return $this;
            }

            public function where(string $condition): self
            {
                $this->where[] = $condition;

                return $this;
            }

            public function orderBy(string $orderBy): self
            {
                $this->orderBy = $orderBy;

                return $this;
            }

            public function limit(int $limit): self
            {
                $this->limit = $limit;

                return $this;
            }

            public function __toString(): string
            {
                $sql = 'SELECT ' . implode(', ', $this->select) . "\nFROM " . $this->from;
                if ($this->where !== []) {
                    $sql .= "\nWHERE " . implode(' AND ', $this->where);
                }
                if ($this->orderBy !== '') {
                    $sql .= "\nORDER BY " . $this->orderBy;
                }
                if ($this->limit !== null) {
                    $sql .= "\nLIMIT " . $this->limit;
                }

                return $sql;
            }
        }
    }

    if (!class_exists('Db')) {
        class Db
        {
            private static ?self $instance = null;
            public string $lastQuery = '';
            public array $rows = [];

            public static function getInstance(): self
            {
                return self::$instance ??= new self();
            }

            public function getRow($sql)
            {
                $this->lastQuery = (string) $sql . ' LIMIT 1';

                if (substr_count(strtoupper($this->lastQuery), 'LIMIT 1') !== 1) {
                    throw new \RuntimeException('PrestaShop getRow() generated duplicate LIMIT clauses');
                }

                return array_shift($this->rows) ?: false;
            }

            public function reset(array $rows): void
            {
                $this->lastQuery = '';
                $this->rows = $rows;
            }
        }
    }
}

namespace ShopVote\ShopVoteReviews\Tests\Unit {
    use Db;
    use PHPUnit\Framework\TestCase;
    use ShopVote\ShopVoteReviews\Repository\ShopSummaryRepository;
    use ShopVote\ShopVoteReviews\Repository\SyncLogRepository;

    class DatabaseSingleRowQueryTest extends TestCase
    {
        public function testLatestSummaryReliesOnGetRowLimit(): void
        {
            Db::getInstance()->reset([['id_summary' => 7]]);

            $this->assertSame(['id_summary' => 7], (new ShopSummaryRepository())->getLatestSummary(1));
            $this->assertSame(1, substr_count(strtoupper(Db::getInstance()->lastQuery), 'LIMIT 1'));
        }

        public function testLastSuccessfulSyncReliesOnGetRowLimit(): void
        {
            Db::getInstance()->reset([['sync_time' => '2026-07-21 12:00:00']]);

            $this->assertSame(
                '2026-07-21 12:00:00',
                (new SyncLogRepository())->getLastSuccessfulSyncTime(1)
            );
            $this->assertSame(1, substr_count(strtoupper(Db::getInstance()->lastQuery), 'LIMIT 1'));
        }

        public function testLastErrorReliesOnGetRowLimit(): void
        {
            Db::getInstance()->reset([['status' => 'error']]);

            $this->assertSame(['status' => 'error'], (new SyncLogRepository())->getLastError(1));
            $this->assertSame(1, substr_count(strtoupper(Db::getInstance()->lastQuery), 'LIMIT 1'));
        }
    }
}
