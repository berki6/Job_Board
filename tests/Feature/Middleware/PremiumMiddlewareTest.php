<?php

use App\Http\Middleware\PremiumMiddleware;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

describe('PremiumMiddleware', function () {
    beforeEach(function () {
        $this->middleware = new PremiumMiddleware();
    });

    it('redirects unauthenticated users', function () {
        $request = Request::create('/auto-apply', 'GET');

        $response = $this->middleware->handle($request, function () {
            return response('OK');
        });

        expect($response)->toBeInstanceOf(RedirectResponse::class);
        expect($response->getTargetUrl())->toContain('subscribe');
        expect(session('error'))->toBe('Upgrade to premium to use this feature.');
    });

    it('redirects non-premium users', function () {
        $user = User::factory()->create();
        $request = Request::create('/auto-apply', 'GET');
        $request->setUserResolver(fn() => $user);

        $response = $this->middleware->handle($request, function () {
            return response('OK');
        });

        expect($response)->toBeInstanceOf(RedirectResponse::class);
        expect($response->getTargetUrl())->toContain('subscribe');
        expect(session('error'))->toBe('Upgrade to premium to use this feature.');
    });

    it('allows premium users to proceed', function () {
        $user = createPremiumUser();
        $request = Request::create('/auto-apply', 'GET');
        $request->setUserResolver(fn() => $user);

        $response = $this->middleware->handle($request, function () {
            return response('OK');
        });

        expect($response->getContent())->toBe('OK');
    });

    it('handles users without stripe customer gracefully', function () {
        $user = User::factory()->create();
        $request = Request::create('/auto-apply', 'GET');
        $request->setUserResolver(fn() => $user);

        $response = $this->middleware->handle($request, function () {
            return response('OK');
        });

        expect($response)->toBeInstanceOf(RedirectResponse::class);
        expect($response->getTargetUrl())->toContain('subscribe');
    });
});
