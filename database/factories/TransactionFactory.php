<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 0.01, 1000.00);
        $commissionRate = 0.015; // 1.5%
        $commissionFee = $amount * $commissionRate;

        return [
            'sender_id' => User::factory(),
            'receiver_id' => User::factory(),
            'amount' => $amount,
            'commission_fee' => $commissionFee,
            'completed_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Indicate that the transaction is for a specific sender.
     */
    public function forSender(User $sender): static
    {
        return $this->state(fn(array $attributes) => [
            'sender_id' => $sender->id,
        ]);
    }

    /**
     * Indicate that the transaction is for a specific receiver.
     */
    public function forReceiver(User $receiver): static
    {
        return $this->state(fn(array $attributes) => [
            'receiver_id' => $receiver->id,
        ]);
    }

    /**
     * Indicate that the transaction has a specific amount.
     */
    public function withAmount(float $amount): static
    {
        $commissionRate = 0.015; // 1.5%
        $commissionFee = $amount * $commissionRate;

        return $this->state(fn(array $attributes) => [
            'amount' => $amount,
            'commission_fee' => $commissionFee,
        ]);
    }
}
