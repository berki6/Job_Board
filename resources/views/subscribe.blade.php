@extends('layouts.app')

@section('content')
    <div class="max-w-lg mx-auto bg-white shadow-lg rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4">Subscribe to Premium</h2>
        <form id="payment-form" action="{{ route('subscribe.create') }}" method="POST">
            @csrf
            <input type="hidden" name="paymentMethodId" id="paymentMethodId">
            <input type="text" id="card-holder-name" placeholder="Name on Card" class="w-full border rounded p-2 mb-4">
            <div id="card-element" class="border rounded p-2 mb-4"></div>
            <button id="card-button" data-secret="{{ $intent->client_secret }}"
                class="bg-blue-500 text-white px-4 py-2 rounded">Subscribe</button>
        </form>
    </div>

    <script src="https://js.stripe.com/v3/"></script>
    <script>
        const stripe = Stripe('{{ env('STRIPE_KEY') }}');
        const elements = stripe.elements();
        const cardElement = elements.create('card');
        cardElement.mount('#card-element');

        const cardButton = document.getElementById('card-button');
        const clientSecret = cardButton.dataset.secret;

        cardButton.addEventListener('click', async (e) => {
            e.preventDefault();
            const { paymentMethod, error } = await stripe.createPaymentMethod(
                'card', cardElement, { billing_details: { name: document.getElementById('card-holder-name').value } }
            );
            if (error) {
                alert(error.message);
            } else {
                document.getElementById('paymentMethodId').value = paymentMethod.id;
                document.getElementById('payment-form').submit();
            }
        });
    </script>
@endsection