<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Models;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use function sprintf;

/**
 * @method static Builder where(string $string, int $userId)
 */
class UserLedgerEntry extends Model
{
    use SoftDeletes;

    public const STATUS_ACCEPTED = 0;

    public const STATUS_PENDING = 1;

    public const STATUS_REJECTED = 2;

    public const STATUS_BLOCKED = 3;

    public const STATUS_PROCESSING = 4;

    public const TYPE_UNKNOWN = 0;

    public const TYPE_DEPOSIT = 1;

    public const TYPE_WITHDRAWAL = 2;

    public const TYPE_AD_INCOME = 3;

    public const TYPE_AD_EXPENDITURE = 4;

    public const ALLOWED_STATUS_LIST = [
        self::STATUS_ACCEPTED,
        self::STATUS_PENDING,
        self::STATUS_REJECTED,
        self::STATUS_BLOCKED,
        self::STATUS_PROCESSING,
    ];

    public const ALLOWED_TYPE_LIST = [
        self::TYPE_DEPOSIT,
        self::TYPE_WITHDRAWAL,
        self::TYPE_AD_INCOME,
        self::TYPE_AD_EXPENDITURE,
    ];

    public const DEBIT_TYPES = [self::TYPE_AD_EXPENDITURE, self::TYPE_WITHDRAWAL];

    protected $dates = [
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'amount' => 'int',
    ];

    public static function getBalanceByUserId(int $userId): int
    {
        return self::balanceRelevantEntriesByUserId($userId)
            ->sum('amount');
    }

    public static function construct(int $userId, int $amount, int $status, int $type): self
    {
        $userLedgerEntry = new self();
        $userLedgerEntry->user_id = $userId;
        $userLedgerEntry->amount = $amount;
        $userLedgerEntry->status = $status;
        $userLedgerEntry->type = $type;

        return $userLedgerEntry;
    }

    public static function balanceRelevantEntriesByUserId(int $userId)
    {
        return self::where('user_id', $userId)
            ->where(function (EloquentBuilder $query) {
                $query->where('status', self::STATUS_ACCEPTED)
                    ->orWhere(function (EloquentBuilder $query) {
                        $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_BLOCKED, self::STATUS_PROCESSING])
                            ->whereIn('type', self::DEBIT_TYPES);
                    });
            });
    }

    public static function removeBlockedExpenditures(): void
    {
        self::blockedEntries()
            ->delete();
    }

    public static function pushBlockedToProcessing(): void
    {
        self::blockedEntries()
            ->update(['status' => self::STATUS_PROCESSING]);
    }

    public static function removeProcessingExpenditures(): void
    {
        self::where('status', self::STATUS_PROCESSING)
            ->where('type', self::TYPE_AD_EXPENDITURE)
            ->delete();
    }

    private static function blockedEntries()
    {
        return self::where('status', self::STATUS_BLOCKED)
            ->where('type', self::TYPE_AD_EXPENDITURE);
    }

    public static function block(int $type, int $userId, int $nonNegativeAmount): self
    {
        if ($nonNegativeAmount < 0) {
            throw new InvalidArgumentException(
                sprintf('Amount needs to be non-negative - User [%s] - Type [%s].', $userId, $type)
            );
        }

        $isDebit = in_array($type, self::DEBIT_TYPES, true);
        if ($isDebit && self::getBalanceByUserId($userId) < $nonNegativeAmount) {
            throw new InvalidArgumentException(
                sprintf('Insufficient funds for User [%s] when blocking Type [%s].', $userId, $type)
            );
        }

        $obj = self::construct(
            $userId,
            $isDebit ? -$nonNegativeAmount : $nonNegativeAmount,
            self::STATUS_BLOCKED,
            $type
        );

        $obj->save();

        return $obj;
    }

    public function addressed(string $addressFrom, string $addressTo): self
    {
        $this->address_from = $addressFrom;
        $this->address_to = $addressTo;

        return $this;
    }
}
