<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)] // ← DATETIME_MUTABLE
    private ?\DateTime $date = null;

    public function __construct()
    {
        $this->date = new \DateTime(); // ← DateTime
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    // public function setAmount(string $amount): static
    // {
    //     $this->amount = $amount;

    //     return $this;
    // }
    public function setAmount(string $amount): static
    {
        // Сохраняем исходное значение
        $this->amount = $amount;
        
        // Автоматически обновляем знак суммы если категория уже установлена
        if ($this->category !== null) {
            $this->updateAmountSign();
        }
        
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;
        return $this;
    }
    
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    // public function setCategory(?Category $category): static
    // {
    //     $this->category = $category;
    //     return $this;
    // }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
        
        // Автоматически обновляем знак суммы если сумма уже установлена
        if ($category && $this->amount !== null) {
            $this->updateAmountSign();
        }
        
        return $this;
    }

    private function updateAmountSign(): void
    {
        $amount = (float) $this->amount;
        $categoryType = $this->category->getType();
        
        if ($categoryType instanceof \BackedEnum) {
            $categoryType = $categoryType->value;
        }
        
        // Применяем правильный знак
        if ($categoryType === 'expense' && $amount > 0) {
            $this->amount = (string) -$amount;
        } elseif ($categoryType === 'income' && $amount < 0) {
            $this->amount = (string) abs($amount);
        }
    }
}