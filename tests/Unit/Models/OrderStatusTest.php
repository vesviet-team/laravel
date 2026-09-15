<?php

namespace Tests\Unit\Models;

use App\Enums\OrderStatus;

/**
 * Unit tests for OrderStatus state machine.
 *
 * Guards against:
 * - Invalid forward transitions being allowed
 * - Backward transitions being permitted
 * - Terminal states allowing further transitions
 * - allowedTransitions() returning raw string instead of enum
 */

// ── Forward transitions ──────────────────────────────────────────────────────

it('allows pending to confirmed', function () {
    expect(OrderStatus::Pending->canTransitionTo(OrderStatus::Confirmed))->toBeTrue();
});

it('allows pending to cancelled', function () {
    expect(OrderStatus::Pending->canTransitionTo(OrderStatus::Cancelled))->toBeTrue();
});

it('allows confirmed to processing', function () {
    expect(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Processing))->toBeTrue();
});

it('allows processing to shipped', function () {
    expect(OrderStatus::Processing->canTransitionTo(OrderStatus::Shipped))->toBeTrue();
});

it('allows shipped to delivered', function () {
    expect(OrderStatus::Shipped->canTransitionTo(OrderStatus::Delivered))->toBeTrue();
});

// ── Blocked transitions (one-way state machine) ──────────────────────────────

it('does not allow skipping pending to processing', function () {
    expect(OrderStatus::Pending->canTransitionTo(OrderStatus::Processing))->toBeFalse();
});

it('does not allow skipping pending to shipped', function () {
    expect(OrderStatus::Pending->canTransitionTo(OrderStatus::Shipped))->toBeFalse();
});

it('does not allow backward transition confirmed to pending', function () {
    expect(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Pending))->toBeFalse();
});

it('does not allow backward transition shipped to processing', function () {
    expect(OrderStatus::Shipped->canTransitionTo(OrderStatus::Processing))->toBeFalse();
});

it('does not allow delivered to any status', function () {
    foreach (OrderStatus::cases() as $next) {
        expect(OrderStatus::Delivered->canTransitionTo($next))->toBeFalse();
    }
});

it('does not allow cancelled to any status', function () {
    foreach (OrderStatus::cases() as $next) {
        expect(OrderStatus::Cancelled->canTransitionTo($next))->toBeFalse();
    }
});

// ── Terminal state detection ─────────────────────────────────────────────────

it('considers delivered a terminal state', function () {
    expect(OrderStatus::Delivered->isTerminal())->toBeTrue();
});

it('considers cancelled a terminal state', function () {
    expect(OrderStatus::Cancelled->isTerminal())->toBeTrue();
});

it('considers non-terminal states not terminal', function () {
    $nonTerminal = [
        OrderStatus::Pending,
        OrderStatus::Confirmed,
        OrderStatus::Processing,
        OrderStatus::Shipped,
    ];

    foreach ($nonTerminal as $status) {
        expect($status->isTerminal())->toBeFalse("Expected {$status->value} to not be terminal");
    }
});

// ── Allowed transitions return type guard ────────────────────────────────────

it('returns enum instances not strings from allowedTransitions', function () {
    $transitions = OrderStatus::Pending->allowedTransitions();

    foreach ($transitions as $t) {
        expect($t)->toBeInstanceOf(OrderStatus::class);
    }
});

// ── Label and color completeness ─────────────────────────────────────────────

it('has a label for every status case', function () {
    foreach (OrderStatus::cases() as $status) {
        expect($status->label())->not->toBeEmpty();
    }
});

it('has a color for every status case', function () {
    $validColors = ['warning', 'info', 'primary', 'success', 'danger', 'gray', 'secondary'];

    foreach (OrderStatus::cases() as $status) {
        expect($validColors)->toContain($status->color());
    }
});

// ── Enum value correctness (regression guard against stale strings) ──────────

it('has correct string values for all cases', function () {
    expect(OrderStatus::Pending->value)->toBe('pending')
        ->and(OrderStatus::Confirmed->value)->toBe('confirmed')
        ->and(OrderStatus::Processing->value)->toBe('processing')
        ->and(OrderStatus::Shipped->value)->toBe('shipped')
        ->and(OrderStatus::Delivered->value)->toBe('delivered')
        ->and(OrderStatus::Cancelled->value)->toBe('cancelled');
});

it('does not have a completed case', function () {
    $values = array_map(fn ($s) => $s->value, OrderStatus::cases());
    expect($values)->not->toContain('completed');
});

it('does not have a shipping case', function () {
    $values = array_map(fn ($s) => $s->value, OrderStatus::cases());
    expect($values)->not->toContain('shipping');
});
