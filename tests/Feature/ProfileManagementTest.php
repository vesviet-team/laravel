<?php

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

describe('Profile Management', function () {
    test('renders profile page for authenticated customer', function () {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')
            ->get(route('account.profile'))
            ->assertOk()
            ->assertViewIs('storefront.account.profile');
    });

    test('redirects guest to login when accessing profile', function () {
        $this->get(route('account.profile'))
            ->assertRedirect(route('login'));
    });

    test('updates profile information', function () {
        $customer = Customer::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'phone' => '0901234567',
        ]);
        $this->actingAs($customer, 'customer')
            ->put(route('account.profile.update'), [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'phone' => '0987654321',
                'date_of_birth' => '1990-01-15',
                'gender' => 'male',
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHas('success');
        $customer->refresh();
        expect($customer->name)->toBe('Updated Name')
            ->and($customer->email)->toBe('updated@example.com')
            ->and($customer->phone)->toBe('0987654321')
            ->and($customer->date_of_birth->format('Y-m-d'))->toBe('1990-01-15')
            ->and($customer->gender)->toBe('male');
    });
});

describe('Address Management', function () {
    test('renders addresses index', function () {
        $customer = Customer::factory()->create();
        CustomerAddress::factory()->count(2)->create(['customer_id' => $customer->id]);
        $this->actingAs($customer, 'customer')
            ->get(route('account.addresses'))
            ->assertOk()
            ->assertViewIs('storefront.account.addresses');
    });

    test('creates shipping address', function () {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')
            ->post(route('account.addresses.store'), [
                'type' => 'shipping',
                'label' => 'Nha rieng',
                'recipient_name' => 'Nguyen Van A',
                'phone' => '0901234567',
                'address_line_1' => '123 Duong ABC',
                'city' => 'Ha Noi',
                'district' => 'Ba Dinh',
                'ward' => 'Phuong Giang Vo',
                'is_default' => true,
            ])
            ->assertRedirect(route('account.addresses'))
            ->assertSessionHas('success');
        expect($customer->addresses()->count())->toBe(1);
        $address = $customer->addresses()->first();
        expect($address->type)->toBe('shipping')
            ->and($address->is_default)->toBeTrue();
    });

    test('auto-sets first address of type as default', function () {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')
            ->post(route('account.addresses.store'), [
                'type' => 'shipping',
                'recipient_name' => 'Test User',
                'phone' => '0901234567',
                'address_line_1' => '123 Test St',
                'city' => 'Ha Noi',
                'district' => 'Ba Dinh',
                'ward' => 'Phuong Test',
            ]);
        $address = $customer->addresses()->where('type', 'shipping')->first();
        expect($address->is_default)->toBeTrue();
    });

    test('validates required fields on address creation', function () {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')
            ->post(route('account.addresses.store'), [])
            ->assertSessionHasErrors(['type', 'recipient_name', 'phone', 'address_line_1', 'city', 'district']);
    });

    test('deletes address', function () {
        $customer = Customer::factory()->create();
        $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
        $this->actingAs($customer, 'customer')
            ->delete(route('account.addresses.destroy', $address))
            ->assertRedirect(route('account.addresses'))
            ->assertSessionHas('success');
        expect($customer->addresses()->count())->toBe(0);
    });

    test('sets next address as default when deleting default', function () {
        $customer = Customer::factory()->create();
        $address1 = CustomerAddress::factory()->create(['customer_id' => $customer->id, 'type' => 'shipping', 'is_default' => true]);
        $address2 = CustomerAddress::factory()->create(['customer_id' => $customer->id, 'type' => 'shipping', 'is_default' => false]);
        $this->actingAs($customer, 'customer')
            ->delete(route('account.addresses.destroy', $address1));
        $address2->refresh();
        expect($address2->is_default)->toBeTrue();
    });
});
