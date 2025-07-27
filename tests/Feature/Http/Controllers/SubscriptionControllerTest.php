<?php

use App\Models\User;

describe('SubscriptionController', function () {
    it('requires authentication to access subscription page', function () {
        $response = $this->get(route('subscribe'));

        $response->assertRedirect(route('login'));
    });

    it('shows subscription page for authenticated users', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('subscribe'));

        $response->assertStatus(200)
            ->assertViewIs('subscribe')
            ->assertViewHas('intent');
    });

    it('creates setup intent for user', function () {
        $user = User::factory()->create();

        // Mock Stripe customer creation
        $user->createAsStripeCustomer();

        $response = $this->actingAs($user)->get(route('subscribe'));

        $response->assertStatus(200);

        $intent = $response->viewData('intent');
        expect($intent)->not->toBeNull()
            ->and($intent->client_secret)->not->toBeNull();
    });

    it('requires authentication to create subscription', function () {
        $response = $this->post(route('subscribe.create'), [
            'paymentMethodId' => 'pm_card_visa',
        ]);

        $response->assertRedirect(route('login'));
    });

    it('validates payment method id', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('subscribe.create'), []);

        $response->assertSessionHasErrors(['paymentMethodId']);
    });

    it('creates premium subscription successfully', function () {
        $user = User::factory()->create();
        $user->createAsStripeCustomer();

        $response = $this->actingAs($user)->post(route('subscribe.create'), [
            'paymentMethodId' => 'pm_card_visa',
        ]);

        $response->assertRedirect(route('auto.apply'))
            ->assertSessionHas('success', 'You are now subscribed to premium!');

        expect($user->fresh()->subscribed('premium'))->toBeTrue();
    });

    it('handles invalid payment method', function () {
        $user = User::factory()->create();
        $user->createAsStripeCustomer();

        $response = $this->actingAs($user)->post(route('subscribe.create'), [
            'paymentMethodId' => 'pm_card_chargeDeclined',
        ]);

        // Should handle Stripe exceptions gracefully
        expect($response->status())->toBeIn([302, 400, 422]);
    });
});
