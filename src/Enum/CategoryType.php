<?php

namespace App\Enum;

enum CategoryType: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';

    public function getLabel(): string
    {
        return match($this) {
            self::INCOME => 'Доход',
            self::EXPENSE => 'Расход',
        };
    }

        public static function fromString(string $type): self
    {
        return match(strtolower($type)) {
            'income', 'доход' => self::INCOME,
            'expense', 'расход' => self::EXPENSE,
            default => throw new \InvalidArgumentException("Неизвестный тип категории: {$type}")
        };
    }

    // Дополнительный метод для удобства
    public static function tryFromString(string $type): ?self
    {
        try {
            return self::fromString($type);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}